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
    return view('wa_confirm_settings', ['c' => fn($k, $d = '') => W::cfg($k, $d), 'tplStatus' => W::cfg('tpl_status', 'NOT SUBMITTED'), 'stats' => $stats, 'body' => W::BODY, 'ready' => W::ready()]);
  }
  public function save(Request $r) {
    $d = $r->validate(['template' => 'required|string|max:80|regex:/^[a-z0-9_]+$/', 'lang' => 'required|string|max:10', 'brand' => 'required|string|max:60', 'waba' => 'required|string|max:30']);
    foreach (['template', 'lang', 'brand', 'waba'] as $k) Procure::put('wa_conf_' . $k, $d[$k]);
    foreach (['enabled', 'on_ai', 'on_manual', 'retry'] as $k) Procure::put('wa_conf_' . $k, $r->boolean($k) ? '1' : '0');
    return back()->with('ok', 'Saved.');
  }
  public function submit() { [$ok, $m] = W::submitTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template: ' . $m); }
  public function sync() { [$ok, $m] = W::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template status: ' . $m); }
  /** Sends the real template with sample values to a test number. Creates no order record. */
  public function test(Request $r) {
    $to = WhatsApp::norm((string)$r->validate(['phone' => 'required|string|max:20'])['phone']);
    if (!preg_match('/^8801[3-9]\d{8}$/', $to)) return back()->with('err', 'Enter a valid mobile number.');
    [$ok, $id, $err] = W::call($to, ['টেস্ট গ্রাহক', 'DFB-TEST', '1,250', 'Cash on Delivery', W::brand()]);
    return back()->with($ok ? 'ok' : 'err', $ok ? 'Test sent to ' . $to . ' (message ' . $id . ')' : 'Test failed: ' . $err);
  }
  public function resend(Request $r, $id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    [$ok, $m] = W::resend((int)$id, (int)auth()->id(), trim((string)$r->input('reason', '')));
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'WhatsApp confirmation: ' . $m : 'WhatsApp confirmation failed: ' . $m);
  }
}
