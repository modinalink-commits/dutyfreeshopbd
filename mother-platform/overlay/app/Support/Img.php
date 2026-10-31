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
  /** srcset for a local upload whose thumbnails exist; '' otherwise. */
  public static function srcset(?string $url): string {
    if (!$url || !str_starts_with($url, '/uploads/')) return '';
    $rel = preg_replace('/\.[A-Za-z0-9]+$/', '', $url); $out = [];
    foreach (self::THUMBS as $w) if (is_file(public_path(substr($rel, 1).'-'.$w.'.webp'))) $out[] = $rel.'-'.$w.'.webp '.$w.'w';
    return $out ? implode(', ', $out).', '.$url.' 600w' : '';
  }
}
