<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supplier Portal phases 2-3: deliveries + goods receiving, files, case notes, disputes, messages, documents, payment requests, 2FA flag, price/score support. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('supplier_users','twofa')) Schema::table('supplier_users', function (Blueprint $t) { $t->boolean('twofa')->default(false); });
    if (!Schema::hasTable('supplier_files')) Schema::create('supplier_files', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('ref_type',20)->index(); $t->unsignedBigInteger('ref_id')->nullable(); $t->string('kind',30)->nullable(); $t->string('name',200); $t->string('path',300); $t->string('mime',100)->nullable(); $t->unsignedInteger('size')->default(0); $t->string('by_side',10)->default('supplier'); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_deliveries')) Schema::create('supplier_deliveries', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('po_id')->index(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('status',20)->default('ready'); $t->date('dispatch_date')->nullable(); $t->string('vehicle',160)->nullable(); $t->string('challan_no',80)->nullable();
      $t->date('expected_arrival')->nullable(); $t->text('notes')->nullable(); $t->timestamp('received_at')->nullable(); $t->unsignedBigInteger('purchase_id')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_delivery_items')) Schema::create('supplier_delivery_items', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('delivery_id')->index(); $t->unsignedBigInteger('po_item_id')->index(); $t->integer('expected_qty')->default(0); $t->integer('received_qty')->nullable(); $t->integer('damaged_qty')->default(0); $t->integer('rejected_qty')->default(0); $t->integer('accepted_qty')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_case_notes')) Schema::create('supplier_case_notes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('case_type',12); $t->unsignedBigInteger('case_id'); $t->string('by_side',10); $t->text('body'); $t->timestamps(); $t->index(['case_type','case_id']);
    });
    if (!Schema::hasTable('supplier_disputes')) Schema::create('supplier_disputes', function (Blueprint $t) {
      $t->id(); $t->string('code',30)->nullable()->unique(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('dtype',30); $t->string('reference',120)->nullable(); $t->text('description'); $t->string('status',20)->default('open'); $t->text('resolution')->nullable();
      $t->unsignedBigInteger('raised_by')->nullable(); $t->string('resolved_by')->nullable(); $t->timestamp('resolved_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_messages')) Schema::create('supplier_messages', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('from_side',10); $t->string('ref_type',20)->nullable(); $t->string('ref_no',60)->nullable(); $t->text('body'); $t->unsignedBigInteger('file_id')->nullable(); $t->string('sender',160)->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_documents')) Schema::create('supplier_documents', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('kind',30); $t->string('title',200); $t->unsignedBigInteger('file_id'); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_payment_requests')) Schema::create('supplier_payment_requests', function (Blueprint $t) {
      $t->id(); $t->string('code',30)->nullable()->unique(); $t->unsignedBigInteger('supplier_id')->index(); $t->decimal('amount',14,2); $t->string('bills',255)->nullable(); $t->string('reason',200)->nullable(); $t->text('note')->nullable();
      $t->string('status',20)->default('requested'); $t->string('decided_by')->nullable(); $t->text('decision_note')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_products')) Schema::create('supplier_products', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('product_id')->index(); $t->string('supplier_sku',120)->nullable(); $t->decimal('latest_price',14,2)->nullable(); $t->integer('moq')->default(0); $t->integer('lead_days')->default(0); $t->timestamps();
      $t->unique(['supplier_id','product_id']);
    });
    if (!Schema::hasTable('supplier_price_alerts')) Schema::create('supplier_price_alerts', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('rfq_id')->nullable(); $t->string('kind',20); $t->string('product',255); $t->decimal('price',14,2); $t->decimal('ref_price',14,2); $t->decimal('pct',8,2); $t->boolean('seen')->default(false); $t->timestamps();
    });
  }
  public function down(): void {}
};
