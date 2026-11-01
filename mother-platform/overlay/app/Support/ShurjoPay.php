<?php
namespace App\Support;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
/** ShurjoPay direct API. Credentials live in the settings table (password encrypted); nothing is hard-coded. */
class ShurjoPay {
  public static function get(string $k, $d = null) { try { $v = DB::table('settings')->where('key', 'sp_'.$k)->value('value'); return $v === null || $v === '' ? $d : $v; } catch (\Throwable $e) { return $d; } }
  public static function put(string $k, ?string $v): void { DB::table('settings')->updateOrInsert(['key' => 'sp_'.$k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function password(): string { $v = self::get('password'); if (!$v) return ''; try { return Crypt::decryptString($v); } catch (\Throwable $e) { return ''; } }
  public static function live(): bool { return self::get('mode', 'sandbox') === 'live'; }
  public static function base(): string { return self::live() ? 'https://engine.shurjopayment.com/api' : 'https://sandbox.shurjopayment.com/api'; }
  public static function ready(): bool { return self::get('username') && self::password() && self::get('prefix'); }
  public static function enabled(): bool { return self::get('enabled') === '1' && self::ready(); }
  /** @return array{token:string,store_id:mixed}|null */
  public static function token(&$err = null): ?array {
    try {
      $r = Http::timeout(20)->acceptJson()->post(self::base().'/get_token', ['username' => self::get('username'), 'password' => self::password()]);
      $j = $r->json();
      if (!empty($j['token'])) return ['token' => $j['token'], 'store_id' => $j['store_id'] ?? null];
      $err = (string)($j['message'] ?? 'Authentication failed ('.$r->status().')');
    } catch (\Throwable $e) { $err = 'Could not reach ShurjoPay'; }
    return null;
  }
  /** Creates a payment and returns [checkout_url, sp_order_id] or [null, error]. */
  public static function create(object $o, string $returnUrl, string $cancelUrl, string $ip): array {
    $t = self::token($err); if (!$t) return [null, $err];
    try {
      $r = Http::timeout(25)->withToken($t['token'])->acceptJson()->post(self::base().'/secret-pay', [
        'prefix' => self::get('prefix'), 'token' => $t['token'], 'return_url' => $returnUrl, 'cancel_url' => $cancelUrl, 'store_id' => $t['store_id'],
        'amount' => round((float)$o->total, 2), 'order_id' => $o->invoice, 'currency' => 'BDT',
        'customer_name' => mb_substr((string)$o->customer_name, 0, 100), 'customer_address' => mb_substr((string)$o->address, 0, 250),
        'customer_email' => $o->email ?: 'noreply@dutyfreeshopbd.com', 'customer_phone' => $o->phone, 'customer_city' => mb_substr((string)($o->district ?: 'Dhaka'), 0, 60),
        'customer_post_code' => '1000', 'client_ip' => $ip,
      ]);
      $j = $r->json();
      if (!empty($j['checkout_url'])) return [$j['checkout_url'], (string)($j['sp_order_id'] ?? '')];
      return [null, (string)($j['message'] ?? 'Payment could not be started ('.$r->status().')')];
    } catch (\Throwable $e) { return [null, 'Could not reach ShurjoPay']; }
  }
  /** Server-side verification. Returns the first transaction row or null. */
  public static function verify(string $spOrderId): ?array {
    $t = self::token($err); if (!$t) return null;
    try {
      $r = Http::timeout(25)->withToken($t['token'])->acceptJson()->post(self::base().'/verification', ['order_id' => $spOrderId]);
      $j = $r->json();
      if (is_array($j) && isset($j[0]) && is_array($j[0])) return $j[0];
      if (is_array($j) && isset($j['sp_code'])) return $j;
    } catch (\Throwable $e) {}
    return null;
  }
}
