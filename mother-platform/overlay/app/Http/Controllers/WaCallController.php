<?php
namespace App\Http\Controllers;

use App\Support\ProfitFloor;
use App\Support\WaCalls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** WhatsApp Calls page (Call Center menu): answer WhatsApp calls in the browser, see today's calls, switch calling on per number (admin). */
class WaCallController extends Controller {
  public function page(Request $r) {
    $f = ['q' => trim((string)$r->query('q')), 'res' => in_array($r->query('res'), ['ended', 'missed', 'rejected', 'recorded'], true) ? (string)$r->query('res') : '', 'sort' => in_array($r->query('sort'), ['old', 'long'], true) ? (string)$r->query('sort') : 'new'];
    $pg = null; $rows = collect();
    if (WaCalls::ready()) {
      $q = DB::table('wa_calls');
      if ($f['q'] !== '') { $s = $f['q']; $d = preg_replace('/\D/', '', $s); $q->where(function ($w) use ($s, $d) { $w->where('from_name', 'ilike', '%' . $s . '%')->orWhere('user_name', 'ilike', '%' . $s . '%'); if (strlen($d) >= 3) $w->orWhere('from_wa', 'like', '%' . ltrim($d, '0') . '%'); }); }
      if ($f['res'] === 'recorded') { if (\App\Support\WaRec::ready()) $q->whereIn('id', DB::table('wa_call_recordings')->whereIn('status', ['complete', 'incomplete'])->select('call_id')); else $q->whereRaw('1 = 0'); }
      elseif ($f['res'] !== '') $q->where('status', $f['res']);
      if ($f['sort'] === 'old') $q->orderBy('id'); elseif ($f['sort'] === 'long') $q->orderByRaw('duration desc nulls last')->orderByDesc('id'); else $q->orderByDesc('id');
      $pg = $q->paginate(50)->withQueryString(); $rows = collect($pg->items());
    }
    $nums = []; foreach (WaCalls::numbers() as $n) $nums[] = ['phone_id' => $n['phone_id'], 'display' => $n['display'], 'name' => $n['name']];
    $k = ['today' => 0, 'answered' => 0, 'missed' => 0, 'open' => WaCalls::missedOpen()]; $rep = collect();
    if (WaCalls::ready()) { $d0 = now('Asia/Dhaka')->startOfDay()->utc();
      $k['today'] = (int)DB::table('wa_calls')->where('created_at', '>=', $d0)->count(); $k['answered'] = (int)DB::table('wa_calls')->where('created_at', '>=', $d0)->where('status', 'ended')->count(); $k['missed'] = (int)DB::table('wa_calls')->where('created_at', '>=', $d0)->where('status', 'missed')->count();
      $rep = DB::table('wa_calls')->where('created_at', '>=', now()->subDays(7))->where('status', 'ended')->whereNotNull('user_name')->groupBy('user_name')->orderByDesc(DB::raw('count(*)'))->get(['user_name', DB::raw('count(*) as n'), DB::raw('coalesce(sum(duration),0) as secs')]); }
    // one recording per call, found by the call's own id (never by customer or time), so a caller with many calls can never get the wrong audio
    $recs = [];
    if (\App\Support\WaRec::ready() && $rows->count()) foreach (DB::table('wa_call_recordings')->whereIn('call_id', $rows->pluck('id'))->get() as $x) {
      $has = in_array($x->status, ['complete', 'incomplete'], true); $file = $has && \App\Support\WaRec::file($x) !== null;
      $recs[(int)$x->call_id] = ['id' => (int)$x->id, 'status' => (string)$x->status, 'duration' => $x->duration !== null ? (int)$x->duration : null, 'play' => $file && \App\Support\WaRec::canListen($r->user(), $x), 'nofile' => $has && !$file, 'why' => (string)$x->fail_reason];
    }
    return view('wa_calls', ['recs' => $recs, 'f' => $f, 'pg' => $pg, 'rows' => $rows, 'nums' => $nums, 'admin' => ProfitFloor::admin(), 'ready' => WaCalls::ready(), 'k' => $k, 'rep' => $rep, 'hasHandled' => \Illuminate\Support\Facades\Schema::hasColumn('wa_calls', 'handled_at')]);
  }
  /** Light poll used on every admin page: answers from cache when nothing rang in the last minute. */
  public function poll(Request $r) {
    if ($r->filled('id')) { $c = WaCalls::ready() ? DB::table('wa_calls')->find((int)$r->query('id')) : null; if ($c && $c->status === 'active' && (int)$c->user_id === (int)$r->user()->id) WaCalls::beat((int)$c->id); return response()->json(['state' => $c->status ?? 'ended', 'by' => $c->user_name ?? null]); } // the answering browser asks: is my call still on?
    if (!$r->boolean('full') && !Cache::has('wa.ring')) return response()->json(['ringing' => []]);
    $out = []; foreach (WaCalls::ringing() as $c) $out[] = ['id' => $c->id, 'from' => $c->from_wa ? '+' . $c->from_wa : 'WhatsApp user', 'name' => (string)$c->from_name, 'to' => WaCalls::numberLabel((string)$c->phone_id), 'age' => max(0, time() - strtotime((string)$c->created_at))];
    return response()->json(['ringing' => $out]);
  }
  public function claim(Request $r, int $id) { [$ok, $m, $sdp] = WaCalls::claim($id, (int)$r->user()->id, (string)$r->user()->name); return response()->json(['ok' => $ok, 'message' => $m, 'sdp' => $sdp]); }
  public function accept(Request $r, int $id) { $d = $r->validate(['sdp' => 'required|string|max:20000']); [$ok, $m] = WaCalls::accept($id, (int)$r->user()->id, $d['sdp']); return response()->json(['ok' => $ok, 'message' => $m]); }
  public function end(Request $r, int $id) { [$ok, $m] = WaCalls::end($id, (int)$r->user()->id, (string)$r->user()->name); return response()->json(['ok' => $ok, 'message' => $m]); }
  /** The separate call window: one call, customer details beside it. */
  public function popup(int $id) {
    $c = WaCalls::ready() ? DB::table('wa_calls')->find($id) : null; abort_unless($c, 404);
    return view('wa_call_popup', ['c' => $c, 'to' => WaCalls::numberLabel((string)$c->phone_id), 'local' => \App\Support\Chat::phone((string)$c->from_wa)]);
  }
  public function extras(int $id) { $c = WaCalls::ready() ? DB::table('wa_calls')->find($id) : null; abort_unless($c, 404); return response()->json(WaCalls::extras((string)$c->from_wa)); }
  public function handled(Request $r, int $id) { return response()->json(['ok' => WaCalls::handle($id, (int)$r->user()->id, (string)$r->user()->name)]); }
  public function release(Request $r, int $id) { return response()->json(['ok' => WaCalls::release($id, (int)$r->user()->id)]); }
  public function note(Request $r, int $id) { $d = $r->validate(['note' => 'required|string|max:240']); return response()->json(['ok' => WaCalls::saveNote($id, $d['note'], (string)$r->user()->name)]); }
  public function status(Request $r) { abort_unless(ProfitFloor::admin(), 403); return response()->json(WaCalls::settings((string)$r->query('phone_id'))); }
  public function setCalling(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $d = $r->validate(['phone_id' => 'required|string|max:40', 'on' => 'required|boolean']);
    [$ok, $m] = WaCalls::setCalling($d['phone_id'], (bool)$d['on']);
    try { DB::table('audit_logs')->insert(['user_id' => $r->user()->id, 'user_name' => $r->user()->name, 'action' => 'wa_call.settings', 'subject' => 'WhatsApp calling ' . ($d['on'] ? 'ON' : 'OFF') . ' for number ' . $d['phone_id'] . ': ' . $m, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return response()->json(['ok' => $ok, 'message' => $m]);
  }
}
