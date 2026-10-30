<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('products', function (Blueprint $t) { $t->decimal('offer_price',12,2)->nullable(); $t->string('barcode')->nullable(); $t->string('size')->nullable(); });
    Schema::table('categories', function (Blueprint $t) { $t->string('image')->nullable(); $t->integer('position')->default(0); $t->boolean('active')->default(true); });
  }
  public function down(): void {}
};
