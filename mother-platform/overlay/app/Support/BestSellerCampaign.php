<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Best Sellers campaign follow-ups (owner plan, 14 Nov 2026). Both use the live 2-carton offer price and go only to people who RECEIVED the product; opted-out numbers never.
 *   1. Refill reminder: some days after a Best Seller was delivered (12 unless changed: the usual gap between two orders is about two weeks), one SMS with the offer, unless the customer has ordered again since.
 *   2. One-time buyers: a single message, sent by hand from the Best Sellers page, to customers who received a Best Seller exactly once and have not ordered anything since.
 *  Results card: orders of the two products since the offer started, by pack size and outcome, read from the orders table. */
class BestSellerCampaign {
  const REMIND = 'bs_remind', ONCE = 'bs_once';
  const T_REMIND = "{product} শেষ হয়ে আসছে? এখন ২ কার্টন ৳{pack} (প্রতিটি ৳{each})। অর্ডার: {link}\nDutyFreeBD";
  const T_ONCE = "{product} এখন ২ কার্টন ৳{pack} (প্রতিটি ৳{each})। অর্ডার: {link}\nDutyFreeBD";
  public static function set(string $k, string $d = ''): string { return Procure::set('bscamp_' . $k, $d); }
  public static function remindOn(): bool { return self::set('remind_on', '0') === '1'; }
  public static function days(): int { return max(3, min(60, (int)self::set('remind_days', '12'))); }
  public static function text(string $which): string { $t = trim(self::set($which . '_text', '')); return $t !== '' ? $t : ($which === 'remind' ? self::T_REMIND : self::T_ONCE); }
  public static function since(): ?string { $s = self::set('since', ''); return $s !== '' ? $s : null; }
  public static function markStart(): void { if (self::since() === null) Procure::put('bscamp_since', now('UTC')->format('Y-m-d H:i:s')); }
  /** Offer facts for one product, or null when it has no 2-carton offer right now. */
  public static function offer($p): ?array {
    $t = BundlePricing::total((int)$p->id, 2); if (!$t || $t >= (float)$p->price * 2 || (int)$p->stock < 2) return null;
    return ['pack' => (float)$t, 'each' => (float)$t / 2];
  }
  public static function body(string $which, $p): ?string {
    $o = self::offer($p); if (!$o) return null;
    return strtr(self::text($which), ['{product}' => trim((string)$p->name), '{pack}' => number_format($o['pack']), '{each}' => number_format($o['each']), '{link}' => PublishNotice::link($p)]);
  }
  private static function received($q) { return $q->where(fn($w) => $w->where('o.status', 'Delivered')->orWhere(fn($x) => $x->where('o.status', 'Partial Delivered')->where('o.collected_amount', '>', 0))); }
  private static function clean(array $phones, string $event, string $refPrefix, ?int $withinDays): array {
    $nums = []; foreach ($phones as $ph) { $n = Sms::number((string)$ph); if ($n) $nums[$n] = true; }
    if (!$nums) return []; $nums = array_values(array_diff(array_keys($nums), SmsMarketing::opted())); $told = [];
    foreach (array_chunk($nums, 1000) as $ch) { $q = DB::table('sms_logs')->where('event', $event)->where('ref', 'like', $refPrefix . '%')->whereIn('to', $ch); if ($withinDays) $q->where('created_at', '>=', now()->subDays($withinDays)); foreach ($q->pluck('to') as $t) $told[$t] = true; }
    return array_values(array_filter($nums, fn($n) => !isset($told[$n])));
  }
  /** Phones whose last received order of this product was delivered on the reminder day and who have not ordered anything since. */
  public static function remindList(int $pid): array {
    $d = self::days(); $from = now()->subDays($d + 1); $to = now()->subDays($d);
    $rows = self::received(DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $pid))->whereNotNull('o.phone')
      ->whereBetween(DB::raw('coalesce(o.courier_delivered_at, o.updated_at)'), [$from, $to])->get(['o.phone', 'o.created_at']);
    $phones = []; foreach ($rows as $r) { if (!DB::table('orders')->where('phone', $r->phone)->where('created_at', '>', $r->created_at)->exists()) $phones[] = $r->phone; }
    return self::clean($phones, self::REMIND, 'br' . $pid . ':', max(7, $d - 2));
  }
  /** Worker: once an hour between 10:00 and 20:00 Dhaka time. */
  public static function tick(): void {
    try {
      if (!self::remindOn() || !AutoPrice::on()) return; $h = (int)now('Asia/Dhaka')->format('G'); if ($h < 10 || $h >= 20) return;
      if (!Cache::add('bscamp.remind.' . now('Asia/Dhaka')->format('YmdH'), 1, 3700)) return;
      foreach (AutoPrice::ids() as $pid) {
        $p = DB::table('products')->find($pid); if (!$p || !$p->published) continue; $body = self::body('remind', $p); if (!$body) continue;
        $to = self::remindList((int)$pid); foreach ($to as $n) SmsMarketing::enqueue($n, $body, self::REMIND, 'marketing', ['ref' => 'br' . $pid . ':' . $n]);
        if ($to) { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'System', 'action' => 'bestseller.refill_reminder', 'subject' => $p->name . ': ' . count($to) . ' reminder SMS queued', 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
      }
    } catch (\Throwable $e) { report($e); }
  }
  /** One-time buyers of a product: received it exactly once, at least $minDays ago, and no order of any kind after that one. Never messaged by this campaign before. */
  public static function onceList(int $pid, int $minDays = 20): array {
    $rows = self::received(DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $pid))->whereNotNull('o.phone')
      ->groupBy('o.phone')->havingRaw('count(distinct o.id) = 1')->havingRaw('max(o.created_at) <= ?', [now()->subDays($minDays)])->selectRaw('o.phone, max(o.created_at) as last')->get();
    $phones = []; foreach ($rows as $r) { if (!DB::table('orders')->where('phone', $r->phone)->where('created_at', '>', $r->last)->exists()) $phones[] = $r->phone; }
    return self::clean($phones, self::ONCE, 'bo' . $pid . ':', null);
  }
  public static function sendOnce(int $pid): int {
    $p = DB::table('products')->find($pid); if (!$p || !$p->published) return 0; $body = self::body('once', $p); if (!$body) return 0;
    $to = self::onceList($pid); foreach ($to as $n) SmsMarketing::enqueue($n, $body, self::ONCE, 'marketing', ['ref' => 'bo' . $pid . ':' . $n]);
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? 'System', 'action' => 'bestseller.one_time_buyers', 'subject' => $p->name . ': ' . count($to) . ' SMS queued for one-time buyers', 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return count($to);
  }
  /** Orders of the Best Sellers since the offer started: per product, single carton vs pack, by outcome. */
  public static function results(): array {
    $since = self::since(); if (!$since) return ['since' => null, 'rows' => []]; $out = [];
    foreach (AutoPrice::ids() as $pid) {
      $p = DB::table('products')->find($pid); if (!$p) continue;
      $rows = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $pid)->where('o.created_at', '>=', $since)->groupBy('o.id', 'o.status')->selectRaw('o.id, o.status, sum(i.qty) as q')->get();
      $b = ['name' => $p->name, 'single' => ['orders' => 0, 'cartons' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0], 'pack' => ['orders' => 0, 'cartons' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0, 'delivered_cartons' => 0]];
      foreach ($rows as $r) { $k = (int)$r->q >= 2 ? 'pack' : 'single'; $b[$k]['orders']++; $b[$k]['cartons'] += (int)$r->q;
        if (in_array($r->status, ['Delivered', 'Partial Delivered'], true)) { $b[$k]['delivered']++; if ($k === 'pack') $b[$k]['delivered_cartons'] += (int)$r->q; }
        elseif (in_array($r->status, ['Returned', 'Return Pending', 'Return'], true)) $b[$k]['returned']++; elseif (in_array($r->status, ['Cancel', 'Cancelled'], true)) $b[$k]['cancelled']++; }
      $out[] = $b;
    }
    return ['since' => $since, 'rows' => $out];
  }
}
