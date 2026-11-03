<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Exchange parcels are not part of the business: "Exchange Received" leaves the active workflow. History (status logs, activity, return_type, pathao_outcome) is kept untouched. */
return new class extends Migration {
  public function up(): void {
    $ids = DB::table('orders')->whereIn('status', ['Exchange Received', 'Exchange Pending'])->get(['id', 'status']);
    foreach ($ids as $o) {
      $to = $o->status === 'Exchange Received' ? 'Returned' : 'Return Pending';
      DB::table('orders')->where('id', $o->id)->update(['status' => $to]);
      DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'System', 'from_status' => $o->status, 'to_status' => $to, 'note' => 'Exchange status retired: old "'.$o->status.'" shown as '.$to.'. Original exchange record kept (return type Exchange).', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (Schema::hasTable('pathao_status_map')) DB::table('pathao_status_map')->whereIn('action', ['exchange_pending', 'exchange_received'])->update(['action' => 'exchange_review', 'note' => 'Exchange is not used: the order status is not changed, the parcel is flagged for manual review.', 'updated_at' => now()]);
  }
  public function down(): void {}
};
