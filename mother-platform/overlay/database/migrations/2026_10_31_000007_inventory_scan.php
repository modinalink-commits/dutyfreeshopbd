<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('stores', function (Blueprint $t) {
      $t->string('platform')->nullable(); $t->string('scan_status')->default('never'); $t->timestamp('last_scan_at')->nullable();
      $t->integer('scan_total')->default(0); $t->integer('scan_published')->default(0); $t->integer('scan_draft')->default(0);
      $t->integer('scan_imported')->default(0); $t->integer('scan_failed')->default(0); $t->integer('scan_percent')->default(0);
      $t->text('scan_note')->nullable(); $t->text('scan_state')->nullable();
    });
    Schema::create('store_products', function (Blueprint $t) {
      $t->id(); $t->foreignId('store_id')->index(); $t->string('ext_key', 400); $t->string('name', 300); $t->string('sku', 120)->nullable()->index();
      $t->string('image', 600)->nullable(); $t->decimal('price', 12, 2)->nullable(); $t->integer('stock')->nullable(); $t->boolean('in_stock')->nullable(); $t->string('category', 200)->nullable();
      $t->string('url', 600)->nullable(); $t->boolean('published')->default(true)->index(); $t->timestamp('seen_at')->nullable(); $t->timestamps();
      $t->unique(['store_id', 'ext_key']);
    });
  }
  public function down(): void {}
};
