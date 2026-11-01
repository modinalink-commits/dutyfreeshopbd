<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('legacy_products')) return;
    Schema::create('legacy_products', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('old_id')->unique(); $t->string('name', 300)->nullable(); $t->string('slug', 300)->nullable(); $t->string('product_code', 100)->nullable(); $t->string('barcode', 100)->nullable();
      $t->unsignedInteger('category_id')->nullable(); $t->unsignedInteger('sub_category_id')->nullable(); $t->unsignedInteger('sub_sub_category_id')->nullable(); $t->unsignedInteger('brand_id')->nullable();
      $t->smallInteger('status')->nullable(); $t->decimal('price', 12, 2)->nullable(); $t->decimal('sale_price', 12, 2)->nullable(); $t->decimal('purchase_price', 12, 2)->nullable(); $t->integer('stock')->nullable();
      $t->string('thumbnail_img', 300)->nullable(); $t->integer('product_position')->nullable(); $t->string('old_created_at', 30)->nullable(); $t->string('old_updated_at', 30)->nullable();
      $t->jsonb('raw')->nullable(); $t->unsignedInteger('src_page')->nullable(); $t->string('action', 12)->nullable(); $t->unsignedBigInteger('new_product_id')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
