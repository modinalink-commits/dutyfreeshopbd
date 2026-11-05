<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supplier Partner Portal, Phase 1: logins, PO, RFQ + quotes, notifications, profile-change requests, activity. Ledger/bills/payments are NOT copied: the portal reads the existing purchase tables. */
return new class extends Migration {
  public function up(): void {
    Schema::table('suppliers', function (Blueprint $t) {
      foreach (['bank_name'=>160,'bank_account_name'=>160,'bank_account_no'=>80,'bank_branch'=>160,'bank_routing'=>40,'tax_bin'=>80,'payment_pref'=>40] as $c=>$l) if (!Schema::hasColumn('suppliers',$c)) $t->string($c,$l)->nullable();
    });
    if (!Schema::hasColumn('purchases','po_id')) Schema::table('purchases', function (Blueprint $t) { $t->unsignedBigInteger('po_id')->nullable()->index(); });

    if (!Schema::hasTable('supplier_users')) Schema::create('supplier_users', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('name',160); $t->string('mobile',30)->nullable()->index(); $t->string('email',160)->nullable()->index();
      $t->string('password'); $t->string('role',20)->default('owner'); $t->string('status',20)->default('active'); $t->boolean('must_change')->default(true);
      $t->timestamp('last_login_at')->nullable(); $t->string('last_login_ip',60)->nullable(); $t->unsignedSmallInteger('failed_attempts')->default(0); $t->timestamp('locked_until')->nullable();
      $t->timestamp('force_logout_at')->nullable(); $t->timestamp('terms_at')->nullable(); $t->string('terms_ver',20)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_login_logs')) Schema::create('supplier_login_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_user_id')->nullable()->index(); $t->unsignedBigInteger('supplier_id')->nullable()->index(); $t->string('identifier',160)->nullable();
      $t->string('event',20); $t->string('ip',60)->nullable(); $t->string('device',200)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_activity')) Schema::create('supplier_activity', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('supplier_user_id')->nullable(); $t->string('action',60); $t->string('subject',120)->nullable(); $t->text('meta')->nullable(); $t->string('ip',60)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_notifications')) Schema::create('supplier_notifications', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('type',40); $t->string('priority',16)->default('info'); $t->string('title',200); $t->text('body')->nullable(); $t->string('link',200)->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_pos')) Schema::create('supplier_pos', function (Blueprint $t) {
      $t->id(); $t->string('po_no',30)->nullable()->unique(); $t->unsignedBigInteger('supplier_id')->index(); $t->date('po_date'); $t->date('delivery_date')->nullable();
      $t->string('status',20)->default('draft'); $t->decimal('total',14,2)->default(0); $t->text('note')->nullable(); $t->string('reject_reason',60)->nullable(); $t->text('response_note')->nullable();
      $t->timestamp('responded_at')->nullable(); $t->unsignedBigInteger('rfq_quote_id')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_po_items')) Schema::create('supplier_po_items', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('po_id')->index(); $t->unsignedBigInteger('product_id')->nullable(); $t->string('name',255); $t->string('sku',120)->nullable();
      $t->integer('qty'); $t->decimal('rate',14,2)->default(0); $t->integer('accepted_qty')->nullable(); $t->decimal('line_total',14,2)->default(0); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_rfqs')) Schema::create('supplier_rfqs', function (Blueprint $t) {
      $t->id(); $t->string('rfq_no',30)->nullable()->unique(); $t->unsignedBigInteger('product_id')->nullable(); $t->string('product',255); $t->string('sku',120)->nullable(); $t->integer('qty');
      $t->date('required_date')->nullable(); $t->string('location',200)->nullable(); $t->text('notes')->nullable(); $t->timestamp('expires_at')->nullable(); $t->string('status',20)->default('draft');
      $t->unsignedBigInteger('awarded_quote_id')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_rfq_invites')) Schema::create('supplier_rfq_invites', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('rfq_id')->index(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('status',20)->default('pending'); $t->timestamp('viewed_at')->nullable(); $t->timestamps();
      $t->unique(['rfq_id','supplier_id']);
    });
    if (!Schema::hasTable('supplier_quotes')) Schema::create('supplier_quotes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('rfq_id')->index(); $t->unsignedBigInteger('supplier_id')->index(); $t->decimal('unit_price',14,2); $t->integer('available_qty'); $t->integer('lead_days')->default(0);
      $t->date('valid_until')->nullable(); $t->integer('moq')->default(0); $t->decimal('delivery_charge',14,2)->default(0); $t->text('note')->nullable(); $t->timestamps();
      $t->unique(['rfq_id','supplier_id']);
    });
    if (!Schema::hasTable('supplier_profile_changes')) Schema::create('supplier_profile_changes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('supplier_user_id')->nullable(); $t->text('changes'); $t->string('status',16)->default('pending');
      $t->string('decided_by')->nullable(); $t->timestamp('decided_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
