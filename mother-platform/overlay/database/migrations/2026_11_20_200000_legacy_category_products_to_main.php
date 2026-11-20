<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner 20 Nov 2026: products still sitting in an old merged category (Hair Care, Sunglass, Skin & Hair Care, Personal Wellness) move to their main category with the same rule the importers use (CatRemap).
 *  The old place of every moved product is kept in settings (legacy_cat_move_20261120) so down() can put it back. */
return new class extends Migration {
  const KEY = 'legacy_cat_move_20261120';
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasTable('categories') || !class_exists(\App\Support\CatRemap::class)) return;
    $old = []; $mains = [];
    foreach (\App\Support\CatRemap::LEGACY as $slug => $to) {
      $cat = DB::table('categories')->whereNull('parent_id')->where('slug', $slug)->first(); if (!$cat) continue;
      foreach (DB::table('products')->where('category_id', $cat->id)->get(['id', 'name', 'sub', 'sub_sub', 'path_slug', 'slug']) as $p) {
        [$ms, $sub] = \App\Support\CatRemap::map($slug, (string)$p->sub, (string)$p->name);
        if (!array_key_exists($ms, $mains)) $mains[$ms] = DB::table('categories')->whereNull('parent_id')->where('slug', $ms)->first();
        $main = $mains[$ms]; if (!$main || (int)$main->id === (int)$cat->id) continue;
        $ps = (string)($p->path_slug ?: $p->slug);
        if ($ps !== '' && DB::table('products')->where('category_id', $main->id)->where('path_slug', $ps)->where('id', '!=', $p->id)->exists()) $ps .= '-' . $p->id;
        $old[$p->id] = ['c' => (int)$cat->id, 's' => $p->sub, 't' => $p->sub_sub, 'p' => $p->path_slug];
        DB::table('products')->where('id', $p->id)->update(['category_id' => $main->id, 'sub' => $sub, 'sub_sub' => null, 'path_slug' => $ps !== '' ? $ps : $p->path_slug, 'updated_at' => now()]);
      }
    }
    if ($old) DB::table('settings')->updateOrInsert(['key' => self::KEY], ['value' => json_encode($old), 'updated_at' => now(), 'created_at' => now()]);
    try { foreach (['store.cats', 'store.subs', 'store.catslugs'] as $k) \Illuminate\Support\Facades\Cache::forget($k); \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
  }
  public function down(): void {
    if (!Schema::hasTable('settings')) return;
    $old = json_decode((string)DB::table('settings')->where('key', self::KEY)->value('value'), true) ?: [];
    foreach ($old as $id => $o) DB::table('products')->where('id', (int)$id)->update(['category_id' => $o['c'], 'sub' => $o['s'], 'sub_sub' => $o['t'], 'path_slug' => $o['p'], 'updated_at' => now()]);
    DB::table('settings')->where('key', self::KEY)->delete();
  }
};
