<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
// Owner (13 Nov 2026): Lighter & Accessories is its own main category on the home page and menu, right after Cigarette and Vape. Display/order only here; the products are moved with the existing restructure tool (logged, 301 redirects, revertable).
return new class extends Migration {
    public function up(): void {
        try {
            $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'slug', 'position']);
            $ids = $top->pluck('id')->all(); $me = $top->firstWhere('slug', 'lighter-accessories'); $cg = $top->firstWhere('slug', 'cigarette-vape');
            if ($me && $cg) {
                DB::table('categories')->where('id', $me->id)->update(['active' => true, 'updated_at' => now()]);
                $ids = array_values(array_diff($ids, [$me->id])); $at = array_search($cg->id, $ids, true);
                if ($at !== false) { array_splice($ids, $at + 1, 0, [$me->id]); $start = (int)$top->min('position'); foreach ($ids as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $start + $i, 'updated_at' => now()]); }
            }
            foreach (['store.cats', 'store.catslugs', 'store.subs', 'store.home11', 'store.home12', 'store.home13', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
            try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
