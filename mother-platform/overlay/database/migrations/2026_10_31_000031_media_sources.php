<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('media_sources')) Schema::create('media_sources', function (Blueprint $t) {
      $t->id(); $t->string('url_hash', 40)->unique(); $t->text('url'); $t->string('path')->nullable(); $t->unsignedSmallInteger('tries')->default(0); $t->string('error', 160)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
