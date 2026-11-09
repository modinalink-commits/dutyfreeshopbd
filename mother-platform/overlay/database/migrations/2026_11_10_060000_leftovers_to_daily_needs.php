<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (10 Nov 2026): whatever is still inside "Lifestyle Essentials" and "Exclusive Deals" goes to Daily Needs > Daily.
 * Then those two categories (now without products) are removed completely, with their empty sub / child nodes.
 * Old links redirect (301). Old category / sub / child kept in category_cleanup_backup (kind product_d); removed category rows kept in empty_category_backup. down() restores both.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      if (!Schema::hasTable('empty_category_backup')) DB::unprepared("create table empty_category_backup (tbl varchar(20), row_json text, created_at timestamp default now())");
      $find = fn($n) => DB::table('categories')->whereNull('parent_id')->whereRaw('lower(name) = ?', [mb_strtolower($n)])->first();
      $dn = $find('Daily Needs'); if (!$dn) return;
      $daily = DB::table('category_nodes')->where('level', 2)->where('category_id', $dn->id)->whereRaw('lower(name) = ?', ['daily'])->value('name'); if (!$daily) return;
      $srcs = array_values(array_filter([$find('Lifestyle Essentials'), $find('Exclusive Deals')]));
      if (!$srcs) return;
      $ids = array_map(fn($m) => $m->id, $srcs); $old = []; $moved = [];
      foreach (DB::table('products')->whereIn('category_id', $ids)->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) {
        $old[$p->id] = \App\Http\Controllers\StoreController::url($p);
        DB::table('category_cleanup_backup')->insert(['kind' => 'product_d', 'ref_id' => $p->id, 'old_value' => json_encode([$p->category_id, $p->sub, $p->sub_sub], JSON_UNESCAPED_UNICODE)]);
        DB::table('products')->where('id', $p->id)->update(['category_id' => $dn->id, 'sub' => $daily, 'sub_sub' => null, 'path_slug' => null, 'updated_at' => now()]);
        $moved[$p->id] = $dn->id;
      }
      if ($moved) {
        \App\Support\Slugs::assignAll();
        if (Schema::hasTable('seo_redirects')) foreach (DB::table('products')->whereIn('id', array_keys($moved))->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) { $u = \App\Http\Controllers\StoreController::url($p); if (($old[$p->id] ?? '') !== '' && $old[$p->id] !== $u) \App\Support\SeoMgr::addRedirect($old[$p->id], $u, 301, 'cleanup'); }
        if (Schema::hasColumn('order_items', 'cat_id')) foreach ($moved as $pid => $cid) DB::table('order_items')->where('product_id', $pid)->update(['cat_id' => $cid]);
      }
      foreach ($srcs as $m) { // remove the now product-less categories
        if (DB::table('products')->where('category_id', $m->id)->exists()) continue;
        DB::transaction(function () use ($m) {
          foreach (DB::table('category_nodes')->where('category_id', $m->id)->orderByDesc('level')->get() as $n) { DB::table('empty_category_backup')->insert(['tbl' => 'category_nodes', 'row_json' => json_encode($n, JSON_UNESCAPED_UNICODE)]); DB::table('category_nodes')->where('id', $n->id)->delete(); }
          DB::table('empty_category_backup')->insert(['tbl' => 'categories', 'row_json' => json_encode($m, JSON_UNESCAPED_UNICODE)]); DB::table('categories')->where('id', $m->id)->delete();
        });
      }
    } catch (\Throwable $e) { report($e); }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home4', 'store.home5', 'store.home6', 'store.home11', 'store.home12', 'store.home13', 'store.sitemap5', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    if (Schema::hasTable('empty_category_backup')) foreach (DB::table('empty_category_backup')->orderByRaw("case tbl when 'categories' then 0 else 1 end")->get() as $b) { $row = (array)json_decode($b->row_json, true); if ($row && !DB::table($b->tbl)->where('id', $row['id'] ?? 0)->exists()) DB::table($b->tbl)->insert($row); }
    if (Schema::hasTable('category_cleanup_backup')) foreach (DB::table('category_cleanup_backup')->where('kind', 'product_d')->get() as $b) { $o = json_decode($b->old_value, true); if (is_array($o)) DB::table('products')->where('id', $b->ref_id)->update(['category_id' => $o[0], 'sub' => $o[1], 'sub_sub' => $o[2], 'path_slug' => null]); }
    try { \App\Support\Slugs::assignAll(); } catch (\Throwable $e) {}
  }
};
