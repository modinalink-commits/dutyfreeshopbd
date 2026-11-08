<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// One-off kick (8 Nov 2026): finish the Gadget import (scan already done) and scan + import the new Sunglass store, on the server, without a browser.
return new class extends Migration {
    public function up(): void {
        try { DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => 'gadget:0,sunglass:1', 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
