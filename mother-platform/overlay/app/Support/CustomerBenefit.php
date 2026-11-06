<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Customer Benefit Eligibility (owner policy 2026-11-06): NO benefit for merely placing orders. Loyalty-class benefits (membership, returning, VIP,
 * special %, birthday, win-back, voice offers) depend only on SUCCESSFULLY RECEIVED parcels, and on the customer's benefit status.
 * One central service; the discount engine calls check() before it applies any of those benefits.
 *
 * Counted as received: Delivered orders (full value) and Partial Delivered orders (only the amount really collected).
 * Never counted: New, Incomplete, Assign, Pending, Scheduled, WFP, AI Approve, Approved, Packaging, Shipment, Late Delivery, Return Pending, Returned, Cancel.
 * Imported history (customers.legacy_orders / legacy_spent) is treated as received, because it came from the old site's delivered orders.
 * Status: Suspended (customer blocked, or an authorised flag) > Review Required (flag) > Limited (nothing received yet, or low success rate) > Eligible.
 * A fraud SIGNAL alone never suspends: only the customer's blocked flag or an authorised benefit_flag does.
 */
class CustomerBenefit {
  const REASONS = ['NO_SUCCESSFUL_DELIVERY'=>'No successfully received parcel yet', 'LOW_RECEIVING_HISTORY'=>'Low successful-receiving rate', 'CONFIRMED_FRAUD'=>'Confirmed abuse / blocked customer', 'FRAUD_REVIEW'=>'Fraud review required', 'ELIGIBLE_LOYAL_CUSTOMER'=>'Loyal customer: parcels successfully received'];
  const OFF = ['Delivered', 'Partial Delivered'];
  private static array $memo = [];

  /** Admin switches (all default ON): membership | discount | delivery | reward | coupon | abuse */
  public static function on(string $k): bool { try { return (string)DB::table('settings')->where('key', 'benefit_'.$k)->value('value') !== '0'; } catch (\Throwable $e) { return true; } }
  private static function alt(string $p): array { return [$p, '+88'.$p, '88'.$p]; }

  /** @return array{status:string, reasons:array, received:int, amount:float, total:int, cancelled:int, returned:int, rate:?float} */
  public static function check(?string $phone, $customer = null, ?int $exclude = null): array {
    $blank = ['status'=>'Limited', 'reasons'=>['NO_SUCCESSFUL_DELIVERY'], 'received'=>0, 'amount'=>0.0, 'total'=>0, 'cancelled'=>0, 'returned'=>0, 'rate'=>null];
    if (!$phone) return $blank;
    $key = $phone.'|'.($exclude ?: 0); if (isset(self::$memo[$key])) return self::$memo[$key];
    try {
      $a = DB::table('orders')->whereIn('phone', self::alt($phone))->when($exclude, fn($q) => $q->where('id', '!=', $exclude))
        ->selectRaw("count(*) total,
          count(*) filter (where status = 'Delivered' or (status = 'Partial Delivered' and coalesce(collected_amount,0) > 0)) rc,
          coalesce(sum(case when status = 'Delivered' then total when status = 'Partial Delivered' then coalesce(collected_amount,0) else 0 end),0) amt,
          count(*) filter (where status = 'Cancel') cn, count(*) filter (where status = 'Returned') rt")->first();
      $c = $customer ?: DB::table('customers')->whereIn('phone', self::alt($phone))->orderBy('id')->first();
      $rc = (int)$a->rc + (int)($c->legacy_orders ?? 0); $amt = (float)$a->amt + (float)($c->legacy_spent ?? 0);
      $final = (int)$a->rc + (int)$a->cn + (int)$a->rt; $rate = $final > 0 ? round((int)$a->rc / $final * 100, 1) : null;
      $r = ['status'=>'Eligible', 'reasons'=>['ELIGIBLE_LOYAL_CUSTOMER'], 'received'=>$rc, 'amount'=>round($amt, 2), 'total'=>(int)$a->total, 'cancelled'=>(int)$a->cn, 'returned'=>(int)$a->rt, 'rate'=>$rate];
      $flag = strtolower((string)($c->benefit_flag ?? ''));
      if (self::on('abuse') && ($c && !empty($c->blocked) || $flag === 'suspended')) { $r['status'] = 'Suspended'; $r['reasons'] = ['CONFIRMED_FRAUD']; }
      elseif (self::on('abuse') && $flag === 'review') { $r['status'] = 'Review Required'; $r['reasons'] = ['FRAUD_REVIEW']; }
      elseif ($rc < 1) { $r['status'] = 'Limited'; $r['reasons'] = ['NO_SUCCESSFUL_DELIVERY']; }
      elseif ($final >= 4 && $rate !== null && $rate < 50.0) { $r['status'] = 'Limited'; $r['reasons'] = ['LOW_RECEIVING_HISTORY']; }
      return self::$memo[$key] = $r;
    } catch (\Throwable $e) { report($e); return ['status'=>'Eligible', 'reasons'=>['ELIGIBLE_LOYAL_CUSTOMER']] + $blank; } // a failed check never blocks an order
  }
  public static function allows(?string $phone, $customer = null, ?int $exclude = null): bool { return self::check($phone, $customer, $exclude)['status'] === 'Eligible'; }
}
