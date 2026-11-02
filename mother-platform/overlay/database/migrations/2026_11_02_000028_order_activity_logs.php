<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_activity_logs')) {
      Schema::create('order_activity_logs', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('staff_id')->nullable()->index(); $t->string('staff_name', 120)->nullable();
        $t->string('action_type', 40)->index(); $t->text('old_value')->nullable(); $t->text('new_value')->nullable(); $t->text('description')->nullable(); $t->json('metadata')->nullable();
        $t->string('ip_address', 64)->nullable(); $t->string('user_agent', 300)->nullable(); $t->string('source', 20)->nullable(); $t->string('batch_id', 64)->nullable();
        $t->timestamp('created_at')->nullable()->index();
      });
    }
    if (DB::getDriverName() !== 'pgsql') return;
    // Immutable: nobody (super admin included) can edit or delete a row; corrections are new rows.
    DB::unprepared("CREATE OR REPLACE FUNCTION oal_immutable() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'order_activity_logs is append-only'; END; \$\$ LANGUAGE plpgsql");
    DB::unprepared("DROP TRIGGER IF EXISTS oal_no_change ON order_activity_logs");
    DB::unprepared("CREATE TRIGGER oal_no_change BEFORE UPDATE OR DELETE ON order_activity_logs FOR EACH ROW EXECUTE FUNCTION oal_immutable()");
    // Every status log written anywhere (single, quick, bulk, created, schedule, auto) is mirrored here.
    DB::unprepared("CREATE OR REPLACE FUNCTION oal_from_status() RETURNS trigger AS \$\$ BEGIN
      INSERT INTO order_activity_logs(order_id,staff_id,staff_name,action_type,old_value,new_value,description,source,created_at)
      VALUES (NEW.order_id,NEW.user_id,COALESCE(NEW.user_name,'System'),
        CASE WHEN NEW.from_status IS NULL THEN 'order_created' ELSE 'status_changed' END,
        NEW.from_status,NEW.to_status,NEW.note,CASE WHEN NEW.user_id IS NULL THEN 'system' ELSE 'staff' END,COALESCE(NEW.created_at,now()));
      RETURN NEW; END; \$\$ LANGUAGE plpgsql");
    DB::unprepared("DROP TRIGGER IF EXISTS oal_status_mirror ON order_status_logs");
    DB::unprepared("CREATE TRIGGER oal_status_mirror AFTER INSERT ON order_status_logs FOR EACH ROW EXECUTE FUNCTION oal_from_status()");
    // One-time backfill of history that already exists.
    if (DB::table('order_activity_logs')->count() === 0) {
      DB::unprepared("INSERT INTO order_activity_logs(order_id,staff_id,staff_name,action_type,old_value,new_value,description,source,created_at)
        SELECT order_id,user_id,COALESCE(user_name,'System'),CASE WHEN from_status IS NULL THEN 'order_created' ELSE 'status_changed' END,from_status,to_status,note,CASE WHEN user_id IS NULL THEN 'system' ELSE 'staff' END,COALESCE(created_at,now()) FROM order_status_logs ORDER BY id");
      DB::unprepared("INSERT INTO order_activity_logs(order_id,staff_id,staff_name,action_type,description,source,created_at)
        SELECT order_id,printed_by,COALESCE(printed_by_name,'System'),CASE WHEN print_type='invoice' THEN 'invoice_printed' ELSE 'label_printed' END,print_type||' printed','staff',COALESCE(printed_at,now()) FROM order_print_logs");
      DB::unprepared("INSERT INTO order_activity_logs(order_id,staff_id,staff_name,action_type,description,source,created_at)
        SELECT order_id,user_id,COALESCE(user_name,'System'),'note_added',left(body,300),'staff',COALESCE(created_at,now()) FROM order_staff_comments");
    }
  }
  public function down(): void {}
};
