<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fraud check (25 Nov 2026): SmartPOS (premium, 1000 checks a day) is now the MAIN fraud check; BD Courier is the automatic backup. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) return;
    DB::table('service_accounts')->where('code', 'smartpos')->update(['name' => 'Fraud check main (SmartPOS)', 'sort' => 99, 'note' => 'Main fraud check on every new order (premium plan, 1000 checks a day). If it fails or runs out, BD Courier takes over by itself. Read from the SmartPOS quota API.', 'updated_at' => now()]);
    DB::table('service_accounts')->where('code', 'bdcourier')->update(['note' => 'Backup fraud check: used automatically when SmartPOS fails or its checks run out. Read from BDCourier "My Plan".', 'updated_at' => now()]);
  }
  public function down(): void {}
};
