<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Posts the product value of a courier-delivered COD order to the Income book (once per order).
 * Income = order total minus the delivery charge, minus anything already paid by another verified payment (those are in the book already).
 * Courier charges are not deducted here. The money is booked to the "Courier Receivable (COD)" account, not a bank account, so bank/bKash balances stay true
 * until the courier pays out.
 */
class CourierBook {
  const ACCOUNT = 'Courier Receivable (COD)';
  public static function account(): int {
    $id = DB::table('money_accounts')->where('name', self::ACCOUNT)->value('id');
    return $id ? (int)$id : (int)DB::table('money_accounts')->insertGetId(['name'=>self::ACCOUNT, 'type'=>'receivable', 'opening'=>0, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
  }
  /** @return float amount posted (0 when nothing to post or already posted) */
  public static function post(int $orderId): float {
    $o = DB::table('orders')->find($orderId); if (!$o || ($o->courier_stage ?? '') !== 'Delivered') return 0.0;
    $ref = 'COD-'.$o->invoice;
    if (DB::table('account_entries')->where('reference', $ref)->exists()) return 0.0;
    $other = (float)DB::table('order_payments')->where('order_id', $o->id)->where('status', 'verified')->where('method', '<>', 'Pathao COD')->sum('amount');
    $amt = round((float)$o->total - (float)($o->shipping ?? 0) - $other, 2);
    if ($amt <= 0) return 0.0;
    $d = $o->courier_delivered_at ? \Carbon\Carbon::parse($o->courier_delivered_at)->setTimezone('Asia/Dhaka')->toDateString() : now('Asia/Dhaka')->toDateString();
    DB::table('account_entries')->insert(['reference'=>$ref, 'direction'=>'income', 'account_id'=>self::account(), 'amount'=>$amt, 'comment'=>'COD delivered by '.($o->courier ?: 'Courier').' — order '.Inv::show($o->invoice).' (product value, delivery charge excluded; payout pending from courier)', 'entry_date'=>$d, 'user_name'=>'System (Courier delivered)', 'created_at'=>now(), 'updated_at'=>now()]);
    return $amt;
  }
}
