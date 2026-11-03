<?php
namespace App\Http\Controllers;

use App\Support\FreeCall as F;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Free Call: public (customer browser) endpoints + admin (staff) endpoints. */
class FreeCallController extends Controller
{
  // =========================================================== customer (public, CSRF-free, no login needed)
  private function mine(Request $r): ?object {
    $u = (string)($r->input('c') ?? $r->query('c')); $s = (string)($r->input('s') ?? $r->query('s')); if ($u === '' || $s === '') return null;
    $c = DB::table('customer_calls')->where('call_uuid', $u)->first(); return $c && hash_equals((string)$c->customer_session_id, $s) ? $c : null;
  }
  /** Is the button shown / usable? */
  public function config() {
    [$vis, $open, $msg] = F::widget(Auth::check());
    return response()->json(['visible' => $vis, 'open' => $open, 'msg' => $msg, 'recording' => false])->header('Cache-Control', 'no-store');
  }
  public function request(Request $r) {
    [$c, $err] = F::request($r->only(['name', 'phone', 'url', 'title', 'product_id', 'invoice', 'cart', 'session']) + ['staff' => Auth::check(), 'ip' => (string)($r->header('CF-Connecting-IP') ?: $r->ip()), 'ua' => (string)$r->userAgent()]);
    if (!$c) return response()->json(['ok' => false, 'error' => $err], 422);
    return response()->json(['ok' => true, 'c' => $c->call_uuid, 's' => $c->customer_session_id, 'status' => $c->status, 'pos' => F::position($c)]);
  }
  public function state(Request $r) {
    $c = $this->mine($r); if (!$c) return response()->json(['ok' => false, 'gone' => true], 404);
    if (in_array($c->status, F::LIVE, true)) { DB::table('customer_calls')->where('id', $c->id)->update(['cust_seen_at' => now()]); F::sweep(); $c = DB::table('customer_calls')->find($c->id); }
    $live = in_array($c->status, F::LIVE, true);
    $o = ['ok' => true, 'status' => $c->status, 'label' => F::STATUS[$c->status] ?? $c->status, 'live' => $live, 'pos' => in_array($c->status, ['waiting', 'ringing'], true) ? F::position($c) : 0, 'wait' => max(0, ($c->ended_at ? strtotime($c->ended_at . ' UTC') : time()) - strtotime($c->requested_at . ' UTC')),
      'agent' => in_array($c->status, ['accepted', 'connected'], true) ? F::agentName($c->assigned_staff_id) : null, 'reason' => $c->disconnect_reason, 'duration' => $c->duration_seconds];
    if (in_array($c->status, ['accepted', 'connected'], true)) { $o['ice'] = F::ice($c->call_uuid); $o['signals'] = F::signals($c->id, 'customer', (int)$r->query('after', 0)); }
    return response()->json($o)->header('Cache-Control', 'no-store');
  }
  public function signal(Request $r) {
    $c = $this->mine($r); if (!$c || !in_array($c->status, ['accepted', 'connected'], true)) return response()->json(['ok' => false], 404);
    F::signal($c->id, 'agent', (string)$r->input('type'), (string)$r->input('payload')); return response()->json(['ok' => true]);
  }
  public function connected(Request $r) { $c = $this->mine($r); if (!$c) return response()->json(['ok' => false], 404); F::connected($c->id); return response()->json(['ok' => true]); }
  public function cancel(Request $r) {
    $c = $this->mine($r); if (!$c) return response()->json(['ok' => false], 404);
    if (in_array($c->status, ['waiting', 'ringing'], true)) F::finish($c->id, 'cancelled', 'customer_cancel'); elseif (in_array($c->status, ['accepted', 'connected'], true)) F::finish($c->id, $c->status === 'connected' ? 'completed' : 'cancelled', 'customer_hangup');
    return response()->json(['ok' => true]);
  }
  public function event(Request $r) { $c = $this->mine($r); if ($c && in_array((string)$r->input('e'), ['muted', 'unmuted'], true)) F::ev($c->id, (string)$r->input('e')); return response()->json(['ok' => true]); }

