<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Staff-only: how many orders / how much sale each source store brings (order lines carry the vendor store, see order_items.src_store_id). */
class StoreSalesController extends Controller {
  const BAD = ['Cancel', 'Cancelled', 'Returned', 'Return Pending', 'Draft'];
  public function index(Request $r) {
    abort_unless(Schema::hasColumn('order_items', 'src_store_id'), 404);
    $range = in_array($r->query('range'), ['today', '7', '30', '90', 'all'], true) ? $r->query('range') : '30';
    $from = match ($range) { 'today' => now('Asia/Dhaka')->startOfDay()->utc(), '7' => now()->subDays(7), '30' => now()->subDays(30), '90' => now()->subDays(90), default => null };
    $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->when($from, fn($w) => $w->where('o.created_at', '>=', $from));
    $bad = "'" . implode("','", self::BAD) . "'";
    $rows = (clone $q)->selectRaw("coalesce(i.src_store_id,0) as sid, max(coalesce(i.src_store,'DutyFreeBD (own stock)')) as name,
      count(distinct case when o.status not in ($bad) then o.id end) as orders,
      coalesce(sum(case when o.status not in ($bad) then i.qty end),0) as units,
      coalesce(sum(case when o.status not in ($bad) then i.price*i.qty end),0) as revenue,
      coalesce(sum(case when o.status not in ($bad) and i.src_store_id is not null then coalesce(i.src_price,0)*i.qty end),0) as cost,
      count(distinct case when o.status = 'Delivered' then o.id end) as delivered,
      count(distinct case when o.status in ('Cancel','Cancelled') then o.id end) as cancelled,
      count(distinct case when o.status in ('Returned','Return Pending') then o.id end) as returned,
      count(distinct o.id) as all_orders,
      coalesce(sum(case when o.status not in ($bad) and i.src_store_id is not null and i.vendor_status is null then 1 end),0) as to_order")
      ->groupBy(DB::raw('coalesce(i.src_store_id,0)'))->get()->keyBy('sid');
    $stores = DB::table('stores')->orderBy('name')->get(['id', 'name', 'domain', 'source_category', 'paused']);
    $list = [];
    foreach ($stores as $s) { $x = $rows->get($s->id); $list[] = ['id' => $s->id, 'name' => $s->name, 'domain' => $s->domain, 'cat' => $s->source_category, 'paused' => (bool)$s->paused, 'x' => $x]; }
    $own = $rows->get(0);
    $tot = ['orders' => (int)$rows->sum('orders'), 'units' => (int)$rows->sum('units'), 'revenue' => (float)$rows->sum('revenue'), 'cost' => (float)$rows->sum('cost')];
    $src = ['orders' => (int)$rows->where('sid', '>', 0)->sum('orders'), 'revenue' => (float)$rows->where('sid', '>', 0)->sum('revenue'), 'cost' => (float)$rows->where('sid', '>', 0)->sum('cost')];
    // daily trend of sourcing sales (last 14 days) for the sparkline
    $trend = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.src_store_id')->whereNotIn('o.status', self::BAD)->where('o.created_at', '>=', now()->subDays(13)->startOfDay())
      ->selectRaw("to_char(o.created_at at time zone 'Asia/Dhaka','MM-DD') d, sum(i.price*i.qty) v")->groupBy('d')->orderBy('d')->pluck('v', 'd');
    return view('store_sales', compact('list', 'own', 'tot', 'src', 'range', 'trend'));
  }
}
