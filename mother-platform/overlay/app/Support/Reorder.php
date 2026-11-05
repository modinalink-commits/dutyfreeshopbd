<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auto Reorder Alert. Published product with stock below its threshold -> ONE open reorder request -> RFQ to the mapped suppliers
 * (supplier app notification + optional SMS). Quotes, award and PO use the existing RFQ/PO flow; this class only creates the need and keeps its status in sync.
 */
class Reorder
{
  const END = ['Closed', 'Cancelled', 'Expired'];
  const EARLY = ['No Supplier', 'New', 'Sent to Supplier', 'Quote Received', 'Under Review'];
  const STATUSES = ['No Supplier', 'New', 'Sent to Supplier', 'Quote Received', 'Under Review', 'Supplier Selected', 'PO Created', 'Ordered', 'Received', 'Closed', 'Cancelled', 'Expired'];
  const DECLINE = ['Out of Stock', 'Discontinued', 'Temporary Shortage', 'Cannot Meet Quantity', 'Other'];

  public static function ready(): bool { static $r = null; return $r ??= Schema::hasTable('reorder_requests') && Schema::hasColumn('supplier_rfqs', 'reorder_id'); }
  public static function cfg(): array {
    return ['enabled' => Procure::on('reorder_enabled'), 'threshold' => max(1, (int)Procure::set('reorder_threshold', '50')), 'target' => max(1, (int)Procure::set('reorder_target', '150')),
      'days' => max(1, (int)Procure::set('reorder_days', '7')), 'hours' => max(1, (int)Procure::set('reorder_rfq_hours', '48')), 'auto_send' => Procure::set('reorder_auto_send', '1') === '1',
      'sourcing' => Procure::on('reorder_sourcing'), 'sms' => Procure::on('reorder_sms'), 'high' => (int)Procure::set('reorder_u_high', '19'), 'med' => (int)Procure::set('reorder_u_med', '39')];
  }
  public static function urgency(int $stock, ?array $c = null): string {
    $c ??= self::cfg(); if ($stock <= 0) return 'Critical'; if ($stock <= $c['high']) return 'High'; if ($stock <= $c['med']) return 'Medium'; return 'Low';
  }
  public static function threshold(object $p, ?array $c = null): int { $c ??= self::cfg(); return (int)($p->low_stock_alert ?: $c['threshold']); }
  /** category ids that are stock controlled (the category itself or its parent says so) */
  public static function ctrl(): array {
    $top = DB::table('categories')->where('inventory_mode', 'STOCK_CONTROLLED')->pluck('id')->all();
    return $top ? DB::table('categories')->whereIn('id', $top)->orWhereIn('parent_id', $top)->pluck('id')->all() : [];
  }
  /** mode of one product: auto | off */
  public static function mode(object $p, array $ctrl, array $c): string {
    if ($p->reorder_mode === 'off') return 'off'; if ($p->reorder_mode === 'auto') return 'auto';
    return in_array((int)$p->category_id, $ctrl, true) || $c['sourcing'] ? 'auto' : 'off';
  }
  /** Products below their threshold: published, or a stock-controlled product that was auto-unpublished at zero and has been bought before. */
  public static function below(?array $ids = null, int $limit = 600) {
    $c = self::cfg(); $ctrl = self::ctrl();
    $q = DB::table('products as p')->whereRaw('coalesce(p.stock,0) < coalesce(nullif(p.low_stock_alert,0), ?)', [$c['threshold']])
      ->where(function ($w) use ($ctrl) {
        $w->where('p.published', true);
        if ($ctrl) $w->orWhere(fn($x) => $x->whereRaw('coalesce(p.stock,0) <= 0')->whereIn('p.category_id', $ctrl)->whereExists(fn($e) => $e->select(DB::raw(1))->from('purchase_items as i')->whereColumn('i.product_id', 'p.id')));
      });
    if ($ids !== null) $q->whereIn('p.id', $ids ?: [0]);
    return $q->orderByRaw('coalesce(p.stock,0)')->limit($limit)->get(['p.id', 'p.name', 'p.sku', 'p.image', 'p.stock', 'p.published', 'p.category_id', 'p.low_stock_alert', 'p.reorder_target', 'p.reorder_mode']);
  }

