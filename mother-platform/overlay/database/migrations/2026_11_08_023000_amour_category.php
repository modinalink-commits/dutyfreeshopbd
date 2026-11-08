<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (8 Nov 2026): new category right after Cigarette, source amour.com.bd at source price +10%; every photo AI-cleaned; prepaid only, no discount, no bundle (same rules as Watch/Sunglass/Gadget).
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('categories', 'prepaid_only')) return;
            DB::table('settings')->updateOrInsert(['key' => 'amour_markup_pct'], ['value' => '10', 'updated_at' => now(), 'created_at' => now()]);
            if (!DB::table('categories')->where('slug', 'lighter-accessories')->exists()) {
                $row = ['name' => 'Lighter & Accessories', 'slug' => 'lighter-accessories', 'position' => 9999, 'active' => true, 'prepaid_only' => true, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('categories', 'inventory_mode')) $row['inventory_mode'] = 'SOURCING';
                DB::table('categories')->insert($row);
            } else DB::table('categories')->where('slug', 'lighter-accessories')->update(['prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'slug', 'position']);
            $ids = $top->pluck('id')->all(); $me = $top->firstWhere('slug', 'lighter-accessories'); $cg = $top->firstWhere('slug', 'cigarette-vape');
            if ($me && $cg) {
                $ids = array_values(array_diff($ids, [$me->id])); $at = array_search($cg->id, $ids, true);
                if ($at !== false) { array_splice($ids, $at + 1, 0, [$me->id]); $start = (int)$top->min('position'); foreach ($ids as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $start + $i, 'updated_at' => now()]); }
            }
            if (!DB::table('stores')->whereIn('domain', ['amour.com.bd', 'www.amour.com.bd'])->exists()) DB::table('stores')->insert(['name' => 'Amour.com.bd', 'domain' => 'amour.com.bd', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => 'amour:1', 'updated_at' => now(), 'created_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'store.home12', 'store.home13', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
