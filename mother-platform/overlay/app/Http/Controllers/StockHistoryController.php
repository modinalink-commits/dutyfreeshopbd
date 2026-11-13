<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stock Tracking History for one product: every stock movement from the permanent stock_logs ledger. */
class StockHistoryController extends Controller {
  const TYPES = ['purchase' => 'PURCHASE_IN', 'order-shipment' => 'SHIPMENT_OUT', 'order-return' => 'RETURN_IN', 'purchase-reversal' => 'MANUAL_ADJUSTMENT', 'damaged' => 'MANUAL_ADJUSTMENT'];
  /** Admin-only stock correction with a full ledger line: sets stock to an exact quantity, logs who/why, re-aligns FIFO batches. */
  public function correct(Request $r, $id) {
    abort_unless(in_array(\Illuminate\Support\Facades\Auth::user()->role ?? '', ['super', 'admin'], true), 403);
    $d = $r->validate(['qty' => 'required|integer|min:0|max:100000', 'reason' => 'required|string|min:5|max:200']);
    return DB::transaction(function () use ($id, $d) {
      $p = DB::table('products')->where('id', (int)$id)->lockForUpdate()->first(); abort_unless($p, 404);
      $delta = (int)$d['qty'] - (int)$p->stock; if ($delta === 0) return response()->json(['ok' => true, 'unchanged' => true, 'stock' => (int)$p->stock]);
      \App\Support\Procure::stock((int)$id, $delta, 'manual-correction', mb_substr($d['reason'], 0, 120), false);
      try { \App\Support\Costing::sync((int)$id); } catch (\Throwable $e) { report($e); }
      try { \App\Support\Procure::audit('stock.correction', 'Stock correction: '.$p->name, ['stock' => (int)$p->stock], ['stock' => (int)$d['qty'], 'reason' => $d['reason']]); } catch (\Throwable $e) { report($e); }
      return response()->json(['ok' => true, 'before' => (int)$p->stock, 'after' => (int)$d['qty']]);
    });
  }
  public function show(Request $r, $id) {
    $p = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where('p.id', (int)$id)->first(array_merge(['p.id', 'p.name', 'p.sku', 'p.stock', 'p.published', 'p.category_id', 'c.name as cat'], \App\Support\StockGuard::lockReady() ? ['p.manual_unpublish_lock'] : []));
    abort_unless($p, 404);
    $rows = DB::table('stock_logs as l')->leftJoin('orders as o', function ($j) { $j->on('o.invoice', '=', 'l.reference')->whereIn('l.source', ['order-shipment', 'order-return']); })
      ->where('l.product_id', $p->id)->orderByDesc('l.id')->limit(1000)
      ->get(array_merge(['l.id', 'l.created_at', 'l.source', 'l.reference', 'l.before_qty', 'l.change_qty', 'l.after_qty', 'l.user_name', 'o.invoice as o_inv', 'o.courier_status', 'o.return_type'], \App\Support\Costing::ready() ? ['l.unit_cost', 'l.batch_note'] : []));
    $cs = \App\Support\Costing::ready() && \App\Support\Costing::canSee(); // purchase / batch costs only for people allowed to see costs
    $out = [];
    foreach ($rows as $x) {
      $type = self::TYPES[$x->source] ?? 'MANUAL_ADJUSTMENT';
      if ($x->source === 'order-return' && ($x->return_type ?? '') === 'Partial') $type = 'PARTIAL_RETURN_IN';
      $isOrd = $x->o_inv !== null; $isPur = in_array($x->source, ['purchase', 'purchase-reversal'], true);
      $note = $x->source === 'damaged' ? 'Damaged goods' : ($x->source === 'purchase-reversal' ? 'Purchase reversed' : (!isset(self::TYPES[$x->source]) ? (string)$x->source : ''));
      $out[] = ['at' => \Carbon\Carbon::parse($x->created_at)->utc()->addHours(6)->format('d M Y H:i'), 'type' => $type, 'before' => $x->before_qty, 'chg' => (int)$x->change_qty, 'after' => $x->after_qty,
        'inv' => $isOrd ? \App\Support\Inv::show($x->o_inv) : '', 'pur' => $isPur ? $x->reference : '', 'courier' => $isOrd ? ($x->courier_status ?? '') : '', 'who' => $x->user_name ?: 'System', 'note' => $note, 'cost' => $cs && ($x->unit_cost ?? null) !== null ? (float)$x->unit_cost : null, 'batch' => $cs ? (string)($x->batch_note ?? '') : ''];
    }
    $mode = \App\Support\StockGuard::mode($p->category_id);
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, array_merge(['Date & Time', 'Product', 'SKU', 'Previous Stock', 'Quantity Change', 'New Stock', 'Movement Type', 'Invoice', 'Purchase Reference', 'Courier Status', 'Staff/System', 'Note'], $cs ? ['Unit Cost', 'Batch'] : []));
      foreach ($out as $x) fputcsv($o, array_merge([$x['at'], $p->name, $p->sku, $x['before'], $x['chg'], $x['after'], $x['type'], $x['inv'], $x['pur'], $x['courier'], $x['who'], $x['note']], $cs ? [$x['cost'] ?? '', $x['batch']] : []));
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="stock-history-' . $p->id . '.csv"']);
    }
    return view('stock_history', ['p' => $p, 'rows' => $out, 'mode' => $mode, 'cs' => $cs]);
  }

  /** Read-only: would this order be allowed into Shipment? (same check the Shipment buttons use) */
  public function shipCheck($id) { $o = DB::table('orders')->where('id', (int)$id)->first(['status', 'stock_deducted_at', 'duplicate_of_order_id']);
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', (int)$id)->get(['i.name', 'i.qty', 'i.product_id', 'p.stock', 'p.category_id'])->map(fn($x) => $x->product_id . ':' . ($x->stock === null ? 'null' : $x->stock) . ' need ' . $x->qty . ' mode ' . \App\Support\StockGuard::mode($x->category_id))->all();
    return response()->json(['order' => (int)$id, 'status' => $o->status ?? null, 'deducted' => !empty($o->stock_deducted_at), 'dup' => !empty($o->duplicate_of_order_id), 'items' => $items, 'blocked' => \App\Support\StockGuard::shipBlock((int)$id)]); }
}
