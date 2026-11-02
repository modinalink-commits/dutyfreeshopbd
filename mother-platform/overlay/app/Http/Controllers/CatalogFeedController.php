<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
/** Meta / Google product feed served live from the database: /products.xml (id = products.ext_id, same ID the Pixel and CAPI send). */
class CatalogFeedController extends Controller {
  const BASE = 'https://dutyfreebd.com';
  private static function x(?string $s): string { $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$s); return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
  public function feed() {
    $xml = StoreController::rem('store.catalogfeed1', 1800, function () {
      $brands = DB::table('brands')->pluck('name', 'id')->all();
      $cats = DB::table('categories')->pluck('name', 'id')->all();
      $o = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel><title>DutyFreeBD</title><link>' . self::BASE . '</link><description>DutyFreeBD product feed</description>' . "\n";
      foreach (DB::table('products')->where('published', true)->where('price', '>', 0)->orderBy('id')->cursor() as $p) {
        $id = (string)(($p->ext_id ?? null) ?: $p->id);
        $img = trim((string)$p->image); if ($img === '') continue;
        if (!preg_match('#^https?://#i', $img)) $img = self::BASE . '/' . ltrim($img, '/');
        $link = self::BASE . StoreController::url($p);
        $price = (float)$p->price; $cmp = (float)($p->compare_price ?? 0);
        $pr = $cmp > $price ? $cmp : $price; $sale = $cmp > $price ? $price : null;
        $desc = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($p->description ?? '')))); if ($desc === '') $desc = (string)$p->name;
        $brand = $brands[$p->brand_id ?? 0] ?? 'DutyFreeBD';
        $o .= '<item><g:id>' . self::x($id) . '</g:id><g:title>' . self::x(mb_substr($p->name, 0, 150)) . '</g:title><g:description>' . self::x(mb_substr($desc, 0, 4900)) . '</g:description><g:link>' . self::x($link) . '</g:link><g:image_link>' . self::x($img) . '</g:image_link><g:availability>' . (((int)$p->stock) > 0 ? 'in stock' : 'out of stock') . '</g:availability><g:condition>new</g:condition><g:price>' . number_format($pr, 2, '.', '') . ' BDT</g:price>' . ($sale ? '<g:sale_price>' . number_format($sale, 2, '.', '') . ' BDT</g:sale_price>' : '') . '<g:brand>' . self::x($brand) . '</g:brand><g:mpn>' . self::x((string)($p->sku ?: 'DF-' . $id)) . '</g:mpn><g:identifier_exists>no</g:identifier_exists><g:product_type>' . self::x(trim(($cats[$p->category_id ?? 0] ?? '') . ' > ' . ($p->sub ?? ''), ' >')) . '</g:product_type></item>' . "\n";
      }
      return $o . '</channel></rss>';
    });
    return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=600']);
  }
}
