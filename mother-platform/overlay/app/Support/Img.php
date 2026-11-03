<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Image optimisation with PHP GD: validate, resize, compress, WebP + thumbnails. Never breaks an upload: on any problem the original file stays as it is. */
class Img {
  /** kind => [max width, max height, jpeg quality]. 0 = no limit on that side. */
  const KINDS = ['product'=>[600, 600, 82], 'category'=>[200, 200, 82], 'banner'=>[1900, 0, 82], 'mobile'=>[1080, 0, 80], 'auto'=>[1600, 1600, 82]];
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
    $s = min(1, $mw ? $mw / $w : 1, $mh ? $mh / $h : 1); // never upscale
    $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
    $img = $src;
    if ($s < 1) { $img = imagecreatetruecolor($nw, $nh); imagealphablending($img, false); imagesavealpha($img, true); imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127)); imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($src); }
    $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $abs);
    $tmp = $abs.'.tmp'; $ok = false;
    if ($type === IMAGETYPE_JPEG) { imageinterlace($img, true); $ok = imagejpeg($img, $tmp, $q); }
    elseif ($type === IMAGETYPE_PNG) { imagealphablending($img, false); imagesavealpha($img, true); $ok = imagepng($img, $tmp, 9); }
    else { $ok = imagewebp($img, $tmp, 80); }
    if ($ok && is_file($tmp) && filesize($tmp) < $before) rename($tmp, $abs); else @unlink($tmp); // keep whichever is smaller
    if ($type !== IMAGETYPE_WEBP) { imagepalettetotruecolor($img); @imagewebp($img, $abs.'.webp', 80); } // served automatically to browsers that accept WebP
    foreach (self::THUMBS as $tw) {
      if ($nw <= $tw && $nh <= $tw && $tw !== 150) continue;
      $k = min(1, $tw / max($nw, $nh)); $tx = max(1, (int)round($nw * $k)); $ty = max(1, (int)round($nh * $k));
      $t = imagecreatetruecolor($tx, $ty); imagealphablending($t, false); imagesavealpha($t, true); imagefill($t, 0, 0, imagecolorallocatealpha($t, 255, 255, 255, 127));
      imagecopyresampled($t, $img, 0, 0, 0, 0, $tx, $ty, $nw, $nh); @imagewebp($t, $base.'-'.$tw.'.webp', 78); imagedestroy($t);
    }
    imagedestroy($img);
    clearstatcache(true, $abs);
    return ['ok'=>true, 'before'=>$before, 'after'=>filesize($abs), 'w'=>$nw, 'h'=>$nh, 'format'=>strtolower(pathinfo($abs, PATHINFO_EXTENSION))];
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
