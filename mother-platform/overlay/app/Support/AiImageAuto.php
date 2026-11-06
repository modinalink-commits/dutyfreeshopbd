<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/** Owner rule (6 Nov 2026): every NEW product image (main or gallery) is automatically turned into the standard AI Product Image (no watermark/logo/overlay, white background, centred, 600x600 web-optimised JPG + WebP + thumbnails) and used on the site. Only images added after the rule went live; old images stay as they are until processed by hand. Cost guard: one image per worker tick and a daily cap. */
class AiImageAuto {
  public static function set(string $k, string $d = ''): string { $v = DB::table('settings')->where('key', 'ai_auto_' . $k)->value('value'); return ($v === null || $v === '') ? $d : (string)$v; }
  private static function flush(): void { foreach (['store.home2','store.home4','store.home6','store.home5','store.cats','store.live2','store.live3','store.live4'] as $k) Cache::forget($k); }
  /** Last result of the automatic worker, shown on the AI Product Images page. */
  private static function note(string $m): void { try { DB::table('settings')->updateOrInsert(['key' => 'ai_auto_last'], ['value' => now()->toDateTimeString() . '|' . $m, 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) {} }
  public static function status(): array {
    $last = explode('|', (string)self::set('last', '|Worker has not reached this job yet'), 2);
    $cap = max(1, (int)self::set('daily_cap', '60'));
    return ['on' => self::set('on', '1') === '1', 'cap' => $cap, 'today' => DB::table('ai_images')->where('processed_at', '>=', now()->startOfDay())->count(), 'at' => $last[0] ?? '', 'msg' => $last[1] ?? '',
      'old_left' => DB::table('ai_images')->where('ai_status', 'completed')->whereNotNull('processed_image')->where('processed_at', '<', self::set('prompt_cutoff', '2000-01-01'))->count(),
      'all_on' => self::set('backfill_all', '0') === '1'];
  }
  public static function tick(): int {
    if (self::set('on', '1') !== '1') { self::note('Automatic mode is switched off'); return 0; }
    if (!AiImage::key()) { self::note('OpenAI key is not set'); return 0; }
    if (!Img::ok()) { self::note('PHP GD is not enabled'); return 0; }
    $cut = self::set('cutoff', ''); if ($cut === '') { self::note('No start time saved (cutoff)'); return 0; }
    $cap = max(1, (int)self::set('daily_cap', '60'));
    if (DB::table('ai_images')->where('processed_at', '>=', now()->startOfDay())->count() >= $cap) { self::note('Daily cap reached, continues tomorrow'); return 0; }
    $lock = Cache::lock('ai-image-auto', 400); if (!$lock->get()) { self::note('Another image is still being processed'); return 0; }
    try {
      $cands = [];
      foreach (DB::table('products')->where('updated_at', '>=', $cut)->whereNotNull('image')->where('image', '<>', '')->orderBy('id')->limit(60)->get(['id', 'image']) as $p) $cands[] = [(int)$p->id, (string)$p->image, true];
      foreach (DB::table('product_images')->where('created_at', '>=', $cut)->orderBy('id')->limit(60)->get(['product_id', 'path']) as $g) $cands[] = [(int)$g->product_id, (string)$g->path, false];
      foreach ($cands as [$pid, $url, $main]) {
        if ($url === '' || str_contains($url, '/uploads/ai/')) continue; // already a processed image
        $h = sha1($url);
        if (DB::table('ai_images')->where('product_id', $pid)->where('source_hash', $h)->exists()) continue;
        $path = parse_url($url, PHP_URL_PATH); if ($path && DB::table('ai_images')->where('product_id', $pid)->where('processed_image', $path)->exists()) continue;
        $id = DB::table('ai_images')->insertGetId(['product_id' => $pid, 'source_hash' => $h, 'source_url' => $url, 'ai_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $r = AiImage::run($id);
        if (!($r['ok'] ?? false)) return 0;
        $row = DB::table('ai_images')->find($id); if (!$row || !$row->processed_image) return 0;
        if ($main) { DB::table('products')->where('id', $pid)->where('image', $url)->update(['image' => $row->processed_image]); DB::table('ai_images')->where('product_id', $pid)->update(['is_main' => false]); DB::table('ai_images')->where('id', $id)->update(['is_main' => true]); }
        else DB::table('product_images')->where('product_id', $pid)->where('path', $url)->update(['path' => $row->processed_image, 'updated_at' => now()]);
        self::flush(); self::note('Processed new image #' . $id);
        return 1; // one image per tick (each AI call takes up to a minute)
      }
      $n = self::backfill(); if (!$n) self::note('Nothing left to process right now'); return $n;
    } catch (\Throwable $e) {
      self::note('Error: ' . mb_substr($e->getMessage(), 0, 200));
      return 0;
    } finally { try { $lock->release(); } catch (\Throwable $e) {} }
    return 0;
  }
  /** Owner rule (6 Nov 2026): images processed with the OLD instruction (they still carry the shop logo) are redone with the new standard, one per tick, inside the same daily cap. Optional second stage (ai_auto_backfill_all=1): every other product whose main image is still an original. */
  private static function backfill(): int {
    $row = null; $pc = self::set('prompt_cutoff', '');
    if (self::set('backfill', '1') === '1' && $pc !== '') $row = DB::table('ai_images')->where('ai_status', 'completed')->whereNotNull('processed_image')->where('processed_at', '<', $pc)->orderBy('id')->first();
    if ($row) {
      $r = AiImage::run((int)$row->id);
      if ($r['ok'] ?? false) { self::flush(); self::note('Redid old image #' . $row->id . ' with the clean standard'); return 1; }
      self::note('Old image #' . $row->id . ' failed: ' . ($r['error'] ?? '')); return 0;
    }
    if (self::set('backfill_all', '0') !== '1') return 0;
    // second stage, limited to the Cigarette & Vape category (owner decision 6 Nov 2026): main image first, then gallery images
    $sc = DB::table('categories')->get(['id', 'slug', 'name'])->filter(fn($c) => \App\Http\Controllers\StoreController::isSmokeCat($c))->pluck('id')->all();
    $smoke = fn($q) => $q->where(function ($w) use ($sc) { $w->where('group_key', 'smoke'); if ($sc) $w->orWhereIn('category_id', $sc); });
    $done = fn($pid, $url) => DB::table('ai_images')->where('product_id', $pid)->where('source_hash', sha1($url))->exists();
    $p = $smoke(DB::table('products'))->whereNotNull('image')->where('image', '<>', '')->where('image', 'not like', '%/uploads/ai/%')
      ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('ai_images')->whereColumn('ai_images.product_id', 'products.id')->where('ai_images.ai_status', 'completed'))->orderBy('id')->first(['id', 'image']);
    $main = true; $pid = $p->id ?? 0; $url = (string)($p->image ?? '');
    if (!$p || $done((int)$pid, $url)) {
      $main = false; $pid = 0; $url = '';
      $ids = $smoke(DB::table('products'))->pluck('id')->all();
      if (!$ids) return 0;
      foreach (DB::table('product_images')->whereIn('product_id', $ids)->where('path', 'not like', '%/uploads/ai/%')->orderBy('id')->limit(400)->get(['product_id', 'path']) as $g) {
        if ((string)$g->path !== '' && !$done((int)$g->product_id, (string)$g->path)) { $pid = (int)$g->product_id; $url = (string)$g->path; break; }
      }
      if (!$pid) return 0;
    }
    $id = DB::table('ai_images')->insertGetId(['product_id' => (int)$pid, 'source_hash' => sha1($url), 'source_url' => $url, 'ai_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    $r = AiImage::run($id); if (!($r['ok'] ?? false)) { self::note('Image #' . $id . ' failed: ' . ($r['error'] ?? '')); return 0; }
    $o = DB::table('ai_images')->find($id); if (!$o || !$o->processed_image) return 0;
    self::note('Cigarette & Vape: processed ' . ($main ? 'main' : 'gallery') . ' image #' . $id);
    if ($main) { DB::table('products')->where('id', $pid)->where('image', $url)->update(['image' => $o->processed_image]); DB::table('ai_images')->where('id', $id)->update(['is_main' => true]); }
    else DB::table('product_images')->where('product_id', $pid)->where('path', $url)->update(['path' => $o->processed_image, 'updated_at' => now()]);
    self::flush(); return 1;
  }
}
