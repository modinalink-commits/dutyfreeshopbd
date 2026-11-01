<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('customers', 'ref_code')) Schema::table('customers', function (Blueprint $t) { $t->string('ref_code', 20)->nullable()->unique(); });
    if (!Schema::hasColumn('customers', 'ref_balance')) Schema::table('customers', function (Blueprint $t) { $t->decimal('ref_balance', 12, 2)->default(0); });
    if (!Schema::hasTable('referrals')) Schema::create('referrals', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('referrer_id')->index(); $t->unsignedBigInteger('referred_id')->nullable(); $t->string('referred_phone', 20)->unique(); $t->string('code', 20)->index(); $t->string('link', 200)->nullable();
      $t->unsignedBigInteger('order_id')->nullable()->index(); $t->decimal('order_amount', 12, 2)->default(0); $t->decimal('discount', 12, 2)->default(0); $t->decimal('reward', 12, 2)->default(0);
      $t->string('order_status', 30)->nullable(); $t->string('status', 12)->default('pending')->index(); $t->timestamp('rewarded_at')->nullable(); $t->string('note', 200)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('referral_ledger')) Schema::create('referral_ledger', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->string('kind', 12); $t->decimal('amount', 12, 2); $t->unsignedBigInteger('referral_id')->nullable(); $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('note', 200)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
