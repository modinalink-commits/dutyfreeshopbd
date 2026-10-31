<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Pathao Courier Merchant API (OAuth2). Credentials live encrypted in integration_secrets. */
class Pathao {
  public static function env(): string { return Sms::secret('pathao_env') === 'sandbox' ? 'sandbox' : 'live'; }
  public static function base(): string { return self::env() === 'sandbox' ? 'https://courier-api-sandbox.pathao.com' : 'https://api-hermes.pathao.com'; }
  public static function configured(): bool { return (bool) (Sms::secret('pathao_client_id') && Sms::secret('pathao_client_secret') && Sms::secret('pathao_username') && Sms::secret('pathao_password')); }
  public static function storeId(): ?int { $v = Sms::secret('pathao_store_id'); return $v ? (int)$v : null; }
  private static function http(string $method, string $path, ?array $json, ?string $token): array {
    $h = "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: MotherCommerce/1.0\r\n".($token ? "Authorization: Bearer $token\r\n" : '');
    $o = ['http'=>['method'=>$method,'timeout'=>25,'ignore_errors'=>true,'header'=>$h],'ssl'=>['verify_peer'=>true]];
    if ($json !== null) $o['http']['content'] = json_encode($json, JSON_UNESCAPED_UNICODE);
    $body = @file_get_contents(self::base().$path, false, stream_context_create($o));
    $code = 0; foreach ($http_response_header ?? [] as $l) if (preg_match('#^HTTP/\S+\s+(\d+)#', $l, $m)) $code = (int)$m[1];
    $j = json_decode((string)$body, true);
    return ['code'=>$code, 'json'=>is_array($j) ? $j : [], 'raw'=>(string)$body];
  }
  /** Valid access token, refreshed or re-issued when needed. */
  public static function token(bool $force = false): ?string {
    $exp = (int) Sms::secret('pathao_exp'); $tok = Sms::secret('pathao_access');
    if (!$force && $tok && $exp > time() + 300) return $tok;
    $cid = Sms::secret('pathao_client_id'); $sec = Sms::secret('pathao_client_secret'); $rt = Sms::secret('pathao_refresh');
    $r = null;
    if ($rt) $r = self::http('POST', '/aladdin/api/v1/issue-token', ['client_id'=>$cid,'client_secret'=>$sec,'grant_type'=>'refresh_token','refresh_token'=>$rt], null);
    if (!$r || empty($r['json']['access_token'])) $r = self::http('POST', '/aladdin/api/v1/issue-token', ['client_id'=>$cid,'client_secret'=>$sec,'grant_type'=>'password','username'=>Sms::secret('pathao_username'),'password'=>Sms::secret('pathao_password')], null);
    $j = $r['json']; if (empty($j['access_token'])) return null;
    Sms::setSecret('pathao_access', $j['access_token']); Sms::setSecret('pathao_refresh', $j['refresh_token'] ?? null); Sms::setSecret('pathao_exp', (string)(time() + (int)($j['expires_in'] ?? 3600)));
    return $j['access_token'];
  }
  public static function call(string $method, string $path, ?array $json = null): array {
    $t = self::token(); if (!$t) return ['code'=>401, 'json'=>[], 'raw'=>'', 'err'=>'Could not get an access token. Check client ID, secret, email and password.'];
    $r = self::http($method, $path, $json, $t);
    if ($r['code'] === 401) { $t = self::token(true); if ($t) $r = self::http($method, $path, $json, $t); }
    return $r;
  }
  public static function stores(): array { $r = self::call('GET', '/aladdin/api/v1/stores'); return $r['json']['data']['data'] ?? []; }
  public static function phone(string $p): string { $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '880')) $d = '0'.substr($d, 3); return substr($d, -11); }
  /** @return array{bool,string,array} ok, message, data */
  public static function send(object $o, $items): array {
    $store = self::storeId(); if (!$store) return [false, 'Choose a Pathao pickup store first (Courier setup page).', []];
    if ($o->consignment_id) return [false, 'Already sent to Pathao: '.$o->consignment_id, []];
    $addr = trim((string)$o->address); if (mb_strlen($addr) < 10) $addr = trim($addr.' '.($o->district ?? '').' Bangladesh');
    if (mb_strlen($addr) < 10) return [false, 'Address is too short for Pathao (minimum 10 characters).', []];
    $names = collect($items)->map(fn($i) => $i->name.' x'.$i->qty)->implode(', ');
    $payload = ['store_id'=>$store, 'merchant_order_id'=>$o->invoice, 'recipient_name'=>mb_substr(str_pad($o->customer_name, 3, ' '), 0, 100), 'recipient_phone'=>self::phone($o->phone), 'recipient_address'=>mb_substr($addr, 0, 220), 'delivery_type'=>48, 'item_type'=>2,
      'special_instruction'=>mb_substr((string)$o->note, 0, 200), 'item_quantity'=>max(1, (int)collect($items)->sum('qty')), 'item_weight'=>(float)(Sms::secret('pathao_weight') ?: 0.5), 'item_description'=>mb_substr($names, 0, 250), 'amount_to_collect'=>(int)round($o->total)];
    if (strlen($payload['recipient_phone']) !== 11) return [false, 'Customer phone must be 11 digits.', []];
    $r = self::call('POST', '/aladdin/api/v1/orders', $payload);
    $d = $r['json']['data'] ?? [];
    if ($r['code'] === 200 && !empty($d['consignment_id'])) return [true, 'Sent to Pathao. Consignment '.$d['consignment_id'], $d];
    $msg = $r['err'] ?? ($r['json']['message'] ?? 'Pathao did not accept the order');
    if (!empty($r['json']['errors'])) $msg .= ': '.collect($r['json']['errors'])->flatten()->take(3)->implode('; ');
    return [false, $msg.' (HTTP '.$r['code'].')', []];
  }
  public static function info(string $cid): array { $r = self::call('GET', '/aladdin/api/v1/orders/'.rawurlencode($cid).'/info'); return $r['json']['data'] ?? []; }
}
