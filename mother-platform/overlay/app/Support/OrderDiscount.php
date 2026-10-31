<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** THE discount engine. Admin Create Order, website checkout and the customer account all call compute(); nothing else calculates a discount.
 *  Every line comes from a saved rule, membership level, coupon or customer-profile value: nothing is invented. */
class OrderDiscount {
  const ORDER = ['special', 'vip', 'membership', 'returning', 'birthday', 'first', 'bundle', 'amount', 'coupon'];
  const MODES = ['highest' => 'Highest discount only', 'priority' => 'Priority order (first match only)', 'stack' => 'Stackable: multiple discounts allowed'];
  public static function mode(): string { $m = (string)DB::table('settings')->where('key', 'order_disc_mode')->value('value'); return isset(self::MODES[$m]) ? $m : 'highest'; }
  private static function rule(string $k): ?array { $r = DB::table('discount_rules')->where('key', $k)->where('enabled', true)->first(); return $r ? ['cfg' => json_decode($r->config, true) ?: [], 'title' => $r->title] : null; }
  private static function alt(string $p): array { return [$p, '+88'.$p, '88'.$p]; }
  public static function customer(string $p) { return DB::table('customers')->whereIn('phone', self::alt($p))->orderBy('id')->first(); }
  /** Phone of the logged-in website customer (cookie session), or null. */
  public static function sessionPhone($request): ?string {
    $tok = (string)$request->cookie('dfa'); if (strlen($tok) < 30) return null;
    $s = DB::table('customer_sessions')->where('token_hash', hash('sha256', $tok))->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first(); if (!$s) return null;
    $c = DB::table('customers')->find($s->customer_id); return $c && !$c->blocked ? (Chat::phone($c->phone) ?: null) : null;
  }
  public static function stats(string $p, $c): array {
    $a = DB::table('orders')->whereIn('phone', self::alt($p))->where('status', '!=', 'Cancel')->selectRaw('count(*) n, coalesce(sum(total),0) s')->first();
    return ['n' => (int)$a->n + (int)($c->legacy_orders ?? 0), 's' => (float)$a->s + (float)($c->legacy_spent ?? 0)];
  }
  /** Saved tier name, or the level the customer's spend has earned. */
  public static function tier(string $p, $c): ?array {
    if (!$c) return null; $st = self::stats($p, $c);
    $lv = DB::table('membership_levels')->where('active', true)->orderByDesc('min_spent')->get();
    if (!empty($c->membership)) { $l = $lv->first(fn($x) => strcasecmp($x->name, $c->membership) === 0); return ['name' => $c->membership, 'pct' => (float)($l->discount_percent ?? 0)]; }
    if ($st['n'] > 1) { $l = $lv->first(fn($x) => $st['s'] >= $x->min_spent); if ($l) return ['name' => $l->name, 'pct' => (float)$l->discount_percent]; }
    return null;
  }
  /** Customer Type (VIP / Regular / New) and Membership (Active / Inactive). */
  public static function profile(string $p): array {
    $c = self::customer($p); $st = self::stats($p, $c); $t = self::tier($p, $c);
    $cat = ($c && strtolower((string)($c->type ?? '')) === 'vip') ? 'VIP' : ($st['n'] > 0 ? 'Regular' : 'New');
    return ['category' => $cat, 'membership' => $t ? 'Active' : 'Inactive', 'membership_name' => $t['name'] ?? null, 'orders' => $st['n'], 'found' => (bool)$c];
  }
  public static function compute(?string $phone, float $sub, ?string $coupon = null, array $ctx = []): array {
    $web = !empty($ctx['web']); $qty = (int)($ctx['qty'] ?? 0); $prof = !$web || !empty($ctx['verified']);
    $c = $phone ? self::customer($phone) : null; $st = $phone ? self::stats($phone, $c) : ['n' => 0, 's' => 0.0]; $profile = $phone ? self::profile($phone) : ['category' => 'New', 'membership' => 'Inactive', 'membership_name' => null, 'orders' => 0, 'found' => false];
    $cand = []; $cerr = null; $free = false;
    $add = function ($key, $type, $rule, $source, $pct, $cap = null, $fixed = null) use (&$cand, $sub) {
      if ($fixed !== null) { if ($fixed <= 0) return; $amt = min((float)$fixed, $sub); $pct = null; } else { if ($pct <= 0) return; $amt = round($sub * $pct / 100, 2); if ($cap !== null && $cap > 0) $amt = min($amt, (float)$cap); }
      if ($amt <= 0) return;
      $cand[] = ['key' => $key, 'type' => $type, 'rule' => $rule, 'source' => $source, 'pct' => $pct !== null ? (float)$pct : null, 'amount' => round($amt, 2), 'label' => $type.' – '.($pct !== null ? rtrim(rtrim(number_format($pct, 2), '0'), '.').'%' : '৳'.number_format($amt))];
    };
    if ($prof && $c && (float)($c->discount_pct ?? 0) > 0) $add('special', 'Special Customer Discount', 'Customer profile', 'Customer profile – special discount', (float)$c->discount_pct);
    if ($prof && $c && strtolower((string)($c->type ?? '')) === 'vip' && ($R = self::rule('vip'))) $add('vip', 'VIP Customer Discount', $R['title'], 'VIP customer rule', (float)($R['cfg']['Discount %'] ?? 0), null, (float)($R['cfg']['Fixed TK'] ?? 0) > 0 ? (float)$R['cfg']['Fixed TK'] : null);
    if ($prof && ($R = self::rule('membership')) && ($t = self::tier($phone, $c))) {
      $pct = $t['pct']; if ($pct <= 0) foreach ($R['cfg'] as $k => $v) if (stripos($t['name'], strtok($k, ' ')) !== false) { $pct = (float)$v; break; }
      $add('membership', 'Membership Discount', $R['title'], 'Membership Plan – '.$t['name'], $pct);
    }
    if ($prof && $st['n'] > 0 && ($R = self::rule('returning'))) $add('returning', 'Returning Customer Offer', $R['title'], 'Returning customer rule', (float)($R['cfg']['Discount %'] ?? 0), (float)($R['cfg']['Max TK'] ?? 0));
    if ($prof && $c && !empty($c->dob) && ($R = self::rule('birthday'))) {
      try { $b = \Carbon\Carbon::parse($c->dob); $d = now('Asia/Dhaka')->startOfDay(); if (abs($d->diffInDays($b->copy()->year($d->year), false)) <= (int)($R['cfg']['Valid days'] ?? 0)) $add('birthday', 'Birthday Discount', $R['title'], 'Birthday rule', (float)($R['cfg']['Birthday %'] ?? 0)); } catch (\Throwable $e) {}
    }
    if ($phone && $st['n'] === 0 && ($R = self::rule('first'))) $add('first', 'First Order Discount', $R['title'], 'First order rule', (float)($R['cfg']['Discount %'] ?? 0), (float)($R['cfg']['Max TK'] ?? 0));
    if ($qty > 0 && ($R = self::rule('bundle')) && $qty >= (int)($R['cfg']['Minimum products'] ?? 2)) $add('bundle', 'Bundle Discount', $R['title'], 'Bundle rule', (float)($R['cfg']['Bundle %'] ?? 0));
    if ($R = self::rule('amount')) { $pct = 0; foreach (['Over 20000 TK %' => 20000, 'Over 10000 TK %' => 10000, 'Over 5000 TK %' => 5000] as $k => $min) if ($sub > $min) { $pct = (float)($R['cfg'][$k] ?? 0); break; }
      $add('amount', 'Order Amount Discount', $R['title'], 'Order amount rule', $pct, (float)($R['cfg']['Max discount per order TK'] ?? 0)); }
    $coupon = strtoupper(trim((string)$coupon));
    if ($coupon !== '') {
      $cp = DB::table('coupons')->whereRaw('upper(code) = ?', [$coupon])->first(); $today = now('Asia/Dhaka')->toDateString();
      if (!$cp || !$cp->active) $cerr = 'Coupon not found or not active';
      elseif ($cp->valid_from && $today < substr((string)$cp->valid_from, 0, 10)) $cerr = 'Coupon is not valid yet';
      elseif ($cp->valid_to && $today > substr((string)$cp->valid_to, 0, 10)) $cerr = 'Coupon has expired';
      elseif ($cp->usage_limit && (int)$cp->used_count >= (int)$cp->usage_limit) $cerr = 'Coupon usage limit reached';
      elseif ($sub < (float)$cp->min_order) $cerr = 'Minimum order for this coupon is ৳'.number_format($cp->min_order);
      elseif ($phone && $cp->per_customer && DB::table('orders')->whereRaw('upper(coupon) = ?', [$coupon])->whereIn('phone', self::alt($phone))->where('status', '!=', 'Cancel')->count() >= (int)$cp->per_customer) $cerr = 'This customer already used the coupon';
      elseif ($cp->type === 'free_delivery') { if ($web) $free = true; else $cerr = 'Free-delivery coupons work on the website checkout only'; }
      else {
        $amt = $cp->type === 'percent' ? round($sub * (float)$cp->value / 100, 2) : min((float)$cp->value, $sub); if ($cp->max_discount) $amt = min($amt, (float)$cp->max_discount);
        if ($amt > 0) $cand[] = ['key' => 'coupon', 'type' => 'Coupon Discount', 'rule' => 'Coupon '.$cp->code, 'source' => 'Coupon – '.$cp->code, 'pct' => $cp->type === 'percent' ? (float)$cp->value : null, 'amount' => $amt, 'code' => $cp->code,
          'label' => 'Coupon '.$cp->code.' – '.($cp->type === 'percent' ? rtrim(rtrim(number_format((float)$cp->value, 2), '0'), '.').'%' : '৳'.number_format($amt))];
      }
    }
    $mode = self::mode(); $sel = [];
    usort($cand, fn($a, $b) => array_search($a['key'], self::ORDER) <=> array_search($b['key'], self::ORDER));
    if ($cand) {
      if ($mode === 'stack') $sel = array_column($cand, 'key');
      elseif ($mode === 'priority') $sel = [$cand[0]['key']];
      else { $best = $cand[0]; foreach ($cand as $x) if ($x['amount'] > $best['amount']) $best = $x; $sel = [$best['key']]; }
    }
    $sum = 0; foreach ($cand as &$x) { $x['applied'] = in_array($x['key'], $sel); if ($x['applied']) $sum += $x['amount']; } unset($x);
    return ['mode' => $mode, 'lines' => $cand, 'total' => min(round($sum, 2), $sub), 'coupon_error' => $cerr, 'free_ship' => $free, 'profile' => $profile];
  }
  /** Columns saved on the order so the reason for a discount is always visible later. */
  public static function meta(array $applied, array $profile, string $by, float $amount): array {
    $j = fn($k) => $applied ? mb_substr(implode(' + ', array_filter(array_column($applied, $k))), 0, 250) : null;
    $pcts = array_filter(array_column($applied, 'pct'), fn($v) => $v !== null);
    return ['discount_type' => $j('type'), 'discount_rule' => $j('rule'), 'discount_pct' => count($applied) === 1 ? ($applied[0]['pct'] ?? null) : null, 'discount_source' => $j('source'),
      'discount_by' => $amount > 0 ? $by : null, 'customer_category' => $profile['category'] ?? null, 'membership_status' => ($profile['membership'] ?? null) ? $profile['membership'].($profile['membership_name'] ? ' – '.$profile['membership_name'] : '') : null];
  }
}
