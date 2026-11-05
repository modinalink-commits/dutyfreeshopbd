<?php
namespace App\Http\Controllers;

use App\Support\AppReward as A;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marketing & Offers -> App Install Reward: settings, report and the list of customers who unlocked it. */
class AppRewardController extends Controller
{
  public function index(Request $r) {
    A::resolve();
    $cfg = A::cfg(); $st = A::report();
    $rows = collect(); $ins = collect();
    if (A::ready()) {
      $q = DB::table('app_rewards as w')->leftJoin('customers as c', 'c.id', '=', 'w.customer_id')->leftJoin('orders as o', 'o.id', '=', 'w.order_id');
      if ($r->filled('status')) $q->where('w.status', $r->status);
      if ($r->filled('q')) { $t = '%' . trim((string)$r->q) . '%'; $q->where(fn($x) => $x->where('c.name', 'ilike', $t)->orWhere('w.phone', 'like', $t)->orWhere('w.invoice', 'like', $t)); }
      $rows = $q->orderByDesc('w.id')->limit(300)->get(['w.*', 'c.name as cname', 'o.status as ostatus']);
      $ins = DB::table('app_installs')->orderByDesc('id')->limit(30)->get();
    }
    return view('app_rewards', ['cfg' => $cfg, 'st' => $st, 'rows' => $rows, 'ins' => $ins, 'f' => $r->only(['q', 'status'])]);
  }
  public function save(Request $r) {
    $c = A::cfg(); abort_unless($c['exists'], 404);
    $cfg = ['Reward TK' => max(0, (float)$r->input('amt', 100)), 'Restore on cancel (1=yes)' => $r->boolean('restore') ? 1 : 0, 'Keep unused part after safe-cap (1=yes)' => $r->boolean('keep') ? 1 : 0];
    DB::table('discount_rules')->where('key', A::KEY)->update(['enabled' => $r->boolean('on'), 'config' => json_encode($cfg), 'updated_at' => now()]);
    \Illuminate\Support\Facades\Cache::forget('store.rules');
    \App\Support\Procure::audit('appreward.settings', 'App Install Welcome Discount', null, $r->except(['_token']));
    return redirect('/admin/app-rewards')->with('ok', 'App Install Reward settings saved');
  }
}
