<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Normalises courier statuses and keeps orders in step with the courier automatically. */
class CourierSync {
  const STAGES = ['Pending','Pickup Requested','Picked Up','In Transit','At Hub','Out for Delivery','Delivered','Hold','Failed Delivery','Rescheduled','Returned','Cancelled'];
  public static function stage(?string $raw): string {
    $s = strtolower(preg_replace('/[^a-z]+/i', ' ', (string)$raw));
    return match (true) {
      str_contains($s, 'partial') => 'Delivered',
      str_contains($s, 'deliver') && (str_contains($s, 'fail') || str_contains($s, 'unsuccess')) => 'Failed Delivery',
      str_contains($s, 'assigned for delivery') || str_contains($s, 'out for delivery') => 'Out for Delivery',
      str_contains($s, 'delivered') => 'Delivered',
      str_contains($s, 'return') => 'Returned',
      str_contains($s, 'cancel') => 'Cancelled',
      str_contains($s, 'hold') => 'Hold',
      str_contains($s, 'resched') => 'Rescheduled',
      str_contains($s, 'pickup request') || str_contains($s, 'assigned for pickup') => 'Pickup Requested',
      str_contains($s, 'pickup fail') => 'Failed Delivery',
      str_contains($s, 'picked') => 'Picked Up',
      str_contains($s, 'hub') || str_contains($s, 'sorting') => 'At Hub',
      str_contains($s, 'transit') || str_contains($s, 'warehouse') => 'In Transit',
      default => 'Pending',
    };
  }
  /** Order stage that a courier stage should drive (null = leave the order alone). */
  public static function orderStatus(string $stage): ?string {
    return ['Delivered'=>'Delivered','Returned'=>'Returned','Cancelled'=>'Cancel','Failed Delivery'=>'Late Delivery'][$stage] ?? null;
  }
  public static function apply(object $o, array $i): bool {
    $raw = $i['order_status'] ?? $i['order_status_slug'] ?? null; if (!$raw) return false;
    $stage = self::stage($raw); $now = now(); $upd = ['courier_status'=>$raw, 'courier_stage'=>$stage, 'courier_synced_at'=>$now, 'updated_at'=>$now];
    if (isset($i['delivery_fee'])) $upd['courier_fee'] = $i['delivery_fee'];
    if ($stage === 'Delivered') { $upd['collected_amount'] = $o->courier_cod ?? $o->total; $upd['courier_delivered_at'] = $o->courier_delivered_at ?: $now; }
    $changed = $stage !== $o->courier_stage;
    if ($changed) DB::table('courier_events')->insert(['order_id'=>$o->id, 'courier'=>$o->courier ?: 'Pathao', 'status'=>$raw, 'stage'=>$stage, 'location'=>$o->courier_location, 'happened_at'=>$now, 'created_at'=>$now, 'updated_at'=>$now]);
    $new = self::orderStatus($stage);
    if ($new && $o->status !== $new && !in_array($o->status, ['Delivered','Returned','Cancel','Exchange Received'], true)) {
      $upd['status'] = $new;
      DB::table('order_status_logs')->insert(['order_id'=>$o->id, 'user_id'=>null, 'user_name'=>'Courier (auto)', 'from_status'=>$o->status, 'to_status'=>$new, 'note'=>($o->courier ?: 'Pathao').': '.$raw, 'created_at'=>$now, 'updated_at'=>$now]);
    }
    DB::table('orders')->where('id', $o->id)->update($upd);
    return $changed;
  }
  /** Poll open parcels. Returns how many changed. */
  public static function run(int $limit = 40): int {
    if (!Pathao::configured() && !Carrybee::configured() && !Redx::configured() && !Steadfast::configured()) return 0;
    $n = 0;
    $rows = DB::table('orders')->whereNotNull('consignment_id')->where(fn($q) => $q->whereNull('courier_stage')->orWhereNotIn('courier_stage', ['Delivered','Returned','Cancelled']))->orderByRaw('courier_synced_at asc nulls first')->limit($limit)->get();
    foreach ($rows as $o) { $i = $o->courier === 'Steadfast' ? (Steadfast::configured() ? Steadfast::info($o->consignment_id) : null) : ($o->courier === 'RedX' ? (Redx::configured() ? Redx::info($o->consignment_id) : null) : ($o->courier === 'Carrybee' ? (Carrybee::configured() ? Carrybee::info($o->consignment_id) : null) : (Pathao::configured() ? Pathao::info($o->consignment_id) : null))); if ($i && self::apply($o, $i)) $n++; elseif ($i === []) DB::table('orders')->where('id', $o->id)->update(['courier_synced_at'=>now()]); }
    Cache::put('courier.last_sync', now()->toDateTimeString(), 86400);
    return $n;
  }
  /** Courier shown on a new order straight away (the courier marked default, else settings default_courier, else Pathao). Booking the parcel is still a separate step. */
  public static function defaultName(): string {
    return Cache::remember('courier.default', 300, fn() => (string)(DB::table('couriers')->where('is_default', true)->where('active', true)->value('name') ?: DB::table('settings')->where('key', 'default_courier')->value('value') ?: 'Pathao'));
  }
  /** Moving an order to Shipment books the parcel with its courier (like the old site). Returns null when nothing to do, [ok, message] otherwise. */
  public static function autoBook(int $id): ?array {
    $o = DB::table('orders')->find($id); if (!$o || $o->consignment_id) return null;
    $name = $o->courier ?: self::defaultName();
    [$cls, $ready] = match ($name) {
      'Pathao' => [\App\Http\Controllers\CourierController::class, Pathao::configured()],
      'RedX' => [\App\Http\Controllers\RedxController::class, Redx::configured()],
      'Carrybee' => [\App\Http\Controllers\CarrybeeController::class, Carrybee::configured()],
      'Steadfast' => [\App\Http\Controllers\SteadfastController::class, Steadfast::configured()],
      default => [null, false],
    };
    if (!$cls || !$ready) return [false, $name.' is not connected, parcel not booked'];
    try { return app($cls)->book($id); } catch (\Throwable $e) { report($e); return [false, $name.' booking error, send it manually']; }
  }
  /** Cheap automatic refresh: at most every 10 minutes, after the response is sent. */
  public static function auto(): void {
    if ((!Pathao::configured() && !Carrybee::configured() && !Redx::configured() && !Steadfast::configured()) || !Cache::add('courier.auto', 1, 600)) return;
    app()->terminating(fn() => rescue(fn() => self::run(25), null, false));
  }
}
