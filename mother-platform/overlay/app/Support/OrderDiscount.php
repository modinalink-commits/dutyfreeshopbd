<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Works out which saved discount rules a customer qualifies for on a new order. Nothing is invented: every line comes from a saved rule, level, coupon or customer profile value. */
class OrderDiscount {
  const ORDER = ['special', 'membership', 'birthday', 'first', 'coupon'];
  const MODES = ['highest' => 'Highest discount only', 'priority' => 'Priority order (first match only)', 'stack' => 'Multiple discounts allowed'];
  public static function mode(): string { $m = (string)DB::table('settings')->where('key', 'order_disc_mode')->value('value'); return isset(self::MODES[$m]) ? $m : 'highest'; }
  private static function rule(string $k): ?array { $r = DB::table('discount_rules')->where('key', $k)->where('enabled', true)->first(); return $r ? (json_decode($r->config, true) ?: []) : null; }
  private static function alt(string $p): array { return [$p, '+88'.$p, '88'.$p]; }
  public static function stats(string $p, $c): array {
    $a = DB::table('orders')->whereIn('phone', self::alt($p))->where('status', '!=', 'Cancel')->selectRaw('count(*) n, coalesce(sum(total),0) s')->first();
    return ['n' => (int)$a->n + (int)($c->legacy_orders ?? 0), 's' => (float)$a->s + (float)($c->legacy_spent ?? 0)];
  }
  /** Saved tier name, or the level the customer's spend has earned (same rule as the customer list). */
  public static function tier(string $p, $c): ?array {
    if (!$c) return null; $st = self::stats($p, $c);
    $lv = DB::table('membership_levels')->where('active', true)->orderByDesc('min_spent')->get();
    if (!empty($c->membership)) { $l = $lv->first(fn($x) => strcasecmp($x->name, $c->membership) === 0); return ['name' => $c->membership, 'pct' => (float)($l->discount_percent ?? 0)]; }
    if ($st['n'] > 1) { $l = $lv->first(fn($x) => $st['s'] >= $x->min_spent); if ($l) return ['name' => $l->name, 'pct' => (float)$l->discount_percent]; }
    return null;
  }
  public static function compute(string $phone, float $sub, ?string $coupon = null): array {
    $alt = self::alt($phone); $c = DB::table('customers')->whereIn('phone', $alt)->orderBy('id')->first(); $st = self::stats($phone, $c); $cand = []; $cerr = null;
    $add = function ($key, $label, $pct, $cap = null) use (&$cand, $sub) { if ($pct <= 0) return; $amt = round($sub * $pct / 100, 2); if ($cap !== null && $cap > 0) $amt = min($amt, (float)$cap); if ($amt > 0) $cand[] = ['key' => $key, 'label' => $label, 'pct' => (float)$pct, 'amount' => $amt]; };
    if ($c && (float)($c->discount_pct ?? 0) > 0) $add('special', 'Special customer discount', (float)$c->discount_pct);
    if (($cfg = self::rule('membership')) !== null && ($t = self::tier($phone, $c))) {
      $pct = $t['pct']; if ($pct <= 0) foreach ($cfg as $k => $v) if (stripos($t['name'], strtok($k, ' ')) !== false) { $pct = (float)$v; break; }
      $add('membership', 'Membership discount ('.$t['name'].')', $pct);
    }
    if (($cfg = self::rule('birthday')) !== null && $c && !empty($c->dob)) {
      try { $b = \Carbon\Carbon::parse($c->dob); $d = now('Asia/Dhaka')->startOfDay(); $this_year = $b->copy()->year($d->year); $diff = abs($d->diffInDays($this_year, false));
        if ($diff <= (int)($cfg['Valid days'] ?? 0)) $add('birthday', 'Birthday discount', (float)($cfg['Birthday %'] ?? 0)); } catch (\Throwable $e) {}
    }
    if (($cfg = self::rule('first')) !== null && $st['n'] === 0) $add('first', 'First order discount', (float)($cfg['Discount %'] ?? 0), (float)($cfg['Max TK'] ?? 0));
    $coupon = strtoupper(trim((string)$coupon));
    if ($coupon !== '') {
      $cp = DB::table('coupons')->where('code', $coupon)->first(); $today = now('Asia/Dhaka')->toDateString();
      if (!$cp || !$cp->active) $cerr = 'Coupon not found or not active';
      elseif ($cp->valid_from && $today < substr((string)$cp->valid_from, 0, 10)) $cerr = 'Coupon is not valid yet';
      elseif ($cp->valid_to && $today > substr((string)$cp->valid_to, 0, 10)) $cerr = 'Coupon has expired';
      elseif ($cp->usage_limit && (int)$cp->used_count >= (int)$cp->usage_limit) $cerr = 'Coupon usage limit reached';
      elseif ($sub < (float)$cp->min_order) $cerr = 'Minimum order for this coupon is ৳'.number_format($cp->min_order);
      elseif ($cp->per_customer && DB::table('orders')->where('coupon', $coupon)->whereIn('phone', $alt)->count() >= (int)$cp->per_customer) $cerr = 'This customer already used the coupon';
      elseif ($cp->type === 'free_delivery') $cerr = 'Free-delivery coupons work on the storefront only';
      else {
        $amt = $cp->type === 'percent' ? round($sub * (float)$cp->value / 100, 2) : min((float)$cp->value, $sub);
        if ($cp->max_discount) $amt = min($amt, (float)$cp->max_discount);
        if ($amt > 0) $cand[] = ['key' => 'coupon', 'label' => 'Coupon '.$coupon, 'pct' => $cp->type === 'percent' ? (float)$cp->value : null, 'amount' => $amt, 'code' => $coupon];
      }
    }
    $mode = self::mode(); $sel = [];
    if ($cand) {
      if ($mode === 'stack') $sel = array_column($cand, 'key');
      elseif ($mode === 'priority') { usort($cand, fn($a, $b) => array_search($a['key'], self::ORDER) <=> array_search($b['key'], self::ORDER)); $sel = [$cand[0]['key']]; }
      else { $best = $cand[0]; foreach ($cand as $x) if ($x['amount'] > $best['amount']) $best = $x; $sel = [$best['key']]; }
    }
    $sum = 0; foreach ($cand as &$x) { $x['applied'] = in_array($x['key'], $sel); if ($x['applied']) $sum += $x['amount']; } unset($x);
    return ['mode' => $mode, 'lines' => $cand, 'total' => min(round($sum, 2), $sub), 'coupon_error' => $cerr];
  }
}
