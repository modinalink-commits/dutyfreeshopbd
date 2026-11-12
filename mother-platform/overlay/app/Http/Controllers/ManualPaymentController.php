<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Manual payments (Bank / bKash Personal / Nagad Personal). ShurjoPay and COD never appear here. */
class ManualPaymentController extends Controller {
  const REASONS = ['Amount mismatch','Invalid transaction ID','Transaction not found','Wrong account','Duplicate payment','Invalid proof','Other'];

  private function base() {
    return DB::table('orders as o')->whereNotNull('o.pay_status')->whereIn('o.pay_status', ['Verification Pending','Verified','Rejected','Cancelled'])
      ->whereRaw("lower(coalesce(o.payment_method,'')) not like '%shurjo%'")->whereRaw("lower(coalesce(o.payment_method,'')) not like '%cash%'");
  }
  private static function kind($label): string {
    $l = strtolower((string)$label);
    return str_contains($l, 'bkash') ? 'bkash' : (str_contains($l, 'nagad') ? 'nagad' : 'bank');
  }
  /** Owner rule 2026-11-12: only Mohammad Faruk (user #1) may verify a manual payment. Rejecting and notes stay with order editors. */
  public static function canVerify($u = null): bool { $u = $u ?: Auth::user(); return $u && (int)$u->id === 1; }
  /** Staff marks that the courier (Pathao) has been told the COD for this parcel is lowered. */
  public function courierInformed(Request $r, $oid) {
    abort_unless(P::can(Auth::user(), 'orders', 'edit'), 403);
    $n = DB::table('orders')->where('id', $oid)->whereNotNull('cod_zeroed_at')->whereNull('pathao_informed_at')->update(['pathao_informed_at' => now(), 'pathao_informed_by' => Auth::user()->name, 'updated_at' => now()]);
    if ($n) \App\Support\OrderActivity::log((int)$oid, 'cod_courier_informed', null, null, 'Courier informed that COD was lowered (customer paid us directly) by '.Auth::user()->name, []);
    return back()->with('ok', $n ? 'Marked as informed.' : 'Nothing to update.');
  }
  public function count() { \App\Http\Controllers\OrderPaymentController::syncLegacy(); return response()->json(['pending' => (int)DB::table('order_payments')->where('status', 'pending')->count()]); }

  public function index(Request $r) { \App\Support\DateDefault::month($r);
    \App\Http\Controllers\OrderPaymentController::syncLegacy();
    $q = DB::table('order_payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')->whereRaw("lower(p.method) not like '%shurjo%'");
    if ($s = trim((string)$r->query('q'))) {
      $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
      $q->where(fn($w) => $w->where('o.customer_name', 'ilike', $like)->orWhere('o.phone', 'ilike', $like)->orWhere('o.invoice', 'ilike', $like)->orWhere('p.txn_id', 'ilike', $like)->orWhere('p.sender', 'ilike', $like));
    }
    $range = (string)$r->query('range', '');
    if ($range === 'today') $q->whereDate('p.created_at', now()->toDateString());
    elseif ($range === 'yesterday') $q->whereDate('p.created_at', now()->subDay()->toDateString());
    if ($f = $r->query('from')) $q->whereDate('p.created_at', '>=', $f);
    if ($t = $r->query('to')) $q->whereDate('p.created_at', '<=', $t);
    if ($m = $r->query('method')) $q->whereRaw('lower(p.method) like ?', ['%'.$m.'%']);
    $st = (string)$r->query('status', '');
    if ($st === 'Pending Verification') $q->where('p.status', 'pending');
    elseif ($st === 'Verified') $q->where('p.status', 'verified');
    elseif ($st === 'Rejected') $q->where('p.status', 'rejected');
    elseif ($st === 'Duplicate') $q->whereRaw("p.txn_id is not null and p.txn_id <> '' and exists (select 1 from order_payments x where x.txn_id = p.txn_id and x.id <> p.id and x.status in ('pending','verified'))");
    if (is_numeric($r->query('min'))) $q->where('p.amount', '>=', (float)$r->query('min'));
    if (is_numeric($r->query('max'))) $q->where('p.amount', '<=', (float)$r->query('max'));
    $sort = $r->query('sort') === 'old' ? 'asc' : 'desc';
    $rows = (clone $q)->orderBy('p.created_at', $sort)->orderBy('p.id', $sort)
      ->select('p.*', 'o.invoice', 'o.pay_account as order_pay_account', 'o.customer_name', 'o.phone', 'o.total as order_total', 'o.paid_total', 'o.payment_status', 'o.status as order_status')->paginate(40)->withQueryString();
    $txns = $rows->pluck('txn_id')->filter()->unique()->all();
    $dups = $txns ? DB::table('order_payments')->whereIn('txn_id', $txns)->whereIn('status', ['pending','verified'])->select('txn_id', DB::raw('count(*) c'))->groupBy('txn_id')->pluck('c', 'txn_id') : collect();
    $k = DB::table('order_payments as p')->whereRaw("lower(p.method) not like '%shurjo%'")->selectRaw("count(*) filter (where p.status='pending') pend, count(*) filter (where p.status='verified' and p.verified_at::date = current_date) vtoday, count(*) filter (where p.status='rejected') rej, coalesce(sum(p.amount) filter (where p.status='pending'),0) pamt, count(*) filter (where lower(p.method) like '%bkash%' and p.status='pending') bk, count(*) filter (where lower(p.method) like '%nagad%' and p.status='pending') ng, count(*) filter (where lower(p.method) like '%bank%' and p.status='pending') bn")->first();
    $banks = DB::table('payment_methods')->where('kind', 'bank')->orderBy('position')->pluck('title');
    return view('manual_payments', ['rows' => $rows, 'dups' => $dups, 'k' => $k, 'banks' => $banks, 'reasons' => self::REASONS, 'canAct' => P::can(Auth::user(), 'orders', 'edit'), 'canVerify' => self::canVerify(), 'mismatch' => DB::table('orders')->where('courier_stage', 'Delivered')->whereNotNull('collected_amount')->whereNotNull('courier_cod')->whereRaw('abs(collected_amount - courier_cod) > 1')->where('courier_delivered_at', '>=', '2026-11-01')->orderByDesc('courier_delivered_at')->limit(30)->get(['id', 'invoice', 'customer_name', 'courier_cod', 'collected_amount', 'cod_direct_amount']), 'toInform' => DB::table('orders')->whereNotNull('cod_zeroed_at')->whereNull('pathao_informed_at')->orderBy('cod_zeroed_at')->limit(50)->get(['id', 'invoice', 'customer_name', 'consignment_id', 'courier', 'cod_direct_amount', 'courier_cod'])]);
  }

