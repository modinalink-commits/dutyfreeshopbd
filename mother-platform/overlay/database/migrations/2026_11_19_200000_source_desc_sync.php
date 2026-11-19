<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner 19 Nov 2026: exact copy of the source shop's product description (with images) into our products. Tracks each product once and keeps the old text for rollback. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('source_desc_sync')) return;
    Schema::create('source_desc_sync', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('watch_source_id')->unique(); $t->unsignedBigInteger('product_id')->index();
      $t->string('status', 16)->index(); // done | nosource | failed
      $t->string('note', 200)->nullable(); $t->unsignedSmallInteger('imgs')->default(0); $t->unsignedInteger('chars')->default(0);
      $t->longText('old_description')->nullable(); $t->timestamp('updated_at')->nullable();
    });
  }
  public function down(): void { Schema::dropIfExists('source_desc_sync'); }
};
