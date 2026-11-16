<?php
namespace App\Http\Controllers;

use App\Support\ProfitFloor;
use App\Support\WaRec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** WhatsApp call recordings: the answering browser uploads the audio in pieces; listening, download and delete are permission-checked and logged. */
class WaRecController extends Controller {
  private function rec(int $rid): object { $r = WaRec::ready() ? DB::table('wa_call_recordings')->find($rid) : null; abort_unless($r, 404); return $r; }
  public function start(Request $r, int $id) { [$ok, $m, $rid] = WaRec::start($id, (int)$r->user()->id, (string)$r->user()->name, (string)$r->input('mime', 'audio/webm')); return response()->json(['ok' => $ok, 'message' => $m, 'rec' => $rid, 'notice' => WaRec::notice()]); }
  public function chunk(Request $r, int $id) {
    $data = (string)$r->getContent(); if (strlen($data) > 4 * 1024 * 1024) return response()->json(['ok' => false, 'message' => 'Piece too large.'], 413);
    [$ok, $m] = WaRec::chunk($id, (int)$r->user()->id, max(0, (int)$r->query('seq', 0)), $data); return response()->json(['ok' => $ok, 'message' => $m]);
  }
  public function finish(Request $r, int $id) { $d = $r->validate(['duration' => 'nullable|integer|min:0|max:86400', 'error' => 'nullable|string|max:200']); [$ok, $m] = WaRec::finish($id, (int)$r->user()->id, $d['duration'] ?? null, $d['error'] ?? null); return response()->json(['ok' => $ok, 'status' => $m]); }
  public function notice(Request $r, int $id) { return response()->json(['ok' => WaRec::noticeGiven($id, (int)$r->user()->id)]); }
  /** The audio itself. Never a public link: every request is checked and written to the log. */
  public function audio(Request $r, int $rid) {
    $rec = $this->rec($rid); $dl = $r->boolean('dl');
    if ($dl ? !ProfitFloor::admin() : !WaRec::canListen($r->user(), $rec)) { WaRec::log($rid, 'denied', $dl ? 'download' : 'listen'); abort(403, 'You are not allowed to open this recording.'); }
    $f = in_array($rec->status, ['complete', 'incomplete'], true) ? WaRec::file($rec) : null; abort_unless($f, 404);
    WaRec::log($rid, $dl ? 'download' : 'listen');
    $h = ['Content-Type' => 'audio/webm', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex'];
    return $dl ? response()->download($f, 'whatsapp-call-' . $rec->call_id . '.webm', $h) : response()->file($f, $h);
  }
  public function list(Request $r) {
    $f = [];
    if ($r->filled('call')) { $c = DB::table('wa_calls')->find((int)$r->query('call')); if (!$c || !$c->from_wa) return response()->json(['rows' => []]); $f = ['wa' => $c->from_wa, 'not_call' => $r->boolean('others') ? (int)$c->id : 0]; }
    elseif ($r->filled('order')) $f = ['order_id' => (int)$r->query('order')];
    else return response()->json(['rows' => []]);
    return response()->json(['rows' => WaRec::rows($f, $r->user(), 20), 'admin' => ProfitFloor::admin()]);
  }
  public function page(Request $r) {
    $adm = ProfitFloor::admin(); if ($adm) WaRec::seen();
    return view('wa_recordings', ['rows' => WaRec::rows(['q' => $r->query('q'), 'status' => in_array($r->query('status'), ['complete', 'incomplete', 'failed', 'deleted', 'expired'], true) ? $r->query('status') : null], $r->user(), 100), 'admin' => $adm, 'ready' => WaRec::ready(),
      'use' => WaRec::usage(), 'cfg' => ['on' => WaRec::on(), 'days' => WaRec::days(), 'listen' => WaRec::listenRule(), 'notice' => WaRec::notice()], 'q' => (string)$r->query('q'), 'status' => (string)$r->query('status')]);
  }
  public function meta(Request $r, int $rid) {
    $rec = $this->rec($rid); abort_unless(WaRec::canListen($r->user(), $rec), 403);
    $d = $r->validate(['verify' => 'nullable|string|max:300', 'order' => 'nullable|string|max:40']); $msg = [];
    if ($r->has('verify')) { DB::table('wa_call_recordings')->where('id', $rid)->update(['verify_result' => $d['verify'] ?? null, 'verify_by' => mb_substr((string)$r->user()->name, 0, 120), 'updated_at' => now()]); WaRec::log($rid, 'verify', $d['verify'] ?? ''); $msg[] = 'Saved.'; }
    $ok = true; if ($r->has('order')) { [$ok, $m] = WaRec::linkOrder($rid, (string)($d['order'] ?? '')); $msg[] = $m; }
    return response()->json(['ok' => $ok, 'message' => implode(' ', $msg)]);
  }
  public function delete(Request $r, int $rid) { abort_unless(ProfitFloor::admin(), 403); return response()->json(['ok' => WaRec::remove($rid, (string)$r->user()->name)]); }
  public function logs(Request $r, int $rid) { abort_unless(ProfitFloor::admin(), 403); return response()->json(['rows' => DB::table('wa_call_recording_logs')->where('recording_id', $rid)->orderByDesc('id')->limit(60)->get(['user_name', 'action', 'detail', 'created_at'])->map(fn($l) => ['by' => $l->user_name, 'action' => $l->action, 'detail' => $l->detail, 'at' => \Carbon\Carbon::parse($l->created_at)->timezone('Asia/Dhaka')->format('d M h:i A')])]); }
  public function settings(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $d = $r->validate(['on' => 'required|boolean', 'days' => 'required|integer|min:1|max:3650', 'listen' => 'required|in:admin,handler,all', 'notice' => 'nullable|string|max:300']);
    $old = ['on' => WaRec::on(), 'days' => WaRec::days(), 'listen' => WaRec::listenRule()];
    WaRec::put('wa_rec_on', $d['on'] ? '1' : '0'); WaRec::put('wa_rec_listen', $d['listen']); WaRec::put('wa_rec_notice', trim((string)($d['notice'] ?? '')) ?: WaRec::NOTICE); if ((int)$d['days'] !== $old['days']) WaRec::setDays((int)$d['days']);
    try { DB::table('audit_logs')->insert(['user_id' => $r->user()->id, 'user_name' => $r->user()->name, 'action' => 'wa_rec.settings', 'subject' => 'WhatsApp call recording settings changed', 'meta' => json_encode(['previous' => $old, 'new' => ['on' => (bool)$d['on'], 'days' => (int)$d['days'], 'listen' => $d['listen']]]), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return response()->json(['ok' => true]);
  }
}
