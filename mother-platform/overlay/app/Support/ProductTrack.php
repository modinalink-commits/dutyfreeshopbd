<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Top Viewed Products analytics. Tracking is anonymous (hashed visitor, no personal data), never blocks the page
 * (insert runs after the response and any failure is swallowed), skips bots, and counts a visitor's repeat views of
 * the same product inside DEDUPE seconds only once.
 */
class ProductTrack {
  const DEDUPE = 600; // 10 minutes
  const BOT = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|whatsapp|telegram|preview|curl|wget|python|java\/|go-http|headless|lighthouse|pingdom|uptime|monitor|gtmetrix|semrush|ahrefs|mj12|petalbot|bytespider|scrapy|axios|node-fetch|yandex|baidu|duckduck|adsbot|mediapartners|google-read|apis-google/i';

  public static function isBot(string $ua): bool { return $ua === '' || (bool)preg_match(self::BOT, $ua); }
  public static function device(string $ua): string {
    if (preg_match('/ipad|tablet|(android(?!.*mobile))/i', $ua)) return 'tablet';
    if (preg_match('/mobi|iphone|android/i', $ua)) return 'mobile';
    return 'desktop';
  }
  /** Where the visitor came from: explicit ?src=, campaign tags, or our own referring page. */
  public static function source($r): string {
    $q = $r->query();
    if (isset($q['src']) && in_array($q['src'], ['Homepage','Category','Brand','Search','Recently Ordered','Popular Products',"Today's Deal"], true)) return $q['src'];
    foreach (['utm_source','utm_medium','utm_campaign','fbclid','gclid','ttclid'] as $k) if (isset($q[$k])) return 'Campaign';
    $ref = (string)$r->headers->get('referer', ''); if ($ref === '') return 'Direct Link';
    $p = parse_url($ref); $h = strtolower((string)($p['host'] ?? '')); $me = strtolower((string)$r->getHost());
    if ($h === '' || ($h !== $me && ltrim($h, 'w.') !== ltrim($me, 'w.'))) return 'Other';
    $path = (string)($p['path'] ?? '/'); $qs = (string)($p['query'] ?? '');
    if ($path === '/' || $path === '') return 'Homepage';
    if (str_starts_with($path, '/search')) return 'Search';
    if (str_contains($qs, 'sub=')) return 'Brand';
    if (substr_count(trim($path, '/'), '/') <= 1) return 'Category';
    return 'Other';
  }
  public static function view($r, $p): void {
    try {
      $ua = (string)$r->userAgent(); if (self::isBot($ua)) return;
      $vid = substr(sha1($r->ip().'|'.$ua.'|'.config('app.key')), 0, 20);
      if (!Cache::add('pv:'.$vid.':'.$p->id, 1, self::DEDUPE)) return;
      $row = ['event_type'=>'product_view', 'product_id'=>(int)$p->id, 'store_id'=>isset($p->store_id) ? (int)$p->store_id : null, 'visitor_id'=>$vid, 'device_type'=>self::device($ua), 'source'=>self::source($r), 'referrer'=>mb_substr((string)parse_url((string)$r->headers->get('referer', ''), PHP_URL_HOST), 0, 120) ?: null, 'created_at'=>now()];
      app()->terminating(function () use ($row) { try { DB::table('product_analytics_events')->insert($row); } catch (\Throwable $e) {} });
    } catch (\Throwable $e) {}
  }

  /** Demand label from interest vs sales vs stock. */
  public static function status(int $views, int $uniq, int $orders, float $conv, int $stock, int $alert, float $growth): string {
    if ($views >= 30 && $stock <= max(1, $alert)) return 'High Demand / Low Stock';
    if ($uniq >= 50 && $conv < 1.0) return 'High Interest / Low Conversion';
    if ($views >= 100 && $conv >= 2.0) return 'High Demand';
    if ($views >= 20 && $growth >= 40) return 'Rising';
    if ($views < 5) return 'Low Demand';
    return 'Normal';
  }

