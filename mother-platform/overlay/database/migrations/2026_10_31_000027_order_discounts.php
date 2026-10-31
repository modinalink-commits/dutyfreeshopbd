<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('customers', function (Blueprint $t) {
      if (!Schema::hasColumn('customers', 'discount_pct')) $t->decimal('discount_pct', 5, 2)->default(0);
      if (!Schema::hasColumn('customers', 'courier_pref')) $t->string('courier_pref', 60)->nullable();
      if (!Schema::hasColumn('customers', 'assigned_to')) $t->string('assigned_to', 80)->nullable();
    });
    if (!Schema::hasTable('order_discount_logs')) Schema::create('order_discount_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->string('kind', 12); $t->string('label', 200)->nullable();
      $t->decimal('prev_amount', 12, 2)->default(0); $t->decimal('new_amount', 12, 2)->default(0);
      $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 80)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
