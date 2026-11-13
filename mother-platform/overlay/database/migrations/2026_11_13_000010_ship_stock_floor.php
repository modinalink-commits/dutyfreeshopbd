<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\CostingSql;

/** Owner rule 13 Nov 2026: stock never goes below zero. The shipment trigger now refuses (backstop for the PHP StockGuard). Re-applies the costing functions; CREATE OR REPLACE is idempotent. */
return new class extends Migration {
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    DB::unprepared(CostingSql::functions());
  }
  public function down(): void {}
};
