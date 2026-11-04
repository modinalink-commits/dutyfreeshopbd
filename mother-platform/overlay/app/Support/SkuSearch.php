<?php
namespace App\Support;

/** Product-code search that ignores dashes, spaces and case: "DF-1190", "df1190", "DF 1190" and "1190" all find the same product. */
class SkuSearch {
  /** Adds an OR condition on the given sku column to a where-group: $w->orWhere(...). */
  public static function orSku($w, string $term, string $col = 'sku') {
    $n = preg_replace('/[^a-z0-9]/', '', strtolower($term));
    if (strlen($n) >= 2) $w->orWhereRaw("regexp_replace(lower(coalesce($col,'')),'[^a-z0-9]','','g') like ?", ['%'.$n.'%']);
    return $w;
  }
}
