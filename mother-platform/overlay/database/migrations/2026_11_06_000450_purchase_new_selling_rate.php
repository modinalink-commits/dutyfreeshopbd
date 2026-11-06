<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('purchase_items')) Schema::table('purchase_items', function (Blueprint $t) {
      foreach (['old_selling_rate', 'new_selling_rate', 'min_safe_snapshot', 'expected_margin_snapshot'] as $c) if (!Schema::hasColumn('purchase_items', $c)) $t->decimal($c, 14, 2)->nullable();
      if (!Schema::hasColumn('purchase_items', 'price_update_status')) $t->string('price_update_status', 20)->nullable();
      if (!Schema::hasColumn('purchase_items', 'margin_override_reason')) $t->string('margin_override_reason', 300)->nullable();
    });
    if (!Schema::hasTable('product_price_history')) Schema::create('product_price_history', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->decimal('old_rate', 14, 2)->nullable(); $t->decimal('new_rate', 14, 2); $t->decimal('purchase_rate', 14, 2)->nullable();
      $t->unsignedBigInteger('purchase_id')->nullable()->index(); $t->string('purchase_invoice', 40)->nullable(); $t->string('changed_by', 120)->nullable(); $t->string('source', 40)->default('Purchase Entry'); $t->string('note', 300)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
