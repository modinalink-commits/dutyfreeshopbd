<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_threads') && !Schema::hasColumn('chat_threads', 'ai_paused')) Schema::table('chat_threads', function (Blueprint $t) { $t->boolean('ai_paused')->default(false); });
    if (Schema::hasTable('chat_messages') && !Schema::hasColumn('chat_messages', 'is_ai')) Schema::table('chat_messages', function (Blueprint $t) { $t->boolean('is_ai')->default(false); });
  }
  public function down(): void {}
};
