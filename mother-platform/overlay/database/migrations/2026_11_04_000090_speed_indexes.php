<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Speed: additive indexes for the admin dashboard / orders list / reports (all IF NOT EXISTS, columns checked, failures ignored). No data is changed. */
return new class extends Migration {
  public function up(): void {
    $ix = [
      ['orders', 'created_at', ['created_at']],
      ['orders', 'status_created', ['status', 'created_at']],
      ['orders', 'phone_created', ['phone', 'created_at']],
      ['orders', 'store_created', ['store_id', 'created_at']],
      ['orders', 'customer_id', ['customer_id']],
      ['orders', 'district', ['district']],
      ['orders', 'courier_stage', ['courier', 'courier_stage']],
      ['orders', 'delivered_at', ['courier_delivered_at']],
      ['order_items', 'order_id', ['order_id']],
      ['order_staff_comments', 'order_id', ['order_id']],
      ['order_activities', 'order_id', ['order_id', 'created_at']],
      ['customers', 'phone', ['phone']],
      ['customers', 'level', ['customer_level']],
      ['customer_calls', 'status', ['status']],
      ['chat_messages', 'thread_id', ['thread_id']],
    ];
    foreach ($ix as [$t, $n, $cols]) {
      try {
        if (!Schema::hasTable($t)) continue;
        foreach ($cols as $c) if (!Schema::hasColumn($t, $c)) continue 2;
        DB::statement('create index if not exists ' . $t . '_' . $n . '_spd on ' . $t . ' (' . implode(',', array_map(fn($c) => '"' . $c . '"', $cols)) . ')');
      } catch (\Throwable $e) { report($e); }
    }
    try { DB::statement('analyze orders'); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
