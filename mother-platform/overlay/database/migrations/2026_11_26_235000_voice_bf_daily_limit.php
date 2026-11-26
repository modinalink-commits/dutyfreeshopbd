<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** 26 Nov 2026: Black Friday voice campaign #5 may place up to 7000 calls a day (the dialler itself starts ~10 a minute). Only ever raises the limit. */
return new class extends Migration {
  public function up(): void {
    try { DB::table('voice_campaigns')->where('id', 5)->where('daily_limit', '<', 7000)->update(['daily_limit' => 7000, 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
