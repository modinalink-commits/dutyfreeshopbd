<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** "Back in store" SMS (owner, 14 Nov 2026): when a product goes from Unpublished to Published, everyone who RECEIVED that product before gets one SMS about it.
 *  Who: phones on Delivered / Partial Delivered orders that contain the product; opted-out numbers never; the same number is not told about the same product again inside the repeat window.
 *  Safety: a product must have been unpublished for a minimum time (a quick off/on toggle sends nothing); many products published in one go (a bulk action or an accident) send nothing and raise an alert instead;
 *  a daily cap stops a runaway. The text, the switch and every limit are settings on the Back in Store SMS page; the message is queued through the normal SMS log (cost and status visible there). */
class PublishNotice {
  const EVENT = 'pub_back', TEXT = "{product} আবার স্টকে এসেছে। এখনই অর্ডার করুন: {link}\nDutyFreeBD";
  public static function set(string $k, string $d = ''): string { return Procure::set('pubsms_' . $k, $d); }
  public static function on(): bool { return self::set('on', '0') === '1'; }
  public static function text(): string { $t = trim(self::set('text', '')); return $t !== '' ? $t : self::TEXT; }
  public static function days(): int { return max(1, (int)self::set('days', '14')); }
  public static function minOff(): int { return max(0, (int)self::set('min_off', '30')); }       // minutes a product must have been unpublished
  public static function maxAtOnce(): int { return max(1, (int)self::set('max_products', '10')); } // more products than this published in one minute: nothing is sent
  public static function dailyCap(): int { return max(0, (int)self::set('daily_cap', '5000')); }
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasTable('product_pub_watch'); } catch (\Throwable $e) { $r = false; } } return $r; }
  public static function link($p): string { return preg_replace('#^https?://#', '', Domain::main()) . '/p/m' . (int)$p->id; }
  public static function body($p): string { return strtr(self::text(), ['{product}' => trim((string)$p->name), '{link}' => self::link($p), '{price}' => number_format((float)$p->price)]); }
  /** Numbers that received the product and may be told now. */
  public static function recipients(int $pid): array {
    $phones = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $pid)
      ->where(fn($q) => $q->where('o.status', 'Delivered')->orWhere(fn($w) => $w->where('o.status', 'Partial Delivered')->where('o.collected_amount', '>', 0)))
      ->whereNotNull('o.phone')->distinct()->pluck('o.phone')->all();
    $nums = []; foreach ($phones as $ph) { $n = Sms::number((string)$ph); if ($n) $nums[$n] = true; }
    if (!$nums) return [];
    $nums = array_diff(array_keys($nums), SmsMarketing::opted());
    $since = now()->subDays(self::days()); $told = [];
    foreach (array_chunk($nums, 1000) as $ch) foreach (DB::table('sms_logs')->where('event', self::EVENT)->where('ref', 'like', 'pb' . $pid . ':%')->where('created_at', '>=', $since)->whereIn('to', $ch)->pluck('to') as $t) $told[$t] = true;
    return array_values(array_filter($nums, fn($n) => !isset($told[$n])));
  }
  public static function sentToday(): int { try { return DB::table('sms_logs')->where('event', self::EVENT)->where('created_at', '>=', now('Asia/Dhaka')->startOfDay()->utc())->count(); } catch (\Throwable $e) { return 0; } }
  /** Queue the SMS for one product. Returns [queued, skipped by the daily cap]. */
  public static function notify(int $pid): array {
    $p = DB::table('products')->find($pid); if (!$p || !$p->published) return [0, 0];
    $to = self::recipients($pid); if (!$to) return [0, 0];
    $cap = self::dailyCap(); $room = $cap > 0 ? max(0, $cap - self::sentToday()) : PHP_INT_MAX; $send = array_slice($to, 0, $room); $body = self::body($p);
    foreach ($send as $n) SmsMarketing::enqueue($n, $body, self::EVENT, 'marketing', ['ref' => 'pb' . $pid . ':' . $n]);
    [$parts] = SmsMarketing::parts($body);
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'System', 'action' => 'product.back_in_store_sms', 'subject' => $p->name . ': ' . count($send) . ' SMS queued (' . $parts . ' part' . ($parts === 1 ? '' : 's') . ' each)' . (count($to) > count($send) ? ', ' . (count($to) - count($send)) . ' held by the daily cap' : ''), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return [count($send), count($to) - count($send)];
  }
  /** Worker, once a minute: notice publish-state changes and act on Unpublished -> Published. */
  public static function tick(): void {
    try {
      if (!self::ready() || !Cache::add('pubsms.tick', 1, 55)) return;
      // products not seen before are remembered as they are (never a reason to send)
      DB::statement('insert into product_pub_watch (product_id, published, changed_at) select p.id, coalesce(p.published, false), now() from products p where not exists (select 1 from product_pub_watch w where w.product_id = p.id)');
      $min = self::minOff();
      $rows = DB::select("select p.id, coalesce(p.published, false) as pub, (w.changed_at is null or w.changed_at <= now() - (? * interval '1 minute')) as long_off from products p join product_pub_watch w on w.product_id = p.id where coalesce(p.published, false) <> w.published limit 2000", [$min]);
      if (!$rows) return;
      $cand = [];
      foreach ($rows as $r) {
        if ($r->pub && $r->long_off) $cand[] = (int)$r->id;
        DB::table('product_pub_watch')->where('product_id', $r->id)->update(['published' => (bool)$r->pub, 'changed_at' => DB::raw('now()')]);
      }
      if (!$cand || !self::on()) return;
      if (count($cand) > self::maxAtOnce()) {
        DeployHealth::raise('pubsms.bulk', 'high', 'Marketing', 'Back in Store SMS not sent: ' . count($cand) . ' products were published at once', 'More than ' . self::maxAtOnce() . ' products went from Unpublished to Published in the same minute, which looks like a bulk action. No customer SMS was sent for them.', 'Bulk publish, an import, or a mistake.', 'If these should be announced, send them from Marketing > Back in Store SMS, product by product.');
        return;
      }
      foreach ($cand as $pid) self::notify($pid);
    } catch (\Throwable $e) { report($e); }
  }
}
