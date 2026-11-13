<?php
namespace App\Http\Controllers;
use App\Support\OrderWaConfirm as W;
use App\Support\Procure;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Settings, template submit/sync, test send and audited resend for the automatic order WhatsApp confirmation. */
class OrderWaConfirmController extends Controller {
  public function settings() {
    $stats = null;
    if (W::ready()) {
      $s = DB::table('order_whatsapp_confirmations')->where('seq', 1)->where('created_at', '>=', now()->subDays(7))->selectRaw("send_status, count(*) c")->groupBy('send_status')->pluck('c', 'send_status');
      $stats = ['total' => $s->sum(), 'by' => $s];
    }
    $failed = W::ready() ? DB::table('order_whatsapp_confirmations as f')->join('orders as o', 'o.id', '=', 'f.order_id')->where('f.send_status', 'FAILED')->where('f.created_at', '>=', now()->subDays(7))->orderByDesc('f.id')->limit(30)->get(['f.order_id', 'o.invoice', 'f.mobile', 'f.failure_reason', 'f.permanent', 'f.failed_at']) : collect();
    return view('wa_confirm_settings', ['c' => fn($k, $d = '') => W::cfg($k, $d), 'tplStatus' => W::cfg('tpl_status', 'NOT SUBMITTED'), 'stats' => $stats, 'body' => W::BODY, 'ready' => W::ready(), 'failed' => $failed, 'cn' => fn($k, $d = '') => \App\Support\OrderCancelNotice::set($k, $d), 'cnBody' => \App\Support\OrderCancelNotice::WA_BODY]);
  }
  public function save(Request $r) {
    $d = $r->validate(['template' => 'required|string|max:80|regex:/^[a-z0-9_]+$/', 'lang' => 'required|string|max:10', 'brand' => 'required|string|max:60', 'waba' => 'required|string|max:30']);
    foreach (['template', 'lang', 'brand', 'waba'] as $k) Procure::put('wa_conf_' . $k, $d[$k]);
    foreach (['enabled', 'on_ai', 'on_manual', 'retry'] as $k) Procure::put('wa_conf_' . $k, $r->boolean($k) ? '1' : '0');
    return back()->with('ok', 'Saved.');
  }
  public function submit() { [$ok, $m] = W::submitTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template: ' . $m); }
  public function sync() { [$ok, $m] = W::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template status: ' . $m); }
  public function cancelSave(Request $r) {
    $d = $r->validate(['template' => 'required|string|max:80|regex:/^[a-z0-9_]+$/']);
    Procure::put('cancel_notice_wa_tpl', $d['template']);
    foreach (['enabled', 'sms', 'wa'] as $k) Procure::put('cancel_notice_' . $k, $r->boolean($k) ? '1' : '0');
    return back()->with('ok', 'Cancel notice saved.');
  }
  /** Sends the real cancel SMS (and the WhatsApp template when approved) with sample values to one number. Creates no order. */
  public function cancelTest(Request $r) {
    $ph = \App\Support\Chat::phone((string)$r->validate(['phone' => 'required|string|max:20'])['phone']);
    if (!preg_match('/^01[3-9]\d{8}$/', (string)$ph)) return back()->with('err', 'Enter a valid mobile number.');
    [$m, $t] = \App\Support\OrderCancelNotice::testSend($ph);
    return back()->with($m ? 'ok' : 'err', $t);
  }
  public function cancelSubmit() { [$ok, $m] = \App\Support\OrderCancelNotice::submitTemplate(); return back()->with($ok ? 'ok' : 'err', 'Cancel template: ' . $m); }
  public function cancelSync() { [$ok, $m] = \App\Support\OrderCancelNotice::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Cancel template status: ' . $m); }
  /** Sends the real template with sample values to a test number. Creates no order record. */
  public function test(Request $r) {
    $to = WhatsApp::norm((string)$r->validate(['phone' => 'required|string|max:20'])['phone']);
    if (!preg_match('/^8801[3-9]\d{8}$/', $to)) return back()->with('err', 'Enter a valid mobile number.');
    [$ok, $id, $err] = W::call($to, ['টেস্ট গ্রাহক', 'DFB-TEST', '1,250', 'Cash on Delivery', W::brand()]);
    return back()->with($ok ? 'ok' : 'err', $ok ? 'Test sent to ' . $to . ' (message ' . $id . ')' : 'Test failed: ' . $err);
  }
  /** Courier / delivered WhatsApp updates: switches, template submit + status, test send. */
  public function updSave(Request $r) {
    foreach (['enabled', 'ship_on', 'dlv_on'] as $k) Procure::put('wa_upd_' . $k, $r->boolean($k) ? '1' : '0');
    return back()->with('ok', 'Order updates saved.');
  }
  public function updSubmit(Request $r) { [$ok, $m] = \App\Support\OrderWaUpdate::submitTemplate((string)$r->input('stage')); return back()->with($ok ? 'ok' : 'err', 'Template: ' . $m); }
  public function updSync(Request $r) { [$ok, $m] = \App\Support\OrderWaUpdate::syncTemplate((string)$r->input('stage')); return back()->with($ok ? 'ok' : 'err', 'Template status: ' . $m); }
  public function updTest(Request $r) {
    $st = (string)$r->input('stage'); abort_unless(isset(\App\Support\OrderWaUpdate::STAGES[$st]), 404);
    $to = WhatsApp::norm((string)$r->validate(['phone' => 'required|string|max:20'])['phone']);
    if (!preg_match('/^8801[3-9]\d{8}$/', $to)) return back()->with('err', 'Enter a valid mobile number.');
    [$ok, $id, $err] = \App\Support\OrderWaUpdate::call($st, $to, \App\Support\OrderWaUpdate::STAGES[$st][4]);
    return back()->with($ok ? 'ok' : 'err', $ok ? 'Test sent to ' . $to : 'Test failed: ' . $err);
  }
  public function resend(Request $r, $id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    [$ok, $m] = W::resend((int)$id, (int)auth()->id(), trim((string)$r->input('reason', '')));
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'WhatsApp confirmation: ' . $m : 'WhatsApp confirmation failed: ' . $m);
  }
}
