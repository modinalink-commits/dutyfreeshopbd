<?php
namespace App\Http\Controllers;

use App\Support\AreaOrders as AO;
use Illuminate\Http\Request;

class AreaOrdersController extends Controller
{
  private function days(Request $r): int { return in_array((int)$r->query('days'), [30, 90, 365], true) ? (int)$r->query('days') : 0; }

  public function index(Request $r) {
    $days = $this->days($r); $by = in_array($r->query('by'), ['customers', 'orders', 'sales'], true) ? $r->query('by') : 'customers';
    $s = AO::summary($days); $rows = $s['rows'];
    $have = array_column($rows, 'name'); foreach (AO::geo() as $g) if (!in_array($g['en'], $have, true)) $rows[] = ['name' => $g['en'], 'bn' => $g['bn'], 'division' => $g['dv'], 'orders' => 0, 'customers' => 0, 'repeat' => 0, 'sales' => 0, 'success' => null, 'last' => null];
    $sorted = AO::top($rows, $by, 64);
    $reached = count(array_filter($rows, fn($x) => $x['orders'] > 0));
    $tot = ['customers' => array_sum(array_column($rows, 'customers')), 'orders' => array_sum(array_column($rows, 'orders')), 'sales' => array_sum(array_column($rows, 'sales'))];
    return view('area_orders', ['rows' => $sorted, 'top' => array_slice($sorted, 0, 10), 'by' => $by, 'days' => $days, 'reached' => $reached, 'tot' => $tot, 'unknown' => $s['unknown'], 'generated' => $s['generated']]);
  }

  public function export(Request $r) {
    $days = $this->days($r); $rows = AO::top(AO::summary($days)['rows'], 'customers', 64);
    return $this->csv('area-wise-orders.csv', ['District', 'Division', 'Customers', 'Repeat customers', 'Orders', 'Sales (BDT)', 'Delivery success %', 'Last order'], array_map(fn($x) => [$x['name'], $x['division'], $x['customers'], $x['repeat'], $x['orders'], $x['sales'], $x['success'] ?? '', $x['last'] ?? ''], $rows));
  }

  /** customer list of one district (name, phone, address) for campaigns */
  public function customers(Request $r) {
    $d = (string)$r->query('district'); abort_unless(collect(AO::geo())->contains('en', $d), 404);
    $c = AO::customers($d, $this->days($r));
    return $this->csv('customers-' . preg_replace('/[^a-z0-9]+/i', '-', $d) . '.csv', ['Name', 'Phone', 'Address', 'Orders', 'Sales (BDT)', 'Last order'], array_map(fn($x) => [$x['name'], $x['phone'], $x['address'], $x['orders'], round($x['sales']), $x['last']], $c));
  }

  /** Read-only check (24 Nov 2026): web checkout saved the "inside city" label as the district. Compares it with the delivery zone that was priced from the address (Pathao city / zone). Numbers only, no customer data. */
  public function audit(Request $r) {
    $days = max(1, min(120, (int)$r->query('days', 30))); $from = now()->subDays($days);
    $city = "case when delivery_zone like 'Pathao · %' then split_part(substr(delivery_zone, 10), ' / ', 1) when delivery_zone is null or delivery_zone = '' then '(no zone saved)' else delivery_zone end";
    $wrong = "(district ilike 'Inside Chattogram%')"; $ctgAddr = "(address ~* '(chattogram|chittagong|chattagram|ctg|চট্টগ্রাম)')";
    $agg = "count(*) as n, count(*) filter (where status in ('Delivered','Partial Delivered')) as delivered, count(*) filter (where status in ('Returned','Return Pending')) as returned, count(*) filter (where status = 'Cancel') as cancelled, coalesce(sum(shipping) filter (where status <> 'Cancel'),0) as charged, coalesce(sum(delivery_original) filter (where status <> 'Cancel'),0) as list_charge, coalesce(sum(delivery_free_discount) filter (where status <> 'Cancel'),0) as waived, count(*) filter (where status <> 'Cancel' and delivery_free_discount > 0) as free_n, count(*) filter (where status <> 'Cancel' and delivery_manual_adj is not null) as manual_n, count(*) filter (where status <> 'Cancel' and delivery_free_discount = 0 and delivery_manual_adj is null and shipping < delivery_original) as under_n, coalesce(sum(delivery_original - shipping) filter (where status <> 'Cancel' and delivery_free_discount = 0 and delivery_manual_adj is null and shipping < delivery_original),0) as under_amt";
    $q = fn(string $sql) => \Illuminate\Support\Facades\DB::select($sql, [$from]);
    return response()->json([
      'days' => $days,
      'by_flow' => $q("select coalesce(source,'(none)') as source, $wrong as wrong_district, $agg from orders where created_at >= ? group by 1, 2 order by 3 desc"),
      'wrong_by_city' => $q("select $city as city, $agg from orders where created_at >= ? and $wrong group by 1 order by 2 desc limit 40"),
      'all_by_city' => $q("select $city as city, $agg from orders where created_at >= ? group by 1 order by 2 desc limit 40"),
      'dhaka_zones' => $q("select delivery_zone as zone, $agg from orders where created_at >= ? and delivery_zone like 'Pathao · Dhaka%' group by 1 order by 3 desc, 2 desc limit 40"),
      'wrong_priced_as_ctg' => $q("select $ctgAddr as address_says_ctg, (address ~* '(dhaka|ঢাকা)') as address_says_dhaka, $agg from orders where created_at >= ? and $wrong and delivery_zone like 'Pathao · Chittagong%' group by 1, 2"),
      'wrong_first_last' => $q("select min(created_at) as first_at, max(created_at) as last_at, count(*) as n from orders where created_at >= ? and $wrong"),
    ])->header('Cache-Control', 'no-store');
  }

  private function csv(string $name, array $head, array $rows) {
    $fh = fopen('php://temp', 'w+'); fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, $head);
    foreach ($rows as $row) fputcsv($fh, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row));
    rewind($fh); $out = stream_get_contents($fh); fclose($fh);
    return response($out, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
  }
}
