<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Checkout Discount Performance: per discount type, from the per-order snapshots saved at checkout (orders placed after the snapshot went live). */
class CheckoutDiscountReportController extends Controller {
  private function data(int $days): array {
    try {
      $ap = DB::select("select e->>'type' t, count(*) applied, count(distinct o.id) orders, coalesce(sum(o.total),0) revenue, coalesce(avg((e->>'amount')::numeric),0) avg_disc, coalesce(sum((e->>'amount')::numeric),0) total_disc
        from orders o, jsonb_array_elements(o.applied_discounts_snapshot::jsonb) e where o.applied_discounts_snapshot is not null and o.applied_discounts_snapshot <> '' and o.created_at >= now() - (? || ' days')::interval and o.status not in ('Cancel','Incomplete') group by 1 order by 2 desc", [(string)$days]);
      $of = DB::select("select e->>'type' t, count(*) offered, count(distinct o.phone) eligible from orders o, jsonb_array_elements(o.available_discounts_snapshot::jsonb) e where o.available_discounts_snapshot is not null and o.available_discounts_snapshot <> '' and o.created_at >= now() - (? || ' days')::interval and o.status not in ('Cancel','Incomplete') group by 1", [(string)$days]);
      $by = []; foreach ($of as $x) $by[$x->t] = $x;
      $rows = []; foreach ($ap as $x) { $o = $by[$x->t] ?? null; $rows[$x->t] = ['type'=>$x->t, 'eligible'=>(int)($o->eligible ?? 0), 'offered'=>(int)($o->offered ?? $x->applied), 'applied'=>(int)$x->applied, 'orders'=>(int)$x->orders, 'revenue'=>(float)$x->revenue, 'avg'=>(float)$x->avg_disc, 'total'=>(float)$x->total_disc]; }
      foreach ($of as $x) if (!isset($rows[$x->t])) $rows[$x->t] = ['type'=>$x->t, 'eligible'=>(int)$x->eligible, 'offered'=>(int)$x->offered, 'applied'=>0, 'orders'=>0, 'revenue'=>0.0, 'avg'=>0.0, 'total'=>0.0];
      $n = (int)(DB::selectOne("select count(*) n from orders where applied_discounts_snapshot is not null and created_at >= now() - (? || ' days')::interval", [(string)$days])->n ?? 0);
      return [array_values($rows), $n];
    } catch (\Throwable $e) { report($e); return [[], 0]; }
  }
  public function index(Request $r) {
    $days = max(1, min(365, (int)($r->query('days') ?: 30))); [$rows, $n] = $this->data($days);
    if ($r->query('csv')) { $o = fopen('php://temp', 'w+'); fputcsv($o, ['Discount Type', 'Eligible Customers', 'Offered', 'Applied', 'Orders', 'Revenue', 'Average Discount', 'Total Discount', 'Conversion %']);
      foreach ($rows as $x) fputcsv($o, [$x['type'], $x['eligible'], $x['offered'], $x['applied'], $x['orders'], $x['revenue'], round($x['avg'], 2), $x['total'], $x['offered'] ? round($x['applied'] / $x['offered'] * 100, 1) : 0]);
      rewind($o); return response("\xEF\xBB\xBF".stream_get_contents($o), 200, ['Content-Type'=>'text/csv; charset=UTF-8', 'Content-Disposition'=>'attachment; filename="checkout-discount-performance.csv"']); }
    return view('checkout_discount_report', ['rows'=>$rows, 'days'=>$days, 'n'=>$n]);
  }
}
