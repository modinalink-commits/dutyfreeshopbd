<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Daily product blog post (owner, 23 Nov 2026): once a day the worker picks one product that sells and has not been written about,
 * asks Claude for a short Bengali article built ONLY from the catalog facts, and publishes it on /blog with a link to the product.
 * Guard rails: price / size / brand come from the database and are passed as facts (the model is told not to add any other fact);
 * links written by the model are stripped and our own product link is added; no health or "best price" claims;
 * tobacco and vape products are skipped unless the setting blog_auto_smoke is 1. Nothing is published when the AI call fails.
 */
class AutoBlog {
  public static function set(string $k, string $d = ''): string { try { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null ? $d : (string)$v; } catch (\Throwable $e) { return $d; } }
  private static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function on(): bool { return self::set('blog_auto_on', '1') === '1'; }
  public static function hour(): int { return max(0, min(23, (int)self::set('blog_auto_hour', '10'))); }

  /** Worker tick: one post a day after the chosen hour (Dhaka); up to 3 tries, 20 minutes apart, when the AI call fails. */
  public static function tick(): void {
    if (!self::on() || !Schema::hasTable('posts')) return;
    $n = now('Asia/Dhaka'); $day = $n->toDateString(); if ($n->hour < self::hour()) return;
    if (self::set('blog_auto_last') === $day) return;
    $tries = (int)Cache::get('autoblog.tries.' . $day, 0); if ($tries >= 3) return;
    if (!Cache::add('autoblog.lock', 1, 1200)) return;
    Cache::put('autoblog.tries.' . $day, $tries + 1, 90000);
    $r = self::make();
    if ($r['ok']) self::put('blog_auto_last', $day);
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'Auto Blog', 'action' => $r['ok'] ? 'Blog post published' : 'Blog post not made', 'subject' => mb_substr($r['msg'], 0, 180), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    if (!$r['ok'] && $tries + 1 >= 3) throw new \RuntimeException('Daily blog post could not be made: ' . $r['msg']);
  }

  private static function done(): array { $a = json_decode(self::set('blog_auto_done', '[]'), true); return is_array($a) ? array_map('intval', $a) : []; }

