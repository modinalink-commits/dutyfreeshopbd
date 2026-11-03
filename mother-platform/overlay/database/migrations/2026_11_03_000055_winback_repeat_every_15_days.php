<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A number now gets an offer message every 15 days while it stays inactive, so the same (phone, order cycle, stage) may repeat.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('winback_events')) return;
    DB::statement('drop index if exists winback_events_phone_cycle_order_id_stage_days_unique');
    DB::statement('create index if not exists winback_events_phone_created_idx on winback_events (phone, created_at)');
  }
  public function down(): void {}
};
