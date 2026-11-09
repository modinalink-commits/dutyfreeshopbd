<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (10 Nov 2026): (1) products in the odd main categories (ALL CAPS name / empty slug: Electronics & gadgets,
 * Exclusive deals, Lifestyle essentials) go to the real category that already owns a sub category with the same name;
 * products without a matching sub category stay where they are (listed in the audit CSV). Old links redirect (301).
 * (2) ALL CAPS category names become sentence case (slugs unchanged); product / order-line copies of the names follow.
 * Every moved product id and its old category is kept in category_cleanup_backup.
 */
return new class extends Migration {
  private static function sentence(string $n): string {
    $t = trim(preg_replace('/\s+/u', ' ', $n)); if ($t === '' || $t !== mb_strtoupper($t) || !preg_match('/[A-Za-z]{4,}/', $t)) return $n;
    $w = explode(' ', mb_strtolower($t)); foreach ($w as $i => $x) if (in_array($x, ['lm', 'xso', 'uk', 'usa', 'edt', 'edp'], true)) $w[$i] = mb_strtoupper($x);
    $o = implode(' ', $w); return mb_strtoupper(mb_substr($o, 0, 1)) . mb_substr($o, 1);
  }
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      // ---- 1. move products out of the odd main categories
      $mains = DB::table('categories')->whereNull('parent_id')->get();
      $odd = $mains->filter(fn($m) => in_array((string)$m->slug, ['', '/'], true) || (mb_strtoupper((string)$m->name) === (string)$m->name && preg_match('/[A-Za-z]{4,}/', (string)$m->name)))->pluck('id')->all();
      $good = $mains->reject(fn($m) => in_array($m->id, $odd, true))->pluck('id')->all();
      $owner = []; // lower(sub name) => main id (only when exactly one good main owns it)
      foreach (DB::table('category_nodes')->where('level', 2)->whereIn('category_id', $good)->get(['category_id', 'name']) as $n) $owner[mb_strtolower(trim($n->name))][$n->category_id] = true;
      $old = []; $move = [];
      foreach (DB::table('products')->whereIn('category_id', $odd)->whereNotNull('sub')->where('sub', '!=', '')->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) {
        $k = mb_strtolower(trim($p->sub)); if (!isset($owner[$k]) || count($owner[$k]) !== 1) continue;
        $to = (int)array_key_first($owner[$k]); $old[$p->id] = \App\Http\Controllers\StoreController::url($p); $move[$p->id] = $to;
        DB::table('category_cleanup_backup')->insert(['kind' => 'product', 'ref_id' => $p->id, 'old_value' => (string)$p->category_id]);
      }
      foreach ($move as $pid => $to) DB::table('products')->where('id', $pid)->update(['category_id' => $to, 'path_slug' => null, 'updated_at' => now()]);
      if ($move) {
        \App\Support\Slugs::assignAll();
        if (Schema::hasTable('seo_redirects')) foreach (DB::table('products')->whereIn('id', array_keys($move))->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) { $new = \App\Http\Controllers\StoreController::url($p); if (($old[$p->id] ?? '') !== '' && $old[$p->id] !== $new) \App\Support\SeoMgr::addRedirect($old[$p->id], $new, 301, 'cleanup'); }
        if (Schema::hasColumn('order_items', 'cat_id')) foreach ($move as $pid => $to) DB::table('order_items')->where('product_id', $pid)->update(['cat_id' => $to]);
      }
    } catch (\Throwable $e) { report($e); }
    try {
      // ---- 2. ALL CAPS names -> sentence case (slug stays)
      foreach (DB::table('categories')->whereNull('parent_id')->get(['id', 'name']) as $m) { $nn = self::sentence((string)$m->name); if ($nn !== $m->name) { DB::table('category_cleanup_backup')->insert(['kind' => 'main', 'ref_id' => $m->id, 'old_value' => $m->name]); DB::table('categories')->where('id', $m->id)->update(['name' => $nn, 'updated_at' => now()]); } }
      foreach (DB::table('category_nodes')->where('level', 2)->get() as $s) {
        $nn = self::sentence((string)$s->name); if ($nn === $s->name) continue;
        if (DB::table('category_nodes')->where('category_id', $s->category_id)->where('level', 2)->where('name', $nn)->exists()) continue;
        DB::table('category_cleanup_backup')->insert(['kind' => 'sub', 'ref_id' => $s->id, 'old_value' => $s->name]);
        DB::table('category_nodes')->where('id', $s->id)->update(['name' => $nn, 'updated_at' => now()]);
        DB::table('products')->where('category_id', $s->category_id)->where('sub', $s->name)->update(['sub' => $nn]);
        if (Schema::hasColumn('order_items', 'cat_sub')) DB::table('order_items')->where('cat_id', $s->category_id)->where('cat_sub', $s->name)->update(['cat_sub' => $nn]);
      }
      foreach (DB::table('category_nodes')->where('level', 3)->get() as $c) {
        $nn = self::sentence((string)$c->name); if ($nn === $c->name) continue;
        $ps = DB::table('category_nodes')->where('id', $c->parent_id)->first(); if (!$ps) continue;
        if (DB::table('category_nodes')->where('parent_id', $c->parent_id)->where('level', 3)->where('name', $nn)->exists()) continue;
        DB::table('category_cleanup_backup')->insert(['kind' => 'child', 'ref_id' => $c->id, 'old_value' => $c->name]);
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
    foreach (DB::table('category_cleanup_backup')->where('kind', 'product')->get() as $b) DB::table('products')->where('id', $b->ref_id)->update(['category_id' => (int)$b->old_value, 'path_slug' => null]);
    foreach (DB::table('category_cleanup_backup')->where('kind', 'main')->get() as $b) DB::table('categories')->where('id', $b->ref_id)->update(['name' => $b->old_value]);
    foreach (DB::table('category_cleanup_backup')->whereIn('kind', ['sub', 'child'])->get() as $b) DB::table('category_nodes')->where('id', $b->ref_id)->update(['name' => $b->old_value]);
    try { \App\Support\Slugs::assignAll(); } catch (\Throwable $e) {}
    Schema::dropIfExists('category_cleanup_backup');
  }
};
