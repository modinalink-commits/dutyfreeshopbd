<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (8 Nov 2026): new "Watch" category right after Cigarette and Vape. Products come from another company at ITS price + 15%; orders are collected manually from that company; Cash on Delivery is not allowed (full prepayment only).
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('categories', 'prepaid_only')) Schema::table('categories', fn(Blueprint $t) => $t->boolean('prepaid_only')->default(false));
            DB::table('settings')->updateOrInsert(['key' => 'watch_markup_pct'], ['value' => '15', 'updated_at' => now(), 'created_at' => now()]);
            DB::table('settings')->updateOrInsert(['key' => 'watch_min_price'], ['value' => '0', 'updated_at' => now(), 'created_at' => now()]);
            DB::table('settings')->updateOrInsert(['key' => 'watch_max_price'], ['value' => '50000', 'updated_at' => now(), 'created_at' => now()]);
            $w = DB::table('categories')->where('slug', 'watch')->first();
            if (!$w) {
                $cig = DB::table('categories')->where('slug', 'cigarette-vape')->first(); $pos = $cig ? (int)$cig->position + 1 : (int)DB::table('categories')->max('position') + 1;
                if ($cig) DB::table('categories')->where('position', '>=', $pos)->where('slug', '!=', 'cigarette-vape')->increment('position');
                $row = ['name' => 'Watch', 'slug' => 'watch', 'position' => $pos, 'active' => true, 'prepaid_only' => true, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('categories', 'inventory_mode')) $row['inventory_mode'] = 'SOURCING';
                DB::table('categories')->insert($row);
            } else DB::table('categories')->where('id', $w->id)->update(['prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
