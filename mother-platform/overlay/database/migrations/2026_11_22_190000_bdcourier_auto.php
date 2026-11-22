<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Services and Balances (22 Nov 2026), part 4: BDCourier has a "My Plan" API, so its plan expiry and the checks left today are now read automatically instead of typed in. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) return;
    DB::table('service_accounts')->where('name', 'Courier fraud check (BDCourier)')->where('kind', 'manual')->whereNull('code')->update([
      'code' => 'bdcourier', 'kind' => 'auto', 'unit' => 'checks', 'remind_days' => 7, 'updated_at' => now(),
      'note' => 'Used on every new order. Read from BDCourier "My Plan": plan expiry and the checks left today (the allowance resets every day).',
    ]);
  }
  public function down(): void {
    if (Schema::hasTable('service_accounts')) DB::table('service_accounts')->where('code', 'bdcourier')->update(['code' => null, 'kind' => 'manual']);
  }
};
