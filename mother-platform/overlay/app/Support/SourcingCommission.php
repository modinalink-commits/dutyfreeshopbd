<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Sourcing commission. One source of truth for the numbers on the statement page and in Payroll.
 * A line counts for an agreement when: the person is "sourced by" on the line, the entry is approved, the order is in a counted status
 * (default Delivered) and it was delivered in the statement month (Asia/Dhaka).
 * Line profit = sale - share of order discount - buy cost - share of courier cost + share of shipping paid by the customer - share of packaging.
 * Order-level amounts are split between the order's lines by line value. Month result = sum of line profit - marketing cost - deficit brought forward.
 */
class SourcingCommission {
  public static function ready(): bool { return Schema::hasTable('sourcing_agreements') && Schema::hasColumn('order_items', 'sourced_by'); }
  public static function month(?string $m): string { return ($m && preg_match('/^\d{4}-\d{2}$/', $m)) ? $m : now('Asia/Dhaka')->format('Y-m'); }
  private static function range(string $mon): array { $a = \Carbon\Carbon::createFromFormat('Y-m-d', $mon.'-01'); return [$a->format('Y-m-d').' 00:00:00', $a->copy()->addMonthNoOverflow()->format('Y-m-d').' 00:00:00']; } // same plain-date convention as the profit reports
  private static function when(): string { return Schema::hasColumn('orders', 'courier_delivered_at') ? 'coalesce(o.courier_delivered_at, o.updated_at)' : 'o.updated_at'; }

