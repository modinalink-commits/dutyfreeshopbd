<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** RedX Open API v1.0.0-beta (header API-ACCESS-TOKEN: Bearer <token>). Token lives encrypted in integration_secrets. */
class Redx {
  public static function env(): string { return Sms::secret('redx_env') === 'sandbox' ? 'sandbox' : 'live'; }
  public static function base(): string { return self::env() === 'sandbox' ? 'https://sandbox.redx.com.bd/v1.0.0-beta' : 'https://openapi.redx.com.bd/v1.0.0-beta'; }
  public static function token(): string { return trim((string)Sms::secret('redx_token')); }
  public static function configured(): bool { return self::token() !== ''; }
  public static function storeId(): ?string { return Sms::secret('redx_store_id') ?: null; }
  public static function call(string $method, string $path, ?array $json = null): array {
    if (!self::configured()) return ['code' => 0, 'json' => [], 'err' => 'RedX token is not saved yet.'];
    $t = self::token(); if (!str_starts_with(strtolower($t), 'bearer ')) $t = 'Bearer ' . $t;
    try {
      $r = Http::timeout(25)->acceptJson()->withHeaders(['API-ACCESS-TOKEN' => $t])->send($method, self::base() . $path, $json === null ? [] : ['json' => $json]);
      return ['code' => $r->status(), 'json' => $r->json() ?: []];
    } catch (\Throwable $e) { return ['code' => 0, 'json' => [], 'err' => 'Could not reach RedX: ' . $e->getMessage()]; }
  }
  public static function stores(): array { $r = self::call('GET', '/pickup/stores'); return $r['json']['pickup_stores'] ?? []; }
  public static function phone(string $p): string { $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '880')) $d = '0' . substr($d, 3); return substr($d, -11); }
  /** RedX needs a delivery area id: pick the area of the order's district whose name appears in the address, else the first of that district. */
  public static function area(string $district, string $addr): ?array {
    $district = trim($district); if ($district === '') return null;
    $alias = ['chattogram' => 'Chittagong', 'cumilla' => 'Comilla', 'barishal' => 'Barisal', 'bogura' => 'Bogra', 'jashore' => 'Jessore', 'chapainawabganj' => 'Nawabganj', 'brahmanbaria' => 'Brahmanbaria', 'coxs bazar' => "Cox's Bazar"];
    $tries = array_values(array_unique(array_filter([$district, $alias[mb_strtolower($district)] ?? null])));
    $list = [];
    foreach ($tries as $nm) { $list = Cache::remember('redx.areas.' . mb_strtolower($nm), 86400, function () use ($nm) { $r = self::call('GET', '/areas?district_name=' . rawurlencode($nm)); return $r['code'] === 200 ? ($r['json']['areas'] ?? []) : []; }); if ($list) break; Cache::forget('redx.areas.' . mb_strtolower($nm)); }
    if (!$list) return null; $a = mb_strtolower($addr); $best = null;
    foreach ($list as $x) { $n = mb_strtolower(preg_replace('/\(.*?\)/u', '', (string)$x['name'])); $n = trim($n); if ($n !== '' && mb_strpos($a, $n) !== false && (!$best || mb_strlen($n) > mb_strlen($best['n']))) $best = ['n' => $n, 'x' => $x]; }
    if (!$best) return null; /* never guess: a wrong area sends the parcel to the wrong hub */
    $x = $best['x']; return ['id' => (int)$x['id'], 'name' => (string)$x['name']];
  }
  /** @return array{bool,string,array} */
  public static function send(object $o, $items): array {
    $store = self::storeId(); if (!$store) return [false, 'Choose a RedX pickup store first (RedX setup page).', []];
    if ($o->consignment_id) return [false, 'Already sent to a courier: ' . $o->consignment_id, []];
    $addr = trim((string)$o->address); if (mb_strlen($addr) < 5) return [false, 'Address is too short for RedX.', []];
    $phone = self::phone((string)$o->phone); if (strlen($phone) !== 11) return [false, 'Customer phone must be 11 digits.', []];
    $area = self::area((string)($o->district ?? ''), trim($addr . ' ' . ($o->sub_city ?? ''))); if (!$area) return [false, 'RedX delivery area could not be matched for "' . ($o->district ?? '') . '". Put the thana / area name (as RedX spells it, e.g. Kotwali, Agrabad, Khulshi) in the address or Sub City field, then send again.', []];
    $total = (int)round($o->total);
    $p = ['customer_name' => mb_substr(trim((string)$o->customer_name), 0, 100), 'customer_phone' => $phone, 'delivery_area' => $area['name'], 'delivery_area_id' => $area['id'], 'customer_address' => mb_substr($addr, 0, 250), 'merchant_invoice_id' => (string)$o->invoice,
      'cash_collection_amount' => (string)$total, 'parcel_weight' => (int)round(((float)(Sms::secret('redx_weight') ?: 0.5)) * 1000), 'instruction' => mb_substr((string)$o->note, 0, 200), 'value' => $total, 'pickup_store_id' => (int)$store,
      'parcel_details_json' => collect($items)->map(fn($i) => ['name' => mb_substr((string)$i->name, 0, 100), 'category' => 'general', 'value' => (int)round($i->price * $i->qty)])->values()->all()];
    $r = self::call('POST', '/parcel', $p);
    if (in_array($r['code'], [200, 201], true) && !empty($r['json']['tracking_id'])) return [true, 'Sent to RedX. Tracking ' . $r['json']['tracking_id'] . ' (area ' . $area['name'] . ')', ['consignment_id' => $r['json']['tracking_id']]];
    $msg = $r['err'] ?? ($r['json']['message'] ?? 'RedX did not accept the order'); if (!empty($r['json']['validation_errors'])) $msg .= ': ' . collect($r['json']['validation_errors'])->flatten()->take(3)->implode('; ');
    return [false, $msg . ' (HTTP ' . $r['code'] . ')', []];
  }
  const MAP = ['ready-for-delivery' => 'Picked Up', 'delivery-in-progress' => 'Out for Delivery', 'delivered' => 'Delivered', 'agent-hold' => 'Hold', 'agent-returning' => 'Hold', 'agent-area-change' => 'Hold', 'returned' => 'Returned', 'paid' => 'Delivered', 'cancelled' => 'Cancelled'];
  public static function label(string $raw): string { $k = strtolower(trim($raw)); return (self::MAP[$k] ?? ucfirst(str_replace('-', ' ', $k))) . ' (' . $k . ')'; }
  /** Parcel status in the shape CourierSync::apply expects. */
  public static function info(string $cid): array {
    $r = self::call('GET', '/parcel/info/' . rawurlencode($cid)); $d = $r['json']['parcel'] ?? [];
    if (empty($d['status'])) return [];
    return ['order_status' => self::label((string)$d['status']), 'delivery_fee' => $d['charge'] ?? null];
  }
}
