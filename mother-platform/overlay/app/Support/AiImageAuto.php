<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/** Owner rule (6 Nov 2026): every NEW product image (main or gallery) is automatically turned into the standard AI Product Image (no watermark/logo/overlay, white background, centred, 600x600 web-optimised JPG + WebP + thumbnails) and used on the site. Only images added after the rule went live; old images stay as they are until processed by hand. Cost guard: one image per worker tick and a daily cap. */
class AiImageAuto {
  public static function set(string $k, string $d = ''): string { $v = DB::table('settings')->where('key', 'ai_auto_' . $k)->value('value'); return ($v === null || $v === '') ? $d : (string)$v; }
  private static function flush(): void { foreach (['store.home2','store.home4','store.home6','store.home5','store.cats','store.live2','store.live3','store.live4'] as $k) Cache::forget($k); }
  public static function tick(): int {
    if (self::set('on', '1') !== '1' || !AiImage::key() || !Img::ok()) return 0;
    $cut = self::set('cutoff', ''); if ($cut === '') return 0;
    $cap = max(1, (int)self::set('daily_cap', '60'));
    if (DB::table('ai_images')->where('processed_at', '>=', now()->startOfDay())->count() >= $cap) return 0;
    $lock = Cache::lock('ai-image-auto', 400); if (!$lock->get()) return 0;
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
        self::flush();
        return 1; // one image per tick (each AI call takes up to a minute)
      }
    } catch (\Throwable $e) {
      return 0;
    } finally { try { $lock->release(); } catch (\Throwable $e) {} }
    return 0;
  }
}