  /** @return array{lines:array<int,array>,totals:array} live calculation for one agreement and month */
  public static function compute(object $ag, string $mon): array {
    [$from, $to] = self::range($mon); $has = fn($c) => Schema::hasColumn('orders', $c);
    $statuses = array_values(array_filter(array_map('trim', explode(',', (string)$ag->count_statuses)))) ?: ['Delivered'];
    $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.sourced_by', $ag->user_id)->where('i.sourced_status', 'approved')
      ->whereIn('o.status', $statuses)->whereRaw(self::when().' >= ? and '.self::when().' < ?', [$from, $to]);
    if ($ag->starts_on) $q->where('o.created_at', '>=', $ag->starts_on);
    $rows = $q->orderBy('o.id')->orderBy('i.id')->get(['i.id', 'i.order_id', 'i.name', 'i.qty', 'i.price', 'i.src_price', 'i.src_store', 'i.sourced_cost', DB::raw((Schema::hasColumn('order_items', 'sourced_vendor') ? 'i.sourced_vendor' : 'null').' as sourced_vendor'), 'o.invoice', 'o.status', 'o.discount',
      DB::raw(($has('shipping') ? 'o.shipping' : '0').' as shipping'), DB::raw(($has('courier_fee') ? 'coalesce(o.courier_fee, 0)' : '0').' as courier_charge'), DB::raw('0 as cod_fee'), DB::raw(self::when().' as done_at')]);
    $orderValue = $rows->isEmpty() ? collect() : DB::table('order_items')->whereIn('order_id', $rows->pluck('order_id')->unique()->all())->groupBy('order_id')->selectRaw('order_id, sum(price * qty) as v')->pluck('v', 'order_id');
    $lines = []; $gross = 0.0;
    foreach ($rows as $r) {
      $sale = (float)$r->price * (int)$r->qty; $ov = (float)($orderValue[$r->order_id] ?? 0); $ratio = $ov > 0 ? min(1, $sale / $ov) : 1;
      $unit = $r->sourced_cost !== null ? (float)$r->sourced_cost : (float)$r->src_price; $cost = $unit * (int)$r->qty;
      $disc = $ag->deduct_discount ? round((float)$r->discount * $ratio, 2) : 0.0;
      $courierFull = (float)$r->courier_charge + (float)$r->cod_fee; $courierKnown = $courierFull > 0; if (!$courierKnown) $courierFull = (float)$r->shipping; // courier bill not synced yet: use the delivery charge of the order
      $courier = round($courierFull * $ratio, 2); $shipIn = $ag->add_shipping_income ? round((float)$r->shipping * $ratio, 2) : 0.0; $pack = round((float)$ag->packaging_cost * $ratio, 2);
      $profit = round($sale - $disc - $cost - $courier + $shipIn - $pack, 2); $gross += $profit;
      $lines[] = ['id' => $r->id, 'order_id' => $r->order_id, 'invoice' => $r->invoice, 'name' => $r->name, 'store' => $r->src_store, 'vendor' => ($r->sourced_vendor ?: $r->src_store), 'commission' => round($profit * (float)$ag->share_pct / 100, 2), 'qty' => (int)$r->qty, 'sale' => $sale, 'discount' => $disc, 'cost' => $cost, 'courier' => $courier, 'courier_known' => $courierKnown, 'ship_income' => $shipIn, 'packaging' => $pack, 'profit' => $profit, 'done_at' => (string)$r->done_at];
    }
    // Second source: stock the person bought through Purchase invoices. The shipment already recorded exactly which purchase batch each sold unit came from
    // (order_item_batches, real FIFO cost), so these lines need no extra entry: purchase by this person -> batch -> delivered order line.
    if (Schema::hasTable('order_item_batches') && Schema::hasTable('stock_batches') && Schema::hasTable('purchases')) {
      $seen = array_flip(array_map(fn($l) => $l['id'], $lines)); $uid = (int)$ag->user_id; $sb = Schema::hasColumn('purchases', 'sourced_by');
      $bq = DB::table('order_item_batches as ob')->join('stock_batches as b', 'b.id', '=', 'ob.batch_id')->join('purchases as pu', 'pu.id', '=', 'b.purchase_id')->join('order_items as i', 'i.id', '=', 'ob.order_item_id')->join('orders as o', 'o.id', '=', 'i.order_id')->leftJoin('suppliers as s', 's.id', '=', 'pu.supplier_id')
        ->whereRaw($sb ? 'coalesce(pu.sourced_by, pu.user_id) = ?' : 'pu.user_id = ?', [$uid])->whereRaw('ob.qty - ob.returned_qty > 0')->whereIn('o.status', $statuses)->whereRaw(self::when().' >= ? and '.self::when().' < ?', [$from, $to]);
      if ($ag->starts_on) $bq->where('o.created_at', '>=', $ag->starts_on);
      $brows = $bq->orderBy('o.id')->orderBy('ob.id')->get(['ob.id as obid', DB::raw('ob.qty - ob.returned_qty as q'), 'ob.unit_cost', 'i.id', 'i.order_id', 'i.name', 'i.price', 'o.invoice', 'o.discount', 's.name as supplier', 'pu.invoice as pinv',
        DB::raw(($has('shipping') ? 'o.shipping' : '0').' as shipping'), DB::raw(($has('courier_fee') ? 'coalesce(o.courier_fee, 0)' : '0').' as courier_charge'), DB::raw('0 as cod_fee'), DB::raw(self::when().' as done_at')]);
      $ov2 = $brows->isEmpty() ? collect() : DB::table('order_items')->whereIn('order_id', $brows->pluck('order_id')->unique()->all())->groupBy('order_id')->selectRaw('order_id, sum(price * qty) as v')->pluck('v', 'order_id');
      foreach ($brows as $r) {
        if (isset($seen[$r->id])) continue; // already counted through a Vendor Orders entry
        $q = (int)$r->q; $sale = (float)$r->price * $q; $ov = (float)($ov2[$r->order_id] ?? 0); $ratio = $ov > 0 ? min(1, $sale / $ov) : 1; $cost = round((float)$r->unit_cost * $q, 2);
        $disc = $ag->deduct_discount ? round((float)$r->discount * $ratio, 2) : 0.0;
        $courierFull = (float)$r->courier_charge + (float)$r->cod_fee; $courierKnown = $courierFull > 0; if (!$courierKnown) $courierFull = (float)$r->shipping;
        $courier = round($courierFull * $ratio, 2); $shipIn = $ag->add_shipping_income ? round((float)$r->shipping * $ratio, 2) : 0.0; $pack = round((float)$ag->packaging_cost * $ratio, 2);
        $profit = round($sale - $disc - $cost - $courier + $shipIn - $pack, 2); $gross += $profit;
        $lines[] = ['id' => 'b'.$r->obid, 'order_id' => $r->order_id, 'invoice' => $r->invoice, 'name' => $r->name, 'store' => 'Purchase '.$r->pinv, 'vendor' => ($r->supplier ?: 'Supplier').' · '.$r->pinv, 'commission' => round($profit * (float)$ag->share_pct / 100, 2), 'qty' => $q, 'sale' => $sale, 'discount' => $disc, 'cost' => $cost, 'courier' => $courier, 'courier_known' => $courierKnown, 'ship_income' => $shipIn, 'packaging' => $pack, 'profit' => $profit, 'done_at' => (string)$r->done_at];
      }
    }
    $carryIn = 0.0;
    if ($ag->carry_deficit) { $prev = DB::table('sourcing_statements')->where('agreement_id', $ag->id)->where('month', '<', $mon)->whereNotNull('approved_at')->orderByDesc('month')->first(); $carryIn = $prev ? (float)$prev->carry_out : 0.0; }
    $gross = round($gross, 2); $mk = (float)$ag->marketing_cost; $shareable = round($gross - $mk - $carryIn, 2);
    $staff = $shareable > 0 ? round($shareable * (float)$ag->share_pct / 100, 2) : 0.0; $company = $shareable > 0 ? round($shareable - $staff, 2) : 0.0;
    return ['lines' => $lines, 'totals' => ['gross_profit' => $gross, 'marketing_cost' => $mk, 'carry_in' => $carryIn, 'shareable' => $shareable, 'staff_share' => $staff, 'company_share' => $company, 'carry_out' => ($ag->carry_deficit && $shareable < 0) ? abs($shareable) : 0.0, 'line_count' => count($lines)]];
  }

