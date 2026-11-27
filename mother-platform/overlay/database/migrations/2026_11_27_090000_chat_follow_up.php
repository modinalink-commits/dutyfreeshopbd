<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/**
 * Conversation follow-up (owner 27 Nov 2026): the representative who holds a conversation sets when it must be picked up again.
 * follow_up_at is the due time; once it has passed the inbox and the board show "ফলোআপ বাকি". All four columns are new and nullable: nothing existing is read or changed.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) return;
    $add = function (string $col, \Closure $fn) { if (!Schema::hasColumn('chat_threads', $col)) Schema::table('chat_threads', $fn); };
    $add('follow_up_at', fn(Blueprint $t) => $t->timestamp('follow_up_at')->nullable()->index('chat_threads_follow_up_at_ix'));
    $add('follow_up_note', fn(Blueprint $t) => $t->string('follow_up_note', 300)->nullable());
    $add('follow_up_by', fn(Blueprint $t) => $t->unsignedBigInteger('follow_up_by')->nullable());
    $add('follow_up_set_at', fn(Blueprint $t) => $t->timestamp('follow_up_set_at')->nullable());
  }
  public function down(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (Schema::hasColumn('chat_threads', 'follow_up_at')) { $t->dropIndex('chat_threads_follow_up_at_ix'); $t->dropColumn('follow_up_at'); }
      foreach (['follow_up_note', 'follow_up_by', 'follow_up_set_at'] as $c) if (Schema::hasColumn('chat_threads', $c)) $t->dropColumn($c);
    });
  }
};
