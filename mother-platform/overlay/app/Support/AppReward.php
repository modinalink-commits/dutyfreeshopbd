<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** App Install Welcome Discount: a fixed reward for installing the app with a verified mobile, used once on the first App order.
 *  It is a line in the central Discount Engine (OrderDiscount::compute calls apply() after the other discounts and the 15% profit rule), never a coupon code.
 *  Status flow: Available -> Reserved (order placed) -> Used (order moved on) ; Cancelled order -> Available again (setting) ; one reward per customer ever (unique phone + install id). */
class AppReward {
  const KEY = 'appinstall'; const SRC = 'App Install Reward'; const TYPE = 'App Install Welcome Discount';
  const PRE = ['New', 'Incomplete', 'Pre Order', 'AI Order']; // order not yet handled by staff: the reward is only Reserved
  private static ?array $cfg = null;
  /** Rule row (enabled or not) as ['on','amt','restore','keep']. */
  public static function cfg(): array {
    if (self::$cfg !== null) return self::$cfg;
    $r = null; try { $r = DB::table('discount_rules')->where('key', self::KEY)->first(); } catch (\Throwable $e) {}
    $c = $r ? (json_decode((string)$r->config, true) ?: []) : [];
    return self::$cfg = ['on' => (bool)($r->enabled ?? false), 'exists' => (bool)$r, 'id' => $r->id ?? null, 'amt' => max(0.0, (float)($c['Reward TK'] ?? 100)), 'restore' => (int)($c['Restore on cancel (1=yes)'] ?? 1) === 1, 'keep' => (int)($c['Keep unused part after safe-cap (1=yes)'] ?? 0) === 1];
  }
  public static function ready(): bool { static $h = null; if ($h === null) { try { $h = \Illuminate\Support\Facades\Schema::hasTable('app_rewards'); } catch (\Throwable $e) { $h = false; } } return $h; }
  public static function validId($v): ?string { $v = trim((string)$v); return preg_match('/^[A-Za-z0-9\-]{16,48}$/', $v) ? $v : null; }
  /** The install id of the App the request comes from: the cookie is set by the page script only while it runs as the installed app (standalone), so an ordinary browser tab never carries it. */
  public static function installId(): ?string { return self::validId($_COOKIE['df_app'] ?? ''); }
  private static function phone(string $p): string { return substr(preg_replace('/\D/', '', $p), -11); }

  /** The customer's reward row (any status) or null. */
  public static function of(string $phone): ?object {
    if (!self::ready()) return null;
    $this_ = self::phone($phone); if (strlen($this_) < 11) return null;
    return DB::table('app_rewards')->where('phone', $this_)->first();
  }

  /** Record an install (anonymous) and, when the visitor is a logged-in customer with a verified mobile, unlock the reward. Returns the state for the page. */
  public static function activate(string $installId, ?object $cust, string $ua = '', string $ip = '', bool $beat = false): array {
    if (!self::ready()) return ['ok' => false];
    $now = now();
    $row = DB::table('app_installs')->where('install_id', $installId)->first();
    if (!$row) { try { DB::table('app_installs')->insert(['install_id' => $installId, 'ua' => mb_substr($ua, 0, 160), 'ip' => $ip, 'first_seen_at' => $now, 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now]); } catch (\Throwable $e) {} }
    else DB::table('app_installs')->where('install_id', $installId)->update(['last_seen_at' => $now, 'updated_at' => $now]);
    // live presence: who is logged in inside the App right now (the App pings every minute)
    try { if (\App\Support\AppUsers::ready()) { $u = ['login_customer_id' => $cust ? $cust->id : null]; if ($cust && (!$row || (int)$row->login_customer_id !== (int)$cust->id)) $u['login_at'] = $now; DB::table('app_installs')->where('install_id', $installId)->update($u); } } catch (\Throwable $e) {}
    if ($beat) return ['ok' => true];
    $cfg = self::cfg(); $out = ['ok' => true, 'on' => $cfg['on'], 'unlocked' => false, 'status' => null, 'amount' => $cfg['amt']];
    if (!$cust) return $out + ['login' => true];
    $phone = self::phone((string)$cust->phone); if (!preg_match('/^01\d{9}$/', $phone)) return $out + ['verify' => true];
    if (empty($cust->phone_verified_at)) return $out + ['verify' => true];
    DB::table('app_installs')->where('install_id', $installId)->whereNull('customer_id')->update(['customer_id' => $cust->id, 'phone' => $phone, 'activated_at' => $now, 'updated_at' => $now]);
    $rw = DB::table('app_rewards')->where('phone', $phone)->first();
    if (!$rw && $cfg['on'] && $cfg['amt'] > 0 && !DB::table('app_rewards')->where('install_id', $installId)->exists()) {
      try { DB::table('app_rewards')->insert(['customer_id' => $cust->id, 'phone' => $phone, 'install_id' => $installId, 'amount' => $cfg['amt'], 'balance' => $cfg['amt'], 'status' => 'available', 'source' => self::SRC, 'unlocked_at' => $now, 'created_at' => $now, 'updated_at' => $now]); } catch (\Throwable $e) { /* unique: the same customer or install won it a moment ago */ }
      $rw = DB::table('app_rewards')->where('phone', $phone)->first();
      if ($rw) { try { Push::notify((int)$cust->id, 'special_discount', '🎁 ৳'.number_format($cfg['amt']).' App Install Reward', 'আপনার প্রথম App অর্ডারে ৳'.number_format($cfg['amt']).' ছাড় অটো-অ্যাপ্লাই হবে।', '/account'); } catch (\Throwable $e) {} }
    }
    if ($rw) { self::resolve($phone); $rw = DB::table('app_rewards')->where('id', $rw->id)->first(); $out['unlocked'] = true; $out['status'] = $rw->status; $out['amount'] = (float)$rw->balance > 0 ? (float)$rw->balance : (float)$rw->amount; }
    return $out;
  }