  public function act(Request $r, $id, $mode) {
    abort_unless(P::can(Auth::user(), 'orders', 'edit'), 403);
    abort_if($mode === 'verify' && !self::canVerify(), 403, 'Only Mohammad Faruk can verify manual payments.');
    $o = DB::table('orders')->find($id); abort_unless($o && $o->pay_status, 404);
    $u = Auth::user(); $note = trim((string)$r->input('note'));
    if ($mode === 'note') {
      abort_if($note === '', 422);
      DB::table('orders')->where('id', $id)->update(['pay_note' => trim(($o->pay_note ? $o->pay_note."\n" : '').'['.now()->format('d M H:i').' '.$u->name.'] '.mb_substr($note, 0, 300)), 'updated_at' => now()]);
      $this->log($r, $o, 'note', $note);
      return back()->with('ok', 'Note added');
    }
    $upd = ['pay_by' => $u->name, 'pay_at' => now(), 'updated_at' => now()];
    if ($mode === 'verify') {
      $dup = $o->pay_txn && DB::table('orders')->where('pay_txn', $o->pay_txn)->where('id', '<>', $id)->whereIn('pay_status', ['Verification Pending','Verified'])->exists();
      if ($dup && !$r->boolean('dup_ok')) return back()->with('bad', 'Possible duplicate payment: the same Transaction ID is on another order. Tick "I checked the duplicate" to verify.');
      $upd['pay_status'] = 'Verified'; $upd['pay_reason'] = null;
      if (is_numeric($r->input('amount'))) $upd['pay_amount'] = (float)$r->input('amount');
    } else {
      $reason = trim((string)$r->input('reason'));
      if (!in_array($reason, self::REASONS, true)) return back()->with('bad', 'Choose a reject reason.');
      $upd['pay_status'] = 'Rejected'; $upd['pay_reason'] = $reason;
    }
    if ($note !== '') $upd['pay_note'] = trim(($o->pay_note ? $o->pay_note."\n" : '').'['.now()->format('d M H:i').' '.$u->name.'] '.mb_substr($note, 0, 300));
    DB::table('orders')->where('id', $id)->update($upd);
    $label = $upd['pay_status'];
    $ap = $mode === 'verify' ? \App\Support\PaymentSettle::apply((int)$id, 'Manual Payment Verification') : null;
    DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => $u->id, 'user_name' => $u->name, 'from_status' => $o->status, 'to_status' => $o->status, 'note' => "Payment $label (".$o->payment_method.', ref '.$o->pay_txn.')'.($mode === 'reject' ? ' - '.$upd['pay_reason'] : '').($note !== '' ? ' - '.mb_substr($note, 0, 200) : ''), 'created_at' => now(), 'updated_at' => now()]);
    $this->log($r, $o, $mode, $mode === 'reject' ? $upd['pay_reason'].($note !== '' ? ' / '.$note : '') : $note);
    if ($r->boolean('sms') && $o->phone) {
      try {
        $to = \App\Support\Chat::phone($o->phone) ?: $o->phone;
        $body = $mode === 'verify' ? 'আপনার পেমেন্ট সফলভাবে যাচাই করা হয়েছে। অর্ডার নম্বর: '.$o->invoice.'. ধন্যবাদ।' : 'আপনার পেমেন্টটি যাচাই করা যায়নি। বিস্তারিত জানতে কল করুন: '.\App\Support\Biz::hotline();
        \App\Support\Sms::queue($to, $body, 'payment_'.$mode, ['order_id' => $id]);
      } catch (\Throwable $e) {}
    }
    return back()->with('ok', "Payment $label: ".$o->invoice.($ap ? ' · order moved to Approved' : ''));
  }

  private function log(Request $r, $o, $action, $note) {
    DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => 'manual_payment.'.$action, 'subject' => mb_substr($o->invoice.' / '.$o->pay_txn, 0, 190), 'meta' => json_encode(['order_id' => $o->id, 'note' => $note]), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
  }
}
