<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (!Schema::hasColumn('chat_threads', 'reported_at')) { $t->timestamp('reported_at')->nullable()->index(); }
      if (!Schema::hasColumn('chat_threads', 'report_reason')) { $t->string('report_reason', 200)->nullable(); }
    });
  }
  public function down(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (Schema::hasColumn('chat_threads', 'reported_at')) $t->dropColumn('reported_at');
      if (Schema::hasColumn('chat_threads', 'report_reason')) $t->dropColumn('report_reason');
    });
  }
};
