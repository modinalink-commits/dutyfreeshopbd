<?php
namespace App\Http\Controllers;

use App\Support\ProfitFloor;
use App\Support\WaCalls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** WhatsApp Calls page (Call Center menu): answer WhatsApp calls in the browser, see today's calls, switch calling on per number (admin). */
class WaCallController extends Controller {
  public function page() {
    $rows = WaCalls::ready() ? DB::table('wa_calls')->orderByDesc('id')->limit(50)->get() : collect();
    $nums = []; foreach (WaCalls::numbers() as $n) $nums[] = ['phone_id' => $n['phone_id'], 'display' => $n['display'], 'name' => $n['name']];
    return view('wa_calls', ['rows' => $rows, 'nums' => $nums, 'admin' => ProfitFloor::admin(), 'ready' => WaCalls::ready()]);
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
