<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fraud check backup (25 Nov 2026): SmartPOS Free Fraud Checker takes over automatically when BD Courier fails or runs out. Its monthly quota is read from its quota API and shown on Services and Balances. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) return;
    if (DB::table('service_accounts')->where('code', 'smartpos')->exists()) return;
    $row = ['code' => 'smartpos', 'name' => 'Fraud check backup (SmartPOS)', 'category' => 'Fraud check', 'kind' => 'auto', 'unit' => 'checks', 'sort' => 101, 'remind_days' => 5, 'note' => 'Backup for BD Courier: used automatically when BD Courier fails or its checks run out. Read from the SmartPOS quota API (monthly checks left).', 'created_at' => now(), 'updated_at' => now()];
    if (Schema::hasColumn('service_accounts', 'recharge_url')) $row['recharge_url'] = 'https://fraudchecker.smartposbd.com';
    if (Schema::hasColumn('service_accounts', 'low_at')) $row['low_at'] = 100;
    DB::table('service_accounts')->insert($row);
  }
  public function down(): void { if (Schema::hasTable('service_accounts')) DB::table('service_accounts')->where('code', 'smartpos')->delete(); }
};
