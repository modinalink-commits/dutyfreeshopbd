<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Late Delivery = an automatic 72-hour monitoring bucket for undelivered shipments.
 *   Shipment for 72h or more, not delivered / returned / cancelled  ->  Late Delivery   (clock = orders.shipment_at, never created_at)
 *   Late Delivery + courier Delivered                               ->  Delivered       (done by the normal courier sync)
 * Partial Delivered and every final / return state are left alone. A parcel that is Late Delivery but is younger than 72h (old rule or wrong data) goes back to Shipment.
 */
class LateDelivery {
  const HOURS = 72;
  const SKIP_STAGES = ['Delivered', 'Returned', 'Cancelled', 'Failed Delivery'];
  public static function since(object $o): ?\Carbon\Carbon {
    $t = $o->shipment_at ?? null ?: ($o->courier_sent_at ?? null);
    return $t ? \Carbon\Carbon::parse($t) : null;
  }
  public static function hours(object $o): ?int { $s = self::since($o); return $s ? (int)floor($s->diffInMinutes(now(), false) / 60) : null; }
  /** "Late by 2d 6h" / "Late by 4h" from hours in shipment. */
  public static function lateBy(int $hours): string {
    $l = max(0, $hours - self::HOURS); $d = intdiv($l, 24); $h = $l % 24;
    return 'Late by '.($d > 0 ? $d.'d '.$h.'h' : $h.'h');
  }
  /** Cheap enough to call from a page load; runs at most once every two minutes. */
  public static function auto(): void { if (Cache::add('late.delivery.auto', 1, 120)) { try { self::detect(); } catch (\Throwable $e) { report($e); } } }
  /** @return int orders changed */
  public static function detect(): int { $was = OrderActivity::$system; OrderActivity::$system = true; try { return self::run(); } finally { OrderActivity::$system = $was; } }
  private static function run(): int {
    $cut = now()->subHours(self::HOURS); $n = 0;
    $due = DB::table('orders')->where('status', 'Shipment')
      ->where(fn($q) => $q->where('shipment_at', '<=', $cut)->orWhere(fn($w) => $w->whereNull('shipment_at')->where('courier_sent_at', '<=', $cut)))
      ->where(fn($q) => $q->whereNull('courier_stage')->orWhereNotIn('courier_stage', self::SKIP_STAGES))
      ->orderByRaw('coalesce(shipment_at, courier_sent_at) asc')->limit(500)->get(['id', 'invoice', 'shipment_at', 'courier_sent_at']);
    foreach ($due as $o) {
      $s = self::since($o); if (!$s) continue; $late = $s->copy()->addHours(self::HOURS); $now = now();
      $hit = DB::table('orders')->where('id', $o->id)->where('status', 'Shipment')->update(['status' => 'Late Delivery', 'updated_at' => $now]); if (!$hit) continue; $n++;
      $note = 'Shipment age crossed '.self::HOURS.' hours. Shipment Time: '.$s->timezone('Asia/Dhaka')->format('d M Y h:i A').'. Late Since: '.$late->timezone('Asia/Dhaka')->format('d M Y h:i A').'.';
      DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'System', 'from_status' => 'Shipment', 'to_status' => 'Late Delivery', 'note' => 'Late Delivery Detected. '.$note, 'created_at' => $now, 'updated_at' => $now]);
      OrderActivity::log((int)$o->id, 'late_delivery', 'Shipment', 'Late Delivery', 'Late Delivery Detected. '.$note, ['shipment_at' => $s->toIso8601String(), 'late_since' => $late->toIso8601String()]);
    }
    // not late (yet): back to Shipment
    $back = DB::table('orders')->where('status', 'Late Delivery')->whereNotNull('consignment_id')
      ->whereRaw('coalesce(shipment_at, courier_sent_at) > ?', [$cut])->limit(200)->get(['id', 'shipment_at', 'courier_sent_at']);
    foreach ($back as $o) {
      $now = now(); $hit = DB::table('orders')->where('id', $o->id)->where('status', 'Late Delivery')->update(['status' => 'Shipment', 'updated_at' => $now]); if (!$hit) continue; $n++;
      DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'System', 'from_status' => 'Late Delivery', 'to_status' => 'Shipment', 'note' => 'Shipment age is under '.self::HOURS.' hours, so it is not late', 'created_at' => $now, 'updated_at' => $now]);
    }
    return $n;
  }
}
