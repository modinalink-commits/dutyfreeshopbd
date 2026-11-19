<?php
namespace App\Http\Controllers;

use App\Support\OrderCall as OC;
use App\Support\Procure;
use App\Support\Voice as V;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marketing -> Voice -> Order confirmation calls (AI call verification). Settings, readiness, recent calls and the staff exception list. */
class OrderCallController extends Controller
{
  public function index() {
    $s = []; foreach (['mode' => 'off', 'test_only' => '1', 'test_phones' => OC::TEST_DEFAULT, 'source' => 'Web Site', 'start' => '09:00', 'end' => '21:00', 'max_attempts' => '2', 'retry_gap' => '60', 'concurrency' => '2', 'hours_back' => '24'] as $k => $d) $s[$k] = OC::set($k, $d);
    $recent = OC::ready() ? DB::table('order_call_verifications as v')->leftJoin('orders as o', 'o.id', '=', 'v.order_id')->orderByDesc('v.id')->limit(60)->get(['v.*', 'o.invoice']) : collect();
    $cnt = OC::ready() ? DB::table('order_call_verifications')->where('test', false)->where('created_at', '>=', now()->subDays(7))->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all() : [];
    return view('order_calls', ['s' => $s, 'ready' => OC::readiness(), 'ok' => OC::ready(), 'recent' => $recent, 'cnt' => $cnt, 'exc' => OC::exceptions(), 'mode' => OC::mode()]);
  }
  public function save(Request $r) {
    $mode = in_array($r->input('mode'), ['off', 'assist', 'gate'], true) ? $r->input('mode') : 'off';
    $before = OC::mode();
    OC::put2('mode', $mode); OC::put2('test_only', $r->boolean('test_only') ? '1' : '0');
    OC::put2('test_phones', mb_substr(trim((string)$r->input('test_phones', OC::TEST_DEFAULT)), 0, 200));
    OC::put2('source', mb_substr(trim((string)$r->input('source')), 0, 40));
    foreach (['start', 'end'] as $k) { $v = (string)$r->input($k); OC::put2($k, preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : ($k === 'start' ? '09:00' : '21:00')); }
    OC::put2('max_attempts', (string)max(1, min(3, (int)$r->input('max_attempts', 2)))); OC::put2('retry_gap', (string)max(10, min(720, (int)$r->input('retry_gap', 60))));
    OC::put2('concurrency', (string)max(1, min(10, (int)$r->input('concurrency', 2)))); OC::put2('hours_back', (string)max(1, min(72, (int)$r->input('hours_back', 24))));
    Procure::audit('order_call.settings', 'Order confirmation call settings (mode ' . $before . ' -> ' . $mode . ')', null, $r->except(['_token']));
    return redirect('/admin/voice/order-calls')->with('ok', 'Order confirmation call settings saved');
  }
  /** Read-only: which outbound gateways the PBX knows right now (sofia status gateway). */
  public function gateways() {
    [$ok, $out] = V::esl('sofia status gateway', 20);
    return response()->json(['configured' => V::set('gateway'), 'ok' => $ok, 'output' => mb_substr((string)$out, 0, 3000)]);
  }
  public function test(Request $r) {
    [$st, $cause] = OC::testCall((string)$r->input('phone', OC::TEST_DEFAULT));
    return redirect('/admin/voice/order-calls')->with('ok', 'Test call result: ' . $st . ($cause ? ' (' . mb_substr($cause, 0, 160) . ')' : ''));
  }
}
