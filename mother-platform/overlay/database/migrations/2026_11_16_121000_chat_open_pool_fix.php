<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Correction to 2026_11_16_120000_chat_open_pool: that step also released conversations whose owner HAD answered the customer, when the answer was sent
 * before the morning step stamped "assigned_at". A representative who answered the customer in the last 12 hours gets the conversation back, unless the
 * customer has already waited past the release limit or somebody else took it meanwhile. Each restore is written to the ownership record.
 */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('chat_assign_events') || !Schema::hasColumn('chat_threads', 'assign_ver')) return;
      $lim = 20; try { $lim = \App\Support\ChatOwner::releaseMin(); } catch (\Throwable $e) {}
      $ev = DB::table('chat_assign_events')->where('action', 'release')->where('actor_name', 'System')->where('note', 'like', 'New rule: no assignment in advance.%')->where('created_at', '>=', now()->subHours(6))->whereNotNull('from_user')->get(['thread_id', 'from_user']);
      $names = DB::table('users')->whereIn('id', $ev->pluck('from_user')->unique())->where('active', true)->pluck('name', 'id');
      foreach ($ev as $e) {
        $nm = $names[$e->from_user] ?? null; if ($nm === null) continue;
        $t = DB::table('chat_threads')->where('id', $e->thread_id)->first(['id', 'assigned_to', 'status', 'awaiting_reply', 'awaiting_since']);
        if (!$t || $t->assigned_to !== null || $t->status === 'resolved') continue;
        if ($lim > 0 && $t->awaiting_reply && $t->awaiting_since && \Carbon\Carbon::parse($t->awaiting_since)->lte(now()->subMinutes($lim))) continue; // the customer is already waiting too long: stays open to everyone
        $answered = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'out')->where('status', 'sent')->whereRaw('not coalesce(is_ai, false)')->where('created_at', '>=', now()->subHours(12))
          ->where(fn($w) => $w->where('user_id', $e->from_user)->orWhere('user_name', $nm))->exists();
        if (!$answered) continue;
        if (!DB::table('chat_threads')->where('id', $t->id)->whereNull('assigned_to')->update(['assigned_to' => $e->from_user, 'assigned_at' => now(), 'assign_ver' => DB::raw('assign_ver + 1'), 'absent_alert_at' => null])) continue;
        DB::table('chat_assign_events')->insert(['thread_id' => $t->id, 'action' => 'assign', 'from_user' => null, 'to_user' => $e->from_user, 'actor_id' => null, 'actor_name' => 'System', 'note' => 'Restored: this representative had answered the customer within the last 12 hours, so the conversation stays with them.', 'created_at' => now(), 'updated_at' => now()]);
      }
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
