<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Hands a conversation to a person. Only staff who are Available (not Busy) and allowed to work orders/chat can receive it; otherwise it waits in the queue. */
class AgentHandoff {
  /** Staff who can take a new hand-off right now, least busy first. */
  public static function eligible(): array {
    $busy = \App\Http\Controllers\CallCenterController::availableAgents(); // ids of agents set to Busy
    $users = DB::table('users')->where('active', true)->get();
    $load = DB::table('chat_threads')->whereNotNull('assigned_to')->whereIn('status', ['new', 'open', 'waiting'])->select('assigned_to', DB::raw('count(*) n'))->groupBy('assigned_to')->pluck('n', 'assigned_to');
    $out = [];
    foreach ($users as $u) {
      if (in_array($u->id, $busy, true)) continue;
      try { if (!\App\Http\Middleware\ModulePermission::can($u, 'orders', 'edit')) continue; } catch (\Throwable $e) { continue; }
      $out[] = ['id'=>(int)$u->id, 'name'=>$u->name, 'load'=>(int)($load[$u->id] ?? 0)];
    }
    usort($out, fn($a, $b) => $a['load'] <=> $b['load'] ?: $a['id'] <=> $b['id']);
    return $out;
  }
  public static function create(array $ctx, array &$st, string $reason, string $summary, string $priority): array {
    $call = Biz::hotline();
    $tell = 'আমাদের টিম সদস্য শীঘ্রই আপনাকে রিপ্লাই দেবেন। জরুরি হলে কল/WhatsApp করুন: '.$call;
    if (!empty($ctx['sim'])) { $st['handed_off'] = true; return ['simulated'=>true, 'handed_over'=>true, 'tell_customer'=>$tell]; }
    $tid = (int)($ctx['thread_id'] ?? 0); if (!$tid) return ['error'=>'no conversation'];
    $st['handed_off'] = true;
    $agents = self::eligible(); $pick = $agents[0] ?? null;
    DB::table('chat_handoffs')->insert(['thread_id'=>$tid, 'assigned_to'=>$pick['id'] ?? null, 'reason'=>$reason, 'summary'=>$summary, 'priority'=>$priority, 'status'=>'open', 'created_at'=>now(), 'updated_at'=>now()]);
    $upd = ['needs_human'=>true, 'handoff_at'=>now(), 'handoff_reason'=>$reason, 'ai_summary'=>$summary, 'priority'=>$priority, 'ai_paused'=>true, 'status'=>'open', 'updated_at'=>now()];
    if ($pick && !ChatOwner::ready()) $upd['assigned_to'] = $pick['id'];
    DB::table('chat_threads')->where('id', $tid)->update($upd);
    if (ChatOwner::ready()) { // ownership changes only through ChatOwner (logged; never over a person who already owns the chat)
      if ($pick && ChatOwner::ownerId($tid) === null) ChatOwner::system($tid, (int)$pick['id'], 'auto_assign', 'AI hand-off: '.mb_substr($reason, 0, 200));
      ChatOwner::$handoffRun[$tid] = true; // the AI's closing line of this run may still be sent
    }
    AiMemory::event($tid, null, 'ai', 'handoff', null, ['reason'=>$reason, 'priority'=>$priority]);
    return ['handed_over'=>true, 'assigned_to_agent'=>(bool)$pick, 'tell_customer'=>$tell];
  }
}
