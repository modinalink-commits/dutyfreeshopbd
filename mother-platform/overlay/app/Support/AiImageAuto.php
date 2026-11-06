<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/** Owner rules (6 Nov 2026): every NEW product image is automatically turned into the standard AI Product Image (no watermark/logo/overlay, white background, centred, 600x600 web-optimised JPG + WebP + thumbnails) and set as the product's main image at once. Old images that still carry the shop logo are redone, then every Cigarette & Vape product image. One image per product (gallery images are not processed). Cost guard: daily cap. Speed: several images are processed in parallel by small child processes (mother:ai-image-one). */
class AiImageAuto {
  public static function set(string $k, string $d = ''): string { $v = DB::table('settings')->where('key', 'ai_auto_' . $k)->value('value'); return ($v === null || $v === '') ? $d : (string)$v; }
  private static function flush(): void { foreach (['store.home2','store.home4','store.home6','store.home5','store.cats','store.live2','store.live3','store.live4','store.globe_products','store.catalogfeed4','card_second_imgs2','ai.catalog'] as $k) Cache::forget($k); }
  /** Last result of the automatic worker, shown on the AI Product Images page. */
  private static function note(string $m): void { try { DB::table('settings')->updateOrInsert(['key' => 'ai_auto_last'], ['value' => now()->toDateTimeString() . '|' . $m, 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) {} }
  public static function fail(string $m): void { self::note('Error: ' . mb_substr($m, 0, 200)); }
  public static function status(): array {
    $last = explode('|', (string)self::set('last', '|Worker has not reached this job yet'), 2);
    $cap = max(1, (int)self::set('daily_cap', '60'));
    return ['on' => self::set('on', '1') === '1', 'cap' => $cap, 'today' => DB::table('ai_images')->where('processed_at', '>=', now()->startOfDay())->count(), 'at' => $last[0] ?? '', 'msg' => $last[1] ?? '',
      'old_left' => DB::table('ai_images')->where('ai_status', 'completed')->whereNotNull('processed_image')->where('processed_at', '<', self::set('prompt_cutoff', '2000-01-01'))->count(),
      'all_on' => self::set('backfill_all', '0') === '1', 'busy' => DB::table('ai_images')->where('ai_status', 'processing')->count()];
  }
  /** Thumbnails the site actually uses (name-300.webp / name-150.webp) for AI images made before they were written: GD only, no API cost. */
  private static function repairThumbs(): void {
    try {
      $n = 0;
      foreach (DB::table('ai_images')->where('ai_status', 'completed')->whereNotNull('processed_image')->orderByDesc('id')->limit(300)->get(['id', 'processed_image']) as $r) {
        $b = preg_replace('/\.jpg$/', '', (string)$r->processed_image);
        if (is_file(public_path(ltrim($b, '/') . '-300.webp')) || !is_file(public_path(ltrim($r->processed_image, '/')))) continue;
        AiImage::thumbs((int)$r->id); if (++$n >= 20) break;
      }
    } catch (\Throwable $e) {}
  }
  private static function canSpawn(): bool { return function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true) && is_file(base_path('artisan')); }

  /** Called by the worker every ~30 s: starts up to ai_auto_parallel child processes (default 4). */
  public static function tick(): int {
    self::repairThumbs();
    if (self::set('on', '1') !== '1') { self::note('Automatic mode is switched off'); return 0; }
    if (!AiImage::key()) { self::note('OpenAI key is not set'); return 0; }
    if (!Img::ok()) { self::note('PHP GD is not enabled'); return 0; }
    // OpenAI credits finished: rows that failed only for that reason go back to the queue; the worker waits (checks again every 15 minutes) instead of failing every product
    foreach (DB::table('ai_images')->where('ai_status', 'failed')->where('processing_error', 'like', '%no credits%')->get(['id', 'processed_image']) as $f) {
      if ($f->processed_image) DB::table('ai_images')->where('id', $f->id)->update(['ai_status' => 'completed', 'processing_error' => null]); else DB::table('ai_images')->where('id', $f->id)->delete();
    }
    $pu = self::set('pause_until', '');
    if ($pu !== '' && now()->lt(\Carbon\Carbon::parse($pu))) { self::note('PAUSED: OpenAI credits are finished. Add credits at platform.openai.com/settings/organization/billing; the job continues by itself (next check ' . \Carbon\Carbon::parse($pu)->format('H:i') . ')'); return 0; }
    // an image stuck in "processing" for 20 minutes (a child process died) is marked failed so it can be retried by hand
    DB::table('ai_images')->where('ai_status', 'processing')->where('updated_at', '<', now()->subMinutes(20))->update(['ai_status' => 'failed', 'processing_error' => 'Interrupted, press Reprocess', 'updated_at' => now()]);
    $n = max(1, min(8, (int)self::set('parallel', '4'))); $started = 0;
    if (!self::canSpawn()) { try { return self::work() ? 1 : 0; } catch (\Throwable $e) { self::fail($e->getMessage()); return 0; } }
    for ($i = 1; $i <= $n; $i++) {
      if (Cache::has('ai-img-child-' . $i)) continue;
      if (self::idle()) { if ($started === 0) self::note('Nothing left to process right now'); break; }
      Cache::put('ai-img-child-' . $i, 1, 300);
      @exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('artisan')) . ' mother:ai-image-one ' . $i . ' > /dev/null 2>&1 &');
      $started++;
    }
    return $started;
  }
  private static function idle(): bool {
    $cap = max(1, (int)self::set('daily_cap', '60'));
    return DB::table('ai_images')->where(fn($w) => $w->where('processed_at', '>=', now()->startOfDay())->orWhere('ai_status', 'processing'))->count() >= $cap || self::candidates(false) === null;
  }

  /** Process exactly one image (claimed under a short lock so parallel children never take the same one). */
  public static function work(): bool {
    if (self::set('on', '1') !== '1' || !AiImage::key() || !Img::ok()) return false;
    $cap = max(1, (int)self::set('daily_cap', '60'));
    $lock = Cache::lock('ai-image-pick', 30);
    try { $lock->block(15); } catch (\Throwable $e) { return false; }
    try {
      if (DB::table('ai_images')->where(fn($w) => $w->where('processed_at', '>=', now()->startOfDay())->orWhere('ai_status', 'processing'))->count() >= $cap) { self::note('Daily cap reached, continues tomorrow'); return false; }
      $c = self::candidates(true);
    } finally { try { $lock->release(); } catch (\Throwable $e) {} }
    if (!$c) return false;
    [$id, $pid, $url, $kind, $oldPath] = $c;
    $r = AiImage::run($id);
    if (!($r['ok'] ?? false)) {
      $err = (string)($r['error'] ?? '');
      if (preg_match('/no credits|insufficient_quota|billing/i', $err)) { DB::table('settings')->updateOrInsert(['key' => 'ai_auto_pause_until'], ['value' => now()->addMinutes(15)->toDateTimeString(), 'updated_at' => now(), 'created_at' => now()]); self::note('PAUSED: OpenAI credits are finished. Add credits at platform.openai.com/settings/organization/billing; the job continues by itself'); }
      else self::note('Image #' . $id . ' failed: ' . $err);
      return false;
    }
    $o = DB::table('ai_images')->find($id); if (!$o || !$o->processed_image) return false;
    $new = $o->processed_image; $cur = (string)DB::table('products')->where('id', $pid)->value('image');
    // the clean image takes the main-image place at once (and everywhere the old file was used)
    if ($kind === 'gallery') DB::table('product_images')->where('product_id', $pid)->where('path', $url)->update(['path' => $new, 'updated_at' => now()]);
    elseif ($cur === $url || ($oldPath && strtok($cur, '?') === $oldPath) || $kind !== 'old') {
      DB::table('products')->where('id', $pid)->update(['image' => $new]);
      DB::table('ai_images')->where('product_id', $pid)->update(['is_main' => false]); DB::table('ai_images')->where('id', $id)->update(['is_main' => true]);
    }
    if ($oldPath && $oldPath !== $new) DB::table('product_images')->where('product_id', $pid)->where('path', $oldPath)->update(['path' => $new, 'updated_at' => now()]);
    self::flush();
    self::note(['new' => 'Processed new image', 'old' => 'Redid old logo image', 'smoke' => 'Cigarette & Vape: processed image', 'gallery' => 'Processed gallery image'][$kind] . ' #' . $id . ' (product #' . $pid . '), now the main image');
    return true;
  }

  /** Next image to process: new images first, then old logo images, then the Cigarette & Vape category. $claim=false only tells whether something is left. */
  private static function candidates(bool $claim): ?array {
    $ins = fn($pid, $url) => DB::table('ai_images')->insertGetId(['product_id' => (int)$pid, 'source_hash' => sha1($url), 'source_url' => $url, 'ai_status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
    $done = fn($pid, $url) => DB::table('ai_images')->where('product_id', $pid)->where(fn($w) => $w->where('source_hash', sha1($url))->orWhere('processed_image', parse_url($url, PHP_URL_PATH) ?: '#'))->exists();
    $cut = self::set('cutoff', '');
    // 1) new images (owner rule: from the day the rule went live)
    if ($cut !== '') foreach (DB::table('products')->where('created_at', '>=', $cut)->whereNotNull('image')->where('image', '<>', '')->where('image', 'not like', '%/uploads/ai/%')->orderBy('id')->limit(60)->get(['id', 'image']) as $p) {
      if ($done((int)$p->id, (string)$p->image)) continue;
      return $claim ? [$ins($p->id, $p->image), (int)$p->id, (string)$p->image, 'new', null] : [0];
    }
    // 2) old images processed before the clean standard (they still carry the shop logo)
    $pc = self::set('prompt_cutoff', '');
    if (self::set('backfill', '1') === '1' && $pc !== '') {
      $row = DB::table('ai_images')->where('ai_status', 'completed')->whereNotNull('processed_image')->where('processed_at', '<', $pc)->orderBy('id')->first();
      if ($row) { if (!$claim) return [0]; DB::table('ai_images')->where('id', $row->id)->update(['ai_status' => 'processing', 'updated_at' => now()]); return [(int)$row->id, (int)$row->product_id, (string)$row->source_url, 'old', (string)$row->processed_image]; }
    }
    // 3) the Cigarette & Vape category (owner decision): the single main image of every product
    if (self::set('backfill_all', '0') === '1') {
      $sc = DB::table('categories')->get(['id', 'slug', 'name'])->filter(fn($c) => \App\Http\Controllers\StoreController::isSmokeCat($c))->pluck('id')->all();
      $q = DB::table('products')->where(function ($w) use ($sc) { $w->where('group_key', 'smoke'); if ($sc) $w->orWhereIn('category_id', $sc); })
        ->whereNotNull('image')->where('image', '<>', '')->where('image', 'not like', '%/uploads/ai/%')
        ->orderBy('id');
      // a product still showing its original image is done only when an AI record exists for THAT image (any status, so safety-rejected ones are not retried forever)
      foreach ($q->limit(600)->get(['id', 'image']) as $p) {
        if ($done((int)$p->id, (string)$p->image)) continue;
        return $claim ? [$ins($p->id, $p->image), (int)$p->id, (string)$p->image, 'smoke', null] : [0];
      }
    }
    return null;
  }
}
