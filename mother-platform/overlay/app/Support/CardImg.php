<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Second (back/side) image per product for the product-card hover swap. One cached query for the whole catalog. */
class CardImg {
  private static $map = null;
  public static function second($id) {
    if (self::$map === null) {
      try {
        self::$map = Cache::remember('card_second_imgs', 600, function () {
          $rows = DB::select("select distinct on (i.product_id) i.product_id, i.path from product_images i join products p on p.id = i.product_id where i.path is not null and i.path <> '' and i.path <> coalesce(p.image, '') order by i.product_id, i.position, i.id");
          $m = []; foreach ($rows as $r) $m[$r->product_id] = $r->path; return $m;
        });
      } catch (\Throwable $e) { self::$map = []; }
    }
    return self::$map[$id] ?? null;
  }
}
