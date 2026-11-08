<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (8 Nov 2026): Skin Care and Hair Care become ONE category "Skin & Hair Care" (URL /skin-care stays). All hair products and sub categories move into it; old /hair-care links redirect (301).
return new class extends Migration {
    public function up(): void {
        try {
            $skin = DB::table('categories')->where('slug', 'skin-care')->first(); $hair = DB::table('categories')->where('slug', 'hair-care')->first();
            if (!$skin || !$hair) return;
            // 1. old URLs of every hair product / sub category (for the redirects)
            $old = []; $prods = DB::table('products')->where('category_id', $hair->id)->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']);
            foreach ($prods as $p) $old[$p->id] = \App\Http\Controllers\StoreController::url($p);
            $oldSubs = DB::table('category_nodes')->where('category_id', $hair->id)->where('level', 2)->get(['id', 'name', 'slug']);
            // 2. sub category nodes: move (merge by name when the same name already exists in Skin Care)
            $have = DB::table('category_nodes')->where('category_id', $skin->id)->where('level', 2)->pluck('id', DB::raw('lower(name)'))->all();
            $pos = (int)DB::table('category_nodes')->where('category_id', $skin->id)->where('level', 2)->max('position');
            foreach (DB::table('category_nodes')->where('category_id', $hair->id)->where('level', 2)->orderBy('position')->get() as $n) {
                $k = mb_strtolower($n->name);
                if (isset($have[$k])) { DB::table('category_nodes')->where('parent_id', $n->id)->update(['parent_id' => $have[$k], 'category_id' => $skin->id]); DB::table('category_nodes')->where('id', $n->id)->delete(); }
                else { DB::table('category_nodes')->where('id', $n->id)->update(['category_id' => $skin->id, 'position' => ++$pos]); DB::table('category_nodes')->where('parent_id', $n->id)->update(['category_id' => $skin->id]); }
            }
            // 3. products
            DB::table('products')->where('category_id', $hair->id)->update(['category_id' => $skin->id, 'path_slug' => null, 'updated_at' => now()]);
            \App\Support\Slugs::assignAll();
            foreach (['categories'] as $t) { try { DB::table($t)->where('parent_id', $hair->id)->update(['parent_id' => $skin->id]); } catch (\Throwable $e) {} }
            // 4. the merged category
            DB::table('categories')->where('id', $skin->id)->update(['name' => 'Skin & Hair Care', 'active' => true, 'updated_at' => now()]);
            // 5. redirects: /hair-care, its sub categories and every product link
            if (Schema::hasTable('seo_redirects')) {
                \App\Support\SeoMgr::addRedirect('/hair-care', '/skin-care', 301, 'merge');
                foreach ($oldSubs as $s) \App\Support\SeoMgr::addRedirect('/hair-care/' . \App\Support\Slugs::subSlug($s->name), '/skin-care/' . \App\Support\Slugs::subSlug($s->name), 301, 'merge');
                foreach (DB::table('products')->whereIn('id', array_keys($old))->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) { Cache::forget('v' . (Cache::get('store.ver') ?: 0) . '.store.catslugs'); $new = \App\Http\Controllers\StoreController::url($p); if (($old[$p->id] ?? '') !== '' && $old[$p->id] !== $new) \App\Support\SeoMgr::addRedirect($old[$p->id], $new, 301, 'merge'); }
            }
            // 6. remove the empty Hair Care category and close the gap in the menu order
            $left = DB::table('products')->where('category_id', $hair->id)->count() + DB::table('category_nodes')->where('category_id', $hair->id)->count();
            if ($left === 0) { DB::table('categories')->where('id', $hair->id)->delete(); } else DB::table('categories')->where('id', $hair->id)->update(['active' => false, 'updated_at' => now()]);
            foreach (DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->pluck('id') as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $i + 1]);
            try { \App\Support\SeoAutoGenerator::onSave('main', (int)$skin->id); } catch (\Throwable $e) { report($e); }
            try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
            foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home11', 'store.home12', 'store.home13', 'store.sitemap5', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
