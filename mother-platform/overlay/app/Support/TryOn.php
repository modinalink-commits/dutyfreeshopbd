<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
/** Sunglass Try-On product overlays. The PRODUCT photo is cut out to a transparent front-view PNG (AI is used on the product only, never on a customer's face). */
class TryOn {
  const PROMPT = "Edit only the supplied photograph of a pair of sunglasses. Return the sunglasses alone as one clean cut-out, seen exactly from the FRONT, upright and perfectly symmetrical (straighten them if the photo is angled; the arms may be folded out of sight so only the front frame and lenses show). Keep the real frame shape, thickness, colour, finish, hinge details, any logo on the frame and the real lens tint exactly as in the photo - never redesign or invent anything. The lenses must stay realistically semi-transparent (keep their tint) so skin can show through when worn. Remove the whole background, every shadow, reflection on the surface, hands, models, faces, watermark, shop logo, text and price tag. Fully transparent background. Square 1:1 image, the sunglasses centred and filling about 90% of the width, not cropped. Do not add anything.";
  public static function dir(): string { $d = public_path('uploads/tryon'); if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
  public static function sunglassQuery() {
    return DB::table('products')->join('categories', 'categories.id', '=', 'products.category_id')
      ->where(fn($q) => $q->whereRaw("lower(categories.slug) like '%sunglass%'")->orWhereRaw("lower(categories.name) like '%sunglass%'"))
      ->select('products.id', 'products.name', 'products.image', 'products.status');
  }
  public static function isSunglass(int $productId): bool { return self::sunglassQuery()->where('products.id', $productId)->exists(); }
  public static function ready(int $productId): ?object { return DB::table('tryon_assets')->where('product_id', $productId)->where('status', 'ready')->first(); }
  private static function srcBytes(string $img): string {
    $abs = AiImage::absOf($img);
    if ($abs) return (string)file_get_contents($abs);
    if (str_starts_with($img, '/')) $img = rtrim((string)config('app.url', 'https://dutyfreebd.com'), '/') . $img;
    if (!preg_match('#^https?://#i', $img)) throw new \RuntimeException('Product image not found');
    $r = Http::timeout(30)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DutyFreeBD-ImageBot/1.0)'])->get($img);
    if (!$r->ok()) throw new \RuntimeException('Could not read the product image (HTTP ' . $r->status() . ')');
    return $r->body();
  }
  /** Square 1024 PNG of the product photo on white (same as the product image pipeline). */
  private static function prepare(string $bin): string {
    $s = @imagecreatefromstring($bin); if (!$s) throw new \RuntimeException('Unsupported product image');
    $w = imagesx($s); $h = imagesy($s); $sc = min(1, 1024 / max($w, $h)); $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
    $c = imagecreatetruecolor(1024, 1024); imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255));
    imagecopyresampled($c, $s, (int)((1024 - $nw) / 2), (int)((1024 - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagepng($c, null, 6); return (string)ob_get_clean();
  }
  /** If the API returned an opaque white background, make the white area connected to the edges transparent. */
  private static function ensureAlpha($im) {
    $w = imagesx($im); $h = imagesy($im); imagealphablending($im, false); imagesavealpha($im, true);
    $tr = 0; foreach ([[2,2],[$w-3,2],[2,$h-3],[$w-3,$h-3]] as [$x,$y]) if (((imagecolorat($im, $x, $y) >> 24) & 127) > 100) $tr++;
    if ($tr >= 3) return $im;
    $seen = []; $stack = []; $clear = imagecolorallocatealpha($im, 255, 255, 255, 127);
    for ($x = 0; $x < $w; $x++) { $stack[] = [$x, 0]; $stack[] = [$x, $h - 1]; } for ($y = 0; $y < $h; $y++) { $stack[] = [0, $y]; $stack[] = [$w - 1, $y]; }
    while ($stack) { [$x, $y] = array_pop($stack); if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) continue; $k = $y * $w + $x; if (isset($seen[$k])) continue; $seen[$k] = 1;
      $c = imagecolorat($im, $x, $y); if (!(((($c >> 16) & 255) > 235) && ((($c >> 8) & 255) > 235) && (($c & 255) > 235))) continue;
      imagesetpixel($im, $x, $y, $clear); $stack[] = [$x + 1, $y]; $stack[] = [$x - 1, $y]; $stack[] = [$x, $y + 1]; $stack[] = [$x, $y - 1]; }
    return $im;
  }
  public static function build(int $pid): array {
    @set_time_limit(200); @ini_set('memory_limit', '512M');
    $p = DB::table('products')->find($pid); if (!$p) return ['ok' => false, 'error' => 'Product not found'];
    DB::table('tryon_assets')->updateOrInsert(['product_id' => $pid], ['status' => 'processing', 'error' => null, 'updated_at' => now(), 'created_at' => now()]);
    try {
      $key = AiImage::key(); if (!$key) throw new \RuntimeException('OpenAI API key is not set (AI settings).');
      $png = self::prepare(self::srcBytes((string)$p->image));
      $model = AiImage::set('ai_img_model', AiImage::MODEL);
      $call = fn(array $f) => Http::timeout(180)->withToken($key)->attach('image', $png, 'product.png')->post('https://api.openai.com/v1/images/edits', $f);
      $r = $call(['model' => $model, 'prompt' => self::PROMPT, 'size' => '1024x1024', 'quality' => 'high', 'background' => 'transparent', 'output_format' => 'png', 'n' => 1]);
      if ($r->status() === 400 && preg_match('/background|output_format|quality|unknown parameter|unsupported|invalid value/i', (string)$r->body()) && !preg_match('/does not exist/i', (string)$r->body())) $r = $call(['model' => $model, 'prompt' => self::PROMPT, 'size' => '1024x1024', 'n' => 1]);
      if (!$r->ok()) throw new \RuntimeException('OpenAI error ' . $r->status() . ': ' . mb_substr((string)($r->json('error.message') ?? $r->body()), 0, 200));
      $b64 = $r->json('data.0.b64_json'); if (!$b64) throw new \RuntimeException('OpenAI returned no image');
      $im = @imagecreatefromstring((string)base64_decode($b64)); if (!$im) throw new \RuntimeException('Could not read the result');
      $im = self::ensureAlpha($im); $w = imagesx($im); $h = imagesy($im);
      $x0 = $w; $x1 = 0; $y0 = $h; $y1 = 0;
      for ($y = 0; $y < $h; $y += 2) for ($x = 0; $x < $w; $x += 2) if ((((imagecolorat($im, $x, $y) >> 24) & 127)) < 100) { $x0 = min($x0, $x); $x1 = max($x1, $x); $y0 = min($y0, $y); $y1 = max($y1, $y); }
      if ($x1 <= $x0 || $y1 <= $y0) throw new \RuntimeException('No sunglasses found in the result');
      $cw = min($w - $x0, $x1 - $x0 + 3); $ch = min($h - $y0, $y1 - $y0 + 3);
      $o = imagecreatetruecolor($cw, $ch); imagealphablending($o, false); imagesavealpha($o, true); imagefill($o, 0, 0, imagecolorallocatealpha($o, 0, 0, 0, 127));
      imagecopy($o, $im, 0, 0, $x0, $y0, $cw, $ch);
      $f = 'p' . $pid . '-' . time() . '.png'; imagepng($o, self::dir() . '/' . $f, 6); @chmod(self::dir() . '/' . $f, 0664);
      DB::table('tryon_assets')->where('product_id', $pid)->update(['status' => 'ready', 'png' => '/uploads/tryon/' . $f, 'w' => $cw, 'h' => $ch, 'error' => null, 'updated_at' => now()]);
      return ['ok' => true, 'png' => '/uploads/tryon/' . $f];
    } catch (\Throwable $e) {
      $m = mb_substr($e->getMessage(), 0, 300); DB::table('tryon_assets')->where('product_id', $pid)->update(['status' => 'failed', 'error' => $m, 'updated_at' => now()]); return ['ok' => false, 'error' => $m];
    }
  }
  public static function days(): int { return max(1, min(365, (int)AiImage::set('tryon_retention_days', '30'))); }
  public static function purge(): int {
    $n = 0; foreach (DB::table('tryon_photos')->where('expires_at', '<', now())->get() as $r) { @unlink(storage_path('app/tryon/' . $r->file)); DB::table('tryon_photos')->where('id', $r->id)->delete(); $n++; } return $n;
  }
}
