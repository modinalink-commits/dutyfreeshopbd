<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Approval Source (7 Nov 2026): who/what approved an order. Saved at the moment of approval, never derived from the status later.
 *  orders.approval_source/approved_by/approved_at/approval_reference = the CURRENT (latest) approval; order_approval_history = every approval, append-only (update/delete blocked by trigger). */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'approval_source')) $t->string('approval_source', 12)->nullable()->index();
      if (!Schema::hasColumn('orders', 'approved_by')) $t->unsignedBigInteger('approved_by')->nullable();
      if (!Schema::hasColumn('orders', 'approved_at')) $t->timestamp('approved_at')->nullable();
      if (!Schema::hasColumn('orders', 'approval_reference')) $t->string('approval_reference', 120)->nullable();
    });
    if (!Schema::hasTable('order_approval_history')) {
      Schema::create('order_approval_history', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->string('source', 12); $t->unsignedBigInteger('approved_by')->nullable(); $t->string('approved_by_name', 120)->nullable();
        $t->timestamp('approved_at'); $t->string('reference', 120)->nullable(); $t->string('note', 300)->nullable(); $t->timestamp('created_at')->nullable();
      });
      DB::unprepared("create or replace function order_approval_history_lock() returns trigger as \$\$ begin raise exception 'order_approval_history is append-only'; end; \$\$ language plpgsql");
      DB::unprepared("drop trigger if exists order_approval_history_lock on order_approval_history");
      DB::unprepared("create trigger order_approval_history_lock before update or delete on order_approval_history for each row execute function order_approval_history_lock()");
      // One-time backfill from what was actually RECORDED at the time (the status log notes), not from the current status.
      DB::unprepared("insert into order_approval_history (order_id, source, approved_by, approved_by_name, approved_at, reference, note, created_at)
        select l.order_id,
          case when l.note like 'Automatically approved: AI Verify%' or l.note like 'AI Approve%' then 'AI' when l.user_id is not null then 'MANUAL' else 'SYSTEM' end,
          case when l.note like 'Automatically approved: AI Verify%' or l.note like 'AI Approve%' then null else l.user_id end,
          l.user_name, coalesce(l.created_at, now()), null, left(l.note, 300), now()
        from order_status_logs l where l.to_status = 'Approved' order by l.id");
      DB::unprepared("update orders o set approval_source = h.source, approved_by = h.approved_by, approved_at = coalesce(o.approved_at, h.approved_at)
        from (select distinct on (order_id) order_id, source, approved_by, approved_at from order_approval_history order by order_id, id desc) h where h.order_id = o.id and o.approval_source is null");
    }
  }
  public function down(): void {}
};
