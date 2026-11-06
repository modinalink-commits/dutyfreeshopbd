<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** One-time: book this month's already-delivered courier orders into the Income book (idempotent by COD-<invoice>). */
return new class extends Migration {
  public function up(): void {
    try {
      foreach (DB::table('orders')->where('courier_stage', 'Delivered')->where('courier_delivered_at', '>=', '2026-11-01')->orderBy('id')->pluck('id') as $id) \App\Support\CourierBook::post((int)$id);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
