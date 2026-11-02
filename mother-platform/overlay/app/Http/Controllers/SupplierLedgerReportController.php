<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Supplier ledger in grid layout: one group per supplier with opening balance, dated purchase/payment rows and running balance. */
class SupplierLedgerReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now()->startOfMonth()->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $sup = (int)$r->query('supplier', 0); $q = trim((string)$r->query('q', ''));
    $sq = DB::table('suppliers')->orderBy('name'); if ($sup) $sq->where('id', $sup); if ($q !== '') $sq->where('name', 'ilike', '%' . $q . '%');
    $suppliers = $sq->limit(60)->get(['id', 'name', 'phone']); $ids = $suppliers->pluck('id')->all();
    $openP = $ids ? DB::table('purchases')->whereIn('supplier_id', $ids)->whereDate('purchase_date', '<', $from)->groupBy('supplier_id')->selectRaw('supplier_id, sum(total) t')->pluck('t', 'supplier_id') : collect();
    $openY = $ids ? DB::table('supplier_payments')->whereIn('supplier_id', $ids)->whereDate('pay_date', '<', $from)->where(fn($w) => $w->whereNull('status')->orWhere('status', 'paid'))->groupBy('supplier_id')->selectRaw('supplier_id, sum(amount) t')->pluck('t', 'supplier_id') : collect();
    $pur = $ids ? DB::table('purchases')->whereIn('supplier_id', $ids)->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->get(['supplier_id', 'invoice', 'purchase_date as d', 'total'])->groupBy('supplier_id') : collect();
    $pay = $ids ? DB::table('supplier_payments')->whereIn('supplier_id', $ids)->whereDate('pay_date', '>=', $from)->whereDate('pay_date', '<=', $to)->where(fn($w) => $w->whereNull('status')->orWhere('status', 'paid'))->get(['supplier_id', 'code', 'pay_date as d', 'amount', 'method'])->groupBy('supplier_id') : collect();
    $groups = []; $g = ['open' => 0, 'debit' => 0, 'credit' => 0, 'close' => 0];
    foreach ($suppliers as $s) {
      $open = (float)($openP[$s->id] ?? 0) - (float)($openY[$s->id] ?? 0); $ev = [];
      foreach ($pur[$s->id] ?? [] as $x) $ev[] = [substr((string)$x->d, 0, 10), 'Purchase', $x->invoice, (float)$x->total, 0.0];
      foreach ($pay[$s->id] ?? [] as $x) $ev[] = [substr((string)$x->d, 0, 10), 'Payment', trim(($x->code ?: '') . ' ' . ($x->method ?: '')), 0.0, (float)$x->amount];
      usort($ev, fn($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
      $bal = $open; $rows = []; $dr = 0; $cr = 0;
      foreach ($ev as $e) { $bal += $e[3] - $e[4]; $dr += $e[3]; $cr += $e[4]; $rows[] = ['d' => $e[0], 't' => $e[1], 'ref' => $e[2], 'dr' => $e[3], 'cr' => $e[4], 'bal' => $bal]; }
      if (!$rows && abs($open) < 0.005) continue;
      $groups[] = ['s' => $s, 'open' => $open, 'rows' => $rows, 'dr' => $dr, 'cr' => $cr, 'close' => $bal];
      $g['open'] += $open; $g['debit'] += $dr; $g['credit'] += $cr; $g['close'] += $bal;
    }
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Supplier', 'Date', 'Type', 'Reference', 'Purchase (owed)', 'Payment (paid)', 'Balance']);
      foreach ($groups as $gr) { fputcsv($out, [$gr['s']->name, $from, 'Opening', '', '', '', $gr['open']]); foreach ($gr['rows'] as $x) fputcsv($out, [$gr['s']->name, $x['d'], $x['t'], $x['ref'], $x['dr'], $x['cr'], $x['bal']]); }
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="supplier-ledger-' . $from . '-' . $to . '.csv"']);
    }
    $all = DB::table('suppliers')->orderBy('name')->get(['id', 'name']);
    if ($r->query('export') === 'doc') { $html = view('supplier_ledger_report', ['groups' => $groups, 'g' => $g, 'from' => $from, 'to' => $to, 'sup' => $sup, 'q' => $q, 'all' => $all])->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="supplier-ledger.doc"']); }
    return view('supplier_ledger_report', ['groups' => $groups, 'g' => $g, 'from' => $from, 'to' => $to, 'sup' => $sup, 'q' => $q, 'all' => $all]);
  }
}
