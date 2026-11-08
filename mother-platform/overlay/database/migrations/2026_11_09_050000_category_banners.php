<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Slide banners per category (owner 9 Nov 2026): each category page can show its own image slider. */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('category_banners')) Schema::create('category_banners', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('category_id')->index(); $t->string('image'); $t->string('mobile_image')->nullable(); $t->string('link')->nullable(); $t->string('alt')->nullable();
        $t->integer('position')->default(0); $t->boolean('active')->default(true); $t->timestamps();
      });
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
