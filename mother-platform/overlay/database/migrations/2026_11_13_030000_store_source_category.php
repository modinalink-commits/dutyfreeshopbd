<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Owner (13 Nov 2026): the Stores page shows which category each source store feeds ("Source category"). Free label per store. Also registers klubhaus.com.bd as the Fashion source.
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasColumn('stores', 'source_category')) Schema::table('stores', fn($t) => $t->string('source_category', 160)->nullable());
            foreach (['sundora.com.bd' => 'Beauty', 'timeaccess-store.com' => 'Watch', 'amour.com.bd' => 'Lighter & Accessories', 'luxotix.com' => 'Sunglass', 'ryans.com' => 'Gadget & Electronics'] as $d => $c)
                DB::table('stores')->where(fn($q) => $q->where('domain', $d)->orWhere('domain', 'www.' . $d))->whereNull('source_category')->update(['source_category' => $c]);
            if (!DB::table('stores')->where('domain', 'klubhaus.com.bd')->exists())
                DB::table('stores')->insert(['name' => 'Klubhaus.com.bd', 'domain' => 'klubhaus.com.bd', 'active' => true, 'source_category' => 'Fashion', 'created_at' => now(), 'updated_at' => now()]);
            foreach (['fashion_source_domain' => 'klubhaus.com.bd', 'affiliate_now' => 'fashion:1'] as $k => $v)
                DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
