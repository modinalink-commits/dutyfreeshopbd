<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Black Friday prices kept apart from the normal price: what the product cost and sold for before, its Black Friday price, and when it was applied and put back. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('black_friday_items')) return;
    Schema::create('black_friday_items', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->unique();
      $t->decimal('cost', 12, 2); $t->decimal('price_before', 12, 2); $t->decimal('compare_before', 12, 2)->nullable(); $t->decimal('bf_price', 12, 2); $t->decimal('off_pct', 5, 1)->default(0);
      $t->string('status', 16)->default('applied')->index();   // applied | restored | kept (staff changed the price meanwhile: left as it is)
      $t->timestamp('applied_at')->nullable(); $t->timestamp('restored_at')->nullable(); $t->string('note', 200)->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('black_friday_items'); }
};
