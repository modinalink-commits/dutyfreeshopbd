<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Inactive-customer win-back: counts days since the last Delivered order, sends the matching stage once per cycle, and applies the discount at checkout. */
class Winback
{
  const DEF_STAGES = [[15, 1], [25, 1.5], [35, 2], [45, 2.5], [55, 3]];
  const SMS = 'আমরা আপনাকে মিস করছি! আপনার পরবর্তী অর্ডারে বিশেষ {pct}% ডিসকাউন্ট উপভোগ করুন।{code} অর্ডার করুন: {link}';
  const SMS_FINAL = 'আপনার জন্য আমাদের বিশেষ Welcome Back Offer — পরবর্তী অর্ডারে {pct}% ডিসকাউন্ট। আবার আমাদের সাথে অর্ডার করুন।{code} {link}';

  private static ?array $mem = null;
  public static function set(string $k, string $def = ''): string { if (self::$mem === null) self::$mem = DB::table('settings')->where('key', 'like', 'wb\\_%')->pluck('value', 'key')->all(); $v = self::$mem['wb_' . $k] ?? null; return $v === null ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'wb_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); self::$mem = null; }
  public static function on(): bool { return self::set('on', '0') === '1'; }
  public static function stages(): array {
    $j = json_decode(self::set('stages', ''), true); $s = is_array($j) && $j ? $j : self::DEF_STAGES; $max = (float)self::set('max_pct', '100'); if ($max <= 0) $max = 100000;
    $o = []; foreach ($s as $r) { $d = (int)($r[0] ?? 0); $p = min((float)($r[1] ?? 0), $max); if ($d > 0 && $p > 0) $o[$d] = $p; } ksort($o); $out = []; foreach ($o as $d => $p) $out[] = [$d, $p]; return $out;
  }
  /** Fixed coupon code per stage (admin-editable; default WB-<days>). */
  public static function codes(): array { $j = json_decode(self::set('codes', ''), true); return is_array($j) ? $j : []; }
  public static function codeFor(int $days): string { $c = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)(self::codes()[$days] ?? ''))); return $c !== '' ? $c : 'WB-' . $days; }
  public static function norm(string $p): string { return substr(preg_replace('/\D/', '', $p), -11); }

  /** phone => [oid, at] of the latest Delivered order for customers with no newer live order. */
  /** Last order placed per customer phone (any live order, cancelled/returned ignored). Name kept for callers; the countdown starts from the order date. */
  public static function lastDelivered(?string $phone = null): array {
    $sql = "select distinct on (right(o.phone,11)) right(o.phone,11) as p, o.id as oid, o.created_at as at from orders o where o.status not in ('Cancel','Returned') and o.phone is not null and length(o.phone) >= 10" . ($phone ? " and right(o.phone,11) = ?" : '') . " order by right(o.phone,11), o.created_at desc, o.id desc";
    $out = [];
    foreach (DB::select($sql, $phone ? [$phone] : []) as $r) $out[$r->p] = ['oid' => (int)$r->oid, 'at' => $r->at];
    return $out;
  }
  public static function days(string $at): int { return (int)floor((time() - strtotime($at . ' UTC')) / 86400); }
  public static function stageFor(int $days): ?array { $hit = null; foreach (self::stages() as $s) if ($days >= $s[0]) $hit = $s; return $hit; }
  public static function nextStage(int $days): ?array { foreach (self::stages() as $s) if ($days < $s[0]) return $s; return null; }

  /** Active (unused, unexpired, same cycle) offer for a phone. */
  public static function active(string $phone): ?object {
    $p = self::norm($phone); if (!$p) return null;
    $e = DB::table('winback_events')->where('phone', $p)->whereNull('used_at')->where('expires_at', '>', now())->orderByDesc('id')->first(); if (!$e) return null;
    if (DB::table('orders')->whereRaw('right(phone,11) = ?', [$p])->whereNotIn('status', ['Cancel', 'Returned'])->where('created_at', '>', $e->created_at)->exists()) return null; // new order reset the cycle
    return $e;
  }

  const LINK = 'https://dutyfreebd.com';
  private static function render(string $t, array $v): string { $v['{link}'] = self::LINK; $o = trim(strtr($t, $v)); if (stripos($o, 'dutyfreebd.com') === false) $o .= ' ' . self::LINK; return $o; }

  public static function tick(bool $force = false): void {
    if (!self::on()) return; $today = now('Asia/Dhaka')->toDateString();
    $blast = self::set('blast', '0') === '1'; if ($blast) $force = true; // "Run now": send to everyone eligible now, in batches on every worker cycle, until the list is finished
    if (!$force) { $stt = self::set('start', ''); if ($stt !== '' && $today < $stt) return; if (self::quiet()) return; if (time() - (int)self::set('last_slot', '0') < self::slotGapMin() * 60) return; self::put('last_slot', (string)time()); } // no fixed timetable: one small slot every few minutes, never during quiet hours
    $st = self::stages(); if (!$st) return; $sent = 0; $exp = max(1, (int)self::set('expiry', '30')); $mode = in_array(self::set('mode', 'auto'), ['coupon', 'fixed'], true) ? self::set('mode', 'auto') : 'auto'; $lastStage = end($st)[0];
    $ch = array_filter(['sms' => self::set('sms', '1') === '1', 'push' => self::set('push', '1') === '1', 'notif' => self::set('notif', '1') === '1']);
    $room = min(self::slotSize(), self::dailyCap() - self::sentToday());
    if ($blast) { $room = 200; $lim = (int)self::set('blast_limit', '0'); if ($lim > 0) { $got = DB::table('winback_events')->where('cycle_order_id', '>', 0)->where('created_at', '>=', \Carbon\Carbon::parse(self::set('blast_at', now()->toIso8601String()))->utc())->count(); $room = min(200, $lim - $got); if ($room <= 0) { self::put('blast', '0'); self::put('blast_done', now()->toIso8601String()); return; } } }
    if ($room <= 0) return; $done = self::doneSet(); $lastSent = self::lastSent(); $gap = self::minGapDays() * 86400;
    $queue = self::lastDelivered(); self::rank($queue); // serial order: best customers (highest spend) first, then longest inactive
    foreach ($queue as $p => $x) {
      $d = self::days($x['at']); $s = self::stageFor($d); if (!$s) continue;
      if (isset($lastSent[$p]) && time() - $lastSent[$p] < $gap) continue; // never more than one offer message per number every 15 days
      $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$p])->where('blocked', false)->first(); if (!$c) continue;
      if ($sent >= $room) { if ($blast) { try { \App\Support\Sms::flush(200); } catch (\Throwable $e) {} self::guardBlast(); } return; } // small batch per tick + daily cap: the rest continue on the next tick / tomorrow
      if (!self::finalOk($p, $x, $c)) continue; // last check right before sending: ON, no newer order, valid mobile, not opted out, cooldown over, no repeated failures
      $lock = null; try { $lock = \Illuminate\Support\Facades\Cache::lock('winback:sms:customer:' . $p, 30); if (!$lock->get()) continue; } catch (\Throwable $e) { $lock = null; }
      if (self::cooling($p)) { if ($lock) $lock->release(); continue; } // re-read inside the lock so two workers can never both send
      $code = null;
      if ($mode === 'coupon') {
        $code = 'WB-' . strtoupper(substr(md5($p . $x['oid'] . $s[0] . microtime()), 0, 6));
        DB::table('coupons')->insert(['code' => $code, 'type' => 'percent', 'value' => $s[1], 'min_order' => (float)self::set('min_order', '0'), 'max_discount' => (float)self::set('max_discount', '0') ?: null, 'usage_limit' => 1, 'per_customer' => 1, 'valid_from' => now('Asia/Dhaka')->toDateString(), 'valid_to' => now('Asia/Dhaka')->addDays($exp)->toDateString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
      }
      if ($mode === 'fixed') {
        $code = self::codeFor($s[0]);
        DB::table('coupons')->updateOrInsert(['code' => $code], ['type' => 'percent', 'value' => $s[1], 'min_order' => (float)self::set('min_order', '0'), 'max_discount' => (float)self::set('max_discount', '0') ?: null, 'usage_limit' => null, 'per_customer' => 1, 'valid_from' => now('Asia/Dhaka')->toDateString(), 'valid_to' => null, 'active' => true, 'updated_at' => now(), 'created_at' => now()]);
      }
      try { DB::table('winback_events')->insert(['phone' => $p, 'customer_id' => $c->id, 'cycle_order_id' => $x['oid'], 'stage_days' => $s[0], 'pct' => $s[1], 'mode' => $mode, 'coupon_code' => $code, 'channels' => implode(',', array_keys($ch)), 'expires_at' => now()->addDays($exp), 'created_at' => now(), 'updated_at' => now()] + (self::hasNext() ? ['next_sms_at' => now()->addDays(self::minGapDays())] : [])); }
      catch (\Throwable $e) { if ($lock) $lock->release(); continue; }
      if ($lock) $lock->release();
      $v = ['{name}' => $c->name, '{pct}' => rtrim(rtrim(number_format($s[1], 2), '0'), '.'), '{days}' => $d, '{expiry}' => $exp, '{code}' => $code ? ' কুপন কোড: ' . $code : ''];
      $final = $s[0] === $lastStage;
      if (!empty($ch['sms'])) Sms::queue($p, self::render(self::set($final ? 'sms_final' : 'sms_text', $final ? self::SMS_FINAL : self::SMS), $v), 'winback');
      if (!empty($ch['push']) || !empty($ch['notif'])) { try { Push::notify((int)$c->id, 'coupon', 'Welcome Back Offer — ' . $v['{pct}'] . '% ছাড়', self::render(self::set($final ? 'sms_final' : 'sms_text', $final ? self::SMS_FINAL : self::SMS), $v), '/account/coupons'); } catch (\Throwable $e) {} }
      $sent++;
    }
    if ($blast) { self::put('blast', '0'); self::put('blast_done', now()->toIso8601String()); } // list finished
    self::put('last_run', $today);
    if ($blast || $sent > 0) { try { \App\Support\Sms::flush($blast ? 200 : 25); } catch (\Throwable $e) {} }
    if ($blast) self::guardBlast();
  }
  /** If the SMS gateway starts refusing (no balance, sender disabled, IP blocked), stop the run and re-open the customers whose message failed so nobody is marked as 'offered' without getting it. */
  private static function guardBlast(): void {
    $f = DB::table('sms_logs')->where('event', 'winback')->where('status', 'failed')->where('updated_at', '>', now()->subMinutes(20))->where(fn($q) => $q->where('provider_response', 'like', '1006%')->orWhere('provider_response', 'like', '1007%')->orWhere('provider_response', 'like', '1002%')->orWhere('provider_response', 'like', '1018%')->orWhere('provider_response', 'like', '1031%')->orWhere('provider_response', 'like', '1032%')->orWhere('provider_response', 'like', '1015%'))->get(['to', 'provider_response']);
    if ($f->count() < 3) return;
    self::put('blast', '0'); self::put('blast_stop', now()->toIso8601String() . ' — ' . mb_substr((string)$f->first()->provider_response, 0, 80));
    foreach ($f as $l) DB::table('winback_events')->where('phone', self::norm((string)$l->to))->whereNull('used_at')->where('cycle_order_id', '>', 0)->where('created_at', '>', now()->subMinutes(40))->delete();
  }

  /** 'phone|cycleOrderId' => highest stage already sent (one query instead of one per customer). */
  /** phone => time of the last offer message sent to that number (customer rule: a number gets at most one offer message every 15 days). */
  /** Events whose SMS was really handed to the gateway (a failed SMS does not start the cooldown). */
  private static function okSql(): string { return "from winback_events e where e.cycle_order_id > 0 and not exists (select 1 from sms_logs l where l.event = 'winback' and l.status = 'failed' and right(l.\"to\", 11) = e.phone and l.created_at >= e.created_at - interval '2 minutes' and l.created_at <= e.created_at + interval '30 minutes')"; }
  public static function lastSent(): array { $o = []; foreach (DB::select('select e.phone, max(e.created_at) as m ' . self::okSql() . ' group by e.phone') as $r) $o[self::norm((string)$r->phone)] = strtotime($r->m . ' UTC'); return $o; }
  /** Fresh single-number read of the last accepted offer message. */
  public static function lastSentFor(string $p): ?int { $r = DB::selectOne('select max(e.created_at) as m ' . self::okSql() . ' and e.phone = ?', [$p]); return $r && $r->m ? strtotime($r->m . ' UTC') : null; }
  public static function cooling(string $p): bool { $l = self::lastSentFor($p); return $l !== null && time() - $l < self::minGapDays() * 86400; }
  private static ?bool $hasNext = null;
  public static function hasNext(): bool { return self::$hasNext ??= \Illuminate\Support\Facades\Schema::hasColumn('winback_events', 'next_sms_at'); }
  public static function slotSize(): int { return max(5, min(10, (int)self::set('slot_size', '10'))); }
  public static function slotGapMin(): int { return max(1, min(240, (int)self::set('slot_gap', '10'))); }
  /** Quiet hours (Dhaka time) when no offer SMS goes out. */
  public static function quiet(): bool { $f = (int)self::set('quiet_from', '22'); $t = (int)self::set('quiet_to', '8'); if ($f === $t) return false; $h = (int)now('Asia/Dhaka')->format('H'); return $f > $t ? ($h >= $f || $h < $t) : ($h >= $f && $h < $t); }
  public static function validMobile(string $p): bool { return (bool)preg_match('/^01[3-9]\d{8}$/', $p); }
  /** Final check right before a send. */
  public static function finalOk(string $p, array $x, object $c): bool {
    if (!self::on() || !self::validMobile($p) || !empty($c->blocked)) return false;
    if (DB::table('sms_optouts')->where('phone', $p)->exists()) return false;
    if (DB::table('orders')->whereRaw('right(phone,11) = ?', [$p])->whereNotIn('status', ['Cancel', 'Returned'])->where('created_at', '>', $x['at'])->exists()) return false; // ordered meanwhile: cycle is closed
    if (self::cooling($p)) return false;
    if (DB::table('sms_logs')->where('event', 'winback')->where('status', 'failed')->whereRaw('right("to", 11) = ?', [$p])->where('created_at', '>', now()->subDay())->count() >= 3) return false; // gateway keeps refusing this number: stop retrying
    return true;
  }
  public static function minGapDays(): int { return max(1, (int)self::set('min_gap', '15')); }
  public static function doneSet(): array { $o = []; foreach (DB::table('winback_events')->selectRaw('phone, cycle_order_id, max(stage_days) as m')->groupBy('phone', 'cycle_order_id')->get() as $r) $o[$r->phone . '|' . $r->cycle_order_id] = (int)$r->m; return $o; }
  /** phone => [orders, spent] over live orders; used to message the best customers first. */
  public static function value(): array { $o = []; foreach (DB::table('orders')->whereNotIn('status', ['Cancel', 'Returned'])->whereNotNull('phone')->selectRaw('right(phone,11) as p, count(*) as n, coalesce(sum(total),0) as s')->groupBy(DB::raw('right(phone,11)'))->get() as $r) $o[$r->p] = [(int)$r->n, (float)$r->s]; return $o; }
  /** Serial order: highest lifetime spend first, then the longest inactive. */
  public static function rank(array &$all): array { $v = self::value(); uksort($all, function ($a, $b) use ($all, $v) { $sa = $v[$a][1] ?? 0; $sb = $v[$b][1] ?? 0; return $sb <=> $sa ?: strcmp($all[$a]['at'], $all[$b]['at']); }); return $v; }
  public static function dailyCap(): int { return max(1, (int)self::set('daily_cap', '250')); }
  public static function sentToday(): int { return (int)DB::table('winback_events')->where('cycle_order_id', '>', 0)->whereRaw("(created_at at time zone 'UTC' at time zone 'Asia/Dhaka')::date = ?", [now('Asia/Dhaka')->toDateString()])->count(); }
  /** Who will be messaged at the next send window, in serial order (oldest delivery first). Read-only preview. */
  public static function queue(?\Carbon\Carbon $at = null): array {
    $st = self::stages(); if (!$st) return ['rows' => [], 'total' => 0, 'when' => null];
    $start = self::set('start', ''); $at = $at ?: ($start !== '' && now('Asia/Dhaka')->toDateString() < $start ? \Carbon\Carbon::parse($start, 'Asia/Dhaka')->startOfDay() : now('Asia/Dhaka')); $ts = $at->getTimestamp(); $cand = []; $cool = [];
    $done = self::doneSet(); $all = self::lastDelivered(); $val = self::rank($all); $lastSent = self::lastSent(); $gap = self::minGapDays() * 86400;
    foreach ($all as $p => $x) {
      $d = (int)floor(($ts - strtotime($x['at'] . ' UTC')) / 86400); $s = self::stageFor($d); if (!$s) continue;
      if (isset($lastSent[$p]) && $ts - $lastSent[$p] < $gap) { $cool[$p] = ['p' => $p, 'd' => $d, 's' => $s, 'at' => $x['at'], 'last' => $lastSent[$p], 'next' => $lastSent[$p] + $gap, 'left' => (int)ceil(($lastSent[$p] + $gap - $ts) / 86400), 'n' => $val[$p][0] ?? 0, 'sp' => $val[$p][1] ?? 0]; continue; }
      $cand[$p] = ['p' => $p, 'd' => $d, 's' => $s, 'at' => $x['at'], 'n' => $val[$p][0] ?? 0, 'sp' => $val[$p][1] ?? 0];
    }
    $optout = array_flip(DB::table('sms_optouts')->pluck('phone')->map(fn($v) => self::norm((string)$v))->all());
    foreach ($cand as $p => $_) if (isset($optout[$p]) || !self::validMobile($p)) unset($cand[$p]);
    $names = []; foreach (array_chunk(array_keys($cand), 1000) as $ch) foreach (DB::table('customers')->where('blocked', false)->whereRaw('right(phone,11) in (' . implode(',', array_fill(0, count($ch), '?')) . ')', $ch)->selectRaw('right(phone,11) as k, name')->get() as $cu) $names[$cu->k] = $cu->name;
    $rows = []; foreach ($cand as $p => $c) { if (!array_key_exists($p, $names)) continue; $c['name'] = $names[$p]; $c['code'] = self::set('mode', 'auto') === 'fixed' ? self::codeFor($c['s'][0]) : null; $rows[] = $c; }
    $cn = []; foreach (array_chunk(array_keys($cool), 1000) as $ch) foreach (DB::table('customers')->whereRaw('right(phone,11) in (' . implode(',', array_fill(0, count($ch), '?')) . ')', $ch)->selectRaw('right(phone,11) as k, name')->get() as $cu) $cn[$cu->k] = $cu->name;
    foreach ($cool as $p => &$cr) $cr['name'] = $cn[$p] ?? null; unset($cr);
    usort($cool, fn($a, $b) => $a['next'] <=> $b['next']);
    return ['rows' => $rows, 'total' => count($rows), 'when' => $at, 'cool' => $cool];
  }

  /** Send the offer to ONE existing customer only (safe test): real SMS via the SMS API, real discount, no other customer touched. */
  public static function testSend(string $phone, float $pct): string {
    $p = self::norm($phone); $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$p])->first(); if (!$c) return 'No customer account with this number — create/login the account first.';
    $max = (float)self::set('max_pct', '100'); if ($max > 0) $pct = min($pct, $max); if ($pct <= 0) return 'Enter a discount % above 0.';
    $exp = max(1, (int)self::set('expiry', '30')); $mode = self::set('mode', 'auto') === 'auto' ? 'auto' : 'coupon'; $code = null;
    DB::table('winback_events')->where('phone', $p)->where('cycle_order_id', 0)->whereNull('used_at')->delete();
    if ($mode === 'coupon') { $code = 'WB-' . strtoupper(substr(md5($p . microtime()), 0, 6));
      DB::table('coupons')->insert(['code' => $code, 'type' => 'percent', 'value' => $pct, 'min_order' => (float)self::set('min_order', '0'), 'max_discount' => (float)self::set('max_discount', '0') ?: null, 'usage_limit' => 1, 'per_customer' => 1, 'valid_from' => now('Asia/Dhaka')->toDateString(), 'valid_to' => now('Asia/Dhaka')->addDays($exp)->toDateString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]); }
    DB::table('winback_events')->insert(['phone' => $p, 'customer_id' => $c->id, 'cycle_order_id' => 0, 'stage_days' => 0, 'pct' => $pct, 'mode' => $mode, 'coupon_code' => $code, 'channels' => 'sms', 'expires_at' => now()->addDays($exp), 'created_at' => now(), 'updated_at' => now()]);
    $v = ['{name}' => $c->name, '{pct}' => rtrim(rtrim(number_format($pct, 2), '0'), '.'), '{days}' => 0, '{expiry}' => $exp, '{code}' => $code ? ' কুপন কোড: ' . $code : ''];
    Sms::queue($p, self::render(self::set('sms_text', self::SMS), $v), 'winback');
    return 'Test offer (' . $v['{pct}'] . '%) sent by SMS to ' . $p . ' only' . ($code ? ' — code ' . $code : ' — applies automatically to that account') . '.';
  }

  public static function onOrder(string $phone, int $orderId, array $qt): void {
    $p = self::norm($phone); $o = DB::table('orders')->find($orderId); if (!$o) return;
    foreach ($qt['applied'] ?? [] as $l) if (($l['key'] ?? '') === 'winback' && !empty($l['wbid'])) DB::table('winback_events')->where('id', $l['wbid'])->update(['used_at' => now(), 'used_order_id' => $orderId, 'used_discount' => (float)$l['amount'], 'used_total' => (float)$o->total, 'updated_at' => now()]);
    if (!empty($qt['coupon'])) {
      $disc = 0; foreach ($qt['applied'] ?? [] as $l) if (($l['key'] ?? '') === 'coupon') $disc = (float)$l['amount'];
      DB::table('winback_events')->where('coupon_code', strtoupper((string)$qt['coupon']))->where('phone', $p)->whereNull('used_at')->update(['used_at' => now(), 'used_order_id' => $orderId, 'used_discount' => $disc, 'used_total' => (float)$o->total, 'updated_at' => now()]);
    }
  }

  /** Customer Activity block data. */
  public static function activity(string $phone): array {
    $p = self::norm($phone); $ld = self::lastDelivered($p); $x = $ld[$p] ?? null;
    if (!$x) { $any = DB::table('order_status_logs as l')->join('orders as o', 'o.id', '=', 'l.order_id')->where('l.to_status', 'Delivered')->whereRaw('right(o.phone,11) = ?', [$p])->max('l.created_at'); $x = $any ? ['oid' => 0, 'at' => $any, 'reset' => true] : null; }
    $d = $x ? self::days($x['at']) : null; $cur = $d !== null && empty($x['reset']) ? self::stageFor($d) : null; $nx = $d !== null && empty($x['reset']) ? self::nextStage($d) : null;
    $ev = DB::table('winback_events')->where('phone', $p)->orderByDesc('id')->first();
    return ['last' => $x ? \Carbon\Carbon::parse($x['at'], 'UTC')->timezone('Asia/Dhaka')->format('d M Y') : null, 'days' => $d, 'reset' => !empty($x['reset']), 'stage' => $cur, 'next' => $nx, 'event' => $ev, 'active' => self::active($p)];
  }
}
