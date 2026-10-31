<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('deal_flags')) return;
    Schema::create('deal_flags', function (Blueprint $t) {
      $t->unsignedBigInteger('product_id')->primary();
      $t->string('mode', 10); // pin or exclude
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('deal_flags'); }
};
