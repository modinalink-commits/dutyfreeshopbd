<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders with no valid Store (store_id empty, or pointing at a store that does not exist) are assigned to the one DutyFreeBD store.
 * They are the orders imported from the old dutyfreebd.com and the admin-made orders that were saved with a fixed store id.
 * Only runs when the system has exactly one store, so no order can be put in the wrong store. The old value of every changed order is kept
 * in orders_store_backfill so the change can be undone. Order status, totals and dates are not touched.
 */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (!Schema::hasTable('orders') || !Schema::hasTable('stores')) return;
      if (DB::table('stores')->count() !== 1) return;
      $sid = (int)DB::table('stores')->value('id');
      if (!Schema::hasTable('orders_store_backfill')) Schema::create('orders_store_backfill', function (Blueprint $t) {
        $t->unsignedBigInteger('order_id')->primary(); $t->unsignedBigInteger('old_store_id')->nullable(); $t->unsignedBigInteger('new_store_id'); $t->timestamp('created_at')->nullable();
      });
      $n = 0;
      DB::table('orders')->whereRaw('store_id is null or store_id not in (select id from stores)')->orderBy('id')->select('id', 'store_id')->chunkById(1000, function ($rows) use ($sid, &$n) {
        DB::transaction(function () use ($rows, $sid, &$n) {
          $ids = [];
          foreach ($rows as $r) { DB::table('orders_store_backfill')->insertOrIgnore(['order_id' => $r->id, 'old_store_id' => $r->store_id, 'new_store_id' => $sid, 'created_at' => now()]); $ids[] = $r->id; }
          $n += DB::table('orders')->whereIn('id', $ids)->update(['store_id' => $sid]);
        });
      });
      DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Orders store backfill', 'subject' => $n . ' orders set to store #' . $sid, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Orders store backfill failed', 'subject' => mb_substr($e->getMessage(), 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
