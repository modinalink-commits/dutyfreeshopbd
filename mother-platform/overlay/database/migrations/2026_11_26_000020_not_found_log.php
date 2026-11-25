<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 404 watch (26 Nov 2026): every public address that answered "not found" is counted here so the owner can see broken links and the system can redirect the ones that clearly belong to a current page. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('not_found_log')) return;
    Schema::create('not_found_log', function (Blueprint $t) {
      $t->id(); $t->string('path', 400)->unique(); $t->unsignedInteger('hits')->default(1); $t->string('last_ref', 300)->nullable();
      $t->timestamp('first_seen')->nullable(); $t->timestamp('last_seen')->nullable()->index(); $t->boolean('fixed')->default(false);
    });
  }
  public function down(): void { Schema::dropIfExists('not_found_log'); }
};
