<?php
namespace App\Support;
use Illuminate\Support\Facades\Http;
/**
 * Reads ONE product photo with Claude vision and returns structured, per-field-confidence product data.
 * Runs server-side only (the API key is the encrypted "ai_key" secret already used by the shop assistant) and never returns any price.
 */
class ProductImageAIService {
  const MODEL = 'claude-sonnet-5-5';
  const FIELDS = ['name','brand','main_category','sub_category','child_category','product_type','style','variant','size','pack_size','qty_per_pack','origin','product_code','barcode','short_description','description','seo_title','seo_description','alt_text'];
  public static function model(): string { return AiImage::set('ai_fill_model', self::MODEL); }
  public static function ready(): bool { return (bool)Sms::secret('ai_key'); }

  /** JPEG (max 1568 px, white behind transparency) as base64, small enough for the API. */
  public static function prepare(string $abs): ?string {
    $i = @getimagesize($abs); if (!$i) return null;
    if (!function_exists('imagecreatetruecolor')) { $b = (string)file_get_contents($abs); return strlen($b) < 4800000 ? base64_encode($b) : null; }
    $s = match ($i[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($abs), IMAGETYPE_PNG => @imagecreatefrompng($abs), IMAGETYPE_WEBP => @imagecreatefromwebp($abs), IMAGETYPE_GIF => @imagecreatefromgif($abs), default => null };
    if (!$s) return null;
    $w = imagesx($s); $h = imagesy($s); $sc = min(1, 1568 / max($w, $h)); $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
    $c = imagecreatetruecolor($nw, $nh); imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255)); imagecopyresampled($c, $s, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($c, null, 86); return base64_encode((string)ob_get_clean());
  }

  private static function field(string $desc): array {
    return ['type'=>'object', 'description'=>$desc, 'properties'=>['value'=>['type'=>['string', 'null']], 'confidence'=>['type'=>'integer', 'minimum'=>0, 'maximum'=>100]], 'required'=>['value', 'confidence']];
  }
  private static function schema(): array {
    $d = [
      'name'=>'Full product name as a shopper would search it: brand + product + variant (e.g. "Patron Nano White Special English"). Follow the style of the EXISTING NAMES list.',
      'brand'=>'Brand printed on the pack.', 'main_category'=>'Copy exactly one MAIN category name from the CATEGORY TREE, else null.',
      'sub_category'=>'Copy exactly one sub category name listed under that main category, else null.', 'child_category'=>'Copy exactly one child category name listed under that sub category, else null.',
      'product_type'=>'Kind of product (e.g. Nano cigarette, Eau de parfum, Face wash).', 'style'=>'Style / edition if visible (e.g. Slim, Special).', 'variant'=>'Flavour, colour, shade or edition printed on the pack.',
      'size'=>'Volume or weight printed on the pack (e.g. 100 ml, 50 g), else null.', 'pack_size'=>'Pack size if visible (e.g. 20 sticks, 6 pcs).', 'qty_per_pack'=>'Quantity per pack if visible, else null.',
      'origin'=>'Country of origin / made in, only if printed.', 'product_code'=>'Model or product code printed on the pack, only if readable.', 'barcode'=>'Barcode digits ONLY if fully readable, else null.',
      'short_description'=>'One sentence (max 160 chars) using only visible facts.', 'description'=>'2-4 short plain-text sentences using only visible facts; no medical, health or guarantee claims; no invented specs.',
      'seo_title'=>'Max 60 chars, e.g. "<Name> Price in Bangladesh | DutyFree Shop BD". No price numbers.', 'seo_description'=>'Max 155 chars, concise and factual, no price numbers.', 'alt_text'=>'Image alt text, max 110 chars, describes the product shown.',
    ];
    $p = []; foreach (self::FIELDS as $k) $p[$k] = self::field($d[$k]);
    $p['tags'] = ['type'=>'array', 'items'=>['type'=>'string'], 'maxItems'=>10, 'description'=>'Short lowercase tags (brand, type, variant). Only facts from the pack.'];
    $p['keywords'] = ['type'=>'array', 'items'=>['type'=>'string'], 'maxItems'=>12, 'description'=>'Search keywords in English; a few Bangla/Banglish forms of the product name are welcome.'];
    $p['identified'] = ['type'=>'boolean', 'description'=>'false when the photo is not a clear retail product or nothing can be read confidently.'];
    $p['multiple_products'] = ['type'=>'boolean', 'description'=>'true when the photo shows several DIFFERENT products.'];
    $p['candidates'] = ['type'=>'array', 'maxItems'=>8, 'items'=>['type'=>'string'], 'description'=>'When multiple_products: a short label for each distinct product (brand + name + variant).'];
    $p['image_quality'] = ['type'=>'string', 'enum'=>['good', 'ok', 'poor']];
    $p['notes'] = ['type'=>'string', 'description'=>'One short sentence: anything the reviewer should double-check, or why it could not be identified.'];
    return ['type'=>'object', 'properties'=>$p, 'required'=>array_merge(self::FIELDS, ['tags', 'keywords', 'identified', 'multiple_products', 'candidates', 'image_quality', 'notes'])];
  }

