<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
/** Server-side AI product image processing: OpenAI cuts out the product, GD centres it on a 600x600 white canvas. */
class AiImage {
  const MODEL = 'gpt-image-2.5-sunburst';
  const PROMPT = "Edit only the supplied product photograph and return one standard e-commerce product image. Keep exactly ONE product: the real physical product with its own original packaging, printed brand name, logo and text unchanged (shape, proportions, colors and details must stay exactly as in the photo; never redesign, redraw or invent anything on the product). Remove everything that is not the product itself: the whole background, hands, props, other items, shadows from the old scene, AND every watermark, shop logo, website name, phone number, badge, sticker, price tag, text overlay, frame or graphic that was added on top of or around the photo. Nothing may touch or overlap the product. Place the complete product upright, vertically and horizontally centered on a clean pure white (#FFFFFF) seamless background, fully visible (never cropped) and filling about 80% of the frame with equal white space on all sides. Use soft, even, neutral studio lighting with natural colors, sharp focus and a very subtle realistic contact shadow directly under the product. Square 1:1 image. Do not add any text, logo, watermark, decoration or extra object.";
  public static function set(string $k, ?string $d = null): ?string { $v = DB::table('settings')->where('key', $k)->value('value'); return ($v === null || $v === '') ? $d : $v; }
  public static function key(): ?string { return self::set('openai_api_key'); }
  public static function prompt(): string { return self::set('ai_img_prompt', self::PROMPT); }
  public static function dir(): string { $d = public_path('uploads/ai'); if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
  public static function absOf(string $url): ?string { $p = parse_url($url, PHP_URL_PATH); if (!$p) return null; $a = public_path(ltrim($p, '/')); return is_file($a) && str_starts_with(realpath($a) ?: '', realpath(public_path()) ?: '#') ? $a : null; }

  /** Resolve the original image to a local copy kept for reprocessing. */
  public static function original(object $row): string {
    if ($row->original_image && is_file(public_path(ltrim($row->original_image, '/')))) return public_path(ltrim($row->original_image, '/'));
    $src = (string)$row->source_url; $abs = null;
    $abs = self::absOf($src);
    if (!$abs && str_starts_with($src, '/') && !str_starts_with($src, '//')) $src = rtrim((string)config('app.url', 'https://dutyfreebd.com'), '/').$src; // file not on this disk (served through the web server): fetch it over HTTP
    if (!$abs && preg_match('#^https?://#i', $src)) $abs = self::download($src, (int)$row->id);
    if (!$abs || !is_file($abs)) throw new \RuntimeException('Original image file not found on the server');
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'jpg'; $name = 'orig-'.$row->id.'.'.$ext; (dirname($abs) === self::dir()) ? rename($abs, self::dir().'/'.$name) : copy($abs, self::dir().'/'.$name);
    DB::table('ai_images')->where('id', $row->id)->update(['original_image'=>'/uploads/ai/'.$name]);
    return self::dir().'/'.$name;
  }
  /** Download an image hosted elsewhere into uploads/ai (SSRF-guarded). Returns the absolute path. */
  private static function download(string $url, int $id): string {
    $host = parse_url($url, PHP_URL_HOST); $ip = $host ? gethostbyname($host) : null;
    if (!$host || !$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('Original image host is not allowed');
    $r = null; $last = '';
    foreach ([['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'https://'.$host.'/'], ['Mozilla/5.0 (compatible; DutyFreeBD-ImageBot/1.0)', null]] as [$ua, $ref]) {
      try { $h = ['User-Agent'=>$ua, 'Accept'=>'image/avif,image/webp,image/*,*/*;q=0.8']; if ($ref) $h['Referer'] = $ref; $r = \Illuminate\Support\Facades\Http::timeout(25)->withHeaders($h)->get($url); if ($r->ok()) break; $last = 'HTTP '.$r->status(); } catch (\Throwable $e) { $last = mb_substr($e->getMessage(), 0, 120); $r = null; }
    }
    if (!$r || !$r->ok()) throw new \RuntimeException('Could not download the original image from its website ('.$last.')');
    $body = $r->body(); if (strlen($body) < 200 || strlen($body) > 15 * 1048576) throw new \RuntimeException('Downloaded original is not a valid image');
    $i = @getimagesizefromstring($body); $ext = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_WEBP=>'webp'][$i[2] ?? 0] ?? null; if (!$ext) throw new \RuntimeException('Downloaded original is not a JPG, PNG or WebP image');
    $abs = self::dir().'/src-'.$id.'.'.$ext; file_put_contents($abs, $body); @chmod($abs, 0664); return $abs;
  }
  private static function load(string $abs) {
    $i = @getimagesize($abs); if (!$i) return null;
    return match ($i[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($abs), IMAGETYPE_PNG => @imagecreatefrompng($abs), IMAGETYPE_WEBP => @imagecreatefromwebp($abs), default => null };
  }
  /** Square PNG (max 1024) of the original, centred on white, ready to send to the API. */
  private static function prepare(string $abs): string {
    $s = self::load($abs); if (!$s) throw new \RuntimeException('Unsupported or corrupted image');
    $w = imagesx($s); $h = imagesy($s); $sc = min(1, 1024 / max($w, $h)); $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
    $c = imagecreatetruecolor(1024, 1024); imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255));
    imagecopyresampled($c, $s, (int)((1024 - $nw) / 2), (int)((1024 - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagepng($c, null, 6); return (string)ob_get_clean();
  }
  /** Run the full pipeline for one ai_images row. */
  public static function run(int $id): array {
    $row = DB::table('ai_images')->find($id); if (!$row) return ['ok'=>false, 'error'=>'Record not found'];
    @set_time_limit(200); @ini_set('memory_limit', '512M');
    DB::table('ai_images')->where('id', $id)->update(['ai_status'=>'processing', 'processing_error'=>null, 'ai_prompt'=>self::prompt(), 'updated_at'=>now()]);
    try {
      if (!Img::ok()) throw new \RuntimeException('PHP GD is not enabled on the server');
      $key = self::key(); if (!$key) throw new \RuntimeException('OpenAI API key is not set. Add it in the AI settings box.');
      $abs = self::original($row);
      $png = self::prepare($abs);
      $q = in_array(self::set('ai_img_quality', 'medium'), ['low', 'medium', 'high'], true) ? self::set('ai_img_quality', 'medium') : 'medium';
      $model = self::set('ai_img_model', self::MODEL);
      $call = fn(array $f) => Http::timeout(180)->withToken($key)->attach('image', $png, 'product.png')->post('https://api.openai.com/v1/images/edits', $f);
      $r = $call(['model'=>$model, 'prompt'=>self::prompt(), 'size'=>'1024x1024', 'quality'=>$q, 'background'=>'transparent', 'output_format'=>'png', 'n'=>1]);
      if ($r->status() === 400 && preg_match('/background|output_format|quality|unknown parameter|unsupported|invalid value/i', (string)$r->body()) && !preg_match('/does not exist/i', (string)$r->body())) $r = $call(['model'=>$model, 'prompt'=>self::prompt(), 'size'=>'1024x1024', 'n'=>1]);
      if (!$r->ok()) throw new \RuntimeException('OpenAI error '.$r->status().': '.mb_substr((string)($r->json('error.message') ?? $r->body()), 0, 220));
      $b64 = $r->json('data.0.b64_json'); if (!$b64) throw new \RuntimeException('OpenAI returned no image');
      $out = self::finish((string)base64_decode($b64), $id);
      DB::table('ai_images')->where('id', $id)->update(['ai_status'=>'completed', 'processed_image'=>$out['p'], 'thumbnail_image'=>$out['t'], 'processed_at'=>now(), 'processing_error'=>null, 'updated_at'=>now()]);
      return ['ok'=>true, 'processed'=>$out['p'].'?v='.time(), 'thumb'=>$out['t'].'?v='.time()];
    } catch (\Throwable $e) {
      $msg = mb_substr($e->getMessage(), 0, 400);
      DB::table('ai_images')->where('id', $id)->update(['ai_status'=>'failed', 'processing_error'=>$msg, 'updated_at'=>now()]);
      return ['ok'=>false, 'error'=>$msg];
    }
  }
  /** Crop to the product, centre on a 600x600 white canvas with a soft shadow, save + thumbnails. */
  public static function finish(string $bin, int $id): array {
    $im = @imagecreatefromstring($bin); if (!$im) throw new \RuntimeException('Could not read the processed image');
    $w = imagesx($im); $h = imagesy($im); imagealphablending($im, false); imagesavealpha($im, true);
    $alpha = false; foreach ([[2,2],[$w-3,2],[2,$h-3],[$w-3,$h-3],[(int)($w/2),2],[2,(int)($h/2)]] as [$x,$y]) if (((imagecolorat($im, $x, $y) >> 24) & 127) > 100) { $alpha = true; break; }
    $x0 = $w; $y0 = $h; $x1 = -1; $y1 = -1; $st = 2;
    for ($y = 0; $y < $h; $y += $st) for ($x = 0; $x < $w; $x += $st) {
      $c = imagecolorat($im, $x, $y); $a = ($c >> 24) & 127;
      $fg = $alpha ? ($a < 100) : (!(((($c >> 16) & 255) > 244) && ((($c >> 8) & 255) > 244) && (($c & 255) > 244)));
      if ($fg) { if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x; if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y; }
    }
    if ($x1 < 0) throw new \RuntimeException('No product found in the processed image');
    $cw = min($w - $x0, $x1 - $x0 + $st + 1); $ch = min($h - $y0, $y1 - $y0 + $st + 1);
    $box = 500; $sc = min($box / $cw, $box / $ch); $nw = max(1, (int)round($cw * $sc)); $nh = max(1, (int)round($ch * $sc));
    $cv = imagecreatetruecolor(600, 600); imagefill($cv, 0, 0, imagecolorallocate($cv, 255, 255, 255));
    $px = (int)round((600 - $nw) / 2); $py = (int)round((600 - $nh) / 2);
    // soft natural shadow under the product
    imagealphablending($cv, true);
    for ($i = 0; $i < 7; $i++) { $sw = (int)($nw * (0.80 - $i * 0.07)); $sh = (int)(10 + (6 - $i) * 1.4); imagefilledellipse($cv, 300, min(594, $py + $nh + 2), max(10, $sw), $sh, imagecolorallocatealpha($cv, 90, 90, 90, 120 - $i * 2)); }
    imagealphablending($cv, true);
    imagecopyresampled($cv, $im, $px, $py, $x0, $y0, $nw, $nh, $cw, $ch);
    $d = self::dir(); $base = 'ai-'.$id; $p = $d.'/'.$base.'.jpg';
    imagejpeg($cv, $p, 90); @chmod($p, 0664);
    foreach ([300, 150] as $s) { $t = imagecreatetruecolor($s, $s); imagecopyresampled($t, $cv, 0, 0, 0, 0, $s, $s, 600, 600); imagejpeg($t, $d.'/'.$base.'-'.$s.'.jpg', 86); @chmod($d.'/'.$base.'-'.$s.'.jpg', 0664); }
    if (function_exists('imagewebp')) { imagewebp($cv, $d.'/'.$base.'.jpg.webp', 82); foreach ([300, 150] as $s) { $t = imagecreatetruecolor($s, $s); imagecopyresampled($t, $cv, 0, 0, 0, 0, $s, $s, 600, 600); imagewebp($t, $d.'/'.$base.'-'.$s.'.jpg.webp', 80); } }
    return ['p'=>'/uploads/ai/'.$base.'.jpg', 't'=>'/uploads/ai/'.$base.'-300.jpg'];
  }
  /** Rebuild only the thumbnails from an already processed image. */
  public static function thumbs(int $id): bool {
    $r = DB::table('ai_images')->find($id); if (!$r || !$r->processed_image) return false;
    $abs = public_path(ltrim($r->processed_image, '/')); $im = self::load($abs); if (!$im) return false; $d = self::dir();
    foreach ([300, 150] as $s) { $t = imagecreatetruecolor($s, $s); imagecopyresampled($t, $im, 0, 0, 0, 0, $s, $s, imagesx($im), imagesy($im)); imagejpeg($t, $d.'/ai-'.$id.'-'.$s.'.jpg', 86); }
    DB::table('ai_images')->where('id', $id)->update(['thumbnail_image'=>'/uploads/ai/ai-'.$id.'-300.jpg']); return true;
  }
}
