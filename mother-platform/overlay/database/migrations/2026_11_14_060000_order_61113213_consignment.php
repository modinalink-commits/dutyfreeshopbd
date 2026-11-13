<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (14 Nov 2026): order 61113213 was sent with Pathao (consignment DD0111269KYNSV, delivered 2 Nov, paid 3 Nov per the owner's
 * Pathao merchant panel) but the consignment number was never saved, so the order stayed in Shipment and raised the
 * "Shipment without a courier consignment" alert. This only links the consignment; the status, delivery date and collected amount
 * then come from Pathao itself through the normal courier sync (CourierSync::apply), never typed by hand. Idempotent.
 */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasColumn('orders', 'consignment_id')) return;
      $o = DB::table('orders')->whereIn('invoice', ['61113213', 'INV-61113213'])->whereNull('consignment_id')->first(); if (!$o) return;
      $up = ['consignment_id' => 'DD0111269KYNSV', 'updated_at' => now()]; if (empty($o->courier)) $up['courier'] = 'Pathao';
      DB::table('orders')->where('id', $o->id)->update($up);
      try { DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_name' => 'System', 'from_status' => $o->status, 'to_status' => $o->status, 'note' => 'Pathao consignment DD0111269KYNSV linked (owner request 14 Nov 2026); status follows the courier sync', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
      try { $i = \App\Support\Pathao::info('DD0111269KYNSV'); if ($i) \App\Support\CourierSync::apply(DB::table('orders')->find($o->id), $i); } catch (\Throwable $e) { report($e); }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
