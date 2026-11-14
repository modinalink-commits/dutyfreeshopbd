<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Staff-only internal notes on a conversation. Never sent to the customer and never read by the AI. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_notes')) return;
    Schema::create('chat_notes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('thread_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->text('body'); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('chat_notes'); }
};
