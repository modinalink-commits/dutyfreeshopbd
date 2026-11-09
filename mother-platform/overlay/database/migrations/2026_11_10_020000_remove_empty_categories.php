<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (10 Nov 2026): categories with no products are removed completely.
 * Empty child -> empty sub (no products, no non-empty child) -> empty main (no products, no non-empty sub).
 * Nothing with a product is touched. Every deleted row is saved as JSON in empty_category_backup (down() restores it).
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('empty_category_backup')) DB::unprepared("create table empty_category_backup (tbl varchar(20), row_json text, created_at timestamp default now())");
      DB::transaction(function () {
        $save = function (string $tbl, $row) { DB::table('empty_category_backup')->insert(['tbl' => $tbl, 'row_json' => json_encode($row, JSON_UNESCAPED_UNICODE)]); };
        $mc = []; $sc = []; $tc = [];
        foreach (DB::table('products')->select('category_id', 'sub', 'sub_sub', DB::raw('count(*) n'))->groupBy('category_id', 'sub', 'sub_sub')->get() as $x) {
          $mc[$x->category_id] = ($mc[$x->category_id] ?? 0) + $x->n;
          if ($x->sub) $sc[$x->category_id . '|' . $x->sub] = ($sc[$x->category_id . '|' . $x->sub] ?? 0) + $x->n;
          if ($x->sub && $x->sub_sub) $tc[$x->category_id . '|' . $x->sub . '|' . $x->sub_sub] = ($tc[$x->category_id . '|' . $x->sub . '|' . $x->sub_sub] ?? 0) + $x->n;
        }
        $nodes = DB::table('category_nodes')->get();
        // children (level 3) with no products
        foreach ($nodes->where('level', 3) as $c) {
          $s = $nodes->firstWhere('id', $c->parent_id); if (!$s) { $save('category_nodes', $c); DB::table('category_nodes')->where('id', $c->id)->delete(); continue; }
          if (($tc[$s->category_id . '|' . $s->name . '|' . $c->name] ?? 0) === 0) { $save('category_nodes', $c); DB::table('category_nodes')->where('id', $c->id)->delete(); }
        }
        // subs (level 2) with no products and no remaining child
        foreach ($nodes->where('level', 2) as $s) {
          if (($sc[$s->category_id . '|' . $s->name] ?? 0) > 0) continue;
          if (DB::table('category_nodes')->where('parent_id', $s->id)->exists()) continue;
          $save('category_nodes', $s); DB::table('category_nodes')->where('id', $s->id)->delete();
        }
        // main categories with no products and no remaining sub
        foreach (DB::table('categories')->whereNull('parent_id')->get() as $m) {
          if (($mc[$m->id] ?? 0) > 0) continue;
          if (DB::table('category_nodes')->where('category_id', $m->id)->exists()) continue;
          $save('categories', $m); DB::table('categories')->where('id', $m->id)->delete();
        }
      });
    } catch (\Throwable $e) { report($e); }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home4', 'store.home5', 'store.home6', 'store.home11', 'store.home12', 'store.home13', 'store.sitemap5', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    if (!Schema::hasTable('empty_category_backup')) return;
    foreach (DB::table('empty_category_backup')->orderBy('tbl')->get() as $b) {
      $row = (array)json_decode($b->row_json, true);
      if (!DB::table($b->tbl)->where('id', $row['id'] ?? 0)->exists()) DB::table($b->tbl)->insert($row);
    }
    Schema::dropIfExists('empty_category_backup');
  }
};
