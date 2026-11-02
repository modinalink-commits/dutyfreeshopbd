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
  public function count() { return response()->json(['pending' => (int)DB::table('orders')->where('pay_status', 'Verification Pending')->count()]); }

  public function index(Request $r) {
    $q = $this->base();
    if ($s = trim((string)$r->query('q'))) {
      $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
      $q->where(fn($w) => $w->where('o.customer_name', 'ilike', $like)->orWhere('o.phone', 'ilike', $like)->orWhere('o.invoice', 'ilike', $like)->orWhere('o.pay_txn', 'ilike', $like)->orWhere('o.pay_account', 'ilike', $like));
    }
    $range = (string)$r->query('range', '');
    if ($range === 'today') $q->whereDate('o.created_at', now()->toDateString());
    elseif ($range === 'yesterday') $q->whereDate('o.created_at', now()->subDay()->toDateString());
    if ($f = $r->query('from')) $q->whereDate('o.created_at', '>=', $f);
    if ($t = $r->query('to')) $q->whereDate('o.created_at', '<=', $t);
    if ($m = $r->query('method')) { $q->whereRaw("lower(o.payment_method) like ?", ['%'.($m === 'bank' ? 'bank' : $m).'%']); }
    if ($b = trim((string)$r->query('bank'))) $q->where('o.pay_account', 'ilike', '%'.$b.'%');
    $st = (string)$r->query('status', '');
    if ($st === 'Pending Verification') $q->where('o.pay_status', 'Verification Pending');
    elseif (in_array($st, ['Verified','Rejected','Cancelled'], true)) $q->where('o.pay_status', $st);
    elseif ($st === 'Duplicate') $q->whereRaw("o.pay_txn is not null and o.pay_txn <> '' and exists (select 1 from orders x where x.pay_txn = o.pay_txn and x.id <> o.id and x.pay_status in ('Verification Pending','Verified'))");
    elseif ($st === 'Amount Mismatch') $q->whereNotNull('o.pay_amount')->whereRaw('o.pay_amount <> o.total');
    if (is_numeric($r->query('min'))) $q->where('o.total', '>=', (float)$r->query('min'));
    if (is_numeric($r->query('max'))) $q->where('o.total', '<=', (float)$r->query('max'));
    $sort = $r->query('sort') === 'old' ? 'asc' : 'desc';
    $rows = (clone $q)->orderBy('o.created_at', $sort)->orderBy('o.id', $sort)
      ->select('o.id','o.invoice','o.customer_name','o.phone','o.payment_method','o.pay_account','o.pay_txn','o.pay_proof','o.pay_status','o.pay_by','o.pay_at','o.pay_note','o.pay_reason','o.pay_amount','o.total','o.created_at','o.status')
      ->paginate(40)->withQueryString();
    $txns = $rows->pluck('pay_txn')->filter()->unique()->all();
    $dups = $txns ? DB::table('orders')->whereIn('pay_txn', $txns)->whereIn('pay_status', ['Verification Pending','Verified'])->select('pay_txn', DB::raw('count(*) c'))->groupBy('pay_txn')->pluck('c', 'pay_txn') : collect();
    $all = $this->base();
    $k = (clone $all)->selectRaw("count(*) filter (where o.pay_status='Verification Pending') pend, count(*) filter (where o.pay_status='Verified' and o.pay_at::date = current_date) vtoday, count(*) filter (where o.pay_status='Rejected') rej, coalesce(sum(o.total) filter (where o.pay_status='Verification Pending'),0) pamt, count(*) filter (where lower(o.payment_method) like '%bkash%' and o.pay_status='Verification Pending') bk, count(*) filter (where lower(o.payment_method) like '%nagad%' and o.pay_status='Verification Pending') ng, count(*) filter (where lower(o.payment_method) like '%bank%' and o.pay_status='Verification Pending') bn")->first();
    $banks = DB::table('payment_methods')->where('kind', 'bank')->orderBy('position')->pluck('title');
    return view('manual_payments', ['rows' => $rows, 'dups' => $dups, 'k' => $k, 'banks' => $banks, 'reasons' => self::REASONS, 'canAct' => P::can(Auth::user(), 'orders', 'edit')]);
  }

  public function act(Request $r, $id, $mode) {
    abort_unless(P::can(Auth::user(), 'orders', 'edit'), 403);
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
    DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => $u->id, 'user_name' => $u->name, 'from_status' => $o->status, 'to_status' => $o->status, 'note' => "Payment $label (".$o->payment_method.', ref '.$o->pay_txn.')'.($mode === 'reject' ? ' - '.$upd['pay_reason'] : '').($note !== '' ? ' - '.mb_substr($note, 0, 200) : ''), 'created_at' => now(), 'updated_at' => now()]);
    $this->log($r, $o, $mode, $mode === 'reject' ? $upd['pay_reason'].($note !== '' ? ' / '.$note : '') : $note);
    if ($r->boolean('sms') && $o->phone) {
      try {
        $to = \App\Support\Chat::phone($o->phone) ?: $o->phone;
        $body = $mode === 'verify' ? 'আপনার পেমেন্ট সফলভাবে যাচাই করা হয়েছে। অর্ডার নম্বর: '.$o->invoice.'. ধন্যবাদ।' : 'আপনার পেমেন্টটি যাচাই করা যায়নি। বিস্তারিত জানতে আমাদের সাথে যোগাযোগ করুন।';
        \App\Support\Sms::queue($to, $body, 'payment_'.$mode, ['order_id' => $id]);
      } catch (\Throwable $e) {}
    }
    return back()->with('ok', "Payment $label: ".$o->invoice);
  }

  private function log(Request $r, $o, $action, $note) {
    DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => 'manual_payment.'.$action, 'subject' => mb_substr($o->invoice.' / '.$o->pay_txn, 0, 190), 'meta' => json_encode(['order_id' => $o->id, 'note' => $note]), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
  }
}
