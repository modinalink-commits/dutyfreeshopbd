<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (10 Nov 2026): the products left in "Lifestyle Essentials" move to the category that really owns them.
 * Skin / face / deodorant / soap items -> Skin & Hair Care (sub category with the same name as their child, else Skin Care);
 * bleach -> Daily Needs > Clothes Washing Essentials; ear pick set -> Daily Needs > Daily. Anything not matched stays.
 * Old product links redirect (301). Old category / sub / child kept in category_cleanup_backup (kind product_l); down() restores.
 * The category itself is removed by the empty-category cleanup only when it has no products left.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      $find = fn($n) => DB::table('categories')->whereNull('parent_id')->whereRaw('lower(name) = ?', [mb_strtolower($n)])->first();
      $src = $find('Lifestyle Essentials'); $shc = $find('Skin & Hair Care'); $dn = $find('Daily Needs');
      if (!$src || !$shc || !$dn) return;
      $subs = fn($m) => DB::table('category_nodes')->where('level', 2)->where('category_id', $m->id)->pluck('name')->mapWithKeys(fn($x) => [mb_strtolower($x) => $x])->all();
      $shcSubs = $subs($shc); $dnSubs = $subs($dn);
      $old = []; $new = [];
      foreach (DB::table('products')->where('category_id', $src->id)->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) {
        $nm = mb_strtolower((string)$p->name); $ss = mb_strtolower(trim((string)$p->sub_sub)); $sb = mb_strtolower(trim((string)$p->sub));
        $to = null; $sub = null;
        if ($ss !== '' && isset($shcSubs[$ss])) { $to = $shc; $sub = $shcSubs[$ss]; }
        elseif (preg_match('/deodorant|antiperspirant|roll-?on/i', $nm) && isset($shcSubs['deodorant'])) { $to = $shc; $sub = $shcSubs['deodorant']; }
        elseif ($sb === 'skin care' && isset($shcSubs['skin care'])) { $to = $shc; $sub = $shcSubs['skin care']; }
        elseif (preg_match('/bleach/i', $nm) && isset($dnSubs['clothes washing essentials'])) { $to = $dn; $sub = $dnSubs['clothes washing essentials']; }
        elseif (preg_match('/ear pick/i', $nm) && isset($dnSubs['daily'])) { $to = $dn; $sub = $dnSubs['daily']; }
        if (!$to) continue;
        $old[$p->id] = \App\Http\Controllers\StoreController::url($p);
        DB::table('category_cleanup_backup')->insert(['kind' => 'product_l', 'ref_id' => $p->id, 'old_value' => json_encode([$p->category_id, $p->sub, $p->sub_sub], JSON_UNESCAPED_UNICODE)]);
        DB::table('products')->where('id', $p->id)->update(['category_id' => $to->id, 'sub' => $sub, 'sub_sub' => null, 'path_slug' => null, 'updated_at' => now()]);
        $new[$p->id] = $to->id;
      }
      if ($new) {
        \App\Support\Slugs::assignAll();
        if (Schema::hasTable('seo_redirects')) foreach (DB::table('products')->whereIn('id', array_keys($new))->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) { $u = \App\Http\Controllers\StoreController::url($p); if (($old[$p->id] ?? '') !== '' && $old[$p->id] !== $u) \App\Support\SeoMgr::addRedirect($old[$p->id], $u, 301, 'cleanup'); }
        if (Schema::hasColumn('order_items', 'cat_id')) foreach ($new as $pid => $cid) DB::table('order_items')->where('product_id', $pid)->update(['cat_id' => $cid]);
      }
    } catch (\Throwable $e) { report($e); }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home4', 'store.home5', 'store.home6', 'store.home11', 'store.home12', 'store.home13', 'store.sitemap5', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    if (!Schema::hasTable('category_cleanup_backup')) return;
    foreach (DB::table('category_cleanup_backup')->where('kind', 'product_l')->get() as $b) { $o = json_decode($b->old_value, true); if (is_array($o)) DB::table('products')->where('id', $b->ref_id)->update(['category_id' => $o[0], 'sub' => $o[1], 'sub_sub' => $o[2], 'path_slug' => null]); }
    try { \App\Support\Slugs::assignAll(); } catch (\Throwable $e) {}
  }
};
