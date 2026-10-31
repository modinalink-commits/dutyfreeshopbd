<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Admin view of checkouts that were started but not finished. These are NOT orders: nothing is confirmed, no SMS, no stock, no courier until an admin converts one. */
class IncompleteController extends Controller {
  const TABS = ['open'=>'Open', 'recovered'=>'Recovered', 'converted'=>'Converted to order', 'archived'=>'Archived'];
  public function index(Request $r) {
    $st = array_key_exists($r->query('s'), self::TABS) ? $r->query('s') : 'open'; $q = trim((string)$r->query('q', ''));
    $rows = DB::table('incomplete_orders')->where('status', $st)->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('phone', 'like', '%'.$q.'%')->orWhere('name', 'ilike', '%'.$q.'%')))->orderByDesc('last_activity_at')->paginate(30)->withQueryString();
    $counts = DB::table('incomplete_orders')->select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n', 'status');
    $open = DB::table('incomplete_orders')->where('status', 'open')->selectRaw('count(*) n, coalesce(sum(total),0) s')->first();
    return view('incomplete', ['rows'=>$rows, 'st'=>$st, 'q'=>$q, 'counts'=>$counts, 'openSum'=>(float)$open->s, 'tabs'=>self::TABS]);
  }
  public function show($id) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    $cust = DB::table('customers')->whereIn('phone', [$o->phone, '+88'.$o->phone, '88'.$o->phone])->orderBy('id')->first();
    return view('incomplete_show', ['o'=>$o, 'items'=>json_decode($o->items ?: '[]', true) ?: [], 'cust'=>$cust, 'profile'=>\App\Support\OrderDiscount::profile($o->phone)]);
  }
  public function act(Request $r, $id, $act) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    DB::table('incomplete_orders')->where('id', $id)->update(['status'=>['recovered'=>'recovered', 'archive'=>'archived', 'reopen'=>'open'][$act], 'updated_at'=>now()]);
    $b = (string)$r->input('back', ''); return redirect(str_starts_with($b, '/admin/incomplete') ? $b : '/admin/incomplete');
  }
}
