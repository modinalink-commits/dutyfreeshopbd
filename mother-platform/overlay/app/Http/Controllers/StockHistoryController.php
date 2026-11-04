<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stock Tracking History for one product: every stock movement from the permanent stock_logs ledger. */
class StockHistoryController extends Controller {
  const TYPES = ['purchase' => 'PURCHASE_IN', 'order-shipment' => 'SHIPMENT_OUT', 'order-return' => 'RETURN_IN', 'purchase-reversal' => 'MANUAL_ADJUSTMENT', 'damaged' => 'MANUAL_ADJUSTMENT'];
  public function show(Request $r, $id) {
    $p = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where('p.id', (int)$id)->first(['p.id', 'p.name', 'p.sku', 'p.stock', 'p.published', 'p.category_id', 'c.name as cat']);
    abort_unless($p, 404);
    $rows = DB::table('stock_logs as l')->leftJoin('orders as o', function ($j) { $j->on('o.invoice', '=', 'l.reference')->whereIn('l.source', ['order-shipment', 'order-return']); })
      ->where('l.product_id', $p->id)->orderByDesc('l.id')->limit(1000)
      ->get(['l.id', 'l.created_at', 'l.source', 'l.reference', 'l.before_qty', 'l.change_qty', 'l.after_qty', 'l.user_name', 'o.invoice as o_inv', 'o.courier_status', 'o.return_type']);
    $out = [];
    foreach ($rows as $x) {
      $type = self::TYPES[$x->source] ?? 'MANUAL_ADJUSTMENT';
      if ($x->source === 'order-return' && ($x->return_type ?? '') === 'Partial') $type = 'PARTIAL_RETURN_IN';
      $isOrd = $x->o_inv !== null; $isPur = in_array($x->source, ['purchase', 'purchase-reversal'], true);
      $note = $x->source === 'damaged' ? 'Damaged goods' : ($x->source === 'purchase-reversal' ? 'Purchase reversed' : (!isset(self::TYPES[$x->source]) ? (string)$x->source : ''));
      $out[] = ['at' => \Carbon\Carbon::parse($x->created_at)->utc()->addHours(6)->format('d M Y H:i'), 'type' => $type, 'before' => $x->before_qty, 'chg' => (int)$x->change_qty, 'after' => $x->after_qty,
        'inv' => $isOrd ? \App\Support\Inv::show($x->o_inv) : '', 'pur' => $isPur ? $x->reference : '', 'courier' => $isOrd ? ($x->courier_status ?? '') : '', 'who' => $x->user_name ?: 'System', 'note' => $note];
    }
    $mode = \App\Support\StockGuard::mode($p->category_id);
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, ['Date & Time', 'Product', 'SKU', 'Previous Stock', 'Quantity Change', 'New Stock', 'Movement Type', 'Invoice', 'Purchase Reference', 'Courier Status', 'Staff/System', 'Note']);
      foreach ($out as $x) fputcsv($o, [$x['at'], $p->name, $p->sku, $x['before'], $x['chg'], $x['after'], $x['type'], $x['inv'], $x['pur'], $x['courier'], $x['who'], $x['note']]);
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="stock-history-' . $p->id . '.csv"']);
    }
    return view('stock_history', ['p' => $p, 'rows' => $out, 'mode' => $mode]);
  }
}
