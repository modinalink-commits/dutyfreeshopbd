<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marketing & Offers -> Sales Funnel: how many visitors view a product, add to cart, start checkout and how many orders follow. Anonymous storefront events only. */
class FunnelController extends Controller
{
  public function index(Request $r) {
    $tz = 'Asia/Dhaka';
    $to = $this->day((string)$r->query('to'), now($tz)->toDateString()); $from = $this->day((string)$r->query('from'), now($tz)->startOfMonth()->toDateString());
    if ($from > $to) [$from, $to] = [$to, $from];
    $a = \Carbon\Carbon::parse($from, $tz)->startOfDay()->utc(); $b = \Carbon\Carbon::parse($to, $tz)->addDay()->startOfDay()->utc();
    $steps = ['product_view' => 'Viewed a product', 'add_to_cart' => 'Added to cart', 'checkout_started' => 'Started checkout'];
    $v = []; foreach (DB::select("select event_type, count(distinct visitor_id) as v from product_analytics_events where created_at >= ? and created_at < ? and event_type in ('product_view','add_to_cart','checkout_started') group by event_type", [$a, $b]) as $x) $v[$x->event_type] = (int)$x->v;
    $o = DB::selectOne("select count(*) filter (where status not in ('Cancel','Returned')) as live, count(*) as everything from orders where created_at >= ? and created_at < ?", [$a, $b]);
    $by = function (string $col) use ($a, $b) {
      $out = []; foreach (DB::select("select coalesce(nullif($col,''),'(unknown)') as k, event_type, count(distinct visitor_id) as v from product_analytics_events where created_at >= ? and created_at < ? and event_type in ('product_view','add_to_cart','checkout_started') group by 1, 2", [$a, $b]) as $x) $out[$x->k][$x->event_type] = (int)$x->v;
      uasort($out, fn($p, $q) => ($q['product_view'] ?? 0) <=> ($p['product_view'] ?? 0)); return array_slice($out, 0, 12, true);
    };
    return view('funnel', ['from' => $from, 'to' => $to, 'steps' => $steps, 'v' => $v, 'orders' => (int)($o->live ?? 0), 'all' => (int)($o->everything ?? 0), 'dev' => $by('device_type'), 'src' => $by('source')]);
  }
  private function day(string $s, string $def): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) ? $s : $def; }
}
