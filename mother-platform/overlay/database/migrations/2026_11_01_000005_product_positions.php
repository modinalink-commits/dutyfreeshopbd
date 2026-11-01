<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('product_positions')) return;
    Schema::create('product_positions', function (Blueprint $t) {
      $t->unsignedBigInteger('pp_product'); $t->string('pp_scope', 40); $t->integer('pp_pos')->default(0); $t->timestamps();
      $t->primary(['pp_product', 'pp_scope']); $t->index(['pp_scope', 'pp_pos']);
    });
  }
  public function down(): void {}
};
