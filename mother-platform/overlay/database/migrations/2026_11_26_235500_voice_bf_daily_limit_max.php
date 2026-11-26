<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** 26 Nov 2026: owner wants the fastest possible Black Friday calling; daily limit of campaign #5 to the allowed maximum (10000). Only raises. */
return new class extends Migration {
  public function up(): void {
    try { DB::table('voice_campaigns')->where('id', 5)->where('daily_limit', '<', 10000)->update(['daily_limit' => 10000, 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
