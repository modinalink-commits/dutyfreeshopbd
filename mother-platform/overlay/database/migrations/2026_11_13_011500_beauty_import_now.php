<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Owner (13 Nov 2026): the Sundora scan is already finished, so start only the import (no re-scan: ":0") for the Beauty category.
return new class extends Migration {
    public function up(): void {
        try { DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => 'beauty:0', 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
