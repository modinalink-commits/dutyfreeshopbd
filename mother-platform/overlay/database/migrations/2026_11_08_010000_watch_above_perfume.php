<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): Watch (and Mobile right below it) sit directly above Perfume in the category order.
return new class extends Migration {
    public function up(): void {
        try {
            $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'name', 'slug', 'position']);
            $w = $top->firstWhere('slug', 'watch'); $m = $top->firstWhere('slug', 'mobile');
            $p = $top->first(fn($c) => stripos((string)$c->slug, 'perfume') !== false || stripos((string)$c->name, 'perfume') !== false);
            if (!$w || !$p) return;
            $ids = $top->pluck('id')->all(); $move = array_values(array_filter([$w->id, $m->id ?? null]));
            $ids = array_values(array_diff($ids, $move)); $at = array_search($p->id, $ids, true); if ($at === false) return;
            array_splice($ids, $at, 0, $move);
            $start = (int)$top->min('position'); foreach ($ids as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $start + $i, 'updated_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'prepaid.catset'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
