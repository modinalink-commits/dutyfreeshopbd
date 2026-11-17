<?php
namespace App\Support;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
/** Central SMS service. Providers (Alpha SMS / BulkSMSBD), fall-back and the attempt record are in SmsGate. API keys are stored encrypted in integration_secrets and never shown again. */
class Sms {
  const API = 'https://bulksmsbd.net/api/smsapi';
  const BAL = 'https://bulksmsbd.net/api/getBalanceApi';
  const CODES = [202=>'Submitted',1001=>'Invalid number',1002=>'Sender ID incorrect or disabled',1003=>'Required fields missing',1005=>'Internal error at gateway',1006=>'Balance validity not available',1007=>'Insufficient balance',1011=>'User not found',1012=>'Masking SMS must be Bengali text',1013=>'No gateway for this sender ID',1014=>'Sender type not found',1015=>'No valid gateway',1018=>'Account disabled',1031=>'Account not verified',1032=>'Server IP not whitelisted in BulkSMSBD'];
  private static bool $hook = false;
  private static ?array $secMemo = null;
  /** All secrets are read once per request (one query) instead of one query per lookup. */
  public static function secret(string $k): ?string {
    if (self::$secMemo === null) { self::$secMemo = []; try { foreach (DB::table('integration_secrets')->pluck('value', 'key') as $kk => $vv) self::$secMemo[$kk] = $vv; } catch (\Throwable $e) { self::$secMemo = null; $v = DB::table('integration_secrets')->where('key',$k)->value('value'); if (!$v) return null; try { return Crypt::decryptString($v); } catch (\Throwable $e2) { return null; } } }
    $v = self::$secMemo[$k] ?? null; if (!$v) return null;
    try { return Crypt::decryptString($v); } catch (\Throwable $e) { return null; }
  }
  public static function setSecret(string $k, ?string $v): void {
    self::$secMemo = null;
    DB::table('integration_secrets')->updateOrInsert(['key'=>$k], ['value'=>$v === null || $v === '' ? null : Crypt::encryptString($v), 'updated_at'=>now(), 'created_at'=>now()]);
  }
  /** True when at least one provider can send (see SmsGate: primary / fall-back selection). */
  public static function configured(): bool { return SmsGate::anyReady(); }
  public static function number(string $p): ?string {
    $d = preg_replace('/\D/', '', $p);
    if (str_starts_with($d, '8801') && strlen($d) === 13) return $d;
    if (str_starts_with($d, '01') && strlen($d) === 11) return '88'.$d;
    if (str_starts_with($d, '1') && strlen($d) === 10) return '880'.$d;
    return null;
  }
  /** Save to the SMS log and send right after the response is delivered. */
  public static function queue(string $to, string $body, string $event, array $extra = []): void {
    DB::table('sms_logs')->insert(['to'=>$to,'body'=>$body,'event'=>$event,'status'=>'queued','created_at'=>now(),'updated_at'=>now()] + $extra);
    if (!self::$hook && self::configured()) { self::$hook = true; app()->terminating(fn() => self::flush(25)); }
  }
  public static function flush(int $limit = 25): int {
    if (!self::configured()) return 0;
    $n = 0;
    foreach (DB::table('sms_logs')->where('status','queued')->orderBy('id')->limit($limit)->get() as $row) { if (self::deliver($row) !== null) $n++; }
    return $n;
  }
  /** Result of the last send() in this request: provider, state (accepted / failed / uncertain), request id, fall-back reason. */
  public static ?array $last = null;
  const OTP_TTL = 300; // login / checkout codes live 5 minutes: an older queued code is never sent
  public static function isOtp(?string $event): bool { return $event !== null && stripos($event, 'otp') !== false; }
  /** Hide codes in stored text (OTP rows) once the message has left. */
  public static function mask(string $body): string { return preg_replace('/\d{4,8}/u', '••••••', $body); }
  /**
   * Claim one queued sms_logs row and send it. Shared by flush() and the SMS Marketing pump, so every queued message
   * gets the same provider routing and the same record. Returns the new status, or null when another worker had it.
   */
  public static function deliver(object $row): ?string {
    if (!DB::table('sms_logs')->where('id',$row->id)->where('status','queued')->update(['status'=>'sending','updated_at'=>now()])) return null;
    $has = \Illuminate\Support\Facades\Schema::hasColumn('sms_logs','provider'); $event = $row->event ?? null; $otp = self::isOtp($event);
    if ($otp && !empty($row->created_at) && \Carbon\Carbon::parse($row->created_at)->lt(now()->subSeconds(self::OTP_TTL))) {
      DB::table('sms_logs')->where('id',$row->id)->update(['status'=>'expired','body'=>self::mask((string)$row->body),'provider_response'=>'Code expired before it could be sent; not sent','updated_at'=>now()]); return 'expired';
    }
    $uid = ($row->uid ?? null) ?: (string)\Illuminate\Support\Str::uuid();
    [$ok, $msg] = self::send($row->to, $row->body, ['log_id'=>$row->id, 'event'=>$event, 'type'=>$row->type ?? null, 'uid'=>$uid]);
    $r = self::$last ?? []; $st = $ok ? 'sent' : ((($r['state'] ?? '') === 'uncertain') ? 'uncertain' : 'failed');
    if (($r['class'] ?? '') === 'duplicate') { DB::table('sms_logs')->where('id',$row->id)->update(['status'=>'queued','updated_at'=>now()]); return 'queued'; } // the twin is in flight: try again on the next round
    $up = ['status'=>$st, 'provider_response'=>mb_substr($msg,0,500), 'sent_at'=>$ok ? now() : null, 'updated_at'=>now()];
    if ($otp) $up['body'] = self::mask((string)$row->body);
    if ($has) $up += ['uid'=>$uid, 'provider'=>$r['provider'] ?? null, 'request_id'=>$r['rid'] ?? null, 'delivery'=>$ok ? ((($r['provider'] ?? '') === 'alpha') ? 'pending' : 'no report') : null, 'fallback_reason'=>isset($r['fallback']) ? mb_substr((string)$r['fallback'],0,250) : null, 'attempts'=>count($r['trace'] ?? [])];
    DB::table('sms_logs')->where('id',$row->id)->update($up);
    return $st;
  }
  /** Send now through the selected provider(s). "true" means the provider ACCEPTED the request, not that the phone received it. @return array{bool,string} */
  public static function send(string $to, string $body, array $ctx = []): array {
    $r = SmsGate::route($to, $body, $ctx); self::$last = $r;
    $msg = $r['msg'] . ($r['state'] === 'uncertain' ? ' — status uncertain, not sent again' : '') . ($r['ok'] && $r['fallback'] ? ' [fall-back: ' . $r['fallback'] . ']' : '');
    return [$r['ok'], $msg];
  }
  /** Balance of the provider in use (primary when it is ready, otherwise the other one). */
  public static function balance(): string {
    $o = SmsGate::order()[0]; if (!$o) return 'API key not set';
    return SmsGate::balance($o[0])['text'];
  }
}
