<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Conversation ownership (owner 16 Nov 2026): one representative answers one customer.
 * chat_threads.assigned_to is the owner. assign_ver counts every change of owner. send_lock_* is a short lease held while one message is being sent,
 * so the owner cannot change in the middle of a send and two senders never send at the same moment. chat_assign_events is the record of who took, moved or released a conversation.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads') || !Schema::hasTable('chat_messages')) return;
    $add = function (string $tbl, string $col, \Closure $fn) { if (!Schema::hasColumn($tbl, $col)) Schema::table($tbl, $fn); };
    $add('chat_threads', 'assigned_at', fn(Blueprint $t) => $t->timestamp('assigned_at')->nullable());
    $add('chat_threads', 'assign_ver', fn(Blueprint $t) => $t->unsignedInteger('assign_ver')->default(0));
    $add('chat_threads', 'send_lock_until', fn(Blueprint $t) => $t->timestamp('send_lock_until')->nullable());
    $add('chat_threads', 'send_lock_tok', fn(Blueprint $t) => $t->string('send_lock_tok', 40)->nullable());
    $add('chat_threads', 'awaiting_since', fn(Blueprint $t) => $t->timestamp('awaiting_since')->nullable());
    $add('chat_threads', 'absent_alert_at', fn(Blueprint $t) => $t->timestamp('absent_alert_at')->nullable());
    // one staff send per client token, kept in the database (a retry after a lost connection never sends twice)
    if (!Schema::hasColumn('chat_messages', 'client_token')) Schema::table('chat_messages', function (Blueprint $t) { $t->string('client_token', 80)->nullable(); $t->unique(['thread_id', 'client_token'], 'chat_messages_thread_token_uq'); });
    // one AI answer per customer message
    $add('chat_messages', 'ai_claim_at', fn(Blueprint $t) => $t->timestamp('ai_claim_at')->nullable());
    if (!Schema::hasTable('chat_assign_events')) Schema::create('chat_assign_events', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('thread_id')->index(); $t->string('action', 30);
      $t->unsignedBigInteger('from_user')->nullable(); $t->unsignedBigInteger('to_user')->nullable();
      $t->unsignedBigInteger('actor_id')->nullable(); $t->string('actor_name', 120)->nullable(); $t->text('note')->nullable(); $t->timestamps();
      $t->index(['to_user', 'created_at']);
    });
    // Until today "assigned_to" only meant "the last person who replied" and was never cleared, while the AI kept answering those chats.
    // Keep an owner only where a person is really working the chat now: open, and handed to a person or answered by a person in the last 12 hours.
    try {
      $q = DB::table('chat_threads')->whereNotNull('assigned_to')->where(function ($w) {
        $w->where('status', 'resolved')->orWhere(function ($x) {
          if (Schema::hasColumn('chat_threads', 'needs_human')) $x->where(fn($n) => $n->whereNull('needs_human')->orWhere('needs_human', false));
          if (Schema::hasColumn('chat_threads', 'handoff_at')) $x->whereNull('handoff_at');
          if (Schema::hasColumn('chat_threads', 'last_successful_agent_reply_at')) $x->where(fn($n) => $n->whereNull('last_successful_agent_reply_at')->orWhere('last_successful_agent_reply_at', '<', now()->subHours(12)));
        });
      });
      $q->update(['assigned_to' => null]);
      DB::table('chat_threads')->whereNotNull('assigned_to')->whereNull('assigned_at')->update(['assigned_at' => now()]);
      DB::table('chat_threads')->where('awaiting_reply', true)->whereNull('awaiting_since')->update(['awaiting_since' => DB::raw('coalesce(last_customer_message_at, last_at, updated_at)')]);
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
