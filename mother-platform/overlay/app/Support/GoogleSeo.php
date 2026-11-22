<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Google Search Console numbers for the Live Dashboard (owner, 23 Nov 2026). Read-only.
 * Uses a Google service account: its JSON key is pasted once by the owner on /admin/live-dashboard/google and kept encrypted in
 * integration_secrets (never shown again). The service account's e-mail must be added as a user of the Search Console property.
 */
class GoogleSeo {
  const SECRET = 'google_sa_json';
  public static function property(): string { try { $v = (string)DB::table('settings')->where('key', 'gsc_property')->value('value'); } catch (\Throwable $e) { $v = ''; } return $v !== '' ? $v : 'sc-domain:dutyfreebd.com'; }
  public static function key(): ?array { try { $j = json_decode((string)Sms::secret(self::SECRET), true); return is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) ? $j : null; } catch (\Throwable $e) { return null; } }
  public static function email(): ?string { $k = self::key(); return $k['client_email'] ?? null; }
  private static function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

  private static function token(array $k): string {
    return Cache::remember('gseo.token.' . md5($k['client_email']), 3000, function () use ($k) {
      $now = time(); $h = self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
      $c = self::b64(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
      if (!openssl_sign($h . '.' . $c, $sig, $k['private_key'], OPENSSL_ALGO_SHA256)) throw new \RuntimeException('The key file could not be used to sign (private_key is not valid).');
      $r = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $h . '.' . $c . '.' . self::b64($sig)]);
      if (!$r->ok() || !$r->json('access_token')) throw new \RuntimeException('Google did not accept the key: ' . mb_substr((string)($r->json('error_description') ?? $r->json('error') ?? $r->status()), 0, 140));
      return (string)$r->json('access_token');
    });
  }

  /** ['state' => none|ok|error, 'msg', 'clicks', 'impressions', 'ctr', 'position', 'days' => [clicks per day], 'at'] — cached 30 min (errors 5 min). */
  public static function data(bool $fresh = false): array {
    $k = self::key(); if (!$k) return ['state' => 'none', 'msg' => 'Google key not saved yet'];
    if (!$fresh && is_array($c = Cache::get('gseo.data'))) return $c;
    if (!$fresh && !Cache::add('gseo.lock', 1, 20)) return ['state' => 'error', 'msg' => 'Loading…'];
    try {
      $end = now('America/Los_Angeles')->subDay()->format('Y-m-d'); $start = now('America/Los_Angeles')->subDays(8)->format('Y-m-d');
      $r = Http::withToken(self::token($k))->timeout(10)->post('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode(self::property()) . '/searchAnalytics/query', ['startDate' => $start, 'endDate' => $end, 'dimensions' => ['date'], 'dataState' => 'all', 'rowLimit' => 30]);
      if (!$r->ok()) {
        $m = (string)($r->json('error.message') ?? ('HTTP ' . $r->status()));
        if ($r->status() === 403) $m = 'No access to ' . self::property() . '. Add ' . $k['client_email'] . ' as a user in Search Console > Settings > Users and permissions.';
        throw new \RuntimeException(mb_substr($m, 0, 240));
      }
      $rows = (array)$r->json('rows', []); $cl = 0; $im = 0; $pos = 0; $days = [];
      foreach ($rows as $x) { $cl += (int)$x['clicks']; $im += (int)$x['impressions']; $pos += (float)$x['position'] * (int)$x['impressions']; $days[] = (int)$x['clicks']; }
      $out = ['state' => 'ok', 'msg' => '', 'clicks' => $cl, 'impressions' => $im, 'ctr' => $im ? round($cl / $im * 100, 1) : 0, 'position' => $im ? round($pos / $im, 1) : 0, 'days' => $days, 'from' => $start, 'to' => $end, 'at' => time()];
      Cache::put('gseo.data', $out, 1800); return $out;
    } catch (\Throwable $e) { $out = ['state' => 'error', 'msg' => mb_substr($e->getMessage(), 0, 240)]; Cache::put('gseo.data', $out, 300); Cache::forget('gseo.token.' . md5($k['client_email'])); return $out; }
  }
}
