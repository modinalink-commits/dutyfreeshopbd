<?php
namespace App\Http\Controllers;

use App\Support\BlackFriday as BF;
use Illuminate\Http\Request;

/** Black Friday plan for the owner's approval: a calculation only. Nothing on the storefront changes from this page. Admin only (purchase costs are shown). */
class BlackFridayController extends Controller
{
  public function index(Request $r) {
    if (!\App\Support\ProfitFloor::admin()) abort(403);
    $d = BF::plan(); $rows = $d['rows']; $s = BF::summary($rows);
    $st = in_array($r->query('show'), ['in', 'check', 'no_cost', 'at_floor', 'no_stock'], true) ? $r->query('show') : 'in'; $cat = mb_substr(trim((string)$r->query('cat')), 0, 120);
    $list = array_values(array_filter($rows, fn($x) => ($st === 'check' ? $x['check'] : $x['status'] === $st) && ($cat === '' || $x['cat'] === $cat)));
    usort($list, fn($a, $b) => [$b['off_pct'], $a['name']] <=> [$a['off_pct'], $b['name']]);
    [$from, $to] = BF::window(); $el = BF::eligible();
    return view('black_friday_plan', ['on' => BF::on(), 'running' => BF::running(), 'from' => $from, 'to' => $to, 'minOff' => BF::minOff(), 'eligible' => count($el), 'elSmoke' => count(array_filter($el, fn($x) => $x['smoke'])), 'cnt' => BF::counts(), 'tot' => $s['tot'], 'by' => $s['by'], 'pct' => $d['pct'], 'list' => array_slice($list, 0, 300), 'count' => count($list), 'show' => $st, 'cat' => $cat]);
  }
  /** Owner switch. on = the campaign starts and ends by itself on its dates; off = normal prices come back within a minute. */
  public function toggle(Request $r) {
    if (!\App\Support\ProfitFloor::admin()) abort(403);
    $on = $r->input('on') === '1'; \App\Support\Procure::put('bf_on', $on ? '1' : '0'); if ($on) \App\Support\Procure::put('bf_applied_at', '');
    \App\Support\Procure::audit($on ? 'Black Friday switched on' : 'Black Friday switched off', 'Campaign', null, ['by' => auth()->user()->name ?? '']);
    try { \Illuminate\Support\Facades\Cache::forget('bf.tick'); } catch (\Throwable $e) {}   // the background worker applies or puts back the prices (thousands of products: not inside a page request)
    return back()->with('ok', $on ? 'Black Friday is on. Prices change by themselves at the start time and come back after the end time.' : 'Black Friday is off. Normal prices come back within a few minutes.');
  }
  public function csv() {
    if (!\App\Support\ProfitFloor::admin()) abort(403);
    $rows = BF::plan()['rows']; usort($rows, fn($a, $b) => [$a['status'] !== 'in', $a['cat'], $b['off_pct']] <=> [$b['status'] !== 'in', $b['cat'], $a['off_pct']]);
    $lab = ['in' => 'In the campaign', 'no_cost' => 'Left out: no purchase cost saved', 'at_floor' => 'Left out: price already at the minimum', 'no_stock' => 'Left out: out of stock', 'twin' => 'Left out: single packet'];
    return response()->streamDownload(function () use ($rows, $lab) {
      $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['Product ID', 'Code', 'Product', 'Category', 'Stock', 'Purchase cost', 'Price now', 'Black Friday price', 'Customer saves (Tk)', 'Customer saves (%)', 'Profit per piece (Tk)', 'Status', 'Check the cost', 'Cigarette or vape', 'Sourced on order']);
      foreach ($rows as $x) fputcsv($o, [$x['id'], $x['sku'], $x['name'], $x['cat'], $x['stock'], $x['cost'] ?: '', $x['price'], $x['bf'], $x['bf'] === null ? '' : $x['off'], $x['bf'] === null ? '' : $x['off_pct'], $x['profit'], $lab[$x['status']] ?? $x['status'], $x['check'] ? 'yes' : '', $x['smoke'] ? 'yes' : '', $x['sourced'] ? 'yes' : '']);
      fclose($o);
    }, 'black-friday-plan-' . now('Asia/Dhaka')->format('Y-m-d-Hi') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
  }
}
