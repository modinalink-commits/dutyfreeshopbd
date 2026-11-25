<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/** Admin search memory (25 Nov 2026): each staff member's own past searches, so typing again offers them back. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('staff_search_history')) return;
    Schema::create('staff_search_history', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('user_id'); $t->string('q', 80); $t->unsignedInteger('hits')->default(1); $t->timestamp('last_at')->useCurrent();
      $t->unique(['user_id', 'q']); $t->index(['user_id', 'last_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('staff_search_history'); }
};
