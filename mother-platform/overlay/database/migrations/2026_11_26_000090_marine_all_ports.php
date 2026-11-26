<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner decision (26 Nov 2026): every marine product can be delivered at all three ports (Chattogram, Mongla, Payra); the delivery charge depends on the port. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('marine_products')) return;
    $n = DB::table('marine_products')->where('enabled', true)->update(['ports' => 'chattogram,mongla,payra']);
    try { \Illuminate\Support\Facades\Cache::forever('marine.ppv', time()); } catch (\Throwable $e) {}
    try { \App\Support\Procure::audit('marine.all_ports', "Marine channel: $n products set to all ports (Chattogram, Mongla, Payra)", null, ['count' => $n]); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
