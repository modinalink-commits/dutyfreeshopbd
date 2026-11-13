<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (14 Nov 2026): inside Cigarette and Vape the sub-categories lost their order. Cause: the product feed sync kept writing its own
 * UPPER-CASE spelling ("DUNHILL") over products.sub, which no longer matched the owner's positioned sub-category ("Dunhill", position 1),
 * and the Taxonomy page then auto-created a second sub-category at the end of the list.
 * Fix: for every pair of sub-categories of one main category that differ only in letter case, the OLDER one (the owner's, lower id) is kept:
 * products are moved to its spelling, child categories are moved under it, and the duplicate is removed. Positions of kept rows are not touched.
 * Removed rows are saved in category_cleanup_backup (kind sub_dup) first.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      $keep = [];
      foreach (DB::table('category_nodes')->where('level', 2)->orderBy('id')->get() as $n) {
        $k = $n->category_id.'|'.mb_strtolower(trim((string)$n->name));
        if (!isset($keep[$k])) { $keep[$k] = $n; continue; }
        $good = $keep[$k];
        DB::transaction(function () use ($n, $good) {
          DB::table('category_cleanup_backup')->insert(['kind' => 'sub_dup', 'ref_id' => $n->id, 'old_value' => json_encode(['name' => $n->name, 'category_id' => $n->category_id, 'position' => $n->position, 'kept' => $good->id], JSON_UNESCAPED_UNICODE)]);
          foreach (DB::table('category_nodes')->where('level', 3)->where('parent_id', $n->id)->get() as $c) {
            $same = DB::table('category_nodes')->where('level', 3)->where('parent_id', $good->id)->whereRaw('lower(name) = ?', [mb_strtolower((string)$c->name)])->first();
            if ($same) { DB::table('products')->where('category_id', $n->category_id)->whereRaw('lower(sub) = ?', [mb_strtolower((string)$n->name)])->where('sub_sub', $c->name)->update(['sub_sub' => $same->name]); DB::table('category_nodes')->where('id', $c->id)->delete(); }
            else DB::table('category_nodes')->where('id', $c->id)->update(['parent_id' => $good->id]);
          }
          DB::table('category_nodes')->where('id', $n->id)->delete();
        });
      }
      // every product uses the kept sub-category's exact spelling
      foreach ($keep as $good) DB::table('products')->where('category_id', $good->category_id)->whereRaw('lower(trim(sub)) = ?', [mb_strtolower(trim((string)$good->name))])->where('sub', '!=', $good->name)->update(['sub' => $good->name]);
      foreach (['store.subs', 'store.cats', 'store.catslugs', 'store.home13', 'store.sitemap15'] as $c) { try { Cache::forget($c); } catch (\Throwable $e) {} }
      try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
