<?php
namespace App\Http\Controllers;

use App\Support\Referral as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marketing & Offers -> Referral / Affiliate. */
class ReferralController extends Controller
{
  public function index(Request $r) {
    R::tick();
    $q = DB::table('referrals as r')->join('customers as a', 'a.id', '=', 'r.referrer_id')->leftJoin('customers as b', 'b.id', '=', 'r.referred_id')->leftJoin('orders as o', 'o.id', '=', 'r.order_id');
    if ($r->filled('status')) $q->where('r.status', $r->status);
    if ($r->filled('q')) { $t = '%' . trim($r->q) . '%'; $q->where(fn($w) => $w->where('a.name', 'ilike', $t)->orWhere('a.phone', 'like', $t)->orWhere('b.name', 'ilike', $t)->orWhere('r.referred_phone', 'like', $t)->orWhere('r.code', 'ilike', $t)); }
    $rows = $q->orderByDesc('r.id')->limit(300)->get(['r.*', 'a.name as rname', 'a.phone as rphone', 'b.name as bname', 'o.invoice', 'o.status as ostatus']);
    $all = DB::table('referrals');
    $d = ['rows' => $rows, 'f' => $r->only(['q', 'status']), 'set' => ['on' => R::on(), 'discount' => R::set('discount', '100'), 'reward' => R::set('reward', '100'), 'min_order' => R::set('min_order', '0'), 'after' => R::set('after', 'Delivered'), 'max' => R::set('max', '0'), 'credit_use' => R::set('credit_use', '1')],
      'st' => ['total' => (clone $all)->count(), 'ok' => (clone $all)->where('status', 'rewarded')->count(), 'pending' => (clone $all)->where('status', 'pending')->count(), 'void' => (clone $all)->where('status', 'void')->count(),
        'disc' => (float)(clone $all)->where('status', '<>', 'void')->sum('discount'), 'credit' => (float)DB::table('referral_ledger')->where('kind', 'reward')->sum('amount'), 'bal' => (float)DB::table('customers')->sum('ref_balance')]];
    return view('referral', $d);
  }
  public function save(Request $r) {
    foreach (['discount', 'reward', 'min_order', 'max'] as $k) R::put($k, (string)max(0, (float)$r->input($k, 0)));
    R::put('on', $r->boolean('on') ? '1' : '0'); R::put('credit_use', $r->boolean('credit_use') ? '1' : '0');
    $a = (string)$r->input('after', 'Delivered'); R::put('after', in_array($a, ['Delivered', 'Completed'], true) ? $a : 'Delivered');
    \App\Support\Procure::audit('referral.settings', 'Referral program', null, $r->except(['_token']));
    return redirect('/admin/referral')->with('ok', 'Referral settings saved');
  }
}
