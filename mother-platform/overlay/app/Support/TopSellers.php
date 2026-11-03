<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Daily Top Selling Products: ranks products by quantity sold on the current Asia/Dhaka day (00:00:00 to 23:59:59), counting only the order statuses chosen in Site Settings.
 *  The ranking (product id + quantity) is cached per day under homepage:top-selling-products:YYYY-MM-DD; the homepage only reads that small list. */
class TopSellers {
  const TZ = 'Asia/Dhaka';
  const DEFAULT_STATUSES = 'Approved,Packaging,Shipment,Partial Delivered,Delivered';
  const TTL = 300;
  /** Order statuses that never count, even if someone types them into the setting. */
  const NEVER = ['Incomplete','Cancel','Returned','Return Pending','Failed','Fake','Duplicate','New'];
  public static function today(): string { return now(self::TZ)->toDateString(); }
  public static function key(string $d): string { return 'homepage:top-selling-products:'.$d; }
  public static function bust(?string $d = null): void { try { Cache::forget(self::key($d ?: self::today())); } catch (\Throwable $e) {} }
  public static function statuses(array $s): array {
    $raw = trim((string)($s['top_statuses'] ?? '')) !== '' ? $s['top_statuses'] : self::DEFAULT_STATUSES;
    $known = \App\Models\Mother::STAGES; $out = [];
    foreach (explode(',', $raw) as $x) { $x = trim($x); if ($x !== '' && in_array($x, $known, true) && !in_array($x, self::NEVER, true)) $out[] = $x; }
    return $out ?: explode(',', self::DEFAULT_STATUSES);
  }
  public static function limit(array $s): int { $n = (int)($s['top_limit'] ?? 10); return in_array($n, [5,10,15,20], true) ? $n : 10; }
  /** [product_id => quantity sold] for one Dhaka calendar day, highest first. Cached. */
  public static function ranking(string $date, array $s): array {
    $sig = md5(implode('|', self::statuses($s)));
    $k = self::key($date);
    $hit = Cache::get($k); if (is_array($hit) && ($hit['sig'] ?? null) === $sig) return $hit['d'];
    $tz = config('app.timezone') ?: 'UTC';
    $from = \Carbon\Carbon::parse($date, self::TZ)->startOfDay()->setTimezone($tz)->format('Y-m-d H:i:s');
    $to = \Carbon\Carbon::parse($date, self::TZ)->endOfDay()->setTimezone($tz)->format('Y-m-d H:i:s');
    $st = self::statuses($s);
    $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
      ->whereNotNull('i.product_id')->whereIn('o.status', $st)->whereNull('o.duplicate_of_order_id')
      ->whereBetween('o.created_at', [$from, $to]);
    $rows = $q->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q')->havingRaw('sum(i.qty) > 0')->orderByDesc('q')->orderBy('pid')->limit(60)->get();
    $out = []; foreach ($rows as $r) $out[(int)$r->pid] = (int)$r->q;
    Cache::put($k, ['sig'=>$sig, 'd'=>$out], self::TTL);
    return $out;
  }
  /** Slider content: today's sellers first, then yesterday's top sellers fill the remaining places. Returns [products(collection), soldTodayByProductId]. */
  public static function products(array $s): array {
    $limit = self::limit($s); $d = self::today();
    $today = self::ranking($d, $s);
    $order = []; foreach ($today as $pid => $q) $order[] = $pid;
    // yesterday's top sellers are always appended as candidates; the list is cut to the limit after unpublished / out-of-stock items drop out, so today's sellers stay first
    $y = self::ranking(now(self::TZ)->subDay()->toDateString(), $s);
    foreach ($y as $pid => $q) { if (!isset($today[$pid])) $order[] = $pid; }
    if (!$order) return [collect(), []];
    $rows = DB::table('products')->where('published', true)->where('stock', '>', 0)->whereIn('id', $order)->get()->keyBy('id');
    $list = collect(); foreach ($order as $pid) { if (isset($rows[$pid])) { $list->push($rows[$pid]); if ($list->count() >= $limit) break; } }
    return [$list, $today];
  }
}
