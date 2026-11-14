<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request (15 Nov, third time): put order 103888 (id 103702) (Patron Nano White Special, Pathao DD1111264Y9YMP) back to Return Pending so the return-receive flow can be tested again.
 * The courier sync would receive it again at once (Pathao still says Return), so the order id is added to the "hold auto receive" list. Stock stays as it is (it was already returned once).
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('orders')) return;
    $o = DB::table('orders')->where(fn($q) => $q->where('id', 103702))->where('status', 'Returned')->where('consignment_id', 'DD1111264Y9YMP')->first();
    if (!$o) return;
    DB::transaction(function () use ($o) {
      $now = now();
      DB::table('order_items')->where('order_id', $o->id)->update(['outcome' => 'Return Pending', 'ret_qty' => DB::raw('qty'), 'back_qty' => null, 'outcome_at' => $now]);
      DB::table('orders')->where('id', $o->id)->update(['status' => 'Return Pending', 'return_type' => 'Full', 'return_received_at' => null, 'updated_at' => $now]);
      DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'System', 'from_status' => 'Returned', 'to_status' => 'Return Pending', 'note' => 'Moved back to Return Pending on the owner\'s request, to test the return receive again', 'created_at' => $now, 'updated_at' => $now]);
      $cur = (string)(DB::table('settings')->where('key', 'return_auto_hold')->value('value') ?? '');
      $ids = array_filter(array_map('trim', explode(',', $cur))); $ids[] = (string)$o->id; $val = implode(',', array_unique($ids));
      if (DB::table('settings')->where('key', 'return_auto_hold')->exists()) DB::table('settings')->where('key', 'return_auto_hold')->update(['value' => $val]); else DB::table('settings')->insert(['key' => 'return_auto_hold', 'value' => $val]);
    });
  }
  public function down(): void {}
};
