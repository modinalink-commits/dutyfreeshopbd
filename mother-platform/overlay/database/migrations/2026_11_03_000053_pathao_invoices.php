<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
/** Pathao settlement invoices (one per payout) and their parcel lines, plus the settlement result kept on the order. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('pathao_invoices')) Schema::create('pathao_invoices', function (Blueprint $t) {
      $t->id(); $t->string('invoice_no', 40)->unique(); $t->date('invoice_date')->nullable(); $t->string('status', 30)->nullable();
      $t->integer('lines')->default(0); $t->integer('matched')->default(0); $t->integer('unmatched')->default(0); $t->integer('flagged')->default(0);
      $t->decimal('collected', 14, 2)->default(0); $t->decimal('fee', 14, 2)->default(0); $t->decimal('payout', 14, 2)->default(0);
      $t->string('imported_by', 120)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('pathao_invoice_lines')) Schema::create('pathao_invoice_lines', function (Blueprint $t) {
      $t->id(); $t->string('invoice_no', 40)->index(); $t->string('line_key', 40)->unique(); $t->date('line_date')->nullable();
      $t->string('consignment_id', 40)->nullable()->index(); $t->string('order_ref', 60)->nullable()->index(); $t->string('recipient', 160)->nullable(); $t->string('phone', 30)->nullable(); $t->string('line_type', 30)->nullable();
      $t->decimal('collectable', 12, 2)->default(0); $t->decimal('collected', 12, 2)->default(0); $t->decimal('cod_fee', 12, 2)->default(0); $t->decimal('delivery_charge', 12, 2)->default(0); $t->decimal('discount', 12, 2)->default(0); $t->decimal('fee', 12, 2)->default(0); $t->decimal('paid_out', 12, 2)->default(0);
      $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('match_status', 20)->default('unmatched')->index(); $t->boolean('applied')->default(false); $t->string('note', 255)->nullable(); $t->timestamps();
    });
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'pathao_invoice_no')) $t->string('pathao_invoice_no', 40)->nullable();
      if (!Schema::hasColumn('orders', 'pathao_collected')) $t->decimal('pathao_collected', 12, 2)->nullable();
      if (!Schema::hasColumn('orders', 'pathao_paid_out')) $t->decimal('pathao_paid_out', 12, 2)->nullable();
      if (!Schema::hasColumn('orders', 'pathao_settled_at')) $t->timestamp('pathao_settled_at')->nullable();
    });
  }
  public function down(): void {}
};
