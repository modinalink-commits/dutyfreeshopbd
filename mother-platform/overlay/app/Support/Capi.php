<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Meta Conversions API (server-side events). Needs the access token typed by the owner in /admin/pixel; no token = silently off. */
class Capi
{
  const VER = 'v19.0';
  public static function pixelId(): string { return trim((string)(\App\Http\Controllers\StoreController::set()['pixel_id'] ?? '')); }
  public static function enabled(): bool { return self::pixelId() !== '' && (bool)Sms::secret('meta_capi_token'); }
  private static function h(?string $v): ?string { $v = trim((string)$v); return $v === '' ? null : hash('sha256', mb_strtolower($v)); }

  /** Hashed customer data per Meta rules (phone as 880XXXXXXXXXX digits, lower-case name/email/city). */
  public static function user(array $c, ?string $fbp, ?string $fbc, ?string $ip, ?string $ua): array {
    $d = substr(preg_replace('/\D/', '', (string)($c['phone'] ?? '')), -10); $nm = preg_split('/\s+/', trim(mb_strtolower((string)($c['name'] ?? ''))), 2);
    $u = ['em' => [self::h($c['email'] ?? null)], 'ph' => [self::h($d !== '' ? '880' . $d : null)], 'fn' => [self::h($nm[0] ?? null)], 'ln' => [self::h($nm[1] ?? null)],
      'ct' => [self::h(preg_replace('/[^a-z]/', '', mb_strtolower((string)($c['city'] ?? ''))))], 'country' => [self::h('bd')], 'external_id' => [self::h((string)($c['external_id'] ?? ''))]];
    $u = array_filter(array_map(fn($a) => array_values(array_filter($a)), $u));
    if ($fbp) $u['fbp'] = $fbp; if ($fbc) $u['fbc'] = $fbc; if ($ip) $u['client_ip_address'] = $ip; if ($ua) $u['client_user_agent'] = $ua;
    return $u;
  }

  public static function send(string $event, string $eventId, array $user, array $custom, string $url, ?int $ts = null, bool $test = false): array {
    $tok = Sms::secret('meta_capi_token'); $pid = self::pixelId(); if (!$tok || $pid === '') return ['ok' => false, 'msg' => 'CAPI not configured'];
    $body = ['data' => [['event_name' => $event, 'event_time' => $ts ?: time(), 'event_id' => $eventId, 'action_source' => 'website', 'event_source_url' => $url, 'user_data' => $user, 'custom_data' => $custom]], 'access_token' => $tok];
    $tc = Sms::secret('meta_capi_test'); if ($test && $tc) $body['test_event_code'] = $tc;
    try { $r = Http::timeout(6)->asJson()->post('https://graph.facebook.com/' . self::VER . '/' . $pid . '/events', $body); $ok = $r->successful(); $msg = $ok ? 'received ' . ($r->json('events_received') ?? '?') : mb_substr((string)($r->json('error.message') ?? $r->body()), 0, 180); }
    catch (\Throwable $e) { $ok = false; $msg = mb_substr($e->getMessage(), 0, 180); }
    self::log($event, $eventId, $ok, $msg); return ['ok' => $ok, 'msg' => $msg];
  }

  private static function log(string $ev, string $id, bool $ok, string $msg): void {
    try { $row = DB::table('settings')->where('key', 'capi_log')->value('value'); $a = json_decode((string)$row, true) ?: [];
      array_unshift($a, ['t' => now('Asia/Dhaka')->format('d M H:i:s'), 'e' => $ev, 'id' => $id, 'ok' => $ok, 'm' => $msg]); $a = array_slice($a, 0, 30);
      DB::table('settings')->updateOrInsert(['key' => 'capi_log'], ['value' => json_encode($a, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]);
    } catch (\Throwable $e) {}
  }

  /** Called right after an order is placed. Runs after the response is sent, so checkout never waits on Meta. event_id = invoice, matching the browser Purchase for de-duplication. */
  public static function purchase(int $orderId, Request $r): void {
    if (!self::enabled()) return;
    $o = DB::table('orders')->where('id', $orderId)->first(); if (!$o) return;
    $its = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $orderId)->get(['i.product_id', 'p.ext_id', 'i.qty', 'i.price']);
    $contents = $its->filter(fn($i) => $i->product_id)->map(fn($i) => ['id' => (string)(($i->ext_id ?? null) ?: $i->product_id), 'quantity' => (int)$i->qty, 'item_price' => (float)$i->price])->values()->all();
    $user = self::user(['phone' => $o->phone, 'name' => $o->customer_name, 'email' => $o->email, 'city' => $o->district, 'external_id' => $o->customer_id], $_COOKIE['_fbp'] ?? null, $_COOKIE['_fbc'] ?? null, $r->ip(), (string)$r->userAgent());
    $custom = ['currency' => 'BDT', 'value' => (float)$o->total, 'content_type' => 'product', 'content_ids' => array_column($contents, 'id'), 'contents' => $contents, 'num_items' => array_sum(array_column($contents, 'quantity')), 'order_id' => $o->invoice];
    $url = url('/order-placed'); $inv = (string)$o->invoice;
    app()->terminating(fn() => self::send('Purchase', $inv, $user, $custom, $url));
  }
}
