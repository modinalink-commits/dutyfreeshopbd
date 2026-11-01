<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Minimal Web Push (RFC 8030 / 8188 / 8291 / 8292) using only PHP openssl + curl. */
class WebPush
{
  public static function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
  public static function unb64u(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

  /** @return array{priv:string,pub:string} PEM private key, base64url raw uncompressed public key */
  public static function keys(): array {
    $priv = DB::table('settings')->where('key', 'vapid_private')->value('value');
    $pub = DB::table('settings')->where('key', 'vapid_public')->value('value');
    if ($priv && $pub) return ['priv' => $priv, 'pub' => $pub];
    $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($k, $pem);
    $d = openssl_pkey_get_details($k)['ec'];
    $raw = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
    $pub = self::b64u($raw);
    foreach (['vapid_private' => $pem, 'vapid_public' => $pub] as $kk => $vv) {
      DB::table('settings')->updateOrInsert(['key' => $kk], ['value' => $vv, 'updated_at' => now(), 'created_at' => now()]);
    }
    return ['priv' => $pem, 'pub' => $pub];
  }

  public static function publicKey(): string { return self::keys()['pub']; }

  private static function derToRaw(string $der): string {
    $o = 3; $rl = ord($der[$o]); $o++; $r = substr($der, $o, $rl); $o += $rl + 1; $sl = ord($der[$o]); $o++; $s = substr($der, $o, $sl);
    $r = ltrim($r, "\0"); $s = ltrim($s, "\0");
    return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
  }

  private static function jwt(string $endpoint): string {
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $h = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $p = self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => 'mailto:' . (config('mail.from.address') && strpos((string)config('mail.from.address'), 'example') === false ? config('mail.from.address') : 'info@dutyfreebd.com')]));
    openssl_sign($h . '.' . $p, $der, self::keys()['priv'], OPENSSL_ALGO_SHA256);
    return $h . '.' . $p . '.' . self::b64u(self::derToRaw($der));
  }

  private static function hmac(string $key, string $data): string { return hash_hmac('sha256', $data, $key, true); }

  private static function peerPem(string $raw): string {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
  }

  public static function encrypt(string $payload, string $uaPublic, string $authSecret, ?string $asPrivPem = null): string {
    if ($asPrivPem === null) { $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']); }
    else { $k = openssl_pkey_get_private($asPrivPem); }
    $d = openssl_pkey_get_details($k)['ec'];
    $asPublic = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
    $shared = openssl_pkey_derive(openssl_pkey_get_public(self::peerPem($uaPublic)), $k, 32);
    $ikm = substr(self::hmac(self::hmac($authSecret, $shared), "WebPush: info\0" . $uaPublic . $asPublic . "\x01"), 0, 32);
    $salt = random_bytes(16);
    $prk = self::hmac($salt, $ikm);
    $cek = substr(self::hmac($prk, "Content-Encoding: aes128gcm\0\x01"), 0, 16);
    $nonce = substr(self::hmac($prk, "Content-Encoding: nonce\0\x01"), 0, 12);
    $tag = '';
    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    return $salt . pack('N', 4096) . chr(65) . $asPublic . $ct . $tag;
  }

  /** Test helper: decrypt what encrypt() produced, with the receiver's private key. */
  public static function decrypt(string $body, string $uaPrivPem, string $uaPublic, string $authSecret): string {
    $salt = substr($body, 0, 16); $idlen = ord($body[20]); $asPublic = substr($body, 21, $idlen); $rest = substr($body, 21 + $idlen);
    $ct = substr($rest, 0, -16); $tag = substr($rest, -16);
    $shared = openssl_pkey_derive(openssl_pkey_get_public(self::peerPem($asPublic)), openssl_pkey_get_private($uaPrivPem), 32);
    $ikm = substr(self::hmac(self::hmac($authSecret, $shared), "WebPush: info\0" . $uaPublic . $asPublic . "\x01"), 0, 32);
    $prk = self::hmac($salt, $ikm);
    $cek = substr(self::hmac($prk, "Content-Encoding: aes128gcm\0\x01"), 0, 16);
    $nonce = substr(self::hmac($prk, "Content-Encoding: nonce\0\x01"), 0, 12);
    $pt = openssl_decrypt($ct, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return rtrim((string)$pt, "\x02");
  }

  /** Send to one subscription row. Returns HTTP status (0 = network failure). Dead endpoints are deactivated. */
  public static function send(object $sub, array $data, int $ttl = 86400, string $urgency = 'normal'): int {
    $body = self::encrypt(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::unb64u($sub->p256dh), self::unb64u($sub->auth));
    $ch = curl_init($sub->endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
      CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . self::jwt($sub->endpoint) . ', k=' . self::publicKey(), 'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream', 'TTL: ' . $ttl, 'Urgency: ' . $urgency, 'Content-Length: ' . strlen($body)]]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 404 || $code === 410) DB::table('push_subscriptions')->where('id', $sub->id)->update(['active' => false, 'updated_at' => now()]);
    elseif ($code >= 200 && $code < 300) DB::table('push_subscriptions')->where('id', $sub->id)->update(['fail_count' => 0, 'last_used_at' => now()]);
    else DB::table('push_subscriptions')->where('id', $sub->id)->increment('fail_count');
    return $code;
  }
}
