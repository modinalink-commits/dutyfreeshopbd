<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_messages') && !Schema::hasColumn('chat_messages', 'read_at')) Schema::table('chat_messages', function (Blueprint $t) { $t->timestamp('read_at')->nullable(); });
  }
  public function down(): void {}
};
