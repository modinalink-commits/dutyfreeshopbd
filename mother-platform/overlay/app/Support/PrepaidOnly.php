<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner rule (8 Nov 2026): products of a category marked prepaid_only (Watch) cannot be ordered Cash on Delivery; the customer must pay in full first. */
class PrepaidOnly {
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasColumn('categories', 'prepaid_only'); } catch (\Throwable $e) { $r = false; } } return $r; }
  /** True when any of these product ids belongs to a prepaid-only category (or its sub category). */
  public static function any(array $productIds): bool {
    if (!self::ready()) return false; $ids = array_values(array_unique(array_filter(array_map('intval', $productIds)))); if (!$ids) return false;
    try {
      $cats = DB::table('products')->whereIn('id', $ids)->pluck('category_id')->filter()->unique()->all(); if (!$cats) return false;
      $par = DB::table('categories')->whereIn('id', $cats)->pluck('parent_id', 'id')->all();
      $all = array_values(array_unique(array_merge($cats, array_filter(array_map('intval', $par)))));
      return DB::table('categories')->whereIn('id', $all)->where('prepaid_only', true)->exists();
    } catch (\Throwable $e) { return false; }
  }
}
