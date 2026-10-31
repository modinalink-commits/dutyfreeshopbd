<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('media_images')) Schema::create('media_images', function (Blueprint $t) {
      $t->id(); $t->string('path')->unique(); $t->unsignedBigInteger('orig_bytes')->default(0); $t->unsignedBigInteger('opt_bytes')->default(0);
      $t->unsignedInteger('width')->nullable(); $t->unsignedInteger('height')->nullable(); $t->string('format', 8)->nullable();
      $t->string('status', 12)->default('optimized'); $t->string('error', 160)->nullable(); $t->timestamp('optimized_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
