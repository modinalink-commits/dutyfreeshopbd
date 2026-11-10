<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Supplier purchase invoices in the Mother Commerce grid layout: invoice header, item rows, subtotal, grand total. */
class PurchaseReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now('Asia/Dhaka')->startOfMonth()->toDateString(); $to = $r->query('to') ?: now('Asia/Dhaka')->toDateString();
    $sup = (int)$r->query('supplier', 0); $q = trim((string)$r->query('q', '')); $per = max(20, min(500, (int)($r->query('per') ?: 100)));
    $pq = DB::table('purchases as p')->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')->whereDate('p.purchase_date', '>=', $from)->whereDate('p.purchase_date', '<=', $to);
    if ($sup) $pq->where('p.supplier_id', $sup);
    if ($q !== '') { $like = '%' . $q . '%'; $pq->where(fn($w) => $w->where('p.invoice', 'ilike', $like)->orWhere('s.name', 'ilike', $like)->orWhereIn('p.id', DB::table('purchase_items')->where('name', 'ilike', $like)->select('purchase_id'))); }
    $total = (clone $pq)->count();
    $sum = (clone $pq)->selectRaw('coalesce(sum(p.total),0) t, coalesce(sum(p.paid),0) pd')->first();
    $purchases = $pq->orderByDesc('p.purchase_date')->orderByDesc('p.id')->limit($per)->get(['p.id', 'p.invoice', 'p.purchase_date', 'p.total', 'p.paid', 'p.note', 's.name as supplier']);
    $ids = $purchases->pluck('id')->all();
    $items = $ids ? DB::table('purchase_items')->whereIn('purchase_id', $ids)->get(['purchase_id', 'product_id', 'name', 'qty', 'cost'])->groupBy('purchase_id') : collect();
    $groups = []; $g = ['qty' => 0, 'total' => 0, 'paid' => 0, 'due' => 0, 'lines' => 0];
    foreach ($purchases as $p) {
      $rows = []; $lt = 0;
      foreach ($items[$p->id] ?? [] as $it) { $s = (float)$it->cost * (int)$it->qty; $rows[] = ['pid' => $it->product_id ?: '—', 'name' => $it->name, 'qty' => (int)$it->qty, 'cost' => (float)$it->cost, 'sum' => $s]; $lt += $s; $g['qty'] += (int)$it->qty; }
      $due = (float)$p->total - (float)$p->paid;
      $groups[] = ['p' => $p, 'rows' => $rows, 'lines' => $lt, 'due' => $due];
      $g['lines'] += count($rows);
    }
    $g['total'] = (float)$sum->t; $g['paid'] = (float)$sum->pd; $g['due'] = $g['total'] - $g['paid'];
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Invoice', 'Date', 'Supplier', 'Product ID', 'Product', 'Qty', 'Cost', 'Line total', 'Invoice total', 'Paid', 'Due']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, [$gr['p']->invoice, $gr['p']->purchase_date, $gr['p']->supplier, $x['pid'], $x['name'], $x['qty'], $x['cost'], $x['sum'], $gr['p']->total, $gr['p']->paid, $gr['due']]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="purchase-report-' . $from . '-' . $to . '.csv"']);
    }
    $suppliers = DB::table('suppliers')->orderBy('name')->get(['id', 'name']);
    if ($r->query('export') === 'doc') { $html = view('purchase_report', get_defined_vars())->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="purchase_report.doc"']); }
    return view('purchase_report', compact('groups', 'g', 'from', 'to', 'sup', 'q', 'per', 'total', 'suppliers'));
  }
}
