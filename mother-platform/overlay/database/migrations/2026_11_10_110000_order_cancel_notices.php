<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Customer cancellation notice. orders get cancel_reason / cancel_note (chosen by staff when cancelling).
 * A database trigger puts every order that moves INTO Cancel on a queue (order_cancel_notices), whatever screen,
 * bulk action or automatic job did it, so no cancellation can be missed. OrderCancelNotice sends the SMS / WhatsApp from that queue.
 * Only orders cancelled after this migration are queued; old cancelled orders are never messaged.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('orders')) return;
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'cancel_reason')) $t->string('cancel_reason', 40)->nullable();
      if (!Schema::hasColumn('orders', 'cancel_note')) $t->string('cancel_note', 300)->nullable();
    });
    if (!Schema::hasTable('order_cancel_notices')) Schema::create('order_cancel_notices', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->unique(); $t->string('from_status', 40)->nullable();
      $t->string('state', 12)->default('pending')->index();           // pending | done | skipped | failed
      $t->string('sms_status', 12)->nullable(); $t->string('wa_status', 12)->nullable(); $t->string('wa_wamid', 120)->nullable();
      $t->string('note', 300)->nullable(); $t->unsignedSmallInteger('tries')->default(0);
      $t->timestamp('created_at')->useCurrent(); $t->timestamp('done_at')->nullable();
    });
    if (DB::getDriverName() !== 'pgsql') return;
    DB::unprepared("create or replace function df_order_cancel_notice() returns trigger as \$\$ begin
      if new.status = 'Cancel' and old.status is distinct from 'Cancel' and old.status is distinct from 'Incomplete' then
        insert into order_cancel_notices (order_id, from_status) values (new.id, old.status) on conflict (order_id) do nothing;
      end if; return new; end \$\$ language plpgsql");
    DB::unprepared("drop trigger if exists df_order_cancel_notice on orders");
    DB::unprepared("create trigger df_order_cancel_notice after update of status on orders for each row execute function df_order_cancel_notice()");
  }
  public function down(): void {
    if (DB::getDriverName() === 'pgsql') { DB::unprepared("drop trigger if exists df_order_cancel_notice on orders"); DB::unprepared("drop function if exists df_order_cancel_notice()"); }
    Schema::dropIfExists('order_cancel_notices');
    if (Schema::hasTable('orders')) Schema::table('orders', function (Blueprint $t) { foreach (['cancel_reason', 'cancel_note'] as $c) if (Schema::hasColumn('orders', $c)) $t->dropColumn($c); });
  }
};