  /** A product that is published, in stock, has a photo, sold in the last 60 days and has no article yet; not the same category as the last one. */
  public static function pick(): ?object {
    $done = self::done(); $lastCat = (int)self::set('blog_auto_last_cat', '0'); $smoke = self::set('blog_auto_smoke', '0') === '1';
    $base = fn() => DB::table('products')->where('published', true)->where('stock', '>', 0)->whereNotNull('image')->where('image', '!=', '')->where('price', '>', 0)
      ->when(!$smoke, fn($q) => $q->where(fn($w) => $w->whereNull('group_key')->orWhere('group_key', '!=', 'smoke')))
      ->when($done, fn($q) => $q->whereNotIn('products.id', array_slice($done, -1500)));
    try {
      $ids = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.created_at', '>=', now()->subDays(60))->whereNotIn('orders.status', ['Cancel', 'Incomplete'])
        ->whereNotNull('order_items.product_id')->groupBy('order_items.product_id')->orderByRaw('sum(order_items.qty) desc')->limit(400)->pluck('order_items.product_id')->all();
      if ($ids) { $rows = $base()->whereIn('products.id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) if (isset($rows[$id]) && (int)$rows[$id]->category_id !== $lastCat) return $rows[$id];
        foreach ($ids as $id) if (isset($rows[$id])) return $rows[$id]; }
    } catch (\Throwable $e) {}
    return $base()->inRandomOrder()->first();
  }

  private static function clean(string $html): string {
    $html = preg_replace('#<(script|style|iframe)\b.*?</\1>#is', '', $html);
    $html = preg_replace('#</?a\b[^>]*>#i', '', $html);                       // links written by the model are dropped; ours is added below
    $html = strip_tags($html, '<h2><h3><p><ul><ol><li><strong><em><br>');
    return trim(preg_replace('#<(h2|h3|p|ul|ol|li|strong|em)\s+[^>]*>#i', '<$1>', $html)); // no attributes
  }

  public static function make(): array {
    $key = Sms::secret('ai_key'); if (!$key) return ['ok' => false, 'msg' => 'Claude API key is not set'];
    $p = self::pick(); if (!$p) return ['ok' => false, 'msg' => 'No product left to write about'];
    $cat = ''; $brand = ''; try { $cat = (string)DB::table('categories')->where('id', $p->category_id)->value('name'); $brand = $p->brand_id ? (string)DB::table('brands')->where('id', $p->brand_id)->value('name') : ''; } catch (\Throwable $e) {}
    $offer = isset($p->offer_price) && (float)$p->offer_price > 0 && (float)$p->offer_price < (float)$p->price ? (float)$p->offer_price : null;
    $facts = array_filter(['name' => $p->name, 'category' => $cat, 'sub_category' => $p->sub ?? null, 'brand' => $brand, 'size' => $p->size ?? null,
      'price_bdt' => (float)$p->price, 'offer_price_bdt' => $offer, 'description_from_catalog' => mb_substr(trim(strip_tags((string)($p->description ?? ''))), 0, 1200),
      'store' => 'DutyFree BD (dutyfreebd.com)', 'delivery' => 'Home delivery all over Bangladesh'], fn($v) => $v !== null && $v !== '');
    $sys = "You write one blog article for the online shop DutyFree BD (Bangladesh). Language: natural Bengali; keep the product and brand names in English exactly as given.\n"
      . "Use ONLY the facts in FACTS. Never add a specification, ingredient, origin, size, award, stock claim, delivery time, discount or price that is not in FACTS. If a detail is missing, leave it out.\n"
      . "No health, medical or performance claims. No 'best price' / 'cheapest' / 'number one' claims. No fake urgency. Do not mention competitors. Do not write links or URLs.\n"
      . "Useful angle: what the product is, who it suits, how to choose or use this kind of product in general, what to check when buying it online, and the price from FACTS (mention the offer price only if it is in FACTS).\n"
      . "Length 300-450 Bengali words. HTML body with only <h2>, <p>, <ul>, <li>, <strong>.\n"
      . "Reply with one JSON object and nothing else: {\"title\": \"...\", \"excerpt\": \"1-2 sentences\", \"meta_title\": \"max 60 chars\", \"meta_description\": \"max 155 chars\", \"html\": \"...\"}";
    try {
      $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->timeout(90)->post('https://api.anthropic.com/v1/messages', ['model' => AiBrief::model(), 'max_tokens' => 3500, 'system' => $sys,
        'messages' => [['role' => 'user', 'content' => "FACTS:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]]);
    } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'network: ' . mb_substr($e->getMessage(), 0, 140)]; }
    if (!$res->ok()) return ['ok' => false, 'msg' => 'AI HTTP ' . $res->status() . ' ' . mb_substr((string)($res->json('error.message') ?? ''), 0, 140)];
    $txt = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
    $a = strpos($txt, '{'); $b = strrpos($txt, '}'); $j = $a !== false && $b !== false ? json_decode(substr($txt, $a, $b - $a + 1), true) : null;
    if (!is_array($j) || empty($j['title']) || empty($j['html'])) return ['ok' => false, 'msg' => 'AI reply was not usable for ' . $p->name];
    $html = self::clean((string)$j['html']); if (mb_strlen(strip_tags($html)) < 400) return ['ok' => false, 'msg' => 'AI article too short for ' . $p->name];
    $url = \App\Http\Controllers\StoreController::url($p);
    $priceLine = $offer ? 'অফার মূল্য ৳' . number_format($offer) . ' (নিয়মিত মূল্য ৳' . number_format((float)$p->price) . ')' : 'মূল্য ৳' . number_format((float)$p->price);
    $html .= '<p><strong>' . e($p->name) . '</strong> · ' . $priceLine . '। <a href="' . e($url) . '">পণ্যটি দেখুন ও অর্ডার করুন</a></p>';
    $slug = trim(substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($p->name)), 0, 70), '-') ?: 'product'; $slug .= '-' . now('Asia/Dhaka')->format('ymd');
    if (DB::table('posts')->where('slug', $slug)->exists()) $slug .= '-' . $p->id;
    $img = (string)$p->image;
    DB::table('posts')->insert(['title' => mb_substr(strip_tags((string)$j['title']), 0, 180), 'slug' => $slug, 'featured_image' => $img, 'excerpt' => mb_substr(strip_tags((string)($j['excerpt'] ?? '')), 0, 300), 'body' => $html,
      'meta_title' => mb_substr(strip_tags((string)($j['meta_title'] ?? $j['title'])), 0, 70), 'meta_description' => mb_substr(strip_tags((string)($j['meta_description'] ?? '')), 0, 170), 'active' => true, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $done = self::done(); $done[] = (int)$p->id; self::put('blog_auto_done', json_encode(array_slice($done, -3000))); self::put('blog_auto_last_cat', (string)(int)$p->category_id);
    return ['ok' => true, 'msg' => $p->name . ' → /blog/' . $slug, 'slug' => $slug];
  }
}
