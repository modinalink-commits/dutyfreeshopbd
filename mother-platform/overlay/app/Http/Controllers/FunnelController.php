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
    $v = []; foreach (DB::select("select event_type, count(distinct visitor_id) as v from product_analytics_events where created_at >= ? and created_at < ? and event_type in ('product_view','add_to_cart','checkout_started') and not (event_type = 'product_view' and device_type = 'desktop' and source = 'Direct Link') group by event_type", [$a, $b]) as $x) $v[$x->event_type] = (int)$x->v;
    $o = DB::selectOne("select count(*) filter (where status not in ('Cancel','Returned')) as live, count(*) as everything from orders where created_at >= ? and created_at < ?", [$a, $b]);
    $by = function (string $col) use ($a, $b) {
      $out = []; foreach (DB::select("select coalesce(nullif($col,''),'(unknown)') as k, event_type, count(distinct visitor_id) as v from product_analytics_events where created_at >= ? and created_at < ? and event_type in ('product_view','add_to_cart','checkout_started') and not (event_type = 'product_view' and device_type = 'desktop' and source = 'Direct Link') group by 1, 2", [$a, $b]) as $x) $out[$x->k][$x->event_type] = (int)$x->v;
      uasort($out, fn($p, $q) => ($q['product_view'] ?? 0) <=> ($p['product_view'] ?? 0)); return array_slice($out, 0, 12, true);
    };
    // Traffic quality: per device, how many viewers looked at one product only, how many ever added to cart, how many arrived with no referrer.
    $q = []; foreach (DB::select("with v as (select visitor_id, coalesce(nullif(device_type,''),'(unknown)') as dev, count(*) as n, bool_or(source = 'Direct Link') as direct from product_analytics_events where created_at >= ? and created_at < ? and event_type = 'product_view' group by 1, 2),
      c as (select distinct visitor_id from product_analytics_events where created_at >= ? and created_at < ? and event_type in ('add_to_cart','checkout_started'))
      select v.dev, count(*) as viewers, count(*) filter (where v.n = 1) as one, count(*) filter (where v.n >= 11) as heavy, count(*) filter (where v.direct) as direct, count(c.visitor_id) as acted
      from v left join c on c.visitor_id = v.visitor_id group by v.dev order by 2 desc", [$a, $b, $a, $b]) as $x) $q[$x->dev] = ['viewers' => (int)$x->viewers, 'one' => (int)$x->one, 'heavy' => (int)$x->heavy, 'direct' => (int)$x->direct, 'acted' => (int)$x->acted];
    $hr = array_fill(0, 24, ['desktop' => 0, 'mobile' => 0]);
    foreach (DB::select("select extract(hour from created_at at time zone 'UTC' at time zone 'Asia/Dhaka')::int as h, device_type as d, count(distinct visitor_id) as v from product_analytics_events where created_at >= ? and created_at < ? and event_type = 'product_view' and device_type in ('desktop','mobile') group by 1, 2", [$a, $b]) as $x) $hr[(int)$x->h][$x->d] = (int)$x->v;
    $ua = []; foreach (DB::select("select metadata->>'ua' as ua, metadata->>'net' as net, count(distinct visitor_id) as v from product_analytics_events where created_at >= ? and created_at < ? and event_type = 'product_view' and metadata->>'ua' is not null group by 1, 2 order by 3 desc limit 12", [$a, $b]) as $x) $ua[] = ['ua' => (string)$x->ua, 'net' => (string)$x->net, 'v' => (int)$x->v];
    return view('funnel', ['tr' => \App\Support\VisitTrack::report($from, $to), 'ua' => $ua, 'from' => $from, 'to' => $to, 'steps' => $steps, 'v' => $v, 'orders' => (int)($o->live ?? 0), 'all' => (int)($o->everything ?? 0), 'dev' => $by('device_type'), 'src' => $by('source'), 'qual' => $q, 'hr' => $hr]);
  }
  private function day(string $s, string $def): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) ? $s : $def; }
}
