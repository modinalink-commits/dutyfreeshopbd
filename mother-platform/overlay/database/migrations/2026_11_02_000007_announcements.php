<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('announcements')) return;
    Schema::create('announcements', function (Blueprint $t) {
      $t->id(); $t->string('kind', 10)->default('offer'); // offer | notice
      $t->string('text', 200); $t->string('link', 300)->nullable(); $t->string('icon', 8)->nullable();
      $t->boolean('active')->default(true)->index(); $t->integer('sort')->default(0);
      $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
