<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** The one order-confirmation SMS. Website orders and admin-created orders both call OrderSms::placed(). */
class OrderSms {
  private static function tk($n): string { return 'Tk '.number_format((float)$n, (float)$n == floor((float)$n) ? 0 : 2); }
  public static function build(object $o): string {
    $ship = (float)$o->shipping; $disc = (float)($o->discount ?? 0); $sub = round((float)$o->total + $disc - $ship, 2);
    $L = ['DutyFreeBD: Your Order '.$o->invoice.' has been received.', 'Subtotal: '.self::tk($sub)];
    if ($disc > 0) {
      $t = trim((string)($o->discount_type ?? '')); if ($t === '' || $t === 'Manual Discount') $t = 'Discount'; $pct = $o->discount_pct ?? null;
      $L[] = $t.': '.($pct !== null && (float)$pct > 0 ? rtrim(rtrim(number_format((float)$pct, 2), '0'), '.').'% ' : '').'(-'.self::tk($disc).')';
      $L[] = 'You Saved: '.self::tk($disc);
    }
    if ($ship > 0) $L[] = 'Delivery: '.self::tk($ship);
    $L[] = 'Payable: '.self::tk($o->total);
    if (!empty($o->pay_status) && $o->pay_status === 'Verification Pending') $L[] = 'Payment verification is pending.';
    $L[] = 'Our representative will contact you shortly for confirmation. Track: dutyfreebd.com/track?invoice='.$o->invoice;
    $L[] = 'Help: '.Biz::hotline();
    return implode("\n", $L);
  }
  /** Queue the confirmation once per order. Respects the SMS "order confirmation" switch. */
  public static function placed(int $orderId): void {
    try {
      if ((DB::table('settings')->where('key', 'sms_order_confirm')->value('value') ?? '1') !== '1') return;
      if (DB::table('sms_logs')->where('order_id', $orderId)->where('event', 'order_placed')->exists()) return;
      $o = DB::table('orders')->find($orderId); if (!$o || !$o->phone) return;
      $to = Chat::phone($o->phone) ?: $o->phone; $disc = (float)($o->discount ?? 0) > 0 ? mb_substr((string)($o->discount_type ?: 'Discount').' -Tk '.number_format((float)$o->discount), 0, 250) : null;
      Sms::queue($to, self::build($o), 'order_placed', ['order_id' => $orderId, 'discount_mentioned' => $disc]);
    } catch (\Throwable $e) {}
  }
}