  // =========================================================== admin (staff)
  private function me(): int { return (int)Auth::id(); }
  /** Polled every ~2s by every admin page: presence, incoming ring, active call + signalling. */
  public function poll(Request $r) {
    if (!F::ready()) return response()->json(['ok' => false]);
    $me = $this->me(); $a = F::touchAgent($me); F::sweep(); $a = DB::table('free_call_agents')->where('staff_id', $me)->first();
    $o = ['ok' => true, 'on' => F::on(), 'status' => $a->voice_status, 'eff' => F::effective($a), 'waiting' => DB::table('customer_calls')->whereIn('status', ['waiting', 'ringing'])->count(), 'ring' => null, 'active' => null, 'signals' => [], 'last' => null];
    $rc = DB::table('customer_calls')->where('status', 'ringing')->where('ring_staff_id', $me)->first(); if ($rc) { $o['ring'] = F::ctx($rc); $o['ring']['ring_left'] = max(0, strtotime($rc->ring_expires_at . ' UTC') - time()); }
    $ac = $a->active_call_id ? DB::table('customer_calls')->find($a->active_call_id) : null;
    if ($ac && in_array($ac->status, ['accepted', 'connected'], true) && (int)$ac->assigned_staff_id === $me) {
      if ((int)$r->query('own', 0) === (int)$ac->id) DB::table('customer_calls')->where('id', $ac->id)->update(['agent_seen_at' => now()]); // only the window that holds the audio keeps the call alive
      $o['active'] = F::ctx($ac) + ['answered_ts' => strtotime(($ac->connected_at ?: $ac->answered_at) . ' UTC'), 'ice' => F::ice($ac->call_uuid)]; $o['signals'] = F::signals($ac->id, 'agent', (int)$r->query('after', 0));
    } else {
      $lc = DB::table('customer_calls')->where('assigned_staff_id', $me)->whereNotNull('ended_at')->where('ended_at', '>=', now()->subMinutes(5))->orderByDesc('ended_at')->first();
      if ($lc && !DB::table('customer_call_notes')->where('call_id', $lc->id)->exists()) $o['last'] = ['id' => (int)$lc->id, 'mobile' => $lc->mobile, 'name' => $lc->guest_name ?: $lc->mobile, 'duration' => (int)$lc->duration_seconds, 'status' => $lc->status];
    }
    return response()->json($o)->header('Cache-Control', 'no-store');
  }
  public function status(Request $r) { F::setStatus($this->me(), (string)$r->input('status')); return response()->json(['ok' => true]); }
  public function accept(Request $r, $id) { $e = F::accept((int)$id, $this->me()); return response()->json(['ok' => !$e, 'error' => $e], $e ? 409 : 200); }
  public function decline(Request $r, $id) { F::decline((int)$id, $this->me()); return response()->json(['ok' => true]); }
  public function hangup(Request $r, $id) {
    $c = DB::table('customer_calls')->find($id); if ($c && (int)$c->assigned_staff_id === $this->me() && in_array($c->status, F::LIVE, true)) F::finish($c->id, $c->status === 'connected' ? 'completed' : 'failed', 'agent_hangup');
    return response()->json(['ok' => true]);
  }
  public function aconnected(Request $r, $id) { $c = DB::table('customer_calls')->find($id); if ($c && (int)$c->assigned_staff_id === $this->me()) F::connected($c->id); return response()->json(['ok' => true]); }
  public function asignal(Request $r, $id) {
    $c = DB::table('customer_calls')->find($id); if (!$c || (int)$c->assigned_staff_id !== $this->me() || !in_array($c->status, ['accepted', 'connected'], true)) return response()->json(['ok' => false], 404);
    F::signal($c->id, 'customer', (string)$r->input('type'), (string)$r->input('payload')); return response()->json(['ok' => true]);
  }
  public function aevent(Request $r, $id) { $c = DB::table('customer_calls')->find($id); if ($c && (int)$c->assigned_staff_id === $this->me() && in_array((string)$r->input('e'), ['muted', 'unmuted', 'hold', 'resume'], true)) F::ev($c->id, (string)$r->input('e'), $this->me()); return response()->json(['ok' => true]); }
  public function note(Request $r, $id) {
    $c = DB::table('customer_calls')->find($id); abort_unless($c, 404);
    $d = $r->validate(['note' => 'nullable|string|max:2000', 'outcome' => 'nullable|in:' . implode(',', array_keys(F::OUTCOMES))]);
    DB::table('customer_call_notes')->insert(['call_id' => $c->id, 'customer_id' => $c->customer_id, 'order_id' => $c->order_id, 'staff_id' => $this->me(), 'note' => $d['note'] ?? null, 'outcome' => $d['outcome'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
    return $r->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Note saved');
  }

  public function index() {
    F::sweep(true);
    $agents = DB::table('free_call_agents as a')->join('users as u', 'u.id', '=', 'a.staff_id')->orderBy('u.name')->get(['a.*', 'u.name'])->map(function ($a) { $a->eff = F::effective($a); return $a; });
    $day = now('Asia/Dhaka')->startOfDay()->utc();
    $today = DB::table('customer_calls')->where('requested_at', '>=', $day);
    $k = ['avail' => $agents->where('eff', 'available')->count(), 'busy' => $agents->where('eff', 'busy')->count(), 'waiting' => DB::table('customer_calls')->whereIn('status', ['waiting', 'ringing'])->count(), 'active' => DB::table('customer_calls')->whereIn('status', ['accepted', 'connected'])->count(),
      'today' => (clone $today)->count(), 'missed' => (clone $today)->whereIn('status', ['missed', 'no_agent'])->count(), 'avgwait' => (int)round((float)(clone $today)->whereNotNull('answered_at')->avg('waiting_seconds')), 'avgdur' => (int)round((float)(clone $today)->where('status', 'completed')->avg('duration_seconds'))];
    $queue = DB::table('customer_calls')->whereIn('status', ['waiting', 'ringing'])->orderBy('requested_at')->get()->map(fn($c) => F::ctx($c));
    $active = DB::table('customer_calls as c')->leftJoin('users as u', 'u.id', '=', 'c.assigned_staff_id')->whereIn('c.status', ['accepted', 'connected'])->get(['c.*', 'u.name as agent']);
    $s = ['on' => F::on(), 'ring_timeout' => F::ringTimeout(), 'max_queue' => F::maxQueue(), 'hours_on' => F::set('hours_on', '0'), 'hours_from' => F::set('hours_from', '09:00'), 'hours_to' => F::set('hours_to', '22:00'), 'heartbeat' => F::set('heartbeat', '20'),
      'stun' => F::set('stun', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'), 'turn_urls' => F::set('turn_urls'), 'has_secret' => (bool)Sms::secret('fc_turn_secret'), 'has_user' => (bool)Sms::secret('fc_turn_user'), 'turn_ready' => F::turnReady()];
    return view('free_calls', compact('agents', 'k', 'queue', 'active', 's'));
  }
  public function history(Request $r) {
    $q = DB::table('customer_calls as c')->leftJoin('users as u', 'u.id', '=', 'c.assigned_staff_id')->leftJoin('orders as o', 'o.id', '=', 'c.order_id')->orderByDesc('c.id');
    if ($r->filled('status')) $q->where('c.status', $r->query('status')); if ($r->filled('q')) { $t = '%' . trim((string)$r->query('q')) . '%'; $q->where(fn($w) => $w->where('c.mobile', 'like', $t)->orWhere('c.guest_name', 'like', $t)->orWhere('c.ip_address', 'like', $t)); }
    $rows = $q->limit(300)->get(['c.*', 'u.name as agent', 'o.invoice']);
    $notes = DB::table('customer_call_notes')->whereIn('call_id', $rows->pluck('id'))->orderBy('id')->get()->groupBy('call_id');
    return view('free_calls_history', ['rows' => $rows, 'notes' => $notes, 'status' => $r->query('status'), 'q' => $r->query('q')]);
  }
  public function toggle(Request $r) {
    $on = !F::on(); F::put('on', $on ? '1' : '0'); \App\Support\Procure::audit('freecall.toggle', 'Free Call ' . ($on ? 'ON' : 'OFF'), null, []);
    return redirect('/admin/free-calls')->with('ok', 'Free Call service is now ' . ($on ? 'ON' : 'OFF'));
  }
  public function settings(Request $r) {
    F::put('on', $r->boolean('on') ? '1' : '0'); F::put('ring_timeout', (string)max(5, min(60, (int)$r->input('ring_timeout', 15)))); F::put('max_queue', (string)max(1, min(200, (int)$r->input('max_queue', 20))));
    F::put('hours_on', $r->boolean('hours_on') ? '1' : '0'); foreach (['hours_from', 'hours_to'] as $k) if (preg_match('/^\d\d:\d\d$/', (string)$r->input($k))) F::put($k, $r->input($k));
    F::put('heartbeat', (string)max(8, min(120, (int)$r->input('heartbeat', 20)))); F::put('stun', mb_substr(trim((string)$r->input('stun')), 0, 300)); F::put('turn_urls', mb_substr(trim((string)$r->input('turn_urls')), 0, 400));
    foreach (['turn_secret' => 'fc_turn_secret', 'turn_user' => 'fc_turn_user', 'turn_cred' => 'fc_turn_cred'] as $f => $k) { $v = trim((string)$r->input($f)); if ($v !== '') Sms::setSecret($k, $v); if ($r->boolean('clear_' . $f)) Sms::setSecret($k, null); }
    \App\Support\Procure::audit('freecall.settings', 'Free Call settings', null, $r->except(['_token', 'turn_secret', 'turn_user', 'turn_cred']));
    return redirect('/admin/free-calls')->with('ok', 'Saved');
  }
}
