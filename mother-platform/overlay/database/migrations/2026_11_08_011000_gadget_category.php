<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): the Mobile category becomes "Gadget & Electronics" (directly below Watch); products come from startech.com.bd at source price +5%, prepaid only, no discount.
return new class extends Migration {
    public function up(): void {
        try {
            $m = DB::table('categories')->where('slug', 'mobile')->first();
            if ($m && !DB::table('categories')->where('slug', 'gadget-electronics')->exists()) DB::table('categories')->where('id', $m->id)->update(['name' => 'Gadget & Electronics', 'slug' => 'gadget-electronics', 'prepaid_only' => true, 'active' => true, 'updated_at' => now()]);
            if (!DB::table('stores')->whereIn('domain', ['startech.com.bd', 'www.startech.com.bd'])->exists()) DB::table('stores')->insert(['name' => 'Startech.com.bd', 'domain' => 'startech.com.bd', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['store.cats', 'store.catslugs', 'store.home11', 'prepaid.catset'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
