<?php
namespace App\Support;
use Illuminate\Support\Facades\Http;

/** Carrybee Delivery API v2 (headers Client-ID / Client-Secret / Client-Context). Credentials live encrypted in integration_secrets. */
class Carrybee {
  public static function env(): string { return Sms::secret('carrybee_env') === 'sandbox' ? 'sandbox' : 'live'; }
  public static function base(): string { return self::env() === 'sandbox' ? 'https://sandbox.carrybee.com' : 'https://developers.carrybee.com'; }
  public static function configured(): bool { return (bool)(Sms::secret('carrybee_client_id') && Sms::secret('carrybee_client_secret') && Sms::secret('carrybee_client_context')); }
  public static function storeId(): ?string { return Sms::secret('carrybee_store_id') ?: null; }
  public static function phone(string $p): string { $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '880')) $d = '0' . substr($d, 3); return substr($d, -11); }
  public static function call(string $method, string $path, ?array $json = null): array {
    if (!self::configured()) return ['code' => 0, 'json' => [], 'err' => 'Carrybee credentials are not saved yet.'];
    try {
      $r = Http::timeout(25)->acceptJson()->withHeaders(['Client-ID' => (string)Sms::secret('carrybee_client_id'), 'Client-Secret' => (string)Sms::secret('carrybee_client_secret'), 'Client-Context' => (string)Sms::secret('carrybee_client_context'), 'Content-Type' => 'application/json'])
        ->send($method, self::base() . $path, $json === null ? [] : ['json' => $json]);
      return ['code' => $r->status(), 'json' => $r->json() ?: []];
    } catch (\Throwable $e) { return ['code' => 0, 'json' => [], 'err' => 'Could not reach Carrybee: ' . $e->getMessage()]; }
  }
  public static function stores(): array { $r = self::call('GET', '/api/v2/stores'); return $r['json']['data']['stores'] ?? []; }
  /** @return array{bool,string,array} ok, message, data */
  public static function send(object $o, $items): array {
    $store = self::storeId(); if (!$store) return [false, 'Choose a Carrybee pickup store first (Carrybee setup page).', []];
    if ($o->consignment_id) return [false, 'Already sent to a courier: ' . $o->consignment_id, []];
    $addr = trim((string)$o->address); if (mb_strlen($addr) < 10) $addr = trim($addr . ' ' . ($o->district ?? '') . ' Bangladesh');
    if (mb_strlen($addr) < 10) return [false, 'Address is too short for Carrybee (minimum 10 characters).', []];
    $phone = self::phone((string)$o->phone); if (strlen($phone) !== 11) return [false, 'Customer phone must be 11 digits.', []];
    $cod = (int)round(PaymentSettle::cod($o)); if ($cod > 100000) return [false, 'Carrybee allows a collectable amount up to ৳100,000 only.', []];
    $names = collect($items)->map(fn($i) => $i->name . ' x' . $i->qty)->implode(', ');
    $p = ['store_id' => $store, 'merchant_order_id' => mb_substr(Inv::show($o->invoice), 0, 49), 'delivery_type' => 1, 'product_type' => 1, 'recipient_phone' => $phone, 'recipient_name' => mb_substr(str_pad(trim((string)$o->customer_name), 2, ' '), 0, 99),
      'recipient_address' => mb_substr($addr, 0, 200), 'special_instruction' => mb_substr((string)$o->note, 0, 250), 'product_description' => mb_substr($names, 0, 250),
      'item_weight' => max(1, min(25000, (int)round(((float)(Sms::secret('carrybee_weight') ?: 0.5)) * 1000))), 'item_quantity' => max(1, min(200, (int)collect($items)->sum('qty'))), 'collectable_amount' => $cod];
    $r = self::call('POST', '/api/v2/orders', $p);
    $d = $r['json']['data']['order'] ?? [];
    if ($r['code'] === 201 && !empty($d['consignment_id'])) return [true, 'Sent to Carrybee. Consignment ' . $d['consignment_id'], ['consignment_id' => $d['consignment_id'], 'delivery_fee' => $d['delivery_fee'] ?? null, 'order_status' => 'Pending']];
    $msg = $r['err'] ?? ($r['json']['message'] ?? 'Carrybee did not accept the order');
    if (!empty($r['json']['causes'])) $msg .= ': ' . collect($r['json']['causes'])->flatten()->take(3)->implode('; ');
    return [false, $msg . ' (HTTP ' . $r['code'] . ')', []];
  }
  /** Parcel status in the shape CourierSync::apply expects. */
  public static function info(string $cid): array {
    $r = self::call('GET', '/api/v2/orders/' . rawurlencode($cid) . '/details'); $d = $r['json']['data']['order'] ?? ($r['json']['data'] ?? []);
    if (!$d || empty($d['transfer_status'])) return [];
    return ['order_status' => (string)$d['transfer_status'], 'delivery_fee' => $d['delivery_fee'] ?? null];
  }
}
