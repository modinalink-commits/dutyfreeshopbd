<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\OrderActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Manual payment ledger: every Paid / Partially Paid state is derived from verified rows in order_payments. */
class OrderPaymentController extends Controller {
  private function can(): void { abort_unless(P::can(Auth::user(), 'orders', 'edit'), 403); }
  public static function recalc(int $oid): void {
    $o = DB::table('orders')->find($oid); if (!$o) return;
    $paid = (float)DB::table('order_payments')->where('order_id', $oid)->where('status', 'verified')->sum('amount');
    $st = ($o->total > 0 && $paid >= (float)$o->total) ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Unpaid');
    $upd = ['paid_total' => $paid, 'payment_status' => $st, 'updated_at' => now()];
    if ($st === 'Paid') $upd += ['pay_status' => 'Verified', 'pay_by' => Auth::user()->name ?? null, 'pay_at' => now()];
    elseif (DB::table('order_payments')->where('order_id', $oid)->where('status', 'pending')->exists()) $upd += ['pay_status' => 'Verification Pending'];
    DB::table('orders')->where('id', $oid)->update($upd);
  }
  /** GET lookup?phone=... → customer summary and open orders with paid/due. */
  public function lookup(Request $r) {
    $ph = preg_replace('/\D+/', '', (string)$r->query('phone')); abort_if(strlen($ph) < 6, 422);
    $ors = DB::table('orders')->where('phone', 'like', '%'.substr($ph, -10).'%')->orderByDesc('id')->limit(80)->get(['id','invoice','customer_name','total','paid_total','status','created_at']);
    $name = $ors->first()->customer_name ?? null;
    $open = $ors->filter(fn($o) => $o->status !== 'Cancel' && (float)$o->paid_total < (float)$o->total)->values();
    return response()->json(['found' => $ors->isNotEmpty(), 'name' => $name, 'total_orders' => $ors->count(),
      'unpaid' => $open->where('paid_total', '<=', 0)->count(), 'partial' => $open->where('paid_total', '>', 0)->count(), 'outstanding' => (float)$open->sum(fn($o) => $o->total - $o->paid_total),
      'orders' => $open->map(fn($o) => ['id' => $o->id, 'invoice' => $o->invoice, 'total' => (float)$o->total, 'paid' => (float)$o->paid_total, 'due' => (float)$o->total - (float)$o->paid_total, 'status' => $o->paid_total > 0 ? 'Partially Paid' : 'Unpaid'])]);
  }
  public function store(Request $r) {
    $this->can();
    $d = $r->validate(['order_id' => 'required|integer', 'method' => 'required|string|max:60', 'amount' => 'required|numeric|min:1', 'txn_id' => 'nullable|string|max:120', 'sender' => 'nullable|string|max:60',
      'paid_at' => 'nullable|date', 'note' => 'nullable|string|max:500', 'received_by' => 'nullable|string|max:120', 'mark_paid' => 'nullable|boolean', 'overpay' => 'nullable|in:apply_due', 'dup_ok' => 'nullable|boolean', 'proof' => 'nullable|image|max:6144']);
    $o = DB::table('orders')->find($d['order_id']); abort_unless($o, 404);
    $due = round((float)$o->total - (float)$o->paid_total, 2); $amt = (float)$d['amount'];
    if ($amt > $due + 0.009) {
      if (($d['overpay'] ?? '') !== 'apply_due') return back()->withInput()->with('bad', 'Payment amount exceeds order due by ৳'.number_format($amt - $due).'. Choose "Apply ৳'.number_format($due).' to order" or cancel.');
      $amt = $due;
    }
    if ($amt <= 0) return back()->with('bad', 'This order has no due amount.');
    $dup = ($d['txn_id'] ?? '') !== '' && DB::table('order_payments')->where('txn_id', $d['txn_id'])->whereIn('status', ['pending', 'verified'])->where(fn($w) => $w->where('amount', $amt)->orWhere('sender', $d['sender'] ?? '__'))->exists();
    if ($dup && empty($d['dup_ok'])) return back()->withInput()->with('bad', 'Suspicious duplicate: the same Transaction ID (with the same amount or sender) already exists. Tick "I checked the duplicate" to save.');
    $proof = $r->file('proof') ? $r->file('proof')->store('payment-proofs', 'public') : null; $u = Auth::user();
    $verify = !empty($d['mark_paid']);
    $pid = DB::table('order_payments')->insertGetId(['order_id' => $o->id, 'method' => $d['method'], 'amount' => $amt, 'txn_id' => $d['txn_id'] ?? null, 'sender' => $d['sender'] ?? null, 'paid_at' => $d['paid_at'] ?? now(), 'note' => $d['note'] ?? null,
      'proof' => $proof, 'status' => $verify ? 'verified' : 'pending', 'received_by' => $d['received_by'] ?? $u->name, 'created_by' => $u->id, 'created_by_name' => $u->name, 'verified_by' => $verify ? $u->name : null, 'verified_at' => $verify ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
    self::recalc((int)$o->id);
    OrderActivity::log((int)$o->id, 'payment_submitted', null, (string)$amt, 'Manual payment added: '.$d['method'].' ৳'.number_format($amt).($d['txn_id'] ?? '' ? ' ref '.$d['txn_id'] : ''), ['payment_id' => $pid]);
    if ($verify) OrderActivity::log((int)$o->id, 'payment_verified', null, (string)$amt, 'Payment verified: '.$d['method'].' ৳'.number_format($amt).' for '.$o->invoice, ['payment_id' => $pid]);
    return back()->with('ok', 'Payment saved for '.$o->invoice.($verify ? ' (verified)' : ' (pending verification)'));
  }
  public function act(Request $r, $pid, $mode) {
    $this->can();
    $p = DB::table('order_payments')->find($pid); abort_unless($p && in_array($mode, ['verify', 'reject']), 404);
    if ($p->status !== 'pending') return back()->with('bad', 'This payment is already '.$p->status.'.');
    $o = DB::table('orders')->find($p->order_id); $u = Auth::user();
    if ($mode === 'verify') {
      $due = (float)$o->total - (float)$o->paid_total;
      if ((float)$p->amount > $due + 0.009) return back()->with('bad', 'Amount exceeds order due (৳'.number_format($due).'). Edit the payment first.');
      DB::table('order_payments')->where('id', $pid)->update(['status' => 'verified', 'verified_by' => $u->name, 'verified_at' => now(), 'updated_at' => now()]);
    } else {
      $reason = trim((string)$r->input('reason')); if ($reason === '') return back()->with('bad', 'Reject reason is required.');
      DB::table('order_payments')->where('id', $pid)->update(['status' => 'rejected', 'reject_reason' => mb_substr($reason, 0, 200), 'verified_by' => $u->name, 'verified_at' => now(), 'updated_at' => now()]);
    }
    self::recalc((int)$o->id);
    OrderActivity::log((int)$o->id, $mode === 'verify' ? 'payment_verified' : 'payment_rejected', null, (string)$p->amount, 'Payment '.($mode === 'verify' ? 'verified' : 'rejected').': '.$p->method.' ৳'.number_format($p->amount).' for '.$o->invoice, ['payment_id' => (int)$pid]);
    return back()->with('ok', 'Payment '.($mode === 'verify' ? 'verified' : 'rejected').'.');
  }
}
