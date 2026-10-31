<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Incomplete checkouts live inside the Orders page (status chip "Incomplete"). They are NOT orders: nothing is confirmed, no SMS, no stock, no courier until an admin converts one. */
class IncompleteController extends Controller {
  const TABS = ['open'=>'Open', 'recovered'=>'Recovered', 'converted'=>'Converted to order', 'archived'=>'Archived'];
  /** product id => image for the given incomplete rows */
  public static function imgs($rows): array {
    $ids = []; foreach ($rows as $o) foreach (json_decode($o->items ?: '[]', true) ?: [] as $i) if (!empty($i['id'])) $ids[] = (int)$i['id'];
    return $ids ? DB::table('products')->whereIn('id', array_unique($ids))->pluck('image', 'id')->all() : [];
  }
  public static function top(Request $r): array {
    if ($r->filled('status') || $r->filled('q') || (int)$r->query('page', 1) > 1) return ['incTop'=>collect(), 'incTopImgs'=>[]];
    $rows = DB::table('incomplete_orders')->where('status', 'open')->orderByDesc('last_activity_at')->limit(30)->get();
    return ['incTop'=>$rows, 'incTopImgs'=>self::imgs($rows)];
  }
  public static function data(Request $r): array {
    $st = array_key_exists($r->query('inc'), self::TABS) ? $r->query('inc') : 'open'; $q = trim((string)$r->query('q', ''));
    $rows = DB::table('incomplete_orders')->where('status', $st)->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('phone', 'like', '%'.$q.'%')->orWhere('name', 'ilike', '%'.$q.'%')))->orderByDesc('last_activity_at')->paginate(\App\Support\Pg::per(24))->withQueryString();
    $counts = DB::table('incomplete_orders')->select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n', 'status');
    $open = DB::table('incomplete_orders')->where('status', 'open')->selectRaw('coalesce(sum(total),0) s')->first();
    $ph = collect($rows->items())->pluck('phone')->all();
    $cust = $ph ? DB::table('customers')->whereIn('phone', $ph)->pluck('id', 'phone') : collect();
    return ['incRows'=>$rows, 'incSt'=>$st, 'incCounts'=>$counts, 'incSum'=>(float)$open->s, 'incTabs'=>self::TABS, 'incCust'=>$cust, 'incImgs'=>self::imgs($rows->items())];
  }
  public function act(Request $r, $id, $act) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    DB::table('incomplete_orders')->where('id', $id)->update(['status'=>['recovered'=>'recovered', 'archive'=>'archived', 'reopen'=>'open'][$act], 'updated_at'=>now()]);
    return redirect('/admin/orders?status=Incomplete&inc='.($act === 'reopen' ? 'open' : $o->status));
  }
}
