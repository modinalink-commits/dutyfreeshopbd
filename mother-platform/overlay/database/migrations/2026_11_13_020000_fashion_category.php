<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (13 Nov 2026): new main category "Fashion", placed after Gadget & Electronics and before Perfume (menu + home serial). Same affiliate rules as the other source categories: prepaid only, no discount, no bundle, sale price source +15%, regular +25%. The source shop is set later (setting fashion_source_domain).
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('categories', 'prepaid_only')) return;
            DB::table('settings')->updateOrInsert(['key' => 'fashion_markup_pct'], ['value' => '15', 'updated_at' => now(), 'created_at' => now()]);
            if (!DB::table('categories')->where('slug', 'fashion')->exists()) {
                $row = ['name' => 'Fashion', 'slug' => 'fashion', 'position' => 9999, 'active' => true, 'prepaid_only' => true, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('categories', 'inventory_mode')) $row['inventory_mode'] = 'SOURCING';
                DB::table('categories')->insert($row);
            } else DB::table('categories')->where('slug', 'fashion')->update(['prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'slug', 'position']);
            $ids = $top->pluck('id')->all(); $me = $top->firstWhere('slug', 'fashion'); $g = $top->firstWhere('slug', 'gadget-electronics');
            if ($me && $g) {
                $ids = array_values(array_diff($ids, [$me->id])); $at = array_search($g->id, $ids, true);
                if ($at !== false) { array_splice($ids, $at + 1, 0, [$me->id]); $start = (int)$top->min('position'); foreach ($ids as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $start + $i, 'updated_at' => now()]); }
            }
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'store.home12', 'store.home13', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
