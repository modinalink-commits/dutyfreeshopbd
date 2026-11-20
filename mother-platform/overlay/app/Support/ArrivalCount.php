<?php
namespace App\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Counts real arrivals from Facebook / Instagram ad clicks on OUR side (the visitor reached the server with an fbclid), so the Meta "link clicks" can be compared with "people who actually arrived". Unique by fbclid, per Dhaka day / hour, and per ad when the link carries utm_content=<ad id>. Light: three cache increments per new arrival, nothing is stored about the visitor. Blocked visitors never reach us, so they are the gap. */
class ArrivalCount {
  const TTL_DAYS = 14;
  private static function day(int $off = 0): string { return now('Asia/Dhaka')->subDays($off)->format('Y-m-d'); }
  private static function bump(string $k): void { try { if (!Cache::add($k, 1, now()->addDays(self::TTL_DAYS))) Cache::increment($k); } catch (\Throwable $e) {} }
  public static function note(Request $r): void {
    try {
      $f = (string)$r->query('fbclid', ''); if ($f === '' || strlen($f) > 400) return;
      if (preg_match('/facebookexternalhit|facebot|meta-externalagent|meta-externalfetcher/i', (string)$r->userAgent())) return;
      if (!Cache::add('arr.s.' . md5($f), 1, now()->addHours(12))) return; /* same click seen already (reload / second page) */
      $n = now('Asia/Dhaka'); $d = $n->format('Y-m-d');
      self::bump('arr.d.' . $d); self::bump('arr.h.' . $d . '-' . $n->format('H'));
      $a = (string)$r->query('utm_content', '');
      if (preg_match('/^\d{6,20}$/', $a)) {
        self::bump('arr.a.' . $d . '.' . $a);
        $ik = 'arr.i.' . $d; $ix = Cache::get($ik, []); if (!is_array($ix)) $ix = [];
        if (!in_array($a, $ix, true) && count($ix) < 400) { $ix[] = $a; Cache::put($ik, $ix, now()->addDays(self::TTL_DAYS)); }
      }
    } catch (\Throwable $e) {}
  }
  public static function report(int $days = 7): array {
    $out = [];
    for ($i = 0; $i < max(1, min(14, $days)); $i++) {
      $d = self::day($i); $hours = [];
      for ($h = 0; $h < 24; $h++) { $v = (int)Cache::get('arr.h.' . $d . '-' . sprintf('%02d', $h), 0); if ($v) $hours[sprintf('%02d', $h)] = $v; }
      $ads = []; foreach ((array)Cache::get('arr.i.' . $d, []) as $a) $ads[$a] = (int)Cache::get('arr.a.' . $d . '.' . $a, 0); arsort($ads);
      $out[$d] = ['arrivals_unique_fbclid' => (int)Cache::get('arr.d.' . $d, 0), 'by_hour' => $hours, 'by_ad_id' => $ads];
    }
    return $out;
  }
}
