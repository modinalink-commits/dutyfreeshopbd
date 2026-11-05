<?php
namespace App\Http\Controllers;

use App\Support\AppUsers as U;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Customers -> App Users: live names, install / last-seen history, device, push state, reward. */
class AppUsersController extends Controller
{
  public function live() { return response()->json(U::live())->header('Cache-Control', 'no-store'); }

  public function index(Request $r) {
    $rows = collect(); $live = U::live(); $st = ['users' => 0, 'push' => 0, 'guests' => 0];
    if (U::ready()) {
      $cid = DB::raw('coalesce(i.login_customer_id, i.customer_id)');
      $q = DB::table('app_installs as i')->leftJoin('customers as c', 'c.id', '=', $cid);
      if ($r->filled('q')) { $t = '%' . trim((string)$r->q) . '%'; $q->where(fn($x) => $x->where('c.name', 'ilike', $t)->orWhere('c.phone', 'like', $t)); }
      $f = (string)$r->get('f', '');
      if ($f === 'live') $q->where('i.last_seen_at', '>=', now()->subSeconds(U::LIVE_SEC))->whereNotNull('i.login_customer_id');
      elseif ($f === 'guest') $q->whereNull('c.id');
      elseif ($f === 'users') $q->whereNotNull('c.id');
      $rows = $q->orderByDesc('i.last_seen_at')->paginate(50)->withQueryString();
      $rows->getCollection()->transform(function ($x) { return $x; });
      $q2 = $rows->getCollection();
      $ids = $q2->map(fn($x) => $x->login_customer_id ?: $x->customer_id)->filter()->all();
      $rw = DB::table('app_rewards')->whereIn('customer_id', $ids ?: [0])->get()->keyBy('customer_id');
      $pu = DB::table('push_subscriptions')->whereIn('customer_id', $ids ?: [0])->where('active', true)->select('customer_id', DB::raw('count(*) n'))->groupBy('customer_id')->pluck('n', 'customer_id');
      $ord = DB::table('orders')->where('via_app', true)->whereIn('customer_id', $ids ?: [0])->select('customer_id', DB::raw('count(*) n'))->groupBy('customer_id')->pluck('n', 'customer_id');
      $rows->getCollection()->each(function ($x) use ($rw, $pu, $ord) { $k = $x->login_customer_id ?: $x->customer_id; $x->_rw = $rw[$k] ?? null; $x->_push = (int)($pu[$k] ?? 0); $x->_ord = (int)($ord[$k] ?? 0); });
      $st = ['users' => (int)DB::table('app_installs')->where(fn($a) => $a->whereNotNull('login_customer_id')->orWhereNotNull('customer_id'))->count(), 'push' => (int)DB::table('push_subscriptions')->where('active', true)->whereIn('customer_id', U::customerIds())->distinct('customer_id')->count('customer_id'), 'guests' => $live['guests']];
    }
    return view('app_users', ['rows' => $rows, 'live' => $live, 'st' => $st, 'f' => $r->only(['q', 'f'])]);
  }
}
