<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('account_transfers')) Schema::create('account_transfers', function (Blueprint $t) {
      $t->id(); $t->string('code', 30)->nullable()->unique(); $t->date('transfer_date')->index();
      $t->unsignedBigInteger('from_account_id')->index(); $t->unsignedBigInteger('to_account_id')->index();
      $t->decimal('amount', 14, 2); $t->string('note', 300)->nullable(); $t->string('status', 12)->default('done');
      $t->string('user_name', 80)->nullable(); $t->string('voided_by', 80)->nullable(); $t->timestamp('voided_at')->nullable(); $t->timestamps();
    });
    if (Schema::hasTable('supplier_payments') && !Schema::hasColumn('supplier_payments', 'money_account_id')) Schema::table('supplier_payments', function (Blueprint $t) { $t->unsignedBigInteger('money_account_id')->nullable()->index(); });
  }
  public function down(): void {}
};
