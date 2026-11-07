<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Unread now means "the customer is waiting for a staff reply". Opening a chat never clears it; only a successful staff reply (or an explicit Resolve) does.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) return;
    Schema::table('chat_threads', function (Blueprint $t) {
      if (!Schema::hasColumn('chat_threads', 'awaiting_reply')) $t->boolean('awaiting_reply')->default(false)->index();
      if (!Schema::hasColumn('chat_threads', 'last_customer_message_at')) $t->timestamp('last_customer_message_at')->nullable();
      if (!Schema::hasColumn('chat_threads', 'last_successful_agent_reply_at')) $t->timestamp('last_successful_agent_reply_at')->nullable();
      if (!Schema::hasColumn('chat_threads', 'last_opened_at')) $t->timestamp('last_opened_at')->nullable();
    });
    if (DB::getDriverName() !== 'pgsql') return;
    // A staff reply = an outgoing message written by a logged-in user that was not rejected. AI and system messages do not count.
    DB::statement("UPDATE chat_threads t SET
      last_customer_message_at = (SELECT max(m.created_at) FROM chat_messages m WHERE m.thread_id = t.id AND m.dir = 'in'),
      last_successful_agent_reply_at = (SELECT max(m.created_at) FROM chat_messages m WHERE m.thread_id = t.id AND m.dir = 'out' AND m.user_id IS NOT NULL AND coalesce(m.status, '') <> 'failed')");
    // Only conversations from the last 7 days start as "waiting"; older ones stay quiet so the badge is not flooded on day one.
    DB::statement("UPDATE chat_threads SET awaiting_reply = true WHERE status <> 'resolved' AND last_customer_message_at IS NOT NULL AND last_customer_message_at >= now() - interval '7 days'
      AND (last_successful_agent_reply_at IS NULL OR last_customer_message_at > last_successful_agent_reply_at)");
    DB::statement("UPDATE chat_threads t SET unread = (SELECT count(*) FROM chat_messages m WHERE m.thread_id = t.id AND m.dir = 'in' AND (t.last_successful_agent_reply_at IS NULL OR m.created_at > t.last_successful_agent_reply_at)) WHERE t.awaiting_reply");
    DB::statement("UPDATE chat_threads SET unread = 0 WHERE NOT awaiting_reply");
    try { \Illuminate\Support\Facades\Cache::forget('sb.chat.v1'); \Illuminate\Support\Facades\Cache::forget('sb.chat.v2'); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
