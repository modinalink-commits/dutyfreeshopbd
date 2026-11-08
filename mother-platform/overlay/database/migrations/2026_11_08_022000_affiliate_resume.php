<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Deploys restart the container and kill background jobs, so unfinished jobs are remembered and resumed automatically: Sunglass import (scan done) and Gadget (ryans.com) scan + import.
return new class extends Migration {
    public function up(): void {
        try {
            DB::table('settings')->updateOrInsert(['key' => 'affiliate_pending'], ['value' => 'sunglass:0,gadget:1', 'updated_at' => now(), 'created_at' => now()]);
            DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => '', 'updated_at' => now(), 'created_at' => now()]);
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
