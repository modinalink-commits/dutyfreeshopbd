<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
  public function up(): void {
    try { if (\App\Support\SeoAutoGenerator::ready()) { \App\Support\SeoAutoGenerator::bulk('brand', true); \Illuminate\Support\Facades\Cache::flush(); } } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
