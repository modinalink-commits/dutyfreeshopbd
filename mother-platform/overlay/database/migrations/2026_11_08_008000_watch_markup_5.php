<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): Watch category profit is only 5% over the source shop price (was 15%).
return new class extends Migration {
    public function up(): void {
        try { DB::table('settings')->updateOrInsert(['key' => 'watch_markup_pct'], ['value' => '5', 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
