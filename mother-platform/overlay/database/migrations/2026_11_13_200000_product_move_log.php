<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('product_move_log')) return;
    Schema::create('product_move_log', function (Blueprint $t) {
      $t->id(); $t->string('batch', 40)->index(); $t->unsignedBigInteger('product_id')->index();
      $t->unsignedBigInteger('old_category_id')->nullable(); $t->string('old_sub')->nullable(); $t->string('old_sub_sub')->nullable(); $t->string('old_path_slug')->nullable(); $t->string('old_url', 500)->nullable();
      $t->unsignedBigInteger('new_category_id')->nullable(); $t->string('new_sub')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('product_move_log'); }
};
