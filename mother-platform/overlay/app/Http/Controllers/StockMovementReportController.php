<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stock movement (stock_logs) and damage (damaged_products) in grid layout, grouped by product. */
class StockMovementReportController extends Controller {
  public function index(Request $r) {
    $mode = $r->query('mode') === 'damage' ? 'damage' : 'movement';
    $from = $r->query('from') ?: now()->subDays(29)->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $q = trim((string)$r->query('q', '')); $per = max(200, min(3000, (int)($r->query('per') ?: 1000)));
    if ($mode === 'movement') {
      $b = DB::table('stock_logs as l')->leftJoin('products as p', 'p.id', '=', 'l.product_id')->whereDate('l.created_at', '>=', $from)->whereDate('l.created_at', '<=', $to);
      if ($q !== '') { $like = '%' . $q . '%'; $b->where(fn($w) => $w->where('p.name', 'ilike', $like)->orWhere('p.sku', 'ilike', $like)->orWhere('l.reference', 'ilike', $like)->orWhere('l.source', 'ilike', $like)); }
      $total = (clone $b)->count();
      $rows = $b->orderBy('p.name')->orderBy('l.id')->limit($per)->get(['l.created_at as d', 'l.source as kind', 'l.reference as ref', 'l.before_qty as bq', 'l.change_qty as cq', 'l.after_qty as aq', 'l.user_name as who', 'p.name as pname', 'p.sku']);
    } else {
      $b = DB::table('damaged_products as l')->leftJoin('products as p', 'p.id', '=', 'l.product_id')->whereRaw('coalesce(l.dmg_date, l.created_at::date) >= ?', [$from])->whereRaw('coalesce(l.dmg_date, l.created_at::date) <= ?', [$to]);
      if ($q !== '') { $like = '%' . $q . '%'; $b->where(fn($w) => $w->where('p.name', 'ilike', $like)->orWhere('p.sku', 'ilike', $like)->orWhere('l.reason', 'ilike', $like)->orWhere('l.code', 'ilike', $like)); }
      $total = (clone $b)->count();
      $rows = $b->orderBy('p.name')->orderBy('l.id')->limit($per)->get([DB::raw('coalesce(l.dmg_date, l.created_at::date) as d'), 'l.reason_type as kind', 'l.code as ref', 'l.qty as cq', 'l.unit_cost as uc', 'l.value as val', 'l.responsibility as who', 'l.reason', 'p.name as pname', 'p.sku']);
    }
    $groups = []; $g = ['n' => 0, 'qty' => 0, 'val' => 0];
    foreach ($rows->groupBy('pname') as $name => $list) {
      $gr = ['name' => $name ?: 'Unknown product', 'sku' => $list[0]->sku ?? '', 'rows' => $list, 'qty' => $list->sum(fn($x) => (int)$x->cq), 'val' => $mode === 'damage' ? $list->sum(fn($x) => (float)$x->val) : 0];
      $groups[] = $gr; $g['n'] += $list->count(); $g['qty'] += $gr['qty']; $g['val'] += $gr['val'];
    }
    $data = compact('mode', 'groups', 'g', 'from', 'to', 'q', 'per', 'total');
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, $mode === 'damage' ? ['Product', 'Date', 'Type', 'Code', 'Qty', 'Unit cost', 'Value', 'Responsibility', 'Reason'] : ['Product', 'Date', 'Source', 'Reference', 'Before', 'Change', 'After', 'User']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, $mode === 'damage' ? [$gr['name'], substr((string)$x->d, 0, 10), $x->kind, $x->ref, $x->cq, $x->uc, $x->val, $x->who, $x->reason] : [$gr['name'], substr((string)$x->d, 0, 16), $x->kind, $x->ref, $x->bq, $x->cq, $x->aq, $x->who]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="stock-' . $mode . '.csv"']);
    }
    if ($r->query('export') === 'doc') { $html = view('stock_movement_report', $data)->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="stock-' . $mode . '.doc"']); }
    return view('stock_movement_report', $data);
  }
}
