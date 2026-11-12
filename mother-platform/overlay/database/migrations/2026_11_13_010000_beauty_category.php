<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (13 Nov 2026): new main category "Beauty" right below Skin & Hair Care, filled with every in-stock product of partner shop sundora.com.bd. Sale price = source +15%, crossed-out regular price = source +25% (source_regular_pct). Same affiliate rules as Watch/Gadget/Sunglass/Lighter (prepaid only, no discount, no bundle).
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('categories', 'prepaid_only')) return;
            DB::table('settings')->updateOrInsert(['key' => 'beauty_markup_pct'], ['value' => '15', 'updated_at' => now(), 'created_at' => now()]);
            if (!DB::table('categories')->where('slug', 'beauty')->exists()) {
                $row = ['name' => 'Beauty', 'slug' => 'beauty', 'position' => 9999, 'active' => true, 'prepaid_only' => true, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('categories', 'inventory_mode')) $row['inventory_mode'] = 'SOURCING';
                DB::table('categories')->insert($row);
            } else DB::table('categories')->where('slug', 'beauty')->update(['prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'slug', 'position']);
            $ids = $top->pluck('id')->all(); $me = $top->firstWhere('slug', 'beauty'); $sk = $top->firstWhere('slug', 'skin-care');
            if ($me && $sk) {
                $ids = array_values(array_diff($ids, [$me->id])); $at = array_search($sk->id, $ids, true);
                if ($at !== false) { array_splice($ids, $at + 1, 0, [$me->id]); $start = (int)$top->min('position'); foreach ($ids as $i => $id) DB::table('categories')->where('id', $id)->update(['position' => $start + $i, 'updated_at' => now()]); }
            }
            if (!DB::table('stores')->whereIn('domain', ['sundora.com.bd', 'www.sundora.com.bd'])->exists()) DB::table('stores')->insert(['name' => 'Sundora.com.bd', 'domain' => 'sundora.com.bd', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => 'beauty:1', 'updated_at' => now(), 'created_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'store.home12', 'store.home13', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