  /** Create requests for products below threshold (no duplicates). Returns [created, noSupplier, skipped]. */
  public static function scan(?array $ids = null, ?int $by = null): array {
    $c = self::cfg(); $ctrl = self::ctrl(); $made = 0; $nos = 0; $skip = 0;
    $open = DB::table('reorder_requests')->whereNotIn('status', self::END)->pluck('product_id')->flip();
    foreach (self::below($ids) as $p) {
      if (self::mode($p, $ctrl, $c) !== 'auto') { $skip++; continue; }
      if (isset($open[$p->id])) continue;
      $r = self::create($p, $c, $by); if ($r === 'No Supplier') $nos++; elseif ($r) $made++;
    }
    return [$made, $nos, $skip];
  }
  public static function create(object $p, array $c, ?int $by = null): ?string {
    $stock = (int)$p->stock; $thr = self::threshold($p, $c); $target = (int)($p->reorder_target ?: $c['target']); if ($target <= $thr) $target = $thr * 3;
    $qty = max(1, $target - max(0, $stock)); $sup = SupplierInsight::suppliersFor((int)$p->id);
    try {
      $id = DB::table('reorder_requests')->insertGetId(['product_id' => $p->id, 'stock_at' => $stock, 'threshold' => $thr, 'target' => $target, 'req_qty' => $qty, 'urgency' => self::urgency($stock, $c),
        'status' => $sup ? 'New' : 'No Supplier', 'required_date' => now('Asia/Dhaka')->addDays($c['days'])->toDateString(), 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) { return null; } // unique open-request index: another worker got there first
    DB::table('reorder_requests')->where('id', $id)->update(['req_no' => 'RO-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT)]);
    Procure::audit('reorder.create', 'reorder#' . $id, null, ['product' => $p->name, 'stock' => $stock, 'qty' => $qty, 'suppliers' => count($sup)]);
    if (!$sup) return 'No Supplier';
    self::makeRfq($id, $sup, $c);
    return 'ok';
  }
  /** RFQ for a request (sent right away when auto-send is on). */
  public static function makeRfq(int $id, array $sup, array $c): void {
    $r = DB::table('reorder_requests')->find($id); $p = DB::table('products')->find($r->product_id); if (!$r || !$p) return;
    $rid = DB::table('supplier_rfqs')->insertGetId(['rfq_no' => 'TMP' . uniqid(), 'product_id' => $p->id, 'product' => $p->name, 'sku' => $p->sku, 'qty' => $r->req_qty, 'required_date' => $r->required_date,
      'notes' => 'Auto reorder ' . $r->req_no . ' · stock ' . $r->stock_at . ' (below ' . $r->threshold . ')', 'expires_at' => now()->addHours($c['hours']), 'status' => 'draft', 'reorder_id' => $id, 'created_by' => $r->created_by, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('supplier_rfqs')->where('id', $rid)->update(['rfq_no' => 'RFQ-' . str_pad((string)$rid, 5, '0', STR_PAD_LEFT)]);
    foreach ($sup as $sid) DB::table('supplier_rfq_invites')->insert(['rfq_id' => $rid, 'supplier_id' => $sid, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('reorder_requests')->where('id', $id)->update(['rfq_id' => $rid, 'status' => 'New', 'updated_at' => now()]);
    if ($c['auto_send']) self::send($id);
  }
  /** Send the draft RFQ: supplier app notification (+ SMS when enabled). */
  public static function send(int $id): bool {
    $r = DB::table('reorder_requests')->find($id); if (!$r || !$r->rfq_id) return false;
    $rfq = DB::table('supplier_rfqs')->find($r->rfq_id); if (!$rfq || $rfq->status !== 'draft') return false;
    $c = self::cfg(); DB::table('supplier_rfqs')->where('id', $rfq->id)->update(['status' => 'sent', 'updated_at' => now()]);
    DB::table('reorder_requests')->where('id', $id)->update(['status' => 'Sent to Supplier', 'updated_at' => now()]);
    $pri = in_array($r->urgency, ['Critical', 'High'], true) ? 'urgent' : 'action';
    foreach (DB::table('supplier_rfq_invites')->where('rfq_id', $rfq->id)->pluck('supplier_id') as $sid) {
      SupplierPortal::notify((int)$sid, 'reorder', 'Product needed: ' . $rfq->product, 'Requested qty ' . $r->req_qty . ' · Current stock ' . $r->stock_at . ' · Required by ' . ($r->required_date ?: '—') . ' · Submit your best price before ' . date('d M, g:i A', strtotime((string)$rfq->expires_at)), '/supplier-portal/rfq/' . $rfq->id, $pri);
      if ($c['sms'] && Sms::configured()) foreach (DB::table('supplier_users')->where('supplier_id', $sid)->where('status', 'active')->whereIn('role', ['owner', 'sales'])->whereNotNull('mobile')->pluck('mobile') as $m) {
        try { Sms::queue($m, "Low Stock Purchase Request\nProduct: {$rfq->product}\nRequired Qty: {$r->req_qty}\nPlease submit your best price and available quantity from the Supplier Portal: " . rtrim((string)config('app.url'), '/') . '/supplier-portal', 'reorder'); } catch (\Throwable $e) {}
      }
    }
    Procure::audit('reorder.send', 'reorder#' . $id, null, ['rfq' => $rfq->rfq_no]);
    return true;
  }
  public static function cancel(int $id, string $why = 'Cancelled by admin'): void {
    $r = DB::table('reorder_requests')->find($id); if (!$r || in_array($r->status, self::END, true)) return;
    if ($r->rfq_id) { $rfq = DB::table('supplier_rfqs')->find($r->rfq_id); if ($rfq && in_array($rfq->status, ['draft', 'sent'], true)) { DB::table('supplier_rfqs')->where('id', $rfq->id)->update(['status' => 'closed', 'updated_at' => now()]);
      if ($rfq->status === 'sent') foreach (DB::table('supplier_rfq_invites')->where('rfq_id', $rfq->id)->pluck('supplier_id') as $sid) SupplierPortal::notify((int)$sid, 'reorder', 'Request closed: ' . $rfq->product, 'This purchase request is no longer needed. Thank you.', '/supplier-portal/rfq/' . $rfq->id, 'info'); } }
    DB::table('reorder_requests')->where('id', $id)->update(['status' => 'Cancelled', 'closed_reason' => $why, 'closed_at' => now(), 'updated_at' => now()]);
    Procure::audit('reorder.cancel', 'reorder#' . $id, ['status' => $r->status], null);
  }
  /** Move every open request to the status the RFQ / PO / stock say it is in. */
  public static function sync(): void {
    $c = self::cfg();
    foreach (DB::table('reorder_requests')->whereNotIn('status', self::END)->get() as $r) {
      try {
        $p = DB::table('products')->find($r->product_id); if (!$p) { self::cancel($r->id, 'Product removed'); continue; }
        $stock = (int)$p->stock; $thr = self::threshold($p, $c); $st = $r->status;
        if (in_array($st, self::EARLY, true) && $stock >= $thr) { self::cancel($r->id, 'Stock recovered'); DB::table('reorder_requests')->where('id', $r->id)->update(['status' => 'Closed']); continue; }
        if ($st === 'No Supplier') { $sup = SupplierInsight::suppliersFor((int)$r->product_id); if ($sup) self::makeRfq($r->id, $sup, $c); continue; }
        $rfq = $r->rfq_id ? DB::table('supplier_rfqs')->find($r->rfq_id) : null; if (!$rfq) continue;
        $quotes = DB::table('supplier_quotes')->where('rfq_id', $rfq->id)->count(); $new = $st; $end = null;
        if ($rfq->status === 'draft') $new = 'New';
        elseif ($rfq->status === 'sent') { $exp = $rfq->expires_at && strtotime((string)$rfq->expires_at) <= time(); $new = $quotes ? ($st === 'Under Review' ? 'Under Review' : 'Quote Received') : ($exp ? 'Expired' : 'Sent to Supplier'); if ($new === 'Expired') DB::table('supplier_rfqs')->where('id', $rfq->id)->update(['status' => 'closed', 'updated_at' => now()]); }
        elseif ($rfq->status === 'closed') $new = $quotes ? 'Under Review' : 'Expired';
        elseif ($rfq->status === 'awarded') {
          $po = DB::table('supplier_pos')->where('rfq_quote_id', $rfq->awarded_quote_id)->where('status', '!=', 'cancelled')->orderByDesc('id')->first();
          $new = !$po ? 'Supplier Selected' : (in_array($po->status, ['draft'], true) ? 'PO Created' : ($po->status === 'closed' ? 'Received' : 'Ordered'));
        }
        if ($new === 'Received') { DB::table('reorder_requests')->where('id', $r->id)->update(['status' => 'Closed', 'closed_reason' => 'Goods received', 'closed_at' => now(), 'updated_at' => now()]); continue; }
        if ($new === 'Expired') { DB::table('reorder_requests')->where('id', $r->id)->update(['status' => 'Expired', 'closed_reason' => 'No supplier quoted in time', 'closed_at' => now(), 'updated_at' => now()]); continue; }
        if ($new !== $st) DB::table('reorder_requests')->where('id', $r->id)->update(['status' => $new, 'updated_at' => now()]);
      } catch (\Throwable $e) {}
    }
  }
  /** Worker hook: drain the stock-movement queue every run, full fallback scan every 30 minutes. */
  public static function tick(): void {
    if (!self::ready()) return;
    $ids = DB::table('reorder_queue')->pluck('product_id')->all(); if ($ids) DB::table('reorder_queue')->whereIn('product_id', $ids)->delete();
    if (!Procure::on('reorder_enabled')) return;
    $full = (time() - (int)Procure::set('reorder_scan_last', '0')) >= 1800;
    if ($full) { Procure::put('reorder_scan_last', (string)time()); self::scan(null); self::sync(); }
    elseif ($ids) { self::scan($ids); self::sync(); }
  }
  /** Number of requests that need the admin (badge). */
  public static function attention(): int {
    if (!self::ready()) return 0; return (int)DB::table('reorder_requests')->whereIn('status', ['No Supplier', 'New', 'Quote Received', 'Under Review', 'Supplier Selected'])->count();
  }
}