  /** Customer-facing state for the account page: null when there is nothing to show. */
  public static function view(string $phone): ?array {
    $cfg = self::cfg(); $rw = self::of($phone); if ($rw) self::resolve($rw->phone) and $rw = self::of($phone);
    if (!$rw) return $cfg['on'] && $cfg['amt'] > 0 ? ['status' => 'locked', 'amount' => $cfg['amt']] : null;
    return ['status' => $rw->status, 'amount' => (float)($rw->status === 'available' ? $rw->balance : $rw->amount), 'invoice' => $rw->invoice, 'used_at' => $rw->used_at, 'unlocked_at' => $rw->unlocked_at, 'applied' => (float)$rw->applied_amount];
  }

  /** Engine step (called by OrderDiscount::compute after the other discounts and the 15% rule). $r is the engine result, $ctx its context.
   *  Adds one "App Install Welcome Discount" line, capped by: the reward balance, what is left of the order, and the protected-profit room (Auto Safe-Cap). */
  public static function apply(array $r, ?string $phone, float $sub, array $ctx): array {
    try {
      if (empty($ctx['app']) || !$phone || !self::ready()) return $r;
      $cfg = self::cfg(); if (!$cfg['on']) return $r;
      $rw = self::of($phone); if (!$rw) return $r;
      $ex = !empty($ctx['exclude_order']) ? (int)$ctx['exclude_order'] : 0;
      $mine = $ex && (int)$rw->order_id === $ex && in_array($rw->status, ['reserved', 'used'], true);
      if ($rw->status !== 'available' && !$mine) return $r;
      $bal = $mine ? (float)$rw->applied_amount : (float)$rw->balance; if ($bal <= 0) return $r;
      $items = array_values((array)($ctx['items'] ?? []));
      $bsIn = 0.0; foreach ($r['lines'] as $l) if (($l['key'] ?? '') === 'qprice' && !empty($l['applied'])) $bsIn = (float)$l['amount'];
      $taken = (float)$r['total'] + ($bsIn > 0 ? 0 : (float)($ctx['bsave'] ?? 0));
      $left = max(0.0, $sub - (float)$r['total']); // what is still payable on the engine's subtotal
      $room = $items ? ProfitFloor::room($items) - $taken : $left;
      $amt = round(min($bal, $left, max(0.0, $room), $sub * 0.03), 2); // owner 8 Nov 2026: no discount above 3% of the order
      if ($amt <= 0) return $r;
      $safe = $amt + 0.004 < min($bal, $left);
      $r['lines'][] = ['key' => self::KEY, 'type' => self::TYPE, 'rule' => 'App Install Reward (first App order)', 'source' => self::SRC, 'pct' => null, 'amount' => $amt, 'applied' => true, 'arid' => (int)$rw->id, 'safecap' => $safe,
        'label' => '🎁 ৳'.number_format($amt).' App Install Reward applied'.($safe ? ' (maximum safe amount)' : '')];
      $r['total'] = round((float)$r['total'] + $amt, 2);
    } catch (\Throwable $e) { report($e); }
    return $r;
  }

