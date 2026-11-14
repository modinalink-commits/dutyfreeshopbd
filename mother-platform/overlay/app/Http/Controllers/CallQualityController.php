<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/** Call Center accountability: the live Call Board, Call Review (score + feedback per call) and the listening log.
 *  Owner / admin only. Everything here reads call_logs; it never changes a call or an order. */
class CallQualityController extends Controller {
  private function guard(): void { abort_unless(\App\Support\ProfitFloor::admin(), 403, 'Only the owner or an admin can open this page.'); }
  private static function p11($v): string { return substr(preg_replace('/\D/', '', (string)$v), -11); }

  public function board() { $this->guard(); return view('call_board'); }

  /** Live numbers for the Call Board (polled every 15 seconds). */
  public function boardData() {
    $this->guard();
    $day = now('Asia/Dhaka')->startOfDay()->utc(); $out = ['agents'=>[], 'kpi'=>[], 'attention'=>[]];
    try {
      $exts = DB::table('sip_accounts')->whereNotNull('user_id')->whereNotNull('username')->pluck('username', 'user_id');
      $users = DB::table('users')->whereIn('id', $exts->keys())->where('active', true)->orderBy('name')->get(['id', 'name']);
      $busy = []; try { $busy = DB::table('agent_availability')->where('agent_status', 'busy')->pluck('status_updated_at', 'user_id')->all(); } catch (\Throwable $e) {}
      $stats = DB::table('call_logs')->where('started_at', '>=', $day)->whereNotNull('user_id')->groupBy('user_id')->selectRaw("user_id, count(*) n, count(*) filter (where status='answered') ans, count(*) filter (where outcome='confirmed') conf, coalesce(avg(duration) filter (where status='answered'),0) avgd, max(coalesce(ended_at, started_at)) last_at")->get()->keyBy('user_id');
      // a call that has started in the last 40 minutes and has not ended yet
      $live = DB::table('call_logs')->whereNull('ended_at')->where('started_at', '>=', now()->subMinutes(40))->whereNotNull('user_id')->orderBy('id')->get(['user_id', 'direction', 'started_at', 'connected_at', 'customer_name', 'phone'])->keyBy('user_id');
      $onCall = 0; $free = 0;
      foreach ($users as $u) {
        $s = $stats[$u->id] ?? null; $l = $live[$u->id] ?? null;
        if ($l) { $state = 'call'; $onCall++; $det = ($l->connected_at ? 'talking' : 'ringing').' · '.($l->direction === 'in' ? 'incoming' : 'outgoing'); $since = $l->connected_at ?: $l->started_at; }
        elseif (isset($busy[$u->id])) { $state = 'off'; $det = 'offline'; $since = $busy[$u->id]; }
        else { $state = 'free'; $free++; $det = $s && $s->last_at ? 'free' : 'no call yet today'; $since = $s->last_at ?? null; }
        $out['agents'][] = ['name'=>$u->name, 'ext'=>(string)$exts[$u->id], 'state'=>$state, 'detail'=>$det, 'since'=>$since ? \Carbon\Carbon::parse($since, 'UTC')->toIso8601String() : null,
          'calls'=>(int)($s->n ?? 0), 'answered'=>(int)($s->ans ?? 0), 'confirmed'=>(int)($s->conf ?? 0), 'avg'=>(int)round((float)($s->avgd ?? 0))];
      }
      $up = json_decode(app(CallCenterController::class)->upNext()->getContent(), true)['items'] ?? [];
      $cnt = ['new'=>0, 'callback'=>0, 'noanswer'=>0, 'missed'=>0]; foreach ($up as $i) $cnt[$i['type']] = ($cnt[$i['type']] ?? 0) + 1;
      $out['kpi'] = ['on_call'=>$onCall, 'free'=>$free, 'new_orders'=>$cnt['new'], 'missed'=>$cnt['missed'], 'callbacks'=>$cnt['callback'] + $cnt['noanswer'],
        'confirmed'=>(int)DB::table('call_logs')->where('started_at', '>=', $day)->where('outcome', 'confirmed')->count(), 'calls'=>(int)DB::table('call_logs')->where('started_at', '>=', $day)->count()];
      $out['attention'] = array_slice($up, 0, 8);
      $out['results'] = DB::table('call_logs')->where('started_at', '>=', $day)->whereNotNull('outcome')->groupBy('outcome')->selectRaw('outcome, count(*) n')->pluck('n', 'outcome');
    } catch (\Throwable $e) { report($e); $out['error'] = 'Could not load the board right now.'; }
    return response()->json($out)->header('Cache-Control', 'no-store');
  }

