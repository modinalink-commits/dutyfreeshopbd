<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Owner 19 Nov 2026: every sourcing product carries the source shop's product description EXACTLY (text, tables, lists and the pictures inside it),
 * with only the source company's name changed to ours. Pictures are copied to our own storage (no hot-linking), links are unwrapped, scripts removed.
 * Runs in small batches from the background worker while setting sdesc_mode = 1. Each product is processed once (table source_desc_sync, old text kept).
 * Shopify-type sources (product .json) first; other site types are reported as "unsupported" until their own reader exists.
 */
class SourceDesc
{
  const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36';
  public static function on(): bool { return (string)DB::table('settings')->where('key', 'sdesc_mode')->value('value') === '1'; }
  public static function setMode(bool $v): void { DB::table('settings')->updateOrInsert(['key' => 'sdesc_mode'], ['value' => $v ? '1' : '0', 'updated_at' => now(), 'created_at' => now()]); }
  public static function ready(): bool { try { return Schema::hasTable('source_desc_sync'); } catch (\Throwable $e) { return false; } }

  /** Words that name the source company in its own text (store name, domain, domain stem); replaced by our name. */
  private static function names(object $st): array {
    $n = []; $d = strtolower(preg_replace('#^www\.#', '', (string)$st->domain)); $stem = preg_replace('/\..*$/', '', $d);
    foreach ([$d, 'www.' . $d, trim((string)$st->name), $stem, str_replace(['-', '_'], ' ', $stem)] as $x) if (mb_strlen($x) >= 5) $n[] = $x;
    return array_values(array_unique($n));
  }
  private static function swapNames(string $h, array $names): string {
    usort($names, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    foreach ($names as $x) {
      $rep = (strpos($x, '.') !== false) ? 'dutyfreebd.com' : 'DutyFreeBD';
      $h = preg_replace('/(?<![\w@\/])' . preg_quote($x, '/') . '(?![\w])/iu', $rep, $h);
    }
    return $h;
  }
  private static function abs(string $u, string $base): string {
    $u = trim(html_entity_decode($u)); if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u; if (strpos($u, '//') === 0) return 'https:' . $u;
    $p = parse_url($base); $root = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    return $u[0] === '/' ? $root . $u : $root . '/' . ltrim($u, '/');
  }
  /** Clean the source HTML: keep structure, copy pictures, unwrap links, swap the company name. Returns [html, imageCount]. */
  public static function build(string $html, string $base, array $names, int $pid, string $alt): array {
    $html = preg_replace('#<!--.*?-->#s', '', $html);
    $html = preg_replace('#<(script|style|iframe|noscript|form|svg|object|embed|button|input|select|textarea)\b.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|noscript|form|svg|object|embed|button|input|select|textarea)\b[^>]*/?>#i', '', $html);
    // pictures: collect, download, rewrite
    $imgs = []; $map = [];
    if (preg_match_all('#<img\b[^>]*>#i', $html, $mm)) foreach ($mm[0] as $tag) {
      $src = ''; foreach (['data-src', 'data-original', 'src'] as $a) if (preg_match('/\b' . $a . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $q)) { $src = $q[2] !== '' ? $q[2] : ($q[3] ?? ''); if ($src !== '' && stripos($src, 'data:image') !== 0) break; }
      if ($src === '' && preg_match('/\bsrcset\s*=\s*"([^"]+)"/i', $tag, $q)) $src = trim(explode(' ', trim(explode(',', $q[1])[0]))[0]);
      $imgs[$tag] = $src === '' ? '' : self::abs($src, $base);
    }
    $urls = array_values(array_unique(array_filter($imgs))); $urls = array_slice($urls, 0, 40);
    $saved = self::saveImages($urls, $pid);
    $n = 0;
    foreach ($imgs as $tag => $u) {
      $rep = ''; if ($u !== '' && !empty($saved[$u])) { $rep = '<img src="' . $saved[$u] . '" alt="' . e($alt) . '" loading="lazy">'; $n++; }
      $html = str_replace($tag, $rep, $html);
    }
    $html = preg_replace('#<a\b[^>]*>(.*?)</a>#is', '$1', $html);
    $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><span><div><img><blockquote><hr><sup><sub><caption><colgroup><col><figure><figcaption><center>');
    $html = preg_replace_callback('#<(?!img\b)([a-z][a-z0-9]*)\b([^>]*)>#i', function ($m) { $a = ''; if (preg_match('/\b(colspan|rowspan)\s*=\s*"(\d{1,2})"/i', $m[2], $q)) $a .= ' ' . strtolower($q[1]) . '="' . $q[2] . '"'; return '<' . $m[1] . $a . '>'; }, $html);
    $html = self::swapNames($html, $names);
    $html = preg_replace('#(<(p|div|span|li|strong|b|em|i)>\s*(&nbsp;|<br\s*/?>|\s)*</\2>)+#i', '', $html);
    return [trim($html), $n];
  }
  private static function saveImages(array $urls, int $pid): array {
    if (!$urls) return []; $dir = public_path('uploads/srcdesc'); if (!is_dir($dir)) @mkdir($dir, 0775, true); $out = [];
    try {
      $resp = Http::pool(function ($pool) use ($urls) { $o = []; foreach ($urls as $i => $u) $o[] = $pool->as((string)$i)->timeout(25)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'image/*,*/*'])->get($u); return $o; });
    } catch (\Throwable $e) { return []; }
    $ext = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
    foreach ($urls as $i => $u) {
      $r = $resp[(string)$i] ?? null; if (!$r || !method_exists($r, 'ok') || !$r->ok()) continue;
      $ct = strtolower(trim(explode(';', (string)$r->header('Content-Type'))[0])); $b = $r->body();
      if (!isset($ext[$ct]) || strlen($b) < 200 || strlen($b) > 8000000) continue;
      $f = $pid . '-' . substr(sha1($u), 0, 10) . '.' . $ext[$ct]; if (@file_put_contents($dir . '/' . $f, $b) !== false) $out[$u] = '/uploads/srcdesc/' . $f;
    }
    return $out;
  }

  /** Process one batch. Returns a small report. $only = optional product id (test). */
  public static function batch(int $limit = 30, ?int $only = null, int $budget = 40): array {
    if (!self::ready()) return ['ok' => false, 'why' => 'table missing'];
    $t0 = microtime(true); @set_time_limit($budget + 40);
    $q = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->join('stores as st', 'st.id', '=', 'w.store_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
      ->leftJoin('source_desc_sync as s', 's.watch_source_id', '=', 'w.id')->whereNull('s.id')->whereNotNull('w.source_url')->where('w.source_url', '<>', '');
    if ($only) $q->where('p.id', $only);
    else $q->orderByRaw("case when lower(coalesce(c.name,'')) ~ 'perfume|fragrance|attar' then 0 when lower(coalesce(c.name,'')) ~ 'electronic|gadget|appliance' then 1 when lower(coalesce(c.name,'')) ~ 'watch' then 2 else 3 end")->orderBy('w.id');
    $rows = $q->limit($limit)->get(['w.id as wid', 'w.source_url', 'p.id as pid', 'p.name', 'p.description', 'c.name as cat', 'st.domain', 'st.name as sname']);
    $rep = ['ok' => true, 'done' => 0, 'nosource' => 0, 'failed' => 0, 'cats' => []];
    if ($rows->isEmpty()) return $rep + ['empty' => true];
    $urls = []; foreach ($rows as $x) { $u = preg_replace('#[?\#].*$#', '', (string)$x->source_url); $urls[$x->wid] = rtrim($u, '/') . '.json'; }
    try { $resp = Http::pool(function ($pool) use ($urls) { $o = []; foreach ($urls as $k => $u) $o[] = $pool->as((string)$k)->timeout(25)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])->get($u); return $o; }); } catch (\Throwable $e) { $resp = []; }
    foreach ($rows as $x) {
      if (microtime(true) - $t0 > $budget) break;
      $r = $resp[(string)$x->wid] ?? null; $cat = $x->cat ?: 'Other'; $rep['cats'][$cat] = $rep['cats'][$cat] ?? ['done' => 0, 'nosource' => 0, 'failed' => 0];
      $j = ($r && method_exists($r, 'ok') && $r->ok()) ? $r->json('product') : null; $st = ($r && method_exists($r, 'status')) ? $r->status() : 0;
      $rec = ['watch_source_id' => $x->wid, 'product_id' => $x->pid, 'updated_at' => now()];
      if (!is_array($j)) { // not a Shopify .json source (or blocked / timed out): timeouts retry later, the rest are marked unsupported
        $retry = ($st === 0 || $st >= 500 || $st === 429);
        if ($retry) { DB::table('source_desc_sync')->insert($rec + ['status' => 'failed', 'note' => 'http ' . $st]); $rep['failed']++; $rep['cats'][$cat]['failed']++; continue; }
        DB::table('source_desc_sync')->insert($rec + ['status' => 'nosource', 'note' => 'not shopify json http ' . $st]); $rep['nosource']++; $rep['cats'][$cat]['nosource']++; continue;
      }
      $body = (string)($j['body_html'] ?? '');
      if (trim(strip_tags($body, '<img>')) === '') { DB::table('source_desc_sync')->insert($rec + ['status' => 'nosource', 'note' => 'source description empty']); $rep['nosource']++; $rep['cats'][$cat]['nosource']++; continue; }
      [$html, $n] = self::build($body, (string)$x->source_url, self::names((object)['domain' => $x->domain, 'name' => $x->sname]), (int)$x->pid, (string)$x->name);
      if (trim(strip_tags($html, '<img>')) === '') { DB::table('source_desc_sync')->insert($rec + ['status' => 'nosource', 'note' => 'empty after clean']); $rep['nosource']++; $rep['cats'][$cat]['nosource']++; continue; }
      $txt = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
      $upd = ['description' => $html, 'updated_at' => now()]; if ($txt !== '') $upd['meta_description'] = mb_substr($txt, 0, 155);
      DB::table('products')->where('id', $x->pid)->update($upd);
      DB::table('source_desc_sync')->insert($rec + ['status' => 'done', 'imgs' => $n, 'chars' => mb_strlen($txt), 'old_description' => $x->description]);
      $rep['done']++; $rep['cats'][$cat]['done']++;
    }
    if ($rep['done']) { try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {} }
    return $rep;
  }
  public static function tick(): void {
    if (!self::on() || !self::ready()) return;
    $lk = Cache::lock('sdesc.tick', 150); if (!$lk->get()) return;
    try {
      $r = self::batch(40, null, 35);
      $a = Cache::get('sdesc.agg', ['done' => 0, 'nosource' => 0, 'failed' => 0, 'cats' => [], 'since' => now()->toDateTimeString()]);
      foreach (['done', 'nosource', 'failed'] as $k) $a[$k] += $r[$k] ?? 0;
      foreach (($r['cats'] ?? []) as $c => $v) foreach ($v as $k => $n) $a['cats'][$c][$k] = ($a['cats'][$c][$k] ?? 0) + $n;
      $a['last'] = now()->toDateTimeString(); Cache::forever('sdesc.agg', $a);
      if (!empty($r['empty'])) self::setMode(false);
    } finally { $lk->release(); }
  }
  /** Progress per category: how many linked products are done / unsupported / failed-retrying / waiting. */
  public static function progress(): array {
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->leftJoin('source_desc_sync as s', 's.watch_source_id', '=', 'w.id')->whereNotNull('w.source_url')
      ->selectRaw("coalesce(c.name,'Other') cat, count(*) total, sum(case when s.status='done' then 1 else 0 end) done, sum(case when s.status='nosource' then 1 else 0 end) nosource, sum(case when s.status='failed' then 1 else 0 end) failed, sum(case when s.id is null then 1 else 0 end) waiting, coalesce(sum(s.imgs),0) imgs")->groupBy('cat')->orderByDesc('total')->get();
    return ['on' => self::on(), 'cats' => $rows, 'agg' => Cache::get('sdesc.agg')];
  }
}
