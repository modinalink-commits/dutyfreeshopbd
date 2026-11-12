<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Resume the paused Ryans scan from its saved position (fixed code). */
return new class extends Migration {
  public function up(): void {
    DB::table('stores')->whereRaw("lower(domain) like '%ryans.com'")->where('scan_status', 'paused')->update(['scan_status' => 'running', 'updated_at' => now()]);
  }
  public function down(): void {}
};
