<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Invoice-wise Profit & Loss in the Mother Commerce grid layout: group header, product rows, subtotal, grand total. */
class ProfitReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now()->startOfMonth()->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $status = $r->query('status', 'Delivered'); $q = trim((string)$r->query('q', '')); $per = max(20, min(500, (int)($r->query('per') ?: 100)));
    $oq = DB::table('orders')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
    if ($status !== '' && $status !== 'all') $oq->where('status', $status);
    if ($q !== '') { $like = '%' . $q . '%'; $oq->where(fn($w) => $w->where('invoice', 'ilike', $like)->orWhere('customer_name', 'ilike', $like)->orWhere('phone', 'like', $like)
      ->orWhereIn('id', DB::table('order_items')->where('name', 'ilike', $like)->select('order_id'))); }
    $total = (clone $oq)->count();
    $orders = $oq->orderByDesc('created_at')->orderByDesc('id')->limit($per)->get(['id', 'invoice', 'customer_name', 'phone', 'status', 'discount', 'shipping', 'total', 'payment_method', 'created_at']);
    $ids = $orders->pluck('id')->all();
    $items = $ids ? DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->whereIn('i.order_id', $ids)
      ->get(['i.order_id', 'i.product_id', 'i.name', 'i.qty', 'i.price', 'p.cost', 'p.sku'])->groupBy('order_id') : collect();
    $groups = []; $g = ['buy' => 0, 'sale' => 0, 'profit' => 0, 'loss' => 0, 'disc' => 0, 'ship' => 0, 'net' => 0, 'lines' => 0];
    foreach ($orders as $o) {
      $rows = []; $buy = 0; $sale = 0;
      foreach ($items[$o->id] ?? [] as $it) {
        $qty = (int)$it->qty; $rate = (float)($it->cost ?? 0); $b = $rate * $qty; $s = (float)$it->price * $qty;
        $rows[] = ['pid' => $it->sku ?: ($it->product_id ?: '—'), 'name' => $it->name, 'qty' => $qty, 'rate' => $rate, 'buy' => $b, 'price' => (float)$it->price, 'sold' => $s, 'pl' => $s - $b, 'm' => $s > 0 ? round(($s - $b) / $s * 100, 1) : 0, 'nocost' => $it->cost === null || (float)$it->cost == 0];
        $buy += $b; $sale += $s;
      }
      $disc = (float)$o->discount; $ship = (float)$o->shipping; $pl = $sale - $buy; $net = $pl - $disc;
      $groups[] = ['o' => $o, 'rows' => $rows, 'buy' => $buy, 'sale' => $sale, 'pl' => $pl, 'disc' => $disc, 'ship' => $ship, 'net' => $net];
      $g['buy'] += $buy; $g['sale'] += $sale; $g['disc'] += $disc; $g['ship'] += $ship; $g['net'] += $net; $g['lines'] += count($rows);
      if ($net >= 0) $g['profit'] += $net; else $g['loss'] += -$net;
    }
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Invoice', 'Date', 'Customer', 'Status', 'Product ID', 'Product', 'Qty', 'Purchase rate', 'Purchase total', 'Selling price', 'Sold total', 'Profit/Loss', 'Margin %']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, [$gr['o']->invoice, substr((string)$gr['o']->created_at, 0, 10), $gr['o']->customer_name, $gr['o']->status, $x['pid'], $x['name'], $x['qty'], $x['rate'], $x['buy'], $x['price'], $x['sold'], $x['pl'], $x['m']]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="profit-loss-' . $from . '-' . $to . '.csv"']);
    }
    $statuses = DB::table('orders')->select('status')->distinct()->orderBy('status')->pluck('status');
    if ($r->query('export') === 'doc') { $html = view('profit_report', get_defined_vars())->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="profit_report.doc"']); }
    return view('profit_report', compact('groups', 'g', 'from', 'to', 'status', 'q', 'per', 'total', 'statuses'));
  }
}
