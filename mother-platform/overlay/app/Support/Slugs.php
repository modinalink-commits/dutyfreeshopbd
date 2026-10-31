<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Readable, lowercase, hierarchy URL slugs: /main-category/secondary-category/product-name */
class Slugs {
  public static function slug(?string $s, int $max = 80): string {
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$s)), '-');
    $s = trim(substr($s, 0, $max), '-');
    return $s;
  }
  public static function subSlug(?string $sub): string { $s = self::slug($sub, 60); return $s !== '' ? $s : 'other'; }
  /** SQL that yields the same slug as subSlug() for the products.sub column. */
  public static function subSql(): string { return "coalesce(nullif(trim(both '-' from regexp_replace(lower(coalesce(sub,'')), '[^a-z0-9]+', '-', 'g')), ''), 'other')"; }
  private static function key($cat, $sub, $slug): string { return ($cat ?? 0).'|'.self::subSlug($sub).'|'.$slug; }
  /** Give every product without a URL slug a unique one inside its category + secondary category. */
  public static function assignAll(): int {
    $used = [];
    foreach (DB::table('products')->whereNotNull('path_slug')->select('category_id','sub','path_slug')->get() as $r) $used[self::key($r->category_id, $r->sub, $r->path_slug)] = 1;
    $n = 0;
    foreach (DB::table('products')->whereNull('path_slug')->orderBy('id')->select('id','name','category_id','sub')->get() as $p) {
      $b = self::slug($p->name); if ($b === '') $b = 'product'; $s = $b; $i = 2;
      while (isset($used[self::key($p->category_id, $p->sub, $s)])) $s = $b.'-'.$i++;
      $used[self::key($p->category_id, $p->sub, $s)] = 1;
      DB::table('products')->where('id', $p->id)->update(['path_slug'=>$s]); $n++;
    }
    return $n;
  }
}
