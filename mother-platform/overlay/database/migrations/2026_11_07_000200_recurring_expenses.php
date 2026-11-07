<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recurring Fixed Expense Scheduler: master + effective-dated amount history + one instance per billing period (unique). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('recurring_expenses')) Schema::create('recurring_expenses', function (Blueprint $t) {
      $t->id(); $t->string('name', 160); $t->string('category', 80); $t->string('expense_type', 30)->default('fixed');
      $t->decimal('amount', 14, 2); $t->string('frequency', 20)->default('monthly'); $t->unsignedSmallInteger('interval_months')->default(1); $t->unsignedTinyInteger('pay_day')->default(1);
      $t->date('start_date'); $t->date('end_date')->nullable(); $t->unsignedBigInteger('account_id')->nullable(); $t->string('expense_for', 40)->default('General Company'); $t->string('store', 80)->nullable();
      $t->boolean('auto_post')->default(true); $t->boolean('auto_deduct')->default(false); $t->string('status', 20)->default('active'); $t->text('notes')->nullable(); $t->string('source', 20)->default('manual');
      $t->boolean('is_loan')->default(false); $t->string('loan_provider', 120)->nullable(); $t->decimal('loan_amount', 14, 2)->nullable(); $t->decimal('principal', 14, 2)->default(0); $t->decimal('interest', 14, 2)->default(0); $t->decimal('fee', 14, 2)->default(0); $t->decimal('remaining_balance', 14, 2)->nullable();
      $t->string('currency', 8)->default('BDT'); $t->decimal('orig_amount', 14, 2)->nullable(); $t->decimal('exch_rate', 12, 4)->nullable();
      $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('recurring_expense_amounts')) Schema::create('recurring_expense_amounts', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('recurring_id')->index(); $t->decimal('amount', 14, 2); $t->decimal('principal', 14, 2)->default(0); $t->decimal('interest', 14, 2)->default(0); $t->decimal('fee', 14, 2)->default(0);
      $t->date('effective_from'); $t->string('changed_by', 80)->nullable(); $t->string('reason', 240)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('recurring_expense_instances')) Schema::create('recurring_expense_instances', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('recurring_id')->index(); $t->date('billing_period'); $t->date('due_date')->index();
      $t->decimal('amount', 14, 2); $t->decimal('principal', 14, 2)->default(0); $t->decimal('interest', 14, 2)->default(0); $t->decimal('fee', 14, 2)->default(0);
      $t->string('status', 20)->default('posted'); $t->string('payment_status', 20)->default('pending'); $t->unsignedBigInteger('account_id')->nullable(); $t->string('entry_ref', 60)->nullable();
      $t->timestamp('posted_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->string('note', 240)->nullable(); $t->timestamps();
      $t->unique(['recurring_id', 'billing_period']);
    });
  }
  public function down(): void {}
};
