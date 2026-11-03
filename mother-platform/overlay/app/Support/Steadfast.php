<?php
namespace App\Support;
use Illuminate\Support\Facades\Http;

/** Steadfast (Packzy) merchant API v1 (headers Api-Key / Secret-Key). Uses the same keys as the Fraud Check page. */
class Steadfast {
  const BASE = 'https://portal.packzy.com/api/v1';
  public static function key(): string { return trim((string)Sms::secret('fc_steadfast_key')); }
  public static function secret(): string { return trim((string)Sms::secret('fc_steadfast_secret')); }
  public static function configured(): bool { return self::key() !== '' && self::secret() !== ''; }
  public static function call(string $method, string $path, ?array $json = null): array {
    if (!self::configured()) return ['code' => 0, 'json' => [], 'err' => 'Steadfast keys are not saved yet (Fraud Check page).'];
    try {
      $r = Http::timeout(25)->acceptJson()->withHeaders(['Api-Key' => self::key(), 'Secret-Key' => self::secret(), 'Content-Type' => 'application/json'])->send($method, self::BASE . $path, $json === null ? [] : ['json' => $json]);
      return ['code' => $r->status(), 'json' => $r->json() ?: []];
    } catch (\Throwable $e) { return ['code' => 0, 'json' => [], 'err' => 'Could not reach Steadfast: ' . $e->getMessage()]; }
  }
  public static function phone(string $p): string { $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '880')) $d = '0' . substr($d, 3); return substr($d, -11); }
  public static function balance(): array { return self::call('GET', '/get_balance'); }
  /** @return array{bool,string,array} */
  public static function send(object $o, $items): array {
    if ($o->consignment_id) return [false, 'Already sent to a courier: ' . $o->consignment_id, []];
    $addr = trim((string)$o->address); if (mb_strlen($addr) < 10) $addr = trim($addr . ' ' . ($o->district ?? '') . ' Bangladesh');
    if (mb_strlen($addr) < 10) return [false, 'Address is too short for Steadfast.', []];
    $phone = self::phone((string)$o->phone); if (strlen($phone) !== 11) return [false, 'Customer phone must be 11 digits.', []];
    $p = ['invoice' => (string)$o->invoice, 'recipient_name' => mb_substr(trim((string)$o->customer_name), 0, 100), 'recipient_phone' => $phone, 'recipient_address' => mb_substr($addr, 0, 250), 'cod_amount' => (int)round($o->total),
      'note' => mb_substr((string)$o->note, 0, 200), 'item_description' => mb_substr(collect($items)->map(fn($i) => $i->name . ' x' . $i->qty)->implode(', '), 0, 250), 'total_lot' => max(1, (int)collect($items)->sum('qty')), 'delivery_type' => 0];
    $r = self::call('POST', '/create_order', $p); $c = $r['json']['consignment'] ?? [];
    if ($r['code'] === 200 && !empty($c['consignment_id'])) return [true, 'Sent to Steadfast. Consignment ' . $c['consignment_id'] . ' (tracking ' . ($c['tracking_code'] ?? '-') . ')', ['consignment_id' => (string)$c['consignment_id'], 'tracking_code' => isset($c['tracking_code']) ? (string)$c['tracking_code'] : null]];
    $msg = $r['err'] ?? ($r['json']['message'] ?? 'Steadfast did not accept the order'); if (!empty($r['json']['errors'])) $msg .= ': ' . collect($r['json']['errors'])->flatten()->take(3)->implode('; ');
    return [false, $msg . ' (HTTP ' . $r['code'] . ')', []];
  }
  /** Parcel status in the shape CourierSync::apply expects. */
  public static function info(string $cid): array {
    $r = self::call('GET', '/status_by_cid/' . rawurlencode($cid)); $s = $r['json']['delivery_status'] ?? null;
    return $s ? ['order_status' => str_replace('_', ' ', (string)$s)] : [];
  }
}
