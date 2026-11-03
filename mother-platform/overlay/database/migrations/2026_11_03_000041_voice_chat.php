<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Voice chat on the same Commerce Agent: voice fields on chat messages + one log row per voice turn. Text chat is untouched. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_messages')) Schema::table('chat_messages', function (Blueprint $t) {
      if (!Schema::hasColumn('chat_messages', 'input_type')) $t->string('input_type', 10)->default('text');
      if (!Schema::hasColumn('chat_messages', 'transcript')) $t->text('transcript')->nullable();
      if (!Schema::hasColumn('chat_messages', 'audio_path')) $t->string('audio_path', 200)->nullable();
      if (!Schema::hasColumn('chat_messages', 'detected_language')) $t->string('detected_language', 12)->nullable();
      if (!Schema::hasColumn('chat_messages', 'tts_audio_path')) $t->string('tts_audio_path', 200)->nullable();
      if (!Schema::hasColumn('chat_messages', 'intent')) $t->string('intent', 30)->nullable();
      if (!Schema::hasColumn('chat_messages', 'metadata')) $t->jsonb('metadata')->nullable();
    });
    if (!Schema::hasTable('voice_chat_logs')) Schema::create('voice_chat_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('thread_id')->nullable()->index(); $t->unsignedBigInteger('message_id')->nullable()->index();
      $t->string('kind', 6)->index();            // stt | tts
      $t->string('provider', 20)->nullable(); $t->boolean('ok')->default(true); $t->string('error', 200)->nullable();
      $t->unsignedInteger('ms')->default(0); $t->unsignedInteger('bytes')->default(0); $t->unsignedInteger('chars')->default(0);
      $t->boolean('cached')->default(false); $t->timestamp('created_at')->useCurrent()->index();
    });
  }
  public function down(): void {}
};