  /** After the order is saved: attach the reward to it (Reserved) and flag the order as an App order. */
  public static function onOrder(string $phone, int $orderId, array $applied, ?string $installId): void {
    if (!self::ready()) return;
    if ($installId) { try { DB::table('orders')->where('id', $orderId)->update(['via_app' => true]); } catch (\Throwable $e) {} }
    foreach ($applied as $l) if (($l['key'] ?? '') === self::KEY && !empty($l['arid'])) {
      $inv = DB::table('orders')->where('id', $orderId)->value('invoice');
      DB::table('app_rewards')->where('id', (int)$l['arid'])->where('status', 'available')->update(['status' => 'reserved', 'order_id' => $orderId, 'invoice' => $inv, 'applied_amount' => round((float)$l['amount'], 2), 'reserved_at' => now(), 'updated_at' => now()]);
    }
  }

  /** Settle Reserved / Used rewards against the real order status. Safe to call often; $phone limits it to one customer. Returns changed count. */
  public static function resolve(?string $phone = null): int {
    if (!self::ready()) return 0;
    $cfg = self::cfg(); $n = 0;
    $q = DB::table('app_rewards')->whereIn('status', ['reserved', 'used'])->whereNotNull('order_id'); if ($phone) $q->where('phone', self::phone($phone));
    foreach ($q->limit(500)->get() as $rw) {
      $o = DB::table('orders')->where('id', $rw->order_id)->first(['status', 'invoice']);
      if (!$o) { // the order no longer exists (hard-removed): give the reward back
        DB::table('app_rewards')->where('id', $rw->id)->update(['status' => 'available', 'order_id' => null, 'applied_amount' => 0, 'restored_at' => now(), 'restore_count' => $rw->restore_count + 1, 'updated_at' => now()]); $n++; continue;
      }
      $st = (string)$o->status;
      if ($st === 'Cancel') {
        if (!$cfg['restore']) { if ($rw->status !== 'used') { DB::table('app_rewards')->where('id', $rw->id)->update(['status' => 'used', 'used_at' => now(), 'used_total' => $rw->used_total + $rw->applied_amount, 'balance' => 0, 'updated_at' => now()]); $n++; } continue; }
        $back = $rw->status === 'used' ? min((float)$rw->amount, (float)$rw->balance + (float)$rw->applied_amount) : (float)$rw->balance;
        DB::table('app_rewards')->where('id', $rw->id)->update(['status' => 'available', 'order_id' => null, 'applied_amount' => 0, 'balance' => $back, 'used_total' => max(0, $rw->used_total - ($rw->status === 'used' ? $rw->applied_amount : 0)), 'used_at' => null, 'restored_at' => now(), 'restore_count' => $rw->restore_count + 1, 'updated_at' => now()]); $n++; continue;
      }
      if ($rw->status === 'reserved' && !in_array($st, self::PRE, true)) {
        $left = round((float)$rw->balance - (float)$rw->applied_amount, 2);
        if ($cfg['keep'] && $left > 0.009) DB::table('app_rewards')->where('id', $rw->id)->update(['status' => 'available', 'order_id' => null, 'balance' => $left, 'used_total' => $rw->used_total + $rw->applied_amount, 'used_at' => now(), 'updated_at' => now()]);
        else DB::table('app_rewards')->where('id', $rw->id)->update(['status' => 'used', 'balance' => 0, 'used_total' => $rw->used_total + $rw->applied_amount, 'used_at' => now(), 'updated_at' => now()]);
        $n++;
      }
    }
    return $n;
  }

  /** Admin report numbers. */
  public static function report(): array {
    $z = ['installs' => 0, 'unlocked' => 0, 'used' => 0, 'reserved' => 0, 'available' => 0, 'amount' => 0.0, 'orders' => 0, 'app_orders' => 0, 'conv' => 0.0, 'unlock_rate' => 0.0];
    if (!self::ready()) return $z;
    $z['installs'] = (int)DB::table('app_installs')->count(); $z['unlocked'] = (int)DB::table('app_rewards')->count();
    foreach (DB::table('app_rewards')->selectRaw('status, count(*) n')->groupBy('status')->get() as $x) $z[$x->status] = (int)$x->n;
    $z['amount'] = (float)DB::table('app_rewards')->sum('used_total');
    $z['orders'] = (int)DB::table('app_rewards')->whereNotNull('used_at')->count() + (int)DB::table('app_rewards')->where('status', 'reserved')->count();
    $z['app_orders'] = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'via_app') ? (int)DB::table('orders')->where('via_app', true)->where('status', '!=', 'Cancel')->count() : 0;
    $z['conv'] = $z['unlocked'] > 0 ? round($z['used'] / $z['unlocked'] * 100, 1) : 0.0; $z['unlock_rate'] = $z['installs'] > 0 ? round($z['unlocked'] / $z['installs'] * 100, 1) : 0.0;
    return $z;
  }
}
