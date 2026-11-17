<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Courier rider of a parcel (owner, 17 Nov 2026). Pathao's parcel tracking names the delivery man and his number once the parcel is
 * "Assigned For Delivery" ("Assigned to <name> (01XXXXXXXXX) for delivery."). The name and number are kept on the order, so a call
 * from that number (PBX or WhatsApp) is shown as the rider of that order instead of an unknown caller. Read only towards Pathao.
 */
class Rider {
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasColumn('orders', 'rider_phone'); } catch (\Throwable $e) { $r = false; } } return $r; }

  /** [name, phone] from a tracking answer, or null. The last "for delivery" assignment wins (a parcel can be re-assigned). */
  public static function parse(?array $data): ?array {
    $name = null; $phone = null;
    foreach ((array)($data['log'] ?? []) as $l) { $d = (string)($l['desc'] ?? '');
      if (preg_match('/Assigned to\s+(.+?)\s*\(\s*(?:\+?88)?(01\d{9})\s*\)\s*for delivery/iu', $d, $m)) { $name = trim($m[1]); $phone = $m[2]; } }
    if (!$phone) return null;
    return [mb_substr($name ?: (string)($data['order']['agent']['name'] ?? 'Rider'), 0, 120), $phone];
  }
  /** Called when Pathao reports a status for a parcel. Asks the tracking only for "Assigned For Delivery", at most once in 15 minutes per order. Never throws. */
  public static function maybe(object $o, string $rawStatus): void {
    try {
      if (!self::ready() || !preg_match('/assigned\s*for\s*delivery/i', str_replace('_', ' ', $rawStatus))) return;
      if (empty($o->consignment_id) || (($o->courier ?? '') ?: 'Pathao') !== 'Pathao') return;
      if (!empty($o->rider_phone) && !empty($o->rider_at) && strtotime((string)$o->rider_at) > time() - 7200) return;
      if (!Cache::add('rider.try.' . $o->id, 1, 900)) return;
      self::fetch($o);
    } catch (\Throwable $e) { report($e); }
  }
  public static function fetch(object $o): ?array {
    $ph = substr(preg_replace('/\D/', '', (string)($o->phone ?? '')), -11); if (strlen($ph) !== 11 || empty($o->consignment_id)) return null;
    $x = Http::timeout(8)->acceptJson()->post('https://merchant.pathao.com/api/v1/user/tracking', ['consignment_id' => (string)$o->consignment_id, 'phone_no' => $ph]);
    $r = $x->ok() ? self::parse((array)($x->json('data') ?? [])) : null; if (!$r) return null;
    DB::table('orders')->where('id', $o->id)->update(['rider_name' => $r[0], 'rider_phone' => $r[1], 'rider_at' => now()]);
    return $r;
  }
  /** The rider behind a phone number: his latest parcel of the last 30 days. Null when the number is no known rider. */
  public static function match(?string $phone): ?array {
    $d = substr(preg_replace('/\D/', '', (string)$phone), -11); if (!self::ready() || strlen($d) !== 11) return null;
    try {
      $o = DB::table('orders')->where('rider_phone', $d)->where('rider_at', '>', now()->subDays(30))->orderByDesc('rider_at')->first(['id', 'invoice', 'customer_name', 'rider_name', 'courier', 'status']); if (!$o) return null;
      $n = (int)DB::table('orders')->where('rider_phone', $d)->where('rider_at', '>', now()->subDays(2))->count();
      return ['name' => (string)$o->rider_name, 'courier' => (string)(($o->courier ?? '') ?: 'Pathao'), 'order_id' => (int)$o->id, 'invoice' => Inv::show($o->invoice), 'customer' => (string)$o->customer_name, 'status' => (string)$o->status, 'parcels' => $n];
    } catch (\Throwable $e) { return null; }
  }
}