  /** add_to_cart / checkout_started / search_click from the storefront beacon. Anonymous, bot-filtered, deduped, never blocks. */
  public static function event($r): void {
    try {
      $ua = (string)$r->userAgent(); if (self::isBot($ua)) return;
      $in = json_decode((string)$r->getContent(), true); if (!is_array($in)) return;
      $e = (string)($in['e'] ?? ''); if (!in_array($e, ['add_to_cart','checkout_started','search_click'], true)) return;
      $vid = substr(sha1($r->ip().'|'.$ua.'|'.config('app.key')), 0, 20); $dev = self::device($ua); $ids = []; $meta = null;
      if ($e === 'search_click') {
        $slug = trim((string)basename(parse_url((string)($in['path'] ?? ''), PHP_URL_PATH) ?: ''), '/'); if ($slug === '') return;
        $pid = (int)DB::table('products')->where('path_slug', $slug)->value('id'); if (!$pid) return;
        $ids = [$pid]; $meta = json_encode(['term'=>mb_substr(trim((string)($in['term'] ?? '')), 0, 80)], JSON_UNESCAPED_UNICODE);
      } elseif ($e === 'add_to_cart') { $ids = [(int)($in['p'] ?? 0)]; }
      else { $ids = array_map('intval', array_slice((array)($in['ids'] ?? []), 0, 20)); }
      $ids = array_values(array_unique(array_filter($ids, fn($x) => $x > 0))); if (!$ids) return;
      $rows = [];
      foreach ($ids as $pid) {
        if (!Cache::add('pe:'.$e.':'.$vid.':'.$pid, 1, $e === 'checkout_started' ? 600 : 30)) continue;
        $rows[] = ['event_type'=>$e, 'product_id'=>$pid, 'visitor_id'=>$vid, 'device_type'=>$dev, 'metadata'=>$meta, 'created_at'=>now()];
      }
      if ($rows) app()->terminating(function () use ($rows) { try { DB::table('product_analytics_events')->insert($rows); } catch (\Throwable $x) {} });
    } catch (\Throwable $x) {}
  }

  /** Dashboard widget data for a range (today | yesterday | 7 | 30 | custom from/to); cached 5 minutes. Five insight lists from one query. */
  public static function dash(string $range = '30', ?string $cf = null, ?string $ct = null): array {
    $tz = new \DateTimeZone('Asia/Dhaka'); $now = new \DateTime('now', $tz);
    if ($range === 'today') { $from = $to = $now->format('Y-m-d'); }
    elseif ($range === 'yesterday') { $from = $to = (clone $now)->modify('-1 day')->format('Y-m-d'); }
    elseif ($range === 'custom' && $cf && $ct && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cf) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ct)) { $from = $cf; $to = $ct; }
    else { $range = $range === '7' ? '7' : '30'; $to = $now->format('Y-m-d'); $from = (clone $now)->modify('-'.((int)$range - 1).' days')->format('Y-m-d'); }
    return Cache::remember('pt.dash.'.$from.'.'.$to, 300, function () use ($from, $to, $range) {
      $rows = Analytics::rows('top-viewed', Analytics::filters(['from'=>$from, 'to'=>$to]))['rows'];
      $by = fn($k, $min = 0) => array_slice(array_values(array_filter($rows, fn($r) => (float)$r[$k] > $min)), 0, 6);
      $sorted = fn($k, $f = null) => (function () use ($rows, $k, $f) { $x = array_values(array_filter($rows, $f ?: fn($r) => (float)$r[$k] > 0)); usort($x, fn($a, $b) => (float)$b[$k] <=> (float)$a[$k]); return array_slice($x, 0, 6); })();
      $opp = null; foreach ($rows as $r) if ($r['status'] === 'High Interest / Low Conversion') { $opp = $r; break; }
      $rise = null; foreach ($rows as $r) if ((float)$r['growth'] >= 40 && $r['views'] >= 20 && (!$rise || $r['growth'] > $rise['growth'])) $rise = $r;
      return [
        'tot'=>['views'=>array_sum(array_column($rows, 'views')), 'uniq'=>array_sum(array_column($rows, 'uniq')), 'cart'=>array_sum(array_column($rows, 'cart')), 'orders'=>array_sum(array_column($rows, 'orders'))],
        'lists'=>['top'=>array_slice($rows, 0, 6), 'search'=>$sorted('search'), 'cart'=>$sorted('cart'), 'low'=>$sorted('views', fn($r) => $r['status'] === 'High Interest / Low Conversion'), 'rise'=>$sorted('growth', fn($r) => (float)$r['growth'] >= 40 && $r['views'] >= 20)],
        'high'=>$rows[0] ?? null, 'opp'=>$opp, 'rise'=>$rise, 'range'=>$range, 'from'=>$from, 'to'=>$to];
    });
  }
}
