<?php
namespace App\Http\Controllers;
use App\Support\ShurjoPay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
class ShurjoPayController extends Controller {
  private function audit(Request $r, string $a, string $s): void { try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$s,'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {} }
  // ---------- admin ----------
  public function settings() {
    $recent = DB::table('orders')->whereNotNull('sp_order_id')->orderByDesc('id')->limit(15)->get(['invoice', 'total', 'pay_status', 'sp_method', 'pay_txn', 'created_at']);
    return view('shurjopay_settings', ['s' => ['enabled' => ShurjoPay::get('enabled') === '1', 'mode' => ShurjoPay::get('mode', 'sandbox'), 'username' => ShurjoPay::get('username', ''), 'prefix' => ShurjoPay::get('prefix', ''), 'has_pw' => (bool)ShurjoPay::get('password')], 'recent' => $recent, 'ready' => ShurjoPay::ready(), 'base' => ShurjoPay::base()]);
  }
  public function save(Request $r) {
    $d = $r->validate(['mode' => 'required|in:sandbox,live', 'username' => 'nullable|string|max:120', 'prefix' => 'nullable|string|max:20', 'password' => 'nullable|string|max:200']);
    ShurjoPay::put('mode', $d['mode']); ShurjoPay::put('username', trim((string)($d['username'] ?? ''))); ShurjoPay::put('prefix', trim((string)($d['prefix'] ?? '')));
    if (!empty($d['password'])) ShurjoPay::put('password', Crypt::encryptString($d['password']));
    ShurjoPay::put('enabled', $r->boolean('enabled') ? '1' : '0');
    $this->audit($r, 'shurjopay.settings', $d['mode'].($r->boolean('enabled') ? ' on' : ' off'));
    return redirect('/admin/shurjopay')->with('ok', 'ShurjoPay settings saved');
  }
  /** Authentication check only. No payment is created. */
  public function test(Request $r) {
    if (!ShurjoPay::ready()) return back()->with('err', 'Fill username, password and prefix first.');
    $t = ShurjoPay::token($err);
    return back()->with($t ? 'ok' : 'err', $t ? 'Connection OK ('.ShurjoPay::base().'). Token received.' : 'Connection failed: '.$err);
  }
  // ---------- storefront ----------
  public function start(Request $r, $invoice) {
    $o = DB::table('orders')->where('invoice', $invoice)->first(); abort_unless($o, 404);
    $done = fn() => URL::temporarySignedRoute('order.done', now()->addDays(3), ['invoice' => $invoice]);
    if ($o->pay_status === 'Verified') return redirect($done());
    if (!ShurjoPay::enabled()) return redirect($done());
    [$url, $sp] = ShurjoPay::create($o, url('/pay/shurjopay/return'), url('/pay/shurjopay/cancel'), $r->ip());
    if (!$url) { DB::table('orders')->where('id', $o->id)->update(['pay_status' => 'Failed', 'updated_at' => now()]); $this->log($o, 'ShurjoPay could not start: '.$sp); return redirect($done()); }
    DB::table('orders')->where('id', $o->id)->update(['sp_order_id' => $sp, 'pay_status' => 'Awaiting Payment', 'updated_at' => now()]);
    return redirect()->away($url);
  }
  public function back(Request $r) {
    $sp = trim((string)$r->query('order_id')); abort_unless($sp !== '', 404);
    $o = DB::table('orders')->where('sp_order_id', $sp)->first(); abort_unless($o, 404);
    $done = URL::temporarySignedRoute('order.done', now()->addDays(3), ['invoice' => $o->invoice]);
    if ($o->pay_status === 'Verified') return redirect($done);
    $v = ShurjoPay::verify($sp);
    $code = (string)($v['sp_code'] ?? '');
    if ($code === '1000' && abs((float)($v['amount'] ?? $v['payable_amount'] ?? 0) - (float)$o->total) < 1.0) {
      $won = DB::table('orders')->where('id', $o->id)->where(fn($q) => $q->whereNull('pay_status')->orWhere('pay_status', '<>', 'Verified'))->update(['pay_status' => 'Verified', 'pay_txn' => (string)($v['bank_trx_id'] ?? $v['invoice_no'] ?? $sp), 'sp_method' => mb_substr((string)($v['method'] ?? ''), 0, 60), 'pay_by' => 'ShurjoPay', 'pay_at' => now(), 'updated_at' => now()]);
      if (!$won) return redirect($done); // a repeated/simultaneous callback: payment already recorded, nothing to do twice
      $this->log($o, 'Online payment received via ShurjoPay'.(!empty($v['method']) ? ' ('.$v['method'].')' : '').', ref '.($v['bank_trx_id'] ?? $sp));
      try { \App\Support\OrderSms::placed((int)$o->id); } catch (\Throwable $e) {}
    } else {
      $st = $code === '1002' ? 'Cancelled' : 'Failed';
      DB::table('orders')->where('id', $o->id)->update(['pay_status' => $st, 'updated_at' => now()]);
      $this->log($o, 'ShurjoPay payment '.strtolower($st).' (code '.($code ?: 'n/a').')');
    }
    return redirect($done);
  }
  private function log(object $o, string $note): void { DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'ShurjoPay', 'from_status' => $o->status, 'to_status' => $o->status, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]); }
}
