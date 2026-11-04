<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** History of every change of a product's average purchase price (stock-in, reversal). Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('avg_cost_logs')) return;
    Schema::create('avg_cost_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('source', 40); $t->string('reference', 120)->nullable(); $t->integer('qty')->default(0);
      $t->decimal('unit_cost', 14, 4)->default(0); $t->decimal('old_avg', 14, 4)->default(0); $t->decimal('new_avg', 14, 4)->default(0);
      $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
