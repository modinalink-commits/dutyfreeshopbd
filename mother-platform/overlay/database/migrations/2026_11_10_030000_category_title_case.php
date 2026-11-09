<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (10 Nov 2026): every main / sub / child category name gets the same style as the good main names
 * ("Cigarette and Vape", "Skin & Hair Care", "Daily Needs"): each word capitalised, small joining words
 * (and, of, for, the, in, to, a, de) stay lower case. Only names that have a lower-case word are changed;
 * names already in that style (brands, acronyms like VOLTBAR) are untouched. Slugs unchanged. Old names are kept in
 * category_cleanup_backup (kind main_t / sub_t / child_t) and down() restores them.
 */
return new class extends Migration {
  private const SMALL = ['and', 'of', 'for', 'the', 'in', 'to', 'a', 'de', 'or', 'with'];
  private static function title(string $n): string {
    $parts = preg_split('/(\s+)/u', trim($n), -1, PREG_SPLIT_DELIM_CAPTURE); $first = true; $changed = false;
    foreach ($parts as $i => $w) {
      if ($w === '' || preg_match('/^\s+$/u', $w)) continue;
      if (!preg_match('/[A-Za-z]/', $w)) { $first = false; continue; }
      $lw = mb_strtolower($w);
      if (!$first && in_array($lw, self::SMALL, true)) { $first = false; continue; }
      if (mb_substr($w, 0, 1) !== mb_strtoupper(mb_substr($w, 0, 1))) { $parts[$i] = mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1); $changed = true; }
      $first = false;
    }
    return $changed ? implode('', $parts) : $n;
  }
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      foreach (DB::table('categories')->whereNull('parent_id')->get(['id', 'name']) as $m) {
        $nn = self::title((string)$m->name); if ($nn === $m->name) continue;
        if (DB::table('categories')->whereNull('parent_id')->where('id', '!=', $m->id)->whereRaw('lower(name) = ?', [mb_strtolower($nn)])->exists()) continue;
        DB::table('category_cleanup_backup')->insert(['kind' => 'main_t', 'ref_id' => $m->id, 'old_value' => $m->name]);
        DB::table('categories')->where('id', $m->id)->update(['name' => $nn, 'updated_at' => now()]);
      }
      foreach (DB::table('category_nodes')->where('level', 2)->get() as $s) {
        $nn = self::title((string)$s->name); if ($nn === $s->name) continue;
        if (DB::table('category_nodes')->where('category_id', $s->category_id)->where('level', 2)->where('id', '!=', $s->id)->whereRaw('lower(name) = ?', [mb_strtolower($nn)])->exists()) continue;
        DB::table('category_cleanup_backup')->insert(['kind' => 'sub_t', 'ref_id' => $s->id, 'old_value' => $s->name]);
        DB::table('category_nodes')->where('id', $s->id)->update(['name' => $nn, 'updated_at' => now()]);
        DB::table('products')->where('category_id', $s->category_id)->where('sub', $s->name)->update(['sub' => $nn]);
        if (Schema::hasColumn('order_items', 'cat_sub')) DB::table('order_items')->where('cat_id', $s->category_id)->where('cat_sub', $s->name)->update(['cat_sub' => $nn]);
      }
      foreach (DB::table('category_nodes')->where('level', 3)->get() as $c) {
        $nn = self::title((string)$c->name); if ($nn === $c->name) continue;
        $ps = DB::table('category_nodes')->where('id', $c->parent_id)->first(); if (!$ps) continue;
        if (DB::table('category_nodes')->where('parent_id', $c->parent_id)->where('level', 3)->where('id', '!=', $c->id)->whereRaw('lower(name) = ?', [mb_strtolower($nn)])->exists()) continue;
        DB::table('category_cleanup_backup')->insert(['kind' => 'child_t', 'ref_id' => $c->id, 'old_value' => $c->name]);
        DB::table('category_nodes')->where('id', $c->id)->update(['name' => $nn, 'updated_at' => now()]);
        DB::table('products')->where('category_id', $ps->category_id)->where('sub', $ps->name)->where('sub_sub', $c->name)->update(['sub_sub' => $nn]);
        if (Schema::hasColumn('order_items', 'cat_sub_sub')) DB::table('order_items')->where('cat_id', $ps->category_id)->where('cat_sub_sub', $c->name)->update(['cat_sub_sub' => $nn]);
      }
    } catch (\Throwable $e) { report($e); }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home4', 'store.home5', 'store.home6', 'store.home11', 'store.home12', 'store.home13', 'store.sitemap5', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    if (!Schema::hasTable('category_cleanup_backup')) return;
    foreach (DB::table('category_cleanup_backup')->where('kind', 'main_t')->get() as $b) DB::table('categories')->where('id', $b->ref_id)->update(['name' => $b->old_value]);
    foreach (DB::table('category_cleanup_backup')->whereIn('kind', ['sub_t', 'child_t'])->get() as $b) DB::table('category_nodes')->where('id', $b->ref_id)->update(['name' => $b->old_value]);
  }
};
