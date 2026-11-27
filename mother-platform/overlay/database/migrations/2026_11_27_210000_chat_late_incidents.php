<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner 27 Nov 2026 (21:02): one record per late reply of a conversation's owner. "Seen" (the staff member read the warning) and "resolved" (what was done) are kept apart. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_late_incidents')) return;
    Schema::create('chat_late_incidents', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('thread_id'); $t->unsignedBigInteger('user_id'); $t->string('user_name', 120)->nullable(); $t->string('customer', 160)->nullable();
      $t->timestamp('awaiting_since')->nullable(); $t->timestamp('alerted_at')->nullable(); $t->unsignedInteger('limit_min')->default(0); $t->unsignedInteger('wait_min')->default(0);
      $t->timestamp('seen_at')->nullable(); $t->timestamp('resolved_at')->nullable(); $t->string('action', 300)->nullable(); $t->timestamps();
      $t->unique(['thread_id', 'awaiting_since']); $t->index(['user_id', 'resolved_at']); $t->index('resolved_at');
    });
  }
  public function down(): void { Schema::dropIfExists('chat_late_incidents'); }
};
