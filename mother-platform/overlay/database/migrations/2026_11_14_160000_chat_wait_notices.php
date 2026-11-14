<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** A notice shown to a staff member when a customer waited past the reply limit. Also the record behind "late replies" on the staff member's own My Day page. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_wait_notices')) return;
    Schema::create('chat_wait_notices', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('thread_id')->index(); $t->unsignedBigInteger('user_id'); $t->string('user_name', 120)->nullable();
      $t->string('customer', 160)->nullable(); $t->unsignedInteger('wait_min')->default(0); $t->boolean('assigned')->default(false); $t->timestamp('seen_at')->nullable(); $t->timestamps();
      $t->index(['user_id', 'seen_at']); $t->index(['user_id', 'created_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('chat_wait_notices'); }
};
