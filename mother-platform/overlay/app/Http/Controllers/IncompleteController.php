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
  /** Open incomplete checkouts merged into All Orders by creation time: returns [order id => rows to print before that order] plus 'tail' (older than the last order on the last page). */
  public static function mix(Request $r, $orders, ?array $range): array {
    $none = ['incBefore'=>[], 'incTail'=>collect(), 'incTop'=>collect(), 'incTopImgs'=>[]];
    if ($r->filled('status') || $r->filled('print') || !\Illuminate\Support\Facades\Schema::hasTable('incomplete_orders')) return $none;
    $items = collect($orders->items()); $first = $items->first(); $last = $items->last();
    $q = DB::table('incomplete_orders')->where('status', 'open');
    if ($r->filled('q')) { $qs = trim((string)$r->q); $qd = preg_replace('/\D/', '', $qs); $q->where(fn($w) => $w->where('phone', 'like', '%'.($qd !== '' ? $qd : $qs).'%')->orWhere('name', 'ilike', '%'.$qs.'%')); }
    if ($range) { if ($range[0]) $q->where('created_at', '>=', $range[0]); if ($range[1]) $q->where('created_at', '<=', $range[1]); }
    $pg = (int)$r->query('page', 1);
    if ($pg > 1 && $first) $q->where('created_at', '<', $first->created_at);   // belongs to an earlier page
    if ($orders->hasMorePages() && $last) $q->where('created_at', '>=', $last->created_at); // belongs to a later page
    $rows = $q->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get();
    $before = []; $tail = collect();
    foreach ($rows as $row) {
      $target = $items->first(fn($o) => $o->created_at <= $row->created_at);
      if ($target) $before[$target->id][] = $row; else $tail->push($row);
    }
    return ['incBefore'=>$before, 'incTail'=>$tail, 'incTop'=>collect(), 'incTopImgs'=>self::imgs($rows)];
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
