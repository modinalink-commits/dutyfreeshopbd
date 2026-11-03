<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/**
 * Order-status bar cleanup: after Shipment only Shipment, Partial Delivered, Delivered, Late Delivery, Return Pending, Returned, Exchange Received remain.
 * Old statuses are moved (history in order_status_logs / activity is never touched; one log row records each move):
 *   Partial Return Pending -> Return Pending (return_type Partial)   Exchange Pending -> Return Pending (return_type Exchange)
 *   Partial Return Received / Partial Returned -> Returned (return_type Partial)
 */
return new class extends Migration {
  public function up(): void {
    try {
      $map = [['Partial Return Pending', 'Return Pending', 'Partial'], ['Exchange Pending', 'Return Pending', 'Exchange'], ['Partial Return Received', 'Returned', 'Partial'], ['Partial Returned', 'Returned', 'Partial']];
      foreach ($map as [$from, $to, $type]) {
        $ids = DB::table('orders')->where('status', $from)->pluck('id')->all(); if (!$ids) continue;
        foreach (array_chunk($ids, 500) as $c) {
          DB::table('orders')->whereIn('id', $c)->update(['status' => $to, 'return_type' => $type, 'updated_at' => now()]);
          DB::table('order_status_logs')->insert(array_map(fn($id) => ['order_id' => $id, 'user_id' => null, 'user_name' => 'System', 'from_status' => $from, 'to_status' => $to, 'note' => 'Status list cleanup: "'.$from.'" is now "'.$to.'" ('.$type.' return kept as the return type)', 'created_at' => now(), 'updated_at' => now()], $c));
        }
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
