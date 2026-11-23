<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Customer reorder reminder: tobacco is a consumable, so each customer has their own buying rhythm. This measures that rhythm from the
 * customer's own order history (median gap of the last orders) and, a little before the pack usually runs out, sends ONE short
 * reminder with the website link. No discount is offered and none is invented. It runs before the Win-Back offers (15+ days).
 * (Not related to Support\Reorder, which is the supplier stock alert.)
 */
class CustomerReorder
{
  const SMS = 'DutyFreeBD: আপনার পণ্য ফুরিয়ে আসছে? আবার অর্ডার করুন: {link}';
  const LINK = 'dutyfreebd.com';

  private static ?array $mem = null;
  public static function set(string $k, string $def = ''): string { if (self::$mem === null) self::$mem = DB::table('settings')->where('key', 'like', 'ro\\_%')->pluck('value', 'key')->all(); $v = self::$mem['ro_' . $k] ?? null; return $v === null ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'ro_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); self::$mem = null; }
  public static function on(): bool { return self::set('on', '0') === '1'; }
  public static function dailyCap(): int { return max(1, (int)self::set('daily_cap', '100')); }
  public static function slotSize(): int { return max(1, min(20, (int)self::set('slot_size', '10'))); }
  public static function slotGap(): int { return max(1, min(240, (int)self::set('slot_gap', '10'))); }
  public static function minDays(): int { return max(3, (int)self::set('min_days', '7')); }
  public static function maxDays(): int { return max(self::minDays(), (int)self::set('max_days', '13')); }
  public static function defaultDays(): int { return max(self::minDays(), min(self::maxDays(), (int)self::set('default_days', '10'))); }
  public static function gapDays(): int { return max(1, (int)self::set('min_gap', '15')); }
  public static function quiet(): bool { $f = (int)self::set('quiet_from', '22'); $t = (int)self::set('quiet_to', '10'); if ($f === $t) return false; $h = (int)now('Asia/Dhaka')->format('H'); return $f > $t ? ($h >= $f || $h < $t) : ($h >= $f && $h < $t); }
  public static function sentToday(): int { return (int)DB::table('reorder_events')->whereRaw("(created_at at time zone 'UTC' at time zone 'Asia/Dhaka')::date = ?", [now('Asia/Dhaka')->toDateString()])->count(); }

  /** Days after the last order when the reminder is due: 85% of the customer's own median gap, kept between min and max (before Win-Back day 15). */
  public static function dueDays(?float $cycle): int { return $cycle && $cycle > 0 ? (int)max(self::minDays(), min(self::maxDays(), round($cycle * 0.85))) : self::defaultDays(); }

  /** phone => [oid, at, n (orders), cycle (median gap days of the last orders, null if only one order)] for customers whose last order was in the last 20 days. */
  private static ?array $cMemo = null; private static float $cAt = 0;
  public static function candidates(): array {
    if (self::$cMemo !== null && microtime(true) - self::$cAt < 60) return self::$cMemo;
    $live = "status not in ('Cancel','Returned') and phone is not null and length(phone) >= 10";
    $sql = "with o as (select right(phone,11) as p, created_at, id from orders where $live and right(phone,11) in (select right(phone,11) from orders where $live and created_at > now() - interval '20 days')),
      w as (select p, created_at, id, lag(created_at) over (partition by p order by created_at, id) as prev, row_number() over (partition by p order by created_at desc, id desc) as rn from o)
      select p, max(case when rn = 1 then created_at end) as at, max(case when rn = 1 then id end) as oid, count(*) as n,
        percentile_cont(0.5) within group (order by extract(epoch from (created_at - prev)) / 86400) filter (where prev is not null and rn <= 4) as cyc
      from w group by p";
    $out = []; foreach (DB::select($sql) as $r) $out[$r->p] = ['oid' => (int)$r->oid, 'at' => $r->at, 'n' => (int)$r->n, 'cycle' => $r->cyc !== null ? round((float)$r->cyc, 1) : null];
    self::$cMemo = $out; self::$cAt = microtime(true); return $out;
  }
  public static function days(string $at): int { return (int)floor((time() - strtotime($at . ' UTC')) / 86400); }

  /** Who is due right now (read-only list, longest waiting first). The day before the first Win-Back stage is the upper limit: after that the Win-Back offers take over. */
  public static function due(): array {
    $until = 14; try { $st = Winback::stages(); if ($st) $until = max(1, (int)$st[0][0] - 1); } catch (\Throwable $e) {}
    $done = DB::table('reorder_events')->pluck('order_id', 'phone')->all(); $rows = [];
    foreach (self::candidates() as $p => $x) {
      $d = self::days($x['at']); $due = self::dueDays($x['cycle']);
      if ($d < $due || $d > $until) continue;
      if (isset($done[$p]) && (int)$done[$p] === $x['oid']) continue; // already reminded for this order
      $rows[] = ['p' => $p, 'd' => $d, 'due' => $due, 'n' => $x['n'], 'cycle' => $x['cycle'], 'oid' => $x['oid'], 'at' => $x['at']];
    }
    usort($rows, fn($a, $b) => $b['d'] <=> $a['d']);
    return $rows;
  }
  public static function lastSent(): array { $o = []; foreach (DB::select('select phone, max(created_at) as m from reorder_events group by phone') as $r) $o[$r->phone] = strtotime($r->m . ' UTC'); return $o; }

  /** Final check right before a send. */
  private static function ok(string $p, array $x, object $c, array $last): bool {
    if (!self::on() || !Winback::validMobile($p) || !empty($c->blocked)) return false;
    if (DB::table('sms_optouts')->where('phone', $p)->exists()) return false;
    if (DB::table('orders')->whereRaw('right(phone,11) = ?', [$p])->whereNotIn('status', ['Cancel', 'Returned'])->where('created_at', '>', $x['at'])->exists()) return false; // already ordered again
    if (Winback::cooling($p)) return false; // never stack on a Win-Back message
    $l = $last[$p] ?? null; if ($l !== null && time() - $l < self::gapDays() * 86400) return false;
    return true;
  }

  public static function tick(): void {
    if (!self::on() || self::quiet()) return;
    if (time() - (int)self::set('last_slot', '0') < self::slotGap() * 60) return; self::put('last_slot', (string)time());
    $room = min(self::slotSize(), self::dailyCap() - self::sentToday()); if ($room <= 0) return; $sent = 0; $last = self::lastSent();
    foreach (self::due() as $x) {
      if ($sent >= $room) break; $p = $x['p'];
      $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$p])->where('blocked', false)->first(); if (!$c) continue;
      if (!self::ok($p, $x, $c, $last)) continue;
      $lock = null; try { $lock = \Illuminate\Support\Facades\Cache::lock('reorder:sms:' . $p, 30); if (!$lock->get()) continue; } catch (\Throwable $e) { $lock = null; }
      try { DB::table('reorder_events')->insert(['phone' => $p, 'customer_id' => $c->id, 'order_id' => $x['oid'], 'orders_n' => $x['n'], 'cycle_days' => $x['cycle'], 'due_days' => $x['due'], 'days_since' => $x['d'], 'created_at' => now(), 'updated_at' => now()]); }
      catch (\Throwable $e) { if ($lock) $lock->release(); continue; }
      if ($lock) $lock->release();
      $t = trim(strtr(self::set('sms', self::SMS), ['{link}' => self::LINK, '{name}' => (string)$c->name])); if (stripos($t, 'dutyfreebd.com') === false) $t .= ' ' . self::LINK;
      Sms::queue($p, $t, 'reorder'); $sent++;
    }
    if ($sent > 0) { try { Sms::flush(25); } catch (\Throwable $e) {} }
    self::put('last_run', now()->toIso8601String());
  }

  /** Page figures: buying-rhythm distribution (all customers with at least 2 orders), results of the reminders. */
  public static function stats(): array {
    $live = "status not in ('Cancel','Returned') and phone is not null and length(phone) >= 10";
    $r = DB::selectOne("with o as (select right(phone,11) as p, created_at, id from orders where $live),
      w as (select p, extract(epoch from (created_at - lag(created_at) over (partition by p order by created_at, id))) / 86400 as gap, row_number() over (partition by p order by created_at desc, id desc) as rn from o),
      c as (select p, percentile_cont(0.5) within group (order by gap) as cyc from w where gap is not null and rn <= 4 group by p)
      select (select count(distinct p) from o) as cust, count(*) as multi,
        count(*) filter (where cyc <= 7) as b1, count(*) filter (where cyc > 7 and cyc <= 14) as b2, count(*) filter (where cyc > 14 and cyc <= 21) as b3, count(*) filter (where cyc > 21 and cyc <= 30) as b4, count(*) filter (where cyc > 30) as b5,
        percentile_cont(0.5) within group (order by cyc) as med from c");
    $conv = (int)(DB::selectOne("select count(*) as n from reorder_events e where exists (select 1 from orders o where right(o.phone,11) = e.phone and o.created_at > e.created_at and o.status not in ('Cancel','Returned'))")->n ?? 0);
    return ['cust' => (int)($r->cust ?? 0), 'multi' => (int)($r->multi ?? 0), 'b' => [(int)($r->b1 ?? 0), (int)($r->b2 ?? 0), (int)($r->b3 ?? 0), (int)($r->b4 ?? 0), (int)($r->b5 ?? 0)], 'med' => $r->med !== null ? round((float)$r->med, 1) : null,
      'sent' => (int)DB::table('reorder_events')->count(), 'conv' => $conv];
  }
}
