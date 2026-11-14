<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner instruction 14 Nov 2026: close every old conversation in one go (silently, nothing is sent to any customer).
 *  Left open: chats where a customer wrote in the last 24 hours and still waits for a reply, open complaints, open Order Requests. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) return;
    try {
      $q = DB::table('chat_threads')->where('status', '<>', 'resolved')
        ->where(fn($w) => $w->where('awaiting_reply', false)->orWhereNull('awaiting_reply')->orWhere('last_at', '<', now()->subHours(24))->orWhereNull('last_at'));
      if (Schema::hasColumn('chat_threads', 'reported_at')) $q->where(fn($w) => $w->whereNull('reported_at')->orWhereNotNull('report_solved_at'));
      if (Schema::hasTable('chat_order_requests')) $q->whereNotIn('id', fn($s) => $s->from('chat_order_requests')->where('status', 'review')->select('thread_id'));
      $rows = $q->get(['id', 'awaiting_reply']); if ($rows->isEmpty()) return;
      $ids = $rows->pluck('id')->all(); $un = $rows->filter(fn($r) => !empty($r->awaiting_reply))->count();
      foreach (array_chunk($ids, 500) as $c) {
        DB::table('chat_threads')->whereIn('id', $c)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'needs_human'=>false, 'handoff_at'=>null, 'handoff_reason'=>null, 'ai_paused'=>false, 'updated_at'=>now()]);
        if (Schema::hasTable('chat_handoffs')) DB::table('chat_handoffs')->whereIn('thread_id', $c)->whereIn('status', ['open', 'accepted'])->update(['status'=>'done', 'updated_at'=>now()]);
      }
      DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System (owner instruction)', 'action'=>'chat.close_old', 'subject'=>count($ids).' old conversations closed in one go ('.$un.' of them had an unanswered customer message older than 24 hours). Nothing was sent to customers.', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
