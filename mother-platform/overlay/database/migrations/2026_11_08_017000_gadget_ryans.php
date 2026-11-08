<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): Gadget & Electronics now comes from ryans.com (+5%, prepaid only, no discount / bundle). Scan + import runs on the server.
return new class extends Migration {
    public function up(): void {
        try {
            DB::table('settings')->updateOrInsert(['key' => 'gadget_source_domain'], ['value' => 'ryans.com', 'updated_at' => now(), 'created_at' => now()]);
            if (!DB::table('stores')->whereIn(DB::raw('lower(domain)'), ['ryans.com', 'www.ryans.com'])->exists()) DB::table('stores')->insert(['name' => 'Ryans.com', 'domain' => 'www.ryans.com', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $cur = (string)(DB::table('settings')->where('key', 'affiliate_now')->value('value') ?? '');
            DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => trim($cur . ',gadget:1', ','), 'updated_at' => now(), 'created_at' => now()]);
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