  /** @return array{ok:bool,data?:array,error?:string} */
  public static function analyze(string $abs, string $context, ?string $focus = null): array {
    $key = Sms::secret('ai_key'); if (!$key) return ['ok'=>false, 'error'=>'AI key is not set (AI Chat settings).'];
    $b64 = self::prepare($abs); if (!$b64) return ['ok'=>false, 'error'=>'Unsupported or corrupted image'];
    $sys = "You identify retail products from ONE package photo for DutyFreeBD, a Bangladeshi online store (perfume and atar, skin care, hair care, cigarette and vape, daily needs, personal wellness, baby care and others). "
      ."Report ONLY what is printed on the pack or confidently identifiable. Never output any price. Never invent a barcode, code, size, country or claim: use null with confidence 0 when it is not visible. "
      ."Confidence is 0-100 per field: 90+ only when the text is clearly readable. Category fields must be copied exactly from the CATEGORY TREE. Cigarettes are sold by the carton, but describe the pack that is shown. "
      ."If the photo is blurry, not a product, or you cannot read the brand and name, set identified=false and explain in notes. If it shows several different products set multiple_products=true and list them in candidates.\n\n".$context;
    $txt = $focus !== null && $focus !== '' ? "The photo contains several products. Analyse ONLY this one: ".mb_substr($focus, 0, 200).". Call report_product." : "Analyse this product photo and call report_product.";
    $body = ['model'=>self::model(), 'max_tokens'=>2500, 'system'=>$sys, 'tools'=>[['name'=>'report_product', 'description'=>'Report the product detected in the photo.', 'input_schema'=>self::schema()]], 'tool_choice'=>['type'=>'tool', 'name'=>'report_product'],
      'messages'=>[['role'=>'user', 'content'=>[['type'=>'image', 'source'=>['type'=>'base64', 'media_type'=>'image/jpeg', 'data'=>$b64]], ['type'=>'text', 'text'=>$txt]]]]];
    $res = null; $err = '';
    for ($try = 0; $try < 2; $try++) {
      try { $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(90)->post('https://api.anthropic.com/v1/messages', $body); }
      catch (\Throwable $e) { $err = 'network: '.mb_substr($e->getMessage(), 0, 100); $res = null; continue; }
      if ($res->ok()) break;
      $err = 'HTTP '.$res->status().' '.mb_substr((string)($res->json('error.message') ?? ''), 0, 160);
      if (!in_array($res->status(), [429, 500, 502, 503, 529], true)) break;
      usleep(1500000);
    }
    if (!$res || !$res->ok()) return ['ok'=>false, 'error'=>$err ?: 'AI request failed'];
    foreach ($res->json('content') ?? [] as $blk) if (($blk['type'] ?? '') === 'tool_use' && ($blk['name'] ?? '') === 'report_product' && is_array($blk['input'] ?? null)) return ['ok'=>true, 'data'=>self::clean($blk['input'])];
    return ['ok'=>false, 'error'=>'AI returned no product data'];
  }

  /** Normalise the tool output: trimmed strings, 0-100 confidence, never a price. */
  private static function clean(array $in): array {
    $f = [];
    foreach (self::FIELDS as $k) {
      $v = $in[$k]['value'] ?? null; $c = (int)($in[$k]['confidence'] ?? 0);
      $v = is_string($v) ? trim(preg_replace('/\s+/u', ' ', strip_tags($v))) : null; if ($v === '' || strcasecmp((string)$v, 'null') === 0) $v = null;
      $f[$k] = ['v'=>$v, 'c'=>$v === null ? 0 : max(0, min(100, $c))];
    }
    if ($f['barcode']['v'] !== null && !preg_match('/^\d{8,14}$/', preg_replace('/\D/', '', $f['barcode']['v']))) $f['barcode'] = ['v'=>null, 'c'=>0];
    elseif ($f['barcode']['v'] !== null) $f['barcode']['v'] = preg_replace('/\D/', '', $f['barcode']['v']);
    $list = fn($a, $n) => array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 60), is_array($a) ? $a : []), fn($x) => $x !== ''))) ?: [];
    return ['fields'=>$f, 'tags'=>array_slice($list($in['tags'] ?? [], 10), 0, 10), 'keywords'=>array_slice($list($in['keywords'] ?? [], 12), 0, 12),
      'identified'=>(bool)($in['identified'] ?? false), 'multiple'=>(bool)($in['multiple_products'] ?? false), 'candidates'=>array_slice($list($in['candidates'] ?? [], 8), 0, 8),
      'quality'=>in_array($in['image_quality'] ?? '', ['good', 'ok', 'poor'], true) ? $in['image_quality'] : 'ok', 'notes'=>mb_substr(trim((string)($in['notes'] ?? '')), 0, 240)];
  }
}
