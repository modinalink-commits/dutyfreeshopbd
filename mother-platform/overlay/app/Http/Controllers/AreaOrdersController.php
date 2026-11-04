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

  private function csv(string $name, array $head, array $rows) {
    $fh = fopen('php://temp', 'w+'); fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, $head);
    foreach ($rows as $row) fputcsv($fh, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row));
    rewind($fh); $out = stream_get_contents($fh); fclose($fh);
    return response($out, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
  }
}
