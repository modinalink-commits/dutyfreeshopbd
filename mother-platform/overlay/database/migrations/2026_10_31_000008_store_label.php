<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void { Schema::table('store_products', function (Blueprint $t) { $t->string('store_label', 200)->nullable(); }); }
  public function down(): void {}
};
