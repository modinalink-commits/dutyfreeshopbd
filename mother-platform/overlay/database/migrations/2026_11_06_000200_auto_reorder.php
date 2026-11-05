<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Auto Reorder Alert: low published stock -> reorder request -> RFQ to mapped suppliers. Reuses the RFQ/quote/PO flow; nothing is duplicated. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('reorder_requests')) Schema::create('reorder_requests', function (Blueprint $t) {
      $t->id(); $t->string('req_no', 20)->nullable()->unique(); $t->unsignedBigInteger('product_id')->index(); $t->unsignedBigInteger('rfq_id')->nullable()->index();
      $t->integer('stock_at')->default(0); $t->integer('threshold')->default(50); $t->integer('target')->default(0); $t->integer('req_qty')->default(1); $t->string('urgency', 12)->default('Low');
      $t->string('status', 24)->default('New')->index(); $t->date('required_date')->nullable(); $t->string('closed_reason', 80)->nullable(); $t->timestamp('closed_at')->nullable();
      $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('reorder_queue')) Schema::create('reorder_queue', function (Blueprint $t) { $t->unsignedBigInteger('product_id')->primary(); $t->timestamp('created_at')->nullable(); });
    Schema::table('products', function (Blueprint $t) {
      if (!Schema::hasColumn('products', 'reorder_target')) $t->integer('reorder_target')->nullable();
      if (!Schema::hasColumn('products', 'reorder_mode')) $t->string('reorder_mode', 10)->nullable();
    });
    Schema::table('supplier_rfqs', function (Blueprint $t) { if (!Schema::hasColumn('supplier_rfqs', 'reorder_id')) $t->unsignedBigInteger('reorder_id')->nullable()->index(); });
    Schema::table('supplier_rfq_invites', function (Blueprint $t) { if (!Schema::hasColumn('supplier_rfq_invites', 'decline_reason')) $t->string('decline_reason', 60)->nullable(); });
    if (DB::getDriverName() === 'pgsql') {
      // one open request per product, enforced by the database (a race can never create a duplicate)
      DB::unprepared("CREATE UNIQUE INDEX IF NOT EXISTS reorder_open_uq ON reorder_requests (product_id) WHERE status NOT IN ('Closed','Cancelled','Expired')");
      // "immediately after stock movement": every stock change queues the product; the worker drains the queue within a minute
      DB::unprepared("CREATE OR REPLACE FUNCTION reorder_queue_push() RETURNS trigger AS \$\$
        BEGIN
          BEGIN INSERT INTO reorder_queue (product_id, created_at) VALUES (NEW.id, now()) ON CONFLICT (product_id) DO NOTHING; EXCEPTION WHEN OTHERS THEN NULL; END;
          RETURN NULL;
        END; \$\$ LANGUAGE plpgsql");
      DB::unprepared("DROP TRIGGER IF EXISTS reorder_queue_t ON products");
      DB::unprepared("CREATE TRIGGER reorder_queue_t AFTER UPDATE OF stock ON products FOR EACH ROW WHEN (OLD.stock IS DISTINCT FROM NEW.stock) EXECUTE FUNCTION reorder_queue_push()");
    }
  }
  public function down(): void {}
};
