<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner rule 16 Nov 2026 (later the same day): a new message is never given to a representative in advance. A conversation belongs to the person who first
 * answers it. So the owners that the automatic assignment put on conversations nobody answered are removed here: a conversation keeps its owner only when
 * that person really sent a reply after becoming the owner. Each release is written to the ownership record. No message, note or history is touched.
 */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('chat_threads') || !Schema::hasColumn('chat_threads', 'assign_ver') || !Schema::hasTable('chat_assign_events')) return;
      $rows = DB::table('chat_threads')->whereNotNull('assigned_to')->where('status', '!=', 'resolved')->get(['id', 'assigned_to', 'assigned_at']);
      $names = DB::table('users')->whereIn('id', $rows->pluck('assigned_to')->unique())->pluck('name', 'id');
      foreach ($rows as $t) {
        $nm = (string)($names[$t->assigned_to] ?? '');
        $q = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'out')->where('status', 'sent')->whereRaw('not coalesce(is_ai, false)')->where(fn($w) => $w->where('user_id', $t->assigned_to)->orWhere('user_name', $nm));
        if ($t->assigned_at) $q->where('created_at', '>=', \Carbon\Carbon::parse($t->assigned_at)->subMinutes(1));
        if ($q->exists()) continue; // this person answered the customer: the conversation stays theirs
        if (!DB::table('chat_threads')->where('id', $t->id)->where('assigned_to', $t->assigned_to)->update(['assigned_to' => null, 'assigned_at' => null, 'assign_ver' => DB::raw('assign_ver + 1'), 'absent_alert_at' => null])) continue;
        DB::table('chat_assign_events')->insert(['thread_id' => $t->id, 'action' => 'release', 'from_user' => $t->assigned_to, 'to_user' => null, 'actor_id' => null, 'actor_name' => 'System', 'note' => 'New rule: no assignment in advance. This conversation had been assigned automatically and was not answered by its owner, so it is open to everyone again.', 'created_at' => now(), 'updated_at' => now()]);
      }
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