  /** Lock the month: snapshot the lines and totals, then put the staff share into Payroll as that month's commission (only while the payroll row is unpaid). */
  public static function approve(object $ag, string $mon, int $by): array {
    if (DB::table('sourcing_statements')->where('agreement_id', $ag->id)->where('month', $mon)->whereNotNull('approved_at')->exists()) return ['ok' => false, 'msg' => 'This month is already approved and locked.'];
    if ($mon >= now('Asia/Dhaka')->format('Y-m')) return ['ok' => false, 'msg' => 'A month can be approved only after it has ended.'];
    return DB::transaction(function () use ($ag, $mon, $by) {
      $c = self::compute($ag, $mon); $t = $c['totals']; $pid = null; $note = 'Sourcing commission '.$mon.': profit ৳'.number_format($t['gross_profit']).' - marketing ৳'.number_format($t['marketing_cost']).($t['carry_in'] > 0 ? ' - brought forward ৳'.number_format($t['carry_in']) : '').' = ৳'.number_format($t['shareable']).', share '.rtrim(rtrim(number_format((float)$ag->share_pct, 2), '0'), '.').'%';
      if ($ag->employee_id && Schema::hasTable('payroll_entries')) {
        $p = DB::table('payroll_entries')->where('employee_id', $ag->employee_id)->where('month', $mon)->first();
        if ($p && $p->status === 'paid') return ['ok' => false, 'msg' => 'Payroll for '.$mon.' is already marked paid. Mark it unpaid first, then approve.'];
        if ($p) { DB::table('payroll_entries')->where('id', $p->id)->update(['amount' => $t['staff_share'], 'note' => $note, 'updated_at' => now()]); $pid = $p->id; }
        else $pid = DB::table('payroll_entries')->insertGetId(['employee_id' => $ag->employee_id, 'month' => $mon, 'amount' => $t['staff_share'], 'note' => $note, 'purpose' => 'Commission', 'due_date' => PayrollDue::dueDate($mon), 'status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);
      }
      DB::table('sourcing_statements')->updateOrInsert(['agreement_id' => $ag->id, 'month' => $mon], $t + ['snapshot' => json_encode(['terms' => ['packaging_cost' => (float)$ag->packaging_cost, 'marketing_cost' => (float)$ag->marketing_cost, 'share_pct' => (float)$ag->share_pct, 'count_statuses' => $ag->count_statuses, 'deduct_discount' => (bool)$ag->deduct_discount, 'add_shipping_income' => (bool)$ag->add_shipping_income, 'carry_deficit' => (bool)$ag->carry_deficit], 'lines' => $c['lines']], JSON_UNESCAPED_UNICODE), 'approved_by' => $by, 'approved_at' => now(), 'payroll_entry_id' => $pid, 'updated_at' => now(), 'created_at' => now()]);
      return ['ok' => true, 'msg' => 'Statement '.$mon.' approved. Staff share ৳'.number_format($t['staff_share']).($pid ? ' is now in Payroll.' : ' (no employee linked, so Payroll was not updated).'), 'payroll' => $pid];
    });
  }
  /** What DutyFreeBD still owes each supplier for this person's approved sourced lines (cancelled / returned orders left out). */
  public static function payable(object $ag) {
    if (!Schema::hasColumn('order_items', 'vendor_paid_at')) return collect();
    return DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.sourced_by', $ag->user_id)->where('i.sourced_status', 'approved')->whereNull('i.vendor_paid_at')
      ->whereNotIn('o.status', ['Cancel', 'Cancelled', 'Returned', 'Return Pending', 'Draft'])
      ->selectRaw("coalesce(nullif(i.sourced_vendor, ''), i.src_store, 'Unknown supplier') as vendor, count(*) as n, sum(coalesce(i.sourced_cost, i.src_price, 0) * i.qty) as due")->groupBy('vendor')->orderByDesc('due')->get();
  }
  /** Unpaid purchase invoices of this person, per supplier (these are paid from Purchases / Supplier ledger as usual). */
  public static function purchaseDues(object $ag) {
    if (!Schema::hasTable('purchases') || !Schema::hasColumn('purchases', 'user_id')) return collect();
    $sb = Schema::hasColumn('purchases', 'sourced_by');
    return DB::table('purchases as pu')->leftJoin('suppliers as s', 's.id', '=', 'pu.supplier_id')->whereRaw($sb ? 'coalesce(pu.sourced_by, pu.user_id) = ?' : 'pu.user_id = ?', [(int)$ag->user_id])->whereRaw('pu.total - pu.paid > 0.5')
      ->selectRaw("coalesce(s.name, 'Unknown supplier') as vendor, count(*) as n, sum(pu.total) as total, sum(pu.paid) as paid, sum(pu.total - pu.paid) as due")->groupBy('vendor')->orderByDesc('due')->get();
  }
  /** A line of an approved month can no longer be changed. */
  public static function locked(object $line): bool {
    if (empty($line->sourced_by) || ($line->sourced_status ?? null) !== 'approved') return false;
    $ags = DB::table('sourcing_agreements')->where('user_id', $line->sourced_by)->pluck('id')->all(); if (!$ags) return false;
    foreach (DB::table('sourcing_statements')->whereIn('agreement_id', $ags)->whereNotNull('approved_at')->get(['snapshot']) as $s) { foreach ((json_decode((string)$s->snapshot, true)['lines'] ?? []) as $l) if ((int)($l['id'] ?? 0) === (int)$line->id) return true; }
    return false;
  }
}
