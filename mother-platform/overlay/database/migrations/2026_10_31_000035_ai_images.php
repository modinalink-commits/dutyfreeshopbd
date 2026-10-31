<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('ai_images')) return;
    Schema::create('ai_images', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('source_hash', 40)->index(); $t->string('source_url', 900)->nullable();
      $t->string('original_image', 500)->nullable(); $t->string('processed_image', 500)->nullable(); $t->string('thumbnail_image', 500)->nullable();
      $t->string('ai_status', 20)->default('pending')->index(); $t->text('ai_prompt')->nullable(); $t->timestamp('processed_at')->nullable();
      $t->text('processing_error')->nullable(); $t->boolean('is_main')->default(false); $t->timestamps();
      $t->unique(['product_id', 'source_hash']);
    });
  }
  public function down(): void {}
};
