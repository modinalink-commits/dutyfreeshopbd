<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Speed audit 15 Nov 2026: Perf Audit showed repeated full scans of chat_messages and order_activity_logs. Additive indexes only (IF NOT EXISTS, columns checked, failures ignored). No data is changed. down() drops only these. */
return new class extends Migration {
  private function ix(): array { return [
    ['chat_messages', 'created_at', ['created_at']],
    ['chat_messages', 'dir_created', ['dir', 'created_at']],
    ['chat_messages', 'thread_dir_id', ['thread_id', 'dir', 'id']],
    ['order_activity_logs', 'source_created', ['source', 'created_at']],
    ['order_activity_logs', 'action_created', ['action_type', 'created_at']],
    ['order_activity_logs', 'staff_created', ['staff_id', 'created_at']],
  ]; }
  public function up(): void {
    foreach ($this->ix() as [$t, $n, $cols]) {
      try {
        if (!Schema::hasTable($t)) continue;
        foreach ($cols as $c) if (!Schema::hasColumn($t, $c)) continue 2;
        DB::statement('create index if not exists ' . $t . '_' . $n . '_spd2 on ' . $t . ' (' . implode(',', array_map(fn($c) => '"' . $c . '"', $cols)) . ')');
      } catch (\Throwable $e) { report($e); }
    }
    foreach (['chat_messages', 'order_activity_logs'] as $t) { try { DB::statement('analyze ' . $t); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    foreach ($this->ix() as [$t, $n]) { try { DB::statement('drop index if exists ' . $t . '_' . $n . '_spd2'); } catch (\Throwable $e) {} }
  }
};
