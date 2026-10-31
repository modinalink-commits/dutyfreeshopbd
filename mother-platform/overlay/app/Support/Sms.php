<?php
namespace App\Support;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
/** BulkSMSBD gateway. The API key is stored encrypted in integration_secrets and never shown again. */
class Sms {
  const API = 'https://bulksmsbd.net/api/smsapi';
  const BAL = 'https://bulksmsbd.net/api/getBalanceApi';
  const CODES = [202=>'Submitted',1001=>'Invalid number',1002=>'Sender ID incorrect or disabled',1003=>'Required fields missing',1005=>'Internal error at gateway',1006=>'Balance validity not available',1007=>'Insufficient balance',1011=>'User not found',1012=>'Masking SMS must be Bengali text',1013=>'No gateway for this sender ID',1014=>'Sender type not found',1015=>'No valid gateway',1018=>'Account disabled',1031=>'Account not verified',1032=>'Server IP not whitelisted in BulkSMSBD'];
  private static bool $hook = false;
  public static function secret(string $k): ?string {
    $v = DB::table('integration_secrets')->where('key',$k)->value('value'); if (!$v) return null;
    try { return Crypt::decryptString($v); } catch (\Throwable $e) { return null; }
  }
  public static function setSecret(string $k, ?string $v): void {
    DB::table('integration_secrets')->updateOrInsert(['key'=>$k], ['value'=>$v === null || $v === '' ? null : Crypt::encryptString($v), 'updated_at'=>now(), 'created_at'=>now()]);
  }
  public static function configured(): bool { return (bool) (self::secret('sms_api_key') && self::secret('sms_sender_id')); }
  public static function number(string $p): ?string {
    $d = preg_replace('/\D/', '', $p);
    if (str_starts_with($d, '8801') && strlen($d) === 13) return $d;
    if (str_starts_with($d, '01') && strlen($d) === 11) return '88'.$d;
    if (str_starts_with($d, '1') && strlen($d) === 10) return '880'.$d;
    return null;
  }
  /** Save to the SMS log and send right after the response is delivered. */
  public static function queue(string $to, string $body, string $event): void {
    DB::table('sms_logs')->insert(['to'=>$to,'body'=>$body,'event'=>$event,'status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
    if (!self::$hook && self::configured()) { self::$hook = true; app()->terminating(fn() => self::flush(25)); }
  }
  public static function flush(int $limit = 25): int {
    if (!self::configured()) return 0;
    $n = 0;
    foreach (DB::table('sms_logs')->where('status','queued')->orderBy('id')->limit($limit)->get() as $row) {
      $claim = DB::table('sms_logs')->where('id',$row->id)->where('status','queued')->update(['status'=>'sending']);
      if (!$claim) continue;
      [$ok, $msg] = self::send($row->to, $row->body);
      DB::table('sms_logs')->where('id',$row->id)->update(['status'=>$ok ? 'sent' : 'failed', 'provider_response'=>mb_substr($msg,0,500), 'sent_at'=>$ok ? now() : null, 'updated_at'=>now()]);
      $n++;
    }
    return $n;
  }
  /** @return array{bool,string} */
  public static function send(string $to, string $body): array {
    $num = self::number($to); if (!$num) return [false, 'Invalid phone number'];
    $res = self::post(['api_key'=>self::secret('sms_api_key'), 'senderid'=>self::secret('sms_sender_id'), 'number'=>$num, 'message'=>$body]);
    $code = (int)($res['response_code'] ?? 0);
    return [$code === 202, $code.' '.(self::CODES[$code] ?? ($res['error_message'] ?? $res['success_message'] ?? 'No response'))];
  }
  public static function balance(): string {
    $k = self::secret('sms_api_key'); if (!$k) return 'API key not set';
    $r = self::http(self::BAL.'?api_key='.rawurlencode($k), null);
    $j = json_decode((string)$r, true);
    if (is_array($j) && isset($j['balance'])) return (string)$j['balance'];
    if (is_array($j) && isset($j['response_code'])) return ($j['response_code']).' '.(self::CODES[(int)$j['response_code']] ?? ($j['error_message'] ?? ''));
    return $r ? mb_substr(strip_tags($r), 0, 120) : 'No response from gateway';
  }
  private static function post(array $f): array { $r = self::http(self::API, http_build_query($f)); $j = json_decode((string)$r, true); return is_array($j) ? $j : ['response_code'=>0, 'error_message'=>$r ? mb_substr(strip_tags($r),0,120) : 'No response from gateway']; }
  private static function http(string $url, ?string $body): ?string {
    $o = ['http'=>['method'=>$body === null ? 'GET' : 'POST','timeout'=>15,'ignore_errors'=>true,'header'=>"User-Agent: MotherCommerce/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\n"],'ssl'=>['verify_peer'=>true]];
    if ($body !== null) $o['http']['content'] = $body;
    $r = @file_get_contents($url, false, stream_context_create($o)); return $r === false ? null : $r;
  }
}
