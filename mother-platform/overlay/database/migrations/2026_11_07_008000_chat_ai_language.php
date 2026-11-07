<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// AI reply language per conversation: ai_lang = staff setting (null = Auto, else bangla|english|banglish), ai_detected = style the AI last detected.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (!Schema::hasColumn('chat_threads', 'ai_lang')) $t->string('ai_lang', 12)->nullable();
      if (!Schema::hasColumn('chat_threads', 'ai_detected')) $t->string('ai_detected', 12)->nullable();
    });
  }
  public function down(): void {}
};
