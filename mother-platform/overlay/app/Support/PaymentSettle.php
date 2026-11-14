<?php
namespace App\Support;
use App\Http\Controllers\OrderPaymentController;
use Illuminate\Support\Facades\DB;
/**
 * One place that turns a verified payment into order state.
 * Paid = SUM(verified ledger rows). Due = total - paid (never negative). Only a FULLY paid order in an early stage becomes Approved; later stages are never moved back.
 * Safe to call any number of times: the ledger row is created once, the status moves once, the activity entry is written only when something changed.
 */
class PaymentSettle {
  /** Stages that may still be moved forward to Approved by a full payment (owner's rule: New, Pending, WFP, AI Order, Scheduled; Assign too). Pre Order / Incomplete are left alone. */
  const ELIGIBLE = ['New', 'Pending', 'WFP', 'Assign', 'AI Order', 'Scheduled'];
  /** Statuses a verified payment may approve by itself. WFP and Scheduled are deliberate holds and are left alone (a schedule is approved by its own date). */
  const AUTO = ['New', 'Pending', 'Assign', 'AI Order'];

  /** Safety net: any fully paid order still waiting in an early stage (paid before auto-approval existed, or verified through a path that did not call apply()) is settled now. Throttled to once every 2 minutes. */
  public static function auto(): void { if (\Illuminate\Support\Facades\Cache::add('shurjopay.verify.sweep', 1, 60)) { try { \App\Http\Controllers\ShurjoPayController::sweep(5); } catch (\Throwable $e) { report($e); } } if (\Illuminate\Support\Facades\Cache::add('payment.settle.sweep', 1, 120)) { try { self::sweep(); } catch (\Throwable $e) { report($e); } } }
  public static function sweep(int $limit = 300): int { $was = OrderActivity::$system; OrderActivity::$system = true; try { return self::sweepRun($limit); } finally { OrderActivity::$system = $was; } }
  private static function sweepRun(int $limit): int {
    $ids = DB::table('orders')->whereIn('status', self::AUTO)->whereNull('approved_at')->whereNull('duplicate_of_order_id')
      ->where(fn($q) => $q->where('pay_status', 'Verified')->orWhere('payment_status', 'Paid')->orWhereExists(fn($x) => $x->select(DB::raw(1))->from('order_payments as p')->whereColumn('p.order_id', 'orders.id')->where('p.status', 'verified')))
      ->orderBy('id')->limit($limit)->pluck('id'); $n = 0;
    foreach ($ids as $id) if (self::apply((int)$id, 'Payment check (automatic)')) $n++;
    return $n;
  }

  public static function apply(int $oid, string $source): ?string {
    try {
      $o = DB::table('orders')->find($oid); if (!$o) return null;
      self::ledgerFromOrder($o);
      $before = DB::table('orders')->find($oid);
      OrderPaymentController::recalc($oid);
      $o = DB::table('orders')->find($oid);
      $paid = (float)$o->paid_total; $total = (float)$o->total; $due = max(0, round($total - $paid, 2));
      $changed = (float)$before->paid_total !== $paid || (string)$before->payment_status !== (string)$o->payment_status;
      $moved = null;
      // Owner rule (14 Nov 2026): an order a person placed on hold stays there. Waiting for Product and Scheduled are never auto-approved by a payment, and an order that was already approved once and then moved out by staff is not approved again by itself.
      if ($o->payment_status === 'Paid' && in_array($o->status, self::AUTO, true) && empty($o->approved_at) && empty($o->duplicate_of_order_id) && !DupGuard::block($o, 'Approved')) {
        $moved = DB::transaction(function () use ($o, $source, $paid) {
          $c = DB::table('orders')->where('id', $o->id)->where('status', $o->status)->update(['status' => 'Approved', 'approved_at' => now(), 'auto_approved' => true, 'updated_at' => now()]);
          if (!$c) return null; // someone changed it meanwhile; never override
          DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'System', 'from_status' => $o->status, 'to_status' => 'Approved',
            'note' => 'Automatically approved: payment fully verified (৳'.number_format($paid).' paid, due ৳0) · '.$source, 'created_at' => now(), 'updated_at' => now()]);
          ApprovalSource::stamp((int)$o->id, 'SYSTEM', null, 'Payment verified · '.$source, 'Automatic approval: payment fully verified');
          return $o->status;
        });
      }
      if ($changed || $moved) OrderActivity::log($oid, 'payment_settled', (string)($before->payment_status ?: 'Unpaid'), (string)$o->payment_status,
        'Paid ৳'.number_format((float)$before->paid_total).' → ৳'.number_format($paid).' · Due ৳'.number_format((float)$before->total - (float)$before->paid_total > 0 ? (float)$before->total - (float)$before->paid_total : 0).' → ৳'.number_format($due)
        .($moved ? ' · Order '.$moved.' → Approved (automatic)' : ($o->payment_status === 'Paid' ? ' · Order status unchanged ('.$o->status.')' : '')).' · Courier COD ৳'.number_format(self::cod($o)).' · Source: '.$source, ['source' => $source, 'paid' => $paid, 'due' => $due]);
      return $moved ? 'Approved' : null;
    } catch (\Throwable $e) { report($e); return null; }
  }

  /** What the courier must collect from the customer: max(final payable - verified paid, 0). A fully prepaid order (ShurjoPay, verified bank/bKash/Nagad) is always 0. */
  public static function cod(object $o): float {
    $total = (float)$o->total;
    $paid = (float)DB::table('order_payments')->where('order_id', $o->id)->where('status', 'verified')->sum('amount');
    if ($paid <= 0 && ($o->pay_status ?? null) === 'Verified') { $m = strtolower((string)($o->payment_method ?? '')); if (!str_contains($m, 'cash') && !str_contains($m, 'cod')) $paid = (float)(($o->pay_amount ?? 0) ?: $total); }
    return max(round($total - $paid, 2), 0.0);
  }
  /** Online (ShurjoPay) and old-style order-level verifications have no ledger row. Make one, once, so Paid is always computed from real records. */
  private static function ledgerFromOrder(object $o): void {
    if ($o->pay_status !== 'Verified') return;
    $m = strtolower((string)$o->payment_method); if (str_contains($m, 'cash') || str_contains($m, 'cod')) return;
    if (DB::table('order_payments')->where('order_id', $o->id)->where('status', 'verified')->exists()) return;
    $pend = DB::table('order_payments')->where('order_id', $o->id)->where('status', 'pending')->whereNull('created_by')->update(['status' => 'verified', 'verified_by' => $o->pay_by, 'verified_at' => $o->pay_at ?: now(), 'updated_at' => now()]);
    if ($pend) return;
    DB::table('order_payments')->insert(['order_id' => $o->id, 'method' => $o->payment_method ?: 'Online', 'amount' => (float)($o->pay_amount ?: $o->total), 'txn_id' => $o->pay_txn, 'sender' => $o->pay_account, 'paid_at' => $o->pay_at ?: now(),
      'status' => 'verified', 'verified_by' => $o->pay_by, 'verified_at' => $o->pay_at ?: now(), 'created_by_name' => str_contains($m, 'shurjo') ? 'ShurjoPay' : 'Payment verification', 'created_at' => now(), 'updated_at' => now()]);
  }
}
