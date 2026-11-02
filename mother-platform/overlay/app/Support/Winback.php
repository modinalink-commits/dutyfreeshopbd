<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Inactive-customer win-back: counts days since the last Delivered order, sends the matching stage once per cycle, and applies the discount at checkout. */
class Winback
{
  const DEF_STAGES = [[15, 3], [25, 4], [35, 5], [45, 6], [55, 7]];
  const SMS = 'আমরা আপনাকে মিস করছি! আপনার পরবর্তী অর্ডারে বিশেষ {pct}% ডিসকাউন্ট উপভোগ করুন।{code}';
  const SMS_FINAL = 'আপনার জন্য আমাদের বিশেষ Welcome Back Offer — পরবর্তী অর্ডারে {pct}% ডিসকাউন্ট। আবার আমাদের সাথে অর্ডার করুন।{code}';

  public static function set(string $k, string $def = ''): string { $v = DB::table('settings')->where('key', 'wb_' . $k)->value('value'); return $v === null ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'wb_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
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
  public static function lastDelivered(?string $phone = null): array {
    $sql = "select distinct on (right(o.phone,11)) right(o.phone,11) as p, o.id as oid, l.created_at as at from order_status_logs l join orders o on o.id = l.order_id where l.to_status = 'Delivered'" . ($phone ? " and right(o.phone,11) = ?" : '') . " order by right(o.phone,11), l.created_at desc";
    $rows = DB::select($sql, $phone ? [$phone] : []); $out = [];
    $newer = DB::table('orders')->whereNotIn('status', ['Cancel', 'Returned'])->selectRaw('right(phone,11) as p, max(created_at) as m')->groupBy(DB::raw('right(phone,11)'))->pluck('m', 'p')->all();
    foreach ($rows as $r) { if (isset($newer[$r->p]) && $newer[$r->p] > $r->at) continue; $out[$r->p] = ['oid' => (int)$r->oid, 'at' => $r->at]; }
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

  private static function render(string $t, array $v): string { return trim(strtr($t, $v)); }

  public static function tick(bool $force = false): void {
    if (!self::on()) return; $today = now('Asia/Dhaka')->toDateString();
    if (!$force) { $h = (int)now('Asia/Dhaka')->format('H'); $stt = self::set('start', ''); if ($stt !== '' && $today < $stt) return; if (self::set('last_run') === $today || $h < (int)self::set('win_from', '14') || $h >= (int)self::set('win_to', '17')) return; } // offers go out only 2 PM to 5 PM Dhaka time
    $st = self::stages(); if (!$st) return; $sent = 0; $exp = max(1, (int)self::set('expiry', '30')); $mode = in_array(self::set('mode', 'auto'), ['coupon', 'fixed'], true) ? self::set('mode', 'auto') : 'auto'; $lastStage = end($st)[0];
    $ch = array_filter(['sms' => self::set('sms', '1') === '1', 'push' => self::set('push', '1') === '1', 'notif' => self::set('notif', '1') === '1']);
    $queue = self::lastDelivered(); uasort($queue, fn($a, $b) => strcmp($a['at'], $b['at'])); // serial order: whoever became eligible first is messaged first
    foreach ($queue as $p => $x) {
      $d = self::days($x['at']); $s = self::stageFor($d); if (!$s) continue;
      if (DB::table('winback_events')->where('phone', $p)->where('cycle_order_id', $x['oid'])->where('stage_days', '>=', $s[0])->exists()) continue; // each stage once per cycle
      $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$p])->where('blocked', false)->first(); if (!$c) continue;
      if ($sent >= 40) return; // small batch per tick: the rest continue on the next tick inside the window
      if (!$force && self::sentToday() + $sent >= self::dailyCap()) return; // daily SMS cap: the rest continue tomorrow
      $code = null;
      if ($mode === 'coupon') {
        $code = 'WB-' . strtoupper(substr(md5($p . $x['oid'] . $s[0] . microtime()), 0, 6));
        DB::table('coupons')->insert(['code' => $code, 'type' => 'percent', 'value' => $s[1], 'min_order' => (float)self::set('min_order', '0'), 'max_discount' => (float)self::set('max_discount', '0') ?: null, 'usage_limit' => 1, 'per_customer' => 1, 'valid_from' => now('Asia/Dhaka')->toDateString(), 'valid_to' => now('Asia/Dhaka')->addDays($exp)->toDateString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
      }
      if ($mode === 'fixed') {
        $code = self::codeFor($s[0]);
        DB::table('coupons')->updateOrInsert(['code' => $code], ['type' => 'percent', 'value' => $s[1], 'min_order' => (float)self::set('min_order', '0'), 'max_discount' => (float)self::set('max_discount', '0') ?: null, 'usage_limit' => null, 'per_customer' => 1, 'valid_from' => now('Asia/Dhaka')->toDateString(), 'valid_to' => null, 'active' => true, 'updated_at' => now(), 'created_at' => now()]);
      }
      try { DB::table('winback_events')->insert(['phone' => $p, 'customer_id' => $c->id, 'cycle_order_id' => $x['oid'], 'stage_days' => $s[0], 'pct' => $s[1], 'mode' => $mode, 'coupon_code' => $code, 'channels' => implode(',', array_keys($ch)), 'expires_at' => now()->addDays($exp), 'created_at' => now(), 'updated_at' => now()]); }
      catch (\Throwable $e) { continue; }
      $v = ['{name}' => $c->name, '{pct}' => rtrim(rtrim(number_format($s[1], 2), '0'), '.'), '{days}' => $d, '{expiry}' => $exp, '{code}' => $code ? ' কুপন কোড: ' . $code : ''];
      $final = $s[0] === $lastStage;
      if (!empty($ch['sms'])) Sms::queue($p, self::render(self::set($final ? 'sms_final' : 'sms_text', $final ? self::SMS_FINAL : self::SMS), $v), 'winback');
      if (!empty($ch['push']) || !empty($ch['notif'])) { try { Push::notify((int)$c->id, 'coupon', 'Welcome Back Offer — ' . $v['{pct}'] . '% ছাড়', self::render(self::set($final ? 'sms_final' : 'sms_text', $final ? self::SMS_FINAL : self::SMS), $v), '/account/coupons'); } catch (\Throwable $e) {} }
      $sent++;
    }
    self::put('last_run', $today);
  }

  public static function dailyCap(): int { return max(1, (int)self::set('daily_cap', '250')); }
  public static function sentToday(): int { return (int)DB::table('winback_events')->where('cycle_order_id', '>', 0)->whereRaw("(created_at at time zone 'UTC' at time zone 'Asia/Dhaka')::date = ?", [now('Asia/Dhaka')->toDateString()])->count(); }
  /** Who will be messaged at the next send window, in serial order (oldest delivery first). Read-only preview. */
  public static function queue(?\Carbon\Carbon $at = null): array {
    $st = self::stages(); if (!$st) return ['rows' => [], 'total' => 0, 'when' => null];
    $start = self::set('start', ''); $day = now('Asia/Dhaka')->addDay()->startOfDay(); if ($start !== '' && $day->toDateString() < $start) $day = \Carbon\Carbon::parse($start, 'Asia/Dhaka')->startOfDay();
    $at = $at ?: $day->copy()->setTime((int)self::set('win_from', '14'), 0); $ts = $at->getTimestamp(); $cand = [];
    $all = self::lastDelivered(); uasort($all, fn($a, $b) => strcmp($a['at'], $b['at']));
    foreach ($all as $p => $x) {
      $d = (int)floor(($ts - strtotime($x['at'] . ' UTC')) / 86400); $s = self::stageFor($d); if (!$s) continue;
      if (DB::table('winback_events')->where('phone', $p)->where('cycle_order_id', $x['oid'])->where('stage_days', '>=', $s[0])->exists()) continue;
      $cand[$p] = ['p' => $p, 'd' => $d, 's' => $s, 'at' => $x['at']];
    }
    $names = []; foreach (array_chunk(array_keys($cand), 1000) as $ch) foreach (DB::table('customers')->where('blocked', false)->whereRaw('right(phone,11) in (' . implode(',', array_fill(0, count($ch), '?')) . ')', $ch)->selectRaw('right(phone,11) as k, name')->get() as $cu) $names[$cu->k] = $cu->name;
    $rows = []; foreach ($cand as $p => $c) { if (!array_key_exists($p, $names)) continue; $c['name'] = $names[$p]; $c['code'] = self::set('mode', 'auto') === 'fixed' ? self::codeFor($c['s'][0]) : null; $rows[] = $c; }
    return ['rows' => $rows, 'total' => count($rows), 'when' => $at];
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
