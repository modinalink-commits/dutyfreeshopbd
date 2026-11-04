<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** What a courier is told about the parcel contents. By owner decision no product names or bill details go to the couriers: only the amount to collect (due), the order note, the customer and the parcel count are sent. A settings row courier_send_products = 1 would bring the product names back. */
class CourierDesc {
  public static function sendProducts(): bool {
    try { return (string)DB::table('settings')->where('key', 'courier_send_products')->value('value') === '1'; } catch (\Throwable $e) { return false; }
  }
  /** Parcel description text (couriers require the field, so a neutral word is used). */
  public static function text($items, int $max = 250): string {
    if (self::sendProducts()) return mb_substr(collect($items)->map(fn($i) => $i->name . ' x' . $i->qty)->implode(', '), 0, $max);
    return 'Parcel';
  }
}
