<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Book every parcel the courier delivered since 1 Nov 2026 (COD minus courier charge) into the Income book; idempotent per order (DEL-<invoice>). */
return new class extends Migration {
  public function up(): void {
    try {
      DB::table('orders')->where('courier_stage', 'Delivered')->where('courier_delivered_at', '>=', '2026-11-01')->orderBy('id')->select('id')->chunkById(200, function ($rows) {
        foreach ($rows as $r) { try { \App\Support\CourierBook::deliver((int)$r->id); } catch (\Throwable $e) { report($e); } }
      });
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
