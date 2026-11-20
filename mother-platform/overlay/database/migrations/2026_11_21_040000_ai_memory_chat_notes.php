<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('ai_customer_memory') && !Schema::hasColumn('ai_customer_memory', 'chat_notes')) Schema::table('ai_customer_memory', function (Blueprint $t) { $t->text('chat_notes')->nullable(); $t->timestamp('notes_at')->nullable()->index(); });
  }
  public function down(): void {
    if (Schema::hasColumn('ai_customer_memory', 'chat_notes')) Schema::table('ai_customer_memory', function (Blueprint $t) { $t->dropColumn(['chat_notes', 'notes_at']); });
  }
};
