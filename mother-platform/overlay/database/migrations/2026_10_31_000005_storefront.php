<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('products', function (Blueprint $t) { $t->bigInteger('ext_id')->nullable()->unique(); $t->string('image')->nullable(); $t->decimal('compare_price',12,2)->nullable(); $t->string('group_key')->nullable()->index(); $t->string('sub')->nullable(); $t->timestamp('synced_at')->nullable(); });
    Schema::table('categories', function (Blueprint $t) { $t->string('slug')->nullable()->unique(); $t->string('title')->nullable(); $t->text('meta_description')->nullable(); $t->text('intro')->nullable(); });
    Schema::table('orders', function (Blueprint $t) { $t->string('ip')->nullable(); });
  }
  public function down(): void {}
};
