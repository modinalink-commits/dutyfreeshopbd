<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** 27 Nov 2026 18:58 (owner): the Black Friday profit goes from 10% to 15% over purchase cost. Stored as setting bf_profit_pct; products already on a Black Friday price are lifted (one with no saving left goes back to its normal price). */
return new class extends Migration {
  public function up(): void {
    try {
      \App\Support\Procure::put('bf_profit_pct', '15');
      $n = \App\Support\BlackFriday::reprice();
      \App\Support\Procure::audit('Black Friday profit changed 10% to 15%', $n . ' products re-priced', ['profit_pct' => 10], ['profit_pct' => 15]);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
