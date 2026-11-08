<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (8 Nov 2026): "Sunglass" category directly below Gadget & Electronics; source luxotix.com at source price +5%; prepaid only, no discount, no bundle.
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('categories', 'prepaid_only')) return;
            DB::table('settings')->updateOrInsert(['key' => 'sunglass_markup_pct'], ['value' => '5', 'updated_at' => now(), 'created_at' => now()]);
            if (!DB::table('categories')->where('slug', 'sunglass')->exists()) {
                $g = DB::table('categories')->where('slug', 'gadget-electronics')->first(); $pos = $g ? (int)$g->position + 1 : (int)DB::table('categories')->max('position') + 1;
                if ($g) DB::table('categories')->where('position', '>=', $pos)->increment('position');
                $row = ['name' => 'Sunglass', 'slug' => 'sunglass', 'position' => $pos, 'active' => true, 'prepaid_only' => true, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('categories', 'inventory_mode')) $row['inventory_mode'] = 'SOURCING';
                DB::table('categories')->insert($row);
            } else DB::table('categories')->where('slug', 'sunglass')->update(['prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            if (!DB::table('stores')->whereIn('domain', ['luxotix.com', 'www.luxotix.com'])->exists()) DB::table('stores')->insert(['name' => 'Luxotix.com', 'domain' => 'luxotix.com', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home13', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
