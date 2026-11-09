<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (10 Nov 2026): the stray "Skin Care" sub-category inside Daily Needs goes away.
 * Its (few) products move to Skin & Hair Care (same-named sub if it exists there), old values kept in category_cleanup_backup (kind product_d),
 * the empty sub node is kept in empty_category_backup. Old links redirect (301). down() restores both.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      if (!Schema::hasTable('empty_category_backup')) DB::unprepared("create table empty_category_backup (tbl varchar(20), row_json text, created_at timestamp default now())");
      $find = fn($n) => DB::table('categories')->whereNull('parent_id')->whereRaw('lower(name) = ?', [mb_strtolower($n)])->first();
      $dn = $find('Daily Needs'); $sh = $find('Skin & Hair Care'); if (!$dn || !$sh) return;
      $node = DB::table('category_nodes')->where('level', 2)->where('category_id', $dn->id)->whereRaw('lower(name) = ?', ['skin care'])->first(); if (!$node) return;
      $ps = DB::table('products')->where('category_id', $dn->id)->whereRaw('lower(sub) = ?', ['skin care'])->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']);
      if ($ps->count() > 5) return; // safety: expected 1
      $tsub = DB::table('category_nodes')->where('level', 2)->where('category_id', $sh->id)->whereRaw('lower(name) = ?', ['skin care'])->value('name');
      $moved = [];
      foreach ($ps as $p) {
        DB::table('category_cleanup_backup')->insert(['kind' => 'product_d', 'ref_id' => $p->id, 'old_value' => json_encode([$p->category_id, $p->sub, $p->sub_sub], JSON_UNESCAPED_UNICODE)]);
        DB::table('products')->where('id', $p->id)->update(['category_id' => $sh->id, 'sub' => $tsub, 'sub_sub' => null, 'path_slug' => null, 'updated_at' => now()]);
        $moved[$p->id] = $sh->id;
      }
      if ($moved) {
        \App\Support\Slugs::assignAll();
        if (Schema::hasColumn('order_items', 'cat_id')) foreach ($moved as $pid => $cid) DB::table('order_items')->where('product_id', $pid)->update(['cat_id' => $cid]);
      }
      if (!DB::table('products')->where('category_id', $dn->id)->whereRaw('lower(sub) = ?', ['skin care'])->exists()) {
        DB::transaction(function () use ($node) {
          foreach (DB::table('category_nodes')->where('parent_id', $node->id)->get() as $c) { DB::table('empty_category_backup')->insert(['tbl' => 'category_nodes', 'row_json' => json_encode($c, JSON_UNESCAPED_UNICODE)]); DB::table('category_nodes')->where('id', $c->id)->delete(); }
          DB::table('empty_category_backup')->insert(['tbl' => 'category_nodes', 'row_json' => json_encode($node, JSON_UNESCAPED_UNICODE)]); DB::table('category_nodes')->where('id', $node->id)->delete();
        });
      }
    } catch (\Throwable $e) { report($e); }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home4', 'store.home5', 'store.home6', 'store.home11', 'store.home12', 'store.home13', 'store.sitemap5', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    if (Schema::hasTable('empty_category_backup')) foreach (DB::table('empty_category_backup')->orderByDesc('created_at')->get() as $b) { $row = (array)json_decode($b->row_json, true); if ($row && ($row['name'] ?? '') !== '' && $b->tbl === 'category_nodes' && !DB::table('category_nodes')->where('id', $row['id'])->exists() && strtolower($row['name']) === 'skin care') DB::table('category_nodes')->insert($row); }
    try { \App\Support\Slugs::assignAll(); } catch (\Throwable $e) {}
  }
};
