<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Image optimisation with PHP GD: validate, resize, compress, WebP + thumbnails. Never breaks an upload: on any problem the original file stays as it is. */
class Img {
  /** kind => [max width, max height, jpeg quality]. 0 = no limit on that side. */
  const KINDS = ['pfit'=>[600, 600, 82], 'product'=>[600, 600, 82], 'category'=>[200, 200, 82], 'banner'=>[1900, 0, 82], 'mobile'=>[1080, 0, 80], 'auto'=>[1600, 1600, 82]];
  const THUMBS = [150, 300];
  public static function ok(): bool { return extension_loaded('gd') && function_exists('imagewebp') && function_exists('imagecreatefromjpeg'); }
  public static function backupOn(): bool { return (DB::table('settings')->where('key', 'img_backup')->value('value') ?? '1') === '1'; }
  public static function dir(): string { return public_path('uploads'); }
  /** true for an original upload (not a generated variant). */
  public static function isOriginal(string $name): bool { return (bool)preg_match('/\.(jpe?g|png|webp|gif)$/i', $name) && !preg_match('/-(150|300)\.webp$/i', $name) && !preg_match('/\.(jpe?g|png|gif)\.webp$/i', $name); }
  public static function kindFor(?string $slug, ?string $field): string {
    if ($slug === 'sliders' || $slug === 'banners') return $field === 'mobile_image' ? 'mobile' : 'banner';
    if ($slug === 'categories') return 'category';
    if ($slug === 'products') return 'product';
    return 'auto';
  }
  /** @return array{ok:bool,error?:string,before?:int,after?:int,w?:int,h?:int,format?:string} */
  public static function process(string $abs, string $kind = 'auto'): array {
    if (!is_file($abs)) return ['ok'=>false, 'error'=>'file missing'];
    $before = filesize($abs);
    if (!self::ok()) return ['ok'=>false, 'error'=>'GD is not enabled on the server', 'before'=>$before];
    $info = @getimagesize($abs);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return ['ok'=>false, 'error'=>'not a valid image', 'before'=>$before];
    [$w, $h] = $info; $type = $info[2];
    if ($w < 1 || $h < 1 || $w > 12000 || $h > 12000) return ['ok'=>false, 'error'=>'image dimensions too large', 'before'=>$before];
    if ($type === IMAGETYPE_GIF) return ['ok'=>true, 'before'=>$before, 'after'=>$before, 'w'=>$w, 'h'=>$h, 'format'=>'gif', 'skipped'=>true]; // animated GIFs are left alone
    @ini_set('memory_limit', '512M');
    if ($w * $h * 5 > 380 * 1048576) return ['ok'=>false, 'error'=>'image too large to process', 'before'=>$before];
    [$mw, $mh, $q] = self::KINDS[$kind] ?? self::KINDS['auto'];
    $src = match ($type) { IMAGETYPE_JPEG => @imagecreatefromjpeg($abs), IMAGETYPE_PNG => @imagecreatefrompng($abs), default => @imagecreatefromwebp($abs) };
    if (!$src) return ['ok'=>false, 'error'=>'corrupted image', 'before'=>$before];
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
      $ex = @exif_read_data($abs); $o = $ex['Orientation'] ?? 1;
      $rot = [3=>180, 6=>-90, 8=>90][$o] ?? 0; if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; $w = imagesx($src); $h = imagesy($src); } }
    }
    $trimmed = false;
    if ($kind === 'product') { // cut away empty margins so the product fills the 600x600 frame (white/transparent background, centred)
      $bx = self::trimBox($src, $w, $h);
      if ($bx) { $sq = self::squareCrop($src, $bx, $type === IMAGETYPE_JPEG); if ($sq) { imagedestroy($src); $src = $sq; $w = $h = imagesx($src); $trimmed = true; } }
    }
    $s = $trimmed ? ($mw / $w) : min(1, $mw ? $mw / $w : 1, $mh ? $mh / $h : 1); // only trimmed product photos may be enlarged to the frame
    $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
    $img = $src;
    if ($s != 1) { $img = imagecreatetruecolor($nw, $nh); imagealphablending($img, false); imagesavealpha($img, true); imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127)); imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($src); }
    $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $abs);
    $tmp = $abs.'.tmp'; $ok = false;
    if ($type === IMAGETYPE_JPEG) { imageinterlace($img, true); $ok = imagejpeg($img, $tmp, $q); }
    elseif ($type === IMAGETYPE_PNG) { imagealphablending($img, false); imagesavealpha($img, true); $ok = imagepng($img, $tmp, 9); }
    else { $ok = imagewebp($img, $tmp, 80); }
    if ($ok && is_file($tmp) && ($trimmed || filesize($tmp) < $before)) rename($tmp, $abs); else @unlink($tmp); // keep whichever is smaller
    if ($type !== IMAGETYPE_WEBP) { imagepalettetotruecolor($img); @imagewebp($img, $abs.'.webp', 80); } // served automatically to browsers that accept WebP
    foreach (self::THUMBS as $tw) {
      if ($nw <= $tw && $nh <= $tw && $tw !== 150) continue;
      $k = min(1, $tw / max($nw, $nh)); $tx = max(1, (int)round($nw * $k)); $ty = max(1, (int)round($nh * $k));
      $t = imagecreatetruecolor($tx, $ty); imagealphablending($t, false); imagesavealpha($t, true); imagefill($t, 0, 0, imagecolorallocatealpha($t, 255, 255, 255, 127));
      imagecopyresampled($t, $img, 0, 0, 0, 0, $tx, $ty, $nw, $nh); @imagewebp($t, $base.'-'.$tw.'.webp', 78); imagedestroy($t);
    }
    imagedestroy($img);
    clearstatcache(true, $abs);
    return ['ok'=>true, 'before'=>$before, 'after'=>filesize($abs), 'w'=>$nw, 'h'=>$nh, 'format'=>strtolower(pathinfo($abs, PATHINFO_EXTENSION)), 'trimmed'=>$trimmed];
  }
  /** Content box of a product photo: [x,y,w,h] in source pixels when the product fills under 80% of the canvas (big transparent/white margins), else null. */
  public static function trimBox($src, int $w, int $h): ?array {
    $k = 256 / max($w, $h); $sw = max(1, (int)round($w * $k)); $sh = max(1, (int)round($h * $k));
    $t = imagecreatetruecolor($sw, $sh); imagealphablending($t, false); imagesavealpha($t, true); imagefill($t, 0, 0, imagecolorallocatealpha($t, 255, 255, 255, 127));
    imagecopyresampled($t, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);
    $x0 = $sw; $x1 = -1; $y0 = $sh; $y1 = -1;
    for ($y = 0; $y < $sh; $y++) for ($x = 0; $x < $sw; $x++) {
      $c = imagecolorat($t, $x, $y); $a = ($c >> 24) & 127; if ($a > 100) continue;
      $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255; if ($r > 240 && $g > 240 && $b > 240) continue;
      if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x; if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y;
    }
    imagedestroy($t);
    if ($x1 < 0) return null;
    $cw = ($x1 - $x0 + 1) / $sw; $ch = ($y1 - $y0 + 1) / $sh;
    if ($cw < 0.06 || $ch < 0.06) return null;      // blank / unreadable: leave alone
    if (max($cw, $ch) >= 0.8) return null;          // already fills the frame: idempotent
    return [(int)floor($x0 / $k), (int)floor($y0 / $k), (int)min($w, ceil(($x1 + 1) / $k)) - (int)floor($x0 / $k), (int)min($h, ceil(($y1 + 1) / $k)) - (int)floor($y0 / $k)];
  }
  /** Crop to the content box and centre it on a square canvas with ~4% breathing room. Returns the new image or null. */
  public static function squareCrop($src, array $bx, bool $whiteBg) {
    [$bx0, $by0, $bw, $bh] = $bx; $side = (int)round(max($bw, $bh) * 1.08); if ($side < 1) return null;
    $c = imagecreatetruecolor($side, $side);
    if ($whiteBg) { imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255)); }
    else { imagealphablending($c, false); imagesavealpha($c, true); imagefill($c, 0, 0, imagecolorallocatealpha($c, 255, 255, 255, 127)); }
    imagecopy($c, $src, (int)(($side - $bw) / 2), (int)(($side - $bh) / 2), $bx0, $by0, $bw, $bh);
    return $c;
  }
  public static function backup(string $abs): void {
    $d = storage_path('app/img-originals'); if (!is_dir($d)) @mkdir($d, 0775, true);
    $t = $d.'/'.basename($abs); if (!is_file($t)) @copy($abs, $t);
  }
  /** URL of the .webp sibling (name.ext.webp) for a local raster upload, or null. Lets markup point browsers straight at the small file instead of relying on Accept negotiation (a CDN may have cached the original). */
  public static function webp(?string $url): ?string {
    if (!$url || !str_starts_with($url, '/uploads/') || !preg_match('/\.(jpe?g|png)$/i', $url)) return null;
    return is_file(public_path(ltrim($url, '/').'.webp')) ? $url.'.webp' : null;
  }
  /** srcset for a local upload whose thumbnails exist; '' otherwise. */
  public static function srcset(?string $url): string {
    if (!$url || !str_starts_with($url, '/uploads/')) return '';
    $rel = preg_replace('/\.[A-Za-z0-9]+$/', '', $url); $out = [];
    foreach (self::THUMBS as $w) if (is_file(public_path(substr($rel, 1).'-'.$w.'.webp'))) $out[] = $rel.'-'.$w.'.webp '.$w.'w';
    return $out ? implode(', ', $out).', '.$url.' 600w' : '';
  }

  /** Every uploaded original (relative to public/uploads, including the chat folder). */
  public static function localFiles(): array {
    $out = []; foreach (['' => self::dir(), 'chat/' => self::dir().'/chat'] as $pre => $d) {
      if (!is_dir($d)) continue; foreach (scandir($d) as $f) if ($f[0] !== '.' && is_file($d.'/'.$f) && self::isOriginal($f)) $out[] = $pre.$f;
    }
    return $out;
  }
  public static function kindOfFile(string $f): string {
    $u = '/uploads/'.$f;
    if (DB::table('sliders')->where('image', $u)->exists()) return 'banner';
    if (DB::table('sliders')->where('mobile_image', $u)->exists()) return 'mobile';
    if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'image') && DB::table('categories')->where('image', $u)->exists()) return 'category';
    if (DB::table('products')->where('image', $u)->exists() || DB::table('product_images')->where('path', $u)->exists()) return 'product';
    return str_starts_with($f, 'chat/') || str_starts_with($f, 'c-') ? 'auto' : 'product';
  }
  /** Optimise up to $max not-yet-processed uploads within $sec seconds. @return array{processed:int,failed:int,saved:int,remaining:int} */
  public static function runBatch(int $max = 12, int $sec = 35): array {
    $t0 = microtime(true); $files = self::localFiles(); $done = DB::table('media_images')->whereIn('path', $files)->pluck('path')->all();
    $todo = array_values(array_diff($files, $done)); $n = 0; $saved = 0; $fail = 0; $backup = self::backupOn();
    foreach ($todo as $f) {
      if ($n >= $max || microtime(true) - $t0 > $sec) break;
      $abs = self::dir().'/'.$f; if ($backup) self::backup($abs);
      try { $res = self::process($abs, self::kindOfFile($f)); } catch (\Throwable $e) { $res = ['ok'=>false, 'error'=>mb_substr($e->getMessage(), 0, 150)]; }
      DB::table('media_images')->updateOrInsert(['path'=>$f], ['orig_bytes'=>$res['before'] ?? 0, 'opt_bytes'=>$res['after'] ?? ($res['before'] ?? 0), 'width'=>$res['w'] ?? null, 'height'=>$res['h'] ?? null, 'format'=>$res['format'] ?? null, 'status'=>$res['ok'] ? 'optimized' : 'failed', 'error'=>$res['ok'] ? null : mb_substr((string)$res['error'], 0, 150), 'optimized_at'=>now(), 'updated_at'=>now(), 'created_at'=>now()]);
      if ($res['ok']) $saved += max(0, ($res['before'] ?? 0) - ($res['after'] ?? 0)); else $fail++;
      $n++;
    }
    return ['processed'=>$n, 'failed'=>$fail, 'saved'=>$saved, 'remaining'=>max(0, count($todo) - $n)];
  }
  /** Source-store product photos live in sub-folders (uploads/store25, uploads/watch ...) that runBatch() never scans, so they stayed at full size (up to 1.5 MB).
   *  This walks those folders a few hundred files at a time and shrinks each photo to fit 600x600 (no cropping), with a WebP twin and thumbnails.
   *  No backup copy is kept for these (the source store still has the original); a file that grew again (re-imported) is processed again. */
  public static function runStoreBatch(int $max = 40, int $sec = 25): array {
    $C = \Illuminate\Support\Facades\Cache::class; $t0 = microtime(true); $n = 0; $saved = 0; $fail = 0; $renamed = 0;
    if ($C::get('img.sb2.rest')) return ['processed'=>0, 'failed'=>0, 'saved'=>0, 'state'=>'resting'];
    $dirs = []; foreach (@scandir(self::dir()) ?: [] as $d) if (preg_match('/^(store\d+|watch)$/', $d) && is_dir(self::dir().'/'.$d)) $dirs[] = $d;
    sort($dirs); $st = $C::get('img.sb2', ['d'=>0, 'after'=>'']); $di = (int)($st['d'] ?? 0); $after = (string)($st['after'] ?? '');
    while ($di < count($dirs) && $n < $max && microtime(true) - $t0 < $sec) {
      $d = $dirs[$di]; $files = [];
      foreach (@scandir(self::dir().'/'.$d) ?: [] as $f) if ($f[0] !== '.' && strcmp($f, $after) > 0 && self::isOriginal($f) && is_file(self::dir().'/'.$d.'/'.$f)) { $files[] = $f; if (count($files) >= 300) break; }
      if (!$files) { $di++; $after = ''; continue; }
      $known = []; $korig = []; foreach (DB::table('media_images')->whereIn('path', array_map(fn($f) => $d.'/'.$f, $files))->get(['path', 'opt_bytes', 'orig_bytes']) as $mi) { $known[$mi->path] = $mi->opt_bytes; $korig[$mi->path] = (int)$mi->orig_bytes; }
      foreach ($files as $f) {
        if ($n >= $max || microtime(true) - $t0 > $sec) break;
        $rel = $d.'/'.$f; $abs = self::dir().'/'.$rel; $size = (int)@filesize($abs); $after = $f;
        // A photo that the first run shrank IN PLACE is light on the server, but the CDN still serves the old heavy copy under the same address: it only needs the new file name.
        $stale = !preg_match('/-r\d{9,}\./', $f) && ($korig[$rel] ?? 0) > 150 * 1024 && ($korig[$rel] ?? 0) > $size * 1.3;
        if (!$stale) {
          if ($size <= 150 * 1024) continue;                                                // already light: nothing to gain
          if (isset($known[$rel]) && $size <= max(1, (int)$known[$rel]) * 1.3) continue;   // already done and not replaced by a bigger file
        }
        // The CDN and browsers keep the old heavy file for 30 days under the same address, so the shrunk photo gets a NEW file name (same idea as AiImage) and the product rows are pointed at it.
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION)); $nf = preg_replace('/\.[A-Za-z0-9]+$/', '', $f).'-r'.time().'.'.$ext; $nrel = $d.'/'.$nf; $nabs = self::dir().'/'.$nrel;
        if (!@copy($abs, $nabs)) { $fail++; $n++; continue; }
        try { $res = self::process($nabs, 'pfit'); } catch (\Throwable $e) { $res = ['ok'=>false, 'error'=>mb_substr($e->getMessage(), 0, 150), 'before'=>$size]; }
        clearstatcache(true, $nabs); $now = (int)@filesize($nabs);
        $row = fn($bytes, $ok, $err) => ['orig_bytes'=>$size, 'opt_bytes'=>$bytes, 'width'=>$res['w'] ?? null, 'height'=>$res['h'] ?? null, 'format'=>$res['format'] ?? null, 'status'=>$ok ? 'optimized' : 'failed', 'error'=>$ok ? null : mb_substr((string)$err, 0, 150), 'optimized_at'=>now(), 'updated_at'=>now(), 'created_at'=>now()];
        if ($stale && $now > 0) {
          $u1 = DB::table('products')->where('image', '/uploads/'.$rel)->update(['image' => '/uploads/'.$nrel]);
          $u2 = \Illuminate\Support\Facades\Schema::hasTable('product_images') ? DB::table('product_images')->where('path', '/uploads/'.$rel)->update(['path' => '/uploads/'.$nrel]) : 0;
          if (!$u1 && !$u2) { @unlink($nabs); foreach (['.webp', '-150.webp', '-300.webp'] as $sx) @unlink($sx === '.webp' ? $nabs.'.webp' : preg_replace('/\.[A-Za-z0-9]+$/', '', $nabs).$sx); }
          else { DB::table('media_images')->updateOrInsert(['path'=>$nrel], $row($now, true, null)); $renamed++; $saved += max(0, ($korig[$rel] ?? 0) - $now); }
          DB::table('media_images')->where('path', $rel)->update(['orig_bytes' => $size, 'updated_at' => now()]); // handled: never looked at again
          $n++; continue;
        }
        if (!$res['ok'] || $now < 1 || $now >= $size) { // could not be made smaller: keep the original exactly as it was
          @unlink($nabs); foreach (['.webp', '-150.webp', '-300.webp'] as $sx) @unlink($sx === '.webp' ? $nabs.'.webp' : preg_replace('/\.[A-Za-z0-9]+$/', '', $nabs).$sx);
          DB::table('media_images')->updateOrInsert(['path'=>$rel], $row($size, (bool)$res['ok'], $res['error'] ?? 'not smaller')); if (!$res['ok']) $fail++; $n++; continue;
        }
        $old = '/uploads/'.$rel; $new = '/uploads/'.$nrel;
        DB::table('products')->where('image', $old)->update(['image' => $new]);
        if (\Illuminate\Support\Facades\Schema::hasTable('product_images')) DB::table('product_images')->where('path', $old)->update(['path' => $new]);
        @copy($nabs, $abs); clearstatcache(true, $abs);                                    // the old address keeps working (feeds, old order pages), now with the light file
        DB::table('media_images')->updateOrInsert(['path'=>$nrel], $row($now, true, null)); DB::table('media_images')->updateOrInsert(['path'=>$rel], $row((int)@filesize($abs) ?: $now, true, null));
        $saved += max(0, $size - $now); $renamed++;
        $n++;
      }
    }
    if ($renamed) foreach (['store.home13', 'store.cats'] as $k) { try { $C::forget($k); } catch (\Throwable $e) {} }
    if ($di >= count($dirs)) { $C::put('img.sb2.rest', 1, 21600); $C::forget('img.sb2'); $state = 'cycle done'; } // every folder walked: look again in 6 hours
    else { $C::put('img.sb2', ['d'=>$di, 'after'=>$after], 86400 * 7); $state = ($dirs[$di] ?? '').' after '.$after; }
    return ['processed'=>$n, 'failed'=>$fail, 'saved'=>$saved, 'state'=>$state];
  }
  /** Local copy of an image that was hosted somewhere else, or null if it has not been copied yet. */
  public static function mirrored(?string $url): ?string {
    if (!$url || !preg_match('#^https?://#i', $url)) return null;
    try { $p = DB::table('media_sources')->where('url_hash', sha1($url))->value('path'); return $p && is_file(public_path(ltrim($p, '/'))) ? $p : null; } catch (\Throwable $e) { return null; }
  }
  /** Download an external image into public/uploads and optimise it. @return string|null the local URL */
  public static function mirror(string $url, string $kind): ?string {
    $h = sha1($url); $row = DB::table('media_sources')->where('url_hash', $h)->first();
    if ($row && $row->path && is_file(public_path(ltrim($row->path, '/')))) return $row->path;
    if ($row && (int)$row->tries >= 3) return null;
    $err = null; $path = null;
    try {
      $host = parse_url($url, PHP_URL_HOST); $ip = $host ? gethostbyname($host) : null;
      if (!$host || !$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('blocked host');
      $r = \Illuminate\Support\Facades\Http::timeout(12)->withHeaders(['User-Agent'=>'Mozilla/5.0 (DutyFreeShopBD image optimizer)', 'Accept'=>'image/*'])->get($url);
      if (!$r->ok()) throw new \RuntimeException('HTTP '.$r->status());
      $body = $r->body(); if (strlen($body) < 200 || strlen($body) > 12 * 1048576) throw new \RuntimeException('bad size');
      $info = @getimagesizefromstring($body); $ext = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_WEBP=>'webp', IMAGETYPE_GIF=>'gif'][$info[2] ?? 0] ?? null; if (!$ext) throw new \RuntimeException('not an image');
      $name = 'm-'.substr($h, 0, 16).'.'.$ext; $abs = self::dir().'/'.$name; if (!is_dir(self::dir())) @mkdir(self::dir(), 0775, true);
      file_put_contents($abs, $body); @chmod($abs, 0664); unset($body);
      $res = self::process($abs, $kind); if (!$res['ok']) { @unlink($abs); throw new \RuntimeException($res['error'] ?? 'optimise failed'); }
      DB::table('media_images')->updateOrInsert(['path'=>$name], ['orig_bytes'=>$res['before'] ?? 0, 'opt_bytes'=>$res['after'] ?? 0, 'width'=>$res['w'] ?? null, 'height'=>$res['h'] ?? null, 'format'=>$res['format'] ?? null, 'status'=>'optimized', 'error'=>null, 'optimized_at'=>now(), 'updated_at'=>now(), 'created_at'=>now()]);
      $path = '/uploads/'.$name;
    } catch (\Throwable $e) { $err = mb_substr($e->getMessage(), 0, 150); }
    DB::table('media_sources')->updateOrInsert(['url_hash'=>$h], ['url'=>mb_substr($url, 0, 900), 'path'=>$path, 'tries'=>$path ? 0 : (int)($row->tries ?? 0) + 1, 'error'=>$err, 'updated_at'=>now(), 'created_at'=>$row->created_at ?? now()]);
    return $path;
  }
}
