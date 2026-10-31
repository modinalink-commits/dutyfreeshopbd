<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('customers', function (Blueprint $t) { $t->text('address')->nullable(); $t->text('note')->nullable(); $t->string('username')->nullable(); $t->integer('legacy_orders')->default(0); $t->decimal('legacy_spent',14,2)->default(0); });
  }
  public function down(): void {}
};
