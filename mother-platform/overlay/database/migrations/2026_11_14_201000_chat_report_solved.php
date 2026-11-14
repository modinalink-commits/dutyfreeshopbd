<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (!Schema::hasColumn('chat_threads', 'report_solved_at')) { $t->timestamp('report_solved_at')->nullable(); }
      if (!Schema::hasColumn('chat_threads', 'report_solved_by')) { $t->string('report_solved_by', 120)->nullable(); }
    });
  }
  public function down(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (Schema::hasColumn('chat_threads', 'report_solved_at')) $t->dropColumn('report_solved_at');
      if (Schema::hasColumn('chat_threads', 'report_solved_by')) $t->dropColumn('report_solved_by');
    });
  }
};
