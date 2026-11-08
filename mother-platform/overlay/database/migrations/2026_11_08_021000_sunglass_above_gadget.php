<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): order is Watch, Sunglass, Gadget & Electronics (then Perfume).
return new class extends Migration {
    public function up(): void {
        try {
            $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'slug', 'position']);
            $ids = $top->pluck('id')->all(); $w = $top->firstWhere('slug', 'watch'); $s = $top->firstWhere('slug', 'sunglass'); $g = $top->firstWhere('slug', 'gadget-electronics');
            if (!$w || !$s || !$g) return;
            $ids = array_values(array_diff($ids, [$s->id, $g->id])); $at = array_search($w->id, $ids, true); if ($at === false) return;
            array_splice($ids, $at + 1, 0, [$s->id, $g->id]);
            $start = (int)$top->min('position'); foreach ($ids as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $start + $i, 'updated_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'store.home12', 'store.home13', 'stock.modes', 'stock.parents', 'prepaid.catset'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
