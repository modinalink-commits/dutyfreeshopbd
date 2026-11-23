<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * IndexNow (Bing, Yandex and others): tells search engines by itself when a page is new or changed, so nobody has to submit URLs by hand.
 * Source of truth is our own sitemap (same rules: only pages that really open; tobacco stays out). Every 30 minutes the sitemap is compared
 * with the last list that was sent, and only new or changed URLs are submitted. The key file is served at /{KEY}.txt.
 */
class IndexNow
{
  const KEY = '817b7454f457e8bc24ca4cc8e9ce27d0';
  const EVERY = 1800;

  private static function file(): string { return storage_path('app/indexnow-sent.json'); }
  public static function state(): array { $s = Cache::get('indexnow.state'); return is_array($s) ? $s : []; }

  /** url => lastmod from the live sitemap, or null when it could not be read. */
  private static function sitemap(): ?array {
    $B = Domain::main(); $get = fn(string $u) => Http::timeout(25)->withHeaders(['User-Agent' => 'DutyFreeBD-IndexNow/1.0'])->get($u);
    $r = $get($B . '/sitemap.xml'); if (!$r->ok()) return null;
    preg_match_all('#<sitemap>\s*<loc>([^<]+)</loc>#', $r->body(), $m); $parts = array_slice(array_map('html_entity_decode', $m[1]), 0, 60); if (!$parts) $parts = [$B . '/sitemap.xml'];
    $out = [];
    foreach ($parts as $p) {
      if (!str_starts_with($p, $B . '/')) continue; $x = $p === $B . '/sitemap.xml' ? $r : $get($p); if (!$x->ok()) return null;
      preg_match_all('#<url>\s*<loc>([^<]+)</loc>(?:\s*<lastmod>([^<]*)</lastmod>)?#', $x->body(), $mm, PREG_SET_ORDER);
      foreach ($mm as $u) { $loc = html_entity_decode($u[1]); if (str_starts_with($loc, $B . '/')) $out[$loc] = substr((string)($u[2] ?? ''), 0, 19); }
    }
    return $out ?: null;
  }

  public static function tick(): void {
    if (!Cache::add('indexnow.lock2', 1, self::EVERY)) return;
    $st = self::state(); $st['last_run'] = now()->toIso8601String();
    try {
      $now = self::sitemap(); if ($now === null) { $st['last_error'] = 'sitemap could not be read'; Cache::forever('indexnow.state', $st); return; }
      $old = is_file(self::file()) ? (json_decode((string)file_get_contents(self::file()), true) ?: []) : [];
      $send = []; foreach ($now as $u => $lm) if (!array_key_exists($u, $old) || $old[$u] !== $lm) $send[] = $u;
      $st['sitemap_urls'] = count($now); $st['changed'] = count($send);
      if ($send) {
        $host = parse_url(Domain::main(), PHP_URL_HOST);
        foreach (array_chunk($send, 5000) as $chunk) {
          $r = Http::timeout(30)->asJson()->post('https://api.indexnow.org/indexnow', ['host' => $host, 'key' => self::KEY, 'keyLocation' => Domain::main() . '/' . self::KEY . '.txt', 'urlList' => $chunk]);
          $st['last_http'] = $r->status();
          if (!in_array($r->status(), [200, 202], true)) { $st['last_error'] = 'IndexNow answered ' . $r->status() . ': ' . mb_substr($r->body(), 0, 200); Cache::forever('indexnow.state', $st); Cache::put('indexnow.lock2', 1, 300); return; } // not accepted: try again in 5 minutes; nothing saved: the same URLs are tried again next time
        }
        $st['last_sent'] = count($send); $st['last_sent_at'] = now()->toIso8601String(); $st['total_sent'] = (int)($st['total_sent'] ?? 0) + count($send);
      }
      file_put_contents(self::file(), json_encode($now)); $st['last_error'] = null;
    } catch (\Throwable $e) { $st['last_error'] = mb_substr($e->getMessage(), 0, 200); }
    Cache::forever('indexnow.state', $st);
  }
}
