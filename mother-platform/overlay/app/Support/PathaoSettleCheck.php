<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pathao settlement check (26 Nov 2026): delivered Pathao COD parcels that still owe money and appear on no imported Pathao invoice after GRACE days. Pure comparison of our records, nothing is changed on any order. */
class PathaoSettleCheck {
  const GRACE = 7;
  public static function rows(int $limit = 200): array {
    try {
      if (!Schema::hasTable('pathao_invoice_lines') || !Schema::hasColumn('orders', 'courier_delivered_at')) return [];
      $sql = "select o.id, o.consignment_id, o.total, o.courier_delivered_at,
          (o.total - coalesce((select sum(p.amount) from order_payments p where p.order_id = o.id and p.status = 'verified'), 0)) as due
        from orders o
        where o.consignment_id is not null and coalesce(o.courier, 'Pathao') = 'Pathao' and o.status in ('Delivered','Partial Delivered','Late Delivery')
          and o.courier_delivered_at between ? and ?
          and not exists (select 1 from pathao_invoice_lines l where l.order_id = o.id or l.consignment_id = o.consignment_id)
          and (o.total - coalesce((select sum(p.amount) from order_payments p where p.order_id = o.id and p.status = 'verified'), 0)) > 0
        order by o.courier_delivered_at limit " . (int)$limit;
      return DB::select($sql, [now()->subDays(90), now()->subDays(self::GRACE)]);
    } catch (\Throwable $e) { return []; }
  }
  public static function tick(): void {
    if (!Cache::add('pathao.settle.check', 1, 21600)) return;
    try {
      $r = self::rows(); if (!$r) return; $sum = 0.0; foreach ($r as $x) $sum += (float)$x->due;
      $ids = implode(', ', array_map(fn($x) => '#' . $x->id, array_slice($r, 0, 8)));
      DeployHealth::raise('pathao_unsettled_' . date('Ymd'), 'medium', 'Courier', count($r) . ' delivered Pathao parcel(s) not on any Pathao invoice', 'Delivered more than ' . self::GRACE . ' days ago, still owing about Tk ' . number_format($sum) . ' in COD, and not found on any imported Pathao invoice. Oldest: ' . $ids . '.', 'Pathao has not settled these yet, or the invoice was not imported', 'Import the latest Pathao invoice (Courier > Pathao Invoices); if they are still missing, ask Pathao for the settlement.');
    } catch (\Throwable $e) {}
  }
}
