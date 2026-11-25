<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fraud check (25 Nov 2026): the owner chose BD Courier as the main fraud check and SmartPOS (premium, 1000 checks a day) as the automatic backup. The order can be switched on the Fraud Check page. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) return;
    DB::table('service_accounts')->where('code', 'smartpos')->update(['name' => 'Fraud check backup (SmartPOS)', 'sort' => 101, 'note' => 'Backup fraud check (premium plan, 1000 checks a day): used automatically when BD Courier fails or runs out. The main/backup order is set on the Fraud Check page. Read from the SmartPOS quota API.', 'updated_at' => now()]);
    DB::table('service_accounts')->where('code', 'bdcourier')->update(['note' => 'Main fraud check on every new order; SmartPOS takes over automatically when it fails or its checks run out. Read from BDCourier "My Plan".', 'updated_at' => now()]);
  }
  public function down(): void {}
};
