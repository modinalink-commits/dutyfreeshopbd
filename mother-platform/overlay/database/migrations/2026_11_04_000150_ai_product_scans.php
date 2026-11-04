<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** One row per "Create product from image" scan: who uploaded, the image, what the AI detected, what staff edited and the final saved values. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('ai_product_scans')) return;
    Schema::create('ai_product_scans', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->string('user_name', 120)->nullable();
      $t->string('image', 500); $t->string('model', 80)->nullable(); $t->string('status', 20)->default('scanned')->index();
      $t->text('detected')->nullable(); $t->text('edited')->nullable(); $t->text('final')->nullable(); $t->text('edits')->nullable();
      $t->unsignedBigInteger('product_id')->nullable()->index(); $t->string('error', 300)->nullable();
      $t->timestamp('scanned_at')->nullable(); $t->timestamp('saved_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
