<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (8 Nov 2026): "Mobile" category directly below Watch, same rules as Watch: prepaid only (no COD), no discounts, source shop products at source price +5%.
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('categories', 'prepaid_only')) return;
            DB::table('settings')->updateOrInsert(['key' => 'mobile_markup_pct'], ['value' => '5', 'updated_at' => now(), 'created_at' => now()]);
            $m = DB::table('categories')->where('slug', 'mobile')->first();
            if (!$m) {
                $w = DB::table('categories')->where('slug', 'watch')->first(); $pos = $w ? (int)$w->position + 1 : (int)DB::table('categories')->max('position') + 1;
                if ($w) DB::table('categories')->where('position', '>=', $pos)->whereNotIn('slug', ['watch', 'cigarette-vape'])->increment('position');
                $row = ['name' => 'Mobile', 'slug' => 'mobile', 'position' => $pos, 'active' => true, 'prepaid_only' => true, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('categories', 'inventory_mode')) $row['inventory_mode'] = 'SOURCING';
                DB::table('categories')->insert($row);
            } else DB::table('categories')->where('id', $m->id)->update(['prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'prepaid.catset', 'stock.modes', 'stock.parents'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
