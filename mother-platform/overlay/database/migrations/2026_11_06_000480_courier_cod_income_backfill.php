<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Book this month's already-applied Pathao payouts as income (idempotent by PAY-<invoice>-<consignment>). */
return new class extends Migration {
  public function up(): void {
    try {
      if (!\Illuminate\Support\Facades\Schema::hasTable('pathao_invoice_lines')) return;
      foreach (DB::table('pathao_invoice_lines')->where('applied', true)->where('paid_out', '>', 0)->where('line_date', '>=', '2026-11-01')->orderBy('id')->get() as $r) \App\Support\CourierBook::payout($r, null);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