  /** Call Review: answered calls of the last 14 days, with the manager's score. */
  public function review(Request $r) {
    $this->guard();
    $f = in_array($r->query('f'), ['suggested', 'cancelled', 'long', 'reviewed', 'follow'], true) ? (string)$r->query('f') : 'suggested'; $staff = (int)$r->query('staff', 0);
    $q = DB::table('call_logs as c')->leftJoin('call_reviews as v', 'v.call_id', '=', 'c.id')->where('c.started_at', '>=', now()->subDays(14))->where('c.status', 'answered')->where('c.duration', '>=', 15)
      ->when($staff, fn($w) => $w->where('c.user_id', $staff))
      ->when($f === 'cancelled', fn($w) => $w->where('c.outcome', 'cancelled'))->when($f === 'long', fn($w) => $w->where('c.duration', '>=', 240))
      ->when($f === 'reviewed', fn($w) => $w->whereNotNull('v.id'))->when($f === 'follow', fn($w) => $w->where('v.follow_up', true))
      ->when($f === 'suggested', fn($w) => $w->whereNull('v.id')->orderByRaw("(c.outcome = 'cancelled') desc, (c.duration >= 240) desc, (c.recording_url is not null) desc"))
      ->orderByDesc('c.id')->select('c.id', 'c.customer_name', 'c.phone', 'c.user_id', 'c.user_name', 'c.started_at', 'c.duration', 'c.outcome', 'c.direction', 'c.recording_url', 'v.score', 'v.follow_up');
    $rows = $q->paginate(\App\Support\Pg::per(15))->withQueryString();
    $cur = null; $rv = null;
    if ($cid = (int)$r->query('c', 0)) { $cur = DB::table('call_logs')->find($cid); if ($cur) $rv = DB::table('call_reviews')->where('call_id', $cid)->first(); }
    $wk = now()->subDays(7);
    $kpi = ['recorded'=>(int)DB::table('call_logs')->where('started_at', '>=', $wk)->whereNotNull('recording_url')->count(), 'reviewed'=>(int)DB::table('call_reviews')->where('created_at', '>=', $wk)->count(),
      'avg'=>round((float)DB::table('call_reviews')->where('created_at', '>=', $wk)->avg('score'), 1), 'follow'=>(int)DB::table('call_reviews')->where('follow_up', true)->count()];
    $listens = DB::table('call_listens as l')->leftJoin('call_logs as c', 'c.id', '=', 'l.call_id')->orderByDesc('l.id')->limit(12)->get(['l.user_name', 'l.created_at', 'c.customer_name', 'c.phone', 'c.user_name as agent']);
    $agents = DB::table('call_logs')->where('started_at', '>=', now()->subDays(14))->whereNotNull('user_id')->groupBy('user_id')->selectRaw('user_id, max(user_name) as name')->orderBy('name')->get();
    return view('call_review', compact('rows', 'f', 'staff', 'cur', 'rv', 'kpi', 'listens', 'agents'));
  }

  public function saveReview(Request $r, $id) {
    $this->guard();
    $c = DB::table('call_logs')->find($id); abort_unless($c, 404);
    $d = $r->validate(['s_manners'=>'required|integer|min:1|max:5', 's_info'=>'required|integer|min:1|max:5', 's_save'=>'required|integer|min:1|max:5', 's_note'=>'required|integer|min:1|max:5', 'feedback'=>'nullable|string|max:1500']);
    $score = round(($d['s_manners'] + $d['s_info'] + $d['s_save'] + $d['s_note']) / 4, 2);
    $row = ['agent_id'=>$c->user_id, 'reviewer_id'=>Auth::id(), 'reviewer_name'=>Auth::user()->name ?? '', 's_manners'=>$d['s_manners'], 's_info'=>$d['s_info'], 's_save'=>$d['s_save'], 's_note'=>$d['s_note'], 'score'=>$score,
      'feedback'=>trim((string)($d['feedback'] ?? '')) ?: null, 'follow_up'=>$r->boolean('follow_up'), 'updated_at'=>now()];
    DB::table('call_reviews')->updateOrInsert(['call_id'=>(int)$id], $row + ['created_at'=>now()]);
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? '', 'action'=>'call.review', 'subject'=>'Call #'.$id.' of '.($c->user_name ?: 'staff').' scored '.$score.' of 5'.($r->boolean('follow_up') ? ' · needs follow-up' : ''), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Review saved: '.$score.' of 5');
  }

  /** Open a recording. Every play is written to the listening log first. */
  public function play(Request $r, $id) {
    $c = DB::table('call_logs')->find($id); abort_unless($c && !empty($c->recording_url), 404, 'This call has no recording.');
    // the audio player asks for the file several times (seeking); one listen per person per call per 10 minutes
    try { if (!DB::table('call_listens')->where('call_id', (int)$id)->where('user_id', Auth::id())->where('created_at', '>', now()->subMinutes(10))->exists()) DB::table('call_listens')->insert(['call_id'=>(int)$id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? '', 'created_at'=>now()]); } catch (\Throwable $e) {}
    return redirect()->away($c->recording_url);
  }
}
