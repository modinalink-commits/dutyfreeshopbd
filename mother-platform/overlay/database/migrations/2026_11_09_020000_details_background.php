<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Run the source-description import for Sunglass, Gadget, Amour and Watch on the server (no scan, no need for an open browser). */
return new class extends Migration {
  public function up(): void {
    try { DB::table('settings')->updateOrInsert(['key' => 'affiliate_now'], ['value' => 'sunglass:0,gadget:0,amour:0,watch:0', 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
