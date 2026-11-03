<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Win-Back history keeps "next eligible SMS" (sent time + cooldown days) for every message.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('winback_events') || Schema::hasColumn('winback_events', 'next_sms_at')) return;
    Schema::table('winback_events', fn($t) => $t->timestamp('next_sms_at')->nullable());
    DB::statement("update winback_events set next_sms_at = created_at + interval '15 days' where cycle_order_id > 0 and next_sms_at is null");
    DB::statement("insert into settings (key, value, created_at, updated_at) select 'wb_slot_size', '10', now(), now() where not exists (select 1 from settings where key = 'wb_slot_size')");
    DB::statement("insert into settings (key, value, created_at, updated_at) select 'wb_slot_gap', '10', now(), now() where not exists (select 1 from settings where key = 'wb_slot_gap')");
    DB::statement("insert into settings (key, value, created_at, updated_at) select 'wb_min_gap', '15', now(), now() where not exists (select 1 from settings where key = 'wb_min_gap')");
  }
  public function down(): void {}
};
