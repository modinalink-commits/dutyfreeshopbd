<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** THE discount engine. Admin Create Order, website checkout and the customer account all call compute(); nothing else calculates a discount.
 *  Every line comes from a saved rule, membership level, coupon or customer-profile value: nothing is invented. */
class OrderDiscount {
  const ORDER = ['special', 'vip', 'membership', 'returning', 'winback', 'voice', 'birthday', 'first', 'referral', 'bundle', 'flashsale', 'amount', 'coupon', 'refcredit'];
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
  public static function stats(string $p, $c, ?int $ex = null): array {
    $a = DB::table('orders')->whereIn('phone', self::alt($p))->where('status', '!=', 'Cancel')->when($ex, fn($q) => $q->where('id', '!=', $ex))->selectRaw('count(*) n, coalesce(sum(total),0) s')->first();
    return ['n' => (int)$a->n + (int)($c->legacy_orders ?? 0), 's' => (float)$a->s + (float)($c->legacy_spent ?? 0)];
  }
  /** Saved tier name, or the level the customer's spend has earned. */
  public static function tier(string $p, $c): ?array {
    if (!$c) return null; $st = self::stats($p, $c);
    if (CustomerBenefit::on('membership')) { $bk = CustomerBenefit::check($p, $c); $st = ['n' => $bk['received'], 's' => $bk['amount']]; } // policy: tier from successfully received parcels only, never order count
    $lv = DB::table('membership_levels')->where('active', true)->orderByDesc('min_spent')->get();
    if (!empty($c->membership)) { $l = $lv->first(fn($x) => strcasecmp($x->name, $c->membership) === 0); return ['name' => $c->membership, 'pct' => (float)($l->discount_percent ?? 0), 'stars' => self::starsOf((string)$c->membership, (int)($l->stars ?? 0))]; }
    if ($st['n'] > 1) { $l = $lv->first(fn($x) => $st['s'] >= $x->min_spent); if ($l) return ['name' => $l->name, 'pct' => (float)$l->discount_percent, 'stars' => self::starsOf((string)$l->name, (int)($l->stars ?? 0))]; }
    return null;
  }
  private static ?array $rank = null;
  /** Stars are automatic: a level's star count is its position when the active levels are sorted by minimum spend (lowest = 1). Add a new package and it gets the next star count without any setting. */
  public static function starsOf(string $name, int $saved = 0): int {
    if (self::$rank === null) { self::$rank = []; try { $i = 0; foreach (DB::table('membership_levels')->where('active', true)->orderBy('min_spent')->orderBy('id')->get(['name']) as $l) self::$rank[mb_strtolower(trim((string)$l->name))] = ++$i; } catch (\Throwable $e) {} }
    $n = mb_strtolower(trim($name)); if ($n === '') return 0;
    if (isset(self::$rank[$n])) return self::$rank[$n];
    $ks = array_keys(self::$rank); usort($ks, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    foreach ($ks as $k) if ($k !== '' && (str_contains($n, $k) || str_contains($k, $n))) return self::$rank[$k]; // e.g. "Active – Gold / Premium" on an order
    return 0;
  }
  const LEVELS = ['silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum', 'diamond' => 'Diamond'];
  /** Customer level for display: the one an admin set on the customer (customers.customer_level), else worked out from the membership level (1 star = Silver ... 4 = Diamond). Always read at render time, never saved on the order. */
  public static function levelOf(?string $manual, int $autoStars = 0): ?array {
    $m = strtolower(trim((string)$manual)); $keys = array_keys(self::LEVELS);
    if (isset(self::LEVELS[$m])) return ['key' => $m, 'name' => self::LEVELS[$m], 'stars' => array_search($m, $keys) + 1];
    if ($autoStars < 1) return null; $i = min(4, $autoStars) - 1; return ['key' => $keys[$i], 'name' => self::LEVELS[$keys[$i]], 'stars' => $i + 1];
  }
  /** Membership card data for a customer row (null = not a member). */
  public static function cardOf(object $c, ?string $displayId = null): ?array {
    try { $t = self::tier((string)$c->phone, $c); $lv = self::levelOf($c->customer_level ?? null, (int)($t['stars'] ?? 0)); } catch (\Throwable $e) { return null; }
    if (!$lv) return null;
    return ['name' => (string)$c->name, 'id' => $displayId ?: ('DFC' . str_pad((string)$c->id, 6, '0', STR_PAD_LEFT)), 'stars' => $lv['stars'], 'key' => $lv['key']];
  }
  public static function starText(int $n): string { return $n > 0 ? str_repeat('★', $n) : ''; }
  /** Customer Type (VIP / Regular / New) and Membership (Active / Inactive). */
  public static function profile(string $p): array {
    $c = self::customer($p); $st = self::stats($p, $c); $t = self::tier($p, $c);
    $cat = ($c && strtolower((string)($c->type ?? '')) === 'vip') ? 'VIP' : (CustomerBenefit::check($p, $c)['received'] > 0 ? 'Regular' : 'New');
    return ['category' => $cat, 'membership' => $t ? 'Active' : 'Inactive', 'membership_name' => $t['name'] ?? null, 'orders' => $st['n'], 'found' => (bool)$c];
  }
  /** Applies the product "Quantity prices" (Buy 2-5) for every channel that did not already do it (website cart does it itself and sets ctx qprice). Lines priced by hand are left alone. */
  /** Offer the customer picked at checkout (only among offers that are already valid for them). */
  public static ?string $pick = null;
  public static function compute(?string $phone, float $sub, ?string $coupon = null, array $ctx = []): array {
    $r = ProfitFloor::enforce(self::computeRaw($phone, $sub, $coupon, $ctx), array_values((array)($ctx['items'] ?? [])), (float)($ctx['bsave'] ?? 0));
    // App Install Welcome Discount: after bundle / membership / campaign discounts and the 15% protected-profit rule, capped to what is still safe (Auto Safe-Cap).
    return !empty($ctx['app']) ? AppReward::apply($r, $phone, $sub, $ctx) : $r;
  }
  private static function computeRaw(?string $phone, float $sub, ?string $coupon = null, array $ctx = []): array {
    if (array_key_exists('qprice', $ctx) || empty($ctx['items'])) return self::computeCore($phone, $sub, $coupon, $ctx);
    $bs = 0.0;
    try {
      $want = []; foreach ((array)$ctx['items'] as $i) { $pid = (int)($i['product_id'] ?? 0); if ($pid > 0 && (int)($i['qty'] ?? 0) > 0) $want[$pid][] = [(float)($i['price'] ?? 0), (int)$i['qty']]; }
      if ($want) foreach (DB::table('products')->whereIn('id', array_keys($want))->get(['id','price','buy2_price','buy3_price','buy4_price','buy5_price']) as $p) {
        $n = 0; foreach ($want[$p->id] as [$pr, $q]) if (abs($pr - (float)$p->price) < 0.5) $n += $q;
        if ($n >= 2) $bs += max(0, round((float)$p->price * $n - \App\Http\Controllers\StoreController::bundleLine($p, $n), 2));
      }
    } catch (\Throwable $e) { report($e); $bs = 0.0; }
    $bs = min($bs, $sub);
    $r = self::computeCore($phone, $sub - $bs, $coupon, $ctx + ['qprice' => $bs > 0]);
    if ($bs > 0) {
      array_unshift($r['lines'], ['key' => 'qprice', 'type' => 'Bundle Price', 'rule' => 'Quantity price (Buy 2-5)', 'source' => 'Product bundle price', 'pct' => null, 'amount' => round($bs, 2), 'label' => 'Bundle price – saved '.number_format($bs).' TK', 'applied' => true]);
      $r['total'] = min(round($r['total'] + $bs, 2), $sub);
    }
    return $r;
  }
  public static function computeCore(?string $phone, float $sub, ?string $coupon = null, array $ctx = []): array {
    $web = !empty($ctx['web']); $qty = (int)($ctx['qty'] ?? 0); $prof = !$web || !empty($ctx['verified']) || !empty($ctx['known']); // owner rule 2026-11-06: a guest whose phone is already in our customer data gets that customer's discounts
    $c = $phone ? self::customer($phone) : null; $ex = !empty($ctx['exclude_order']) ? (int)$ctx['exclude_order'] : null; $st = $phone ? self::stats($phone, $c, $ex) : ['n' => 0, 's' => 0.0]; $profile = $phone ? self::profile($phone) : ['category' => 'New', 'membership' => 'Inactive', 'membership_name' => null, 'orders' => 0, 'found' => false];
    $cand = []; $cerr = null; $free = false;
    // Customer Benefit Eligibility policy: loyalty benefits need status Eligible (parcels successfully received); acquisition offers (first order, bundle, amount, typed coupon) are unaffected.
    $loy = !$phone || !CustomerBenefit::on('discount') || CustomerBenefit::allows($phone, $c, $ex);
    $add = function ($key, $type, $rule, $source, $pct, $cap = null, $fixed = null) use (&$cand, $sub) {
      if ($fixed !== null) { if ($fixed <= 0) return; $amt = min((float)$fixed, $sub); $pct = null; } else { if ($pct <= 0) return; $amt = round($sub * $pct / 100, 2); if ($cap !== null && $cap > 0) $amt = min($amt, (float)$cap); }
      if ($amt <= 0) return;
      $cand[] = ['key' => $key, 'type' => $type, 'rule' => $rule, 'source' => $source, 'pct' => $pct !== null ? (float)$pct : null, 'amount' => round($amt, 2), 'label' => $type.' – '.($pct !== null ? rtrim(rtrim(number_format($pct, 2), '0'), '.').'%' : '৳'.number_format($amt))];
    };
    if ($prof && $loy && $c && (float)($c->discount_pct ?? 0) > 0) $add('special', 'Special Customer Discount', 'Customer profile', 'Customer profile – special discount', (float)$c->discount_pct);
    if ($prof && $loy && $c && strtolower((string)($c->type ?? '')) === 'vip' && ($R = self::rule('vip'))) $add('vip', 'VIP Customer Discount', $R['title'], 'VIP customer rule', (float)($R['cfg']['Discount %'] ?? 0), null, (float)($R['cfg']['Fixed TK'] ?? 0) > 0 ? (float)$R['cfg']['Fixed TK'] : null);
    if ($prof && $loy && ($R = self::rule('membership')) && ($t = self::tier($phone, $c))) {
      $pct = $t['pct']; if ($pct <= 0) foreach ($R['cfg'] as $k => $v) if (stripos($t['name'], strtok($k, ' ')) !== false) { $pct = (float)$v; break; }
      $add('membership', 'Membership Discount', $R['title'], 'Membership Plan – '.$t['name'], $pct);
    }
    if ($prof && $loy && $phone && CustomerBenefit::check($phone, $c, $ex)['received'] > 0 && ($R = self::rule('returning'))) $add('returning', 'Returning Customer Offer', $R['title'], 'Returning customer rule', (float)($R['cfg']['Discount %'] ?? 0), (float)($R['cfg']['Max TK'] ?? 0));
    if ($prof && $loy && $c && !empty($c->dob) && ($R = self::rule('birthday'))) {
      try { $b = \Carbon\Carbon::parse($c->dob); $d = now('Asia/Dhaka')->startOfDay(); if (abs($d->diffInDays($b->copy()->year($d->year), false)) <= (int)($R['cfg']['Valid days'] ?? 0)) $add('birthday', 'Birthday Discount', $R['title'], 'Birthday rule', (float)($R['cfg']['Birthday %'] ?? 0)); } catch (\Throwable $e) {}
    }
    if ($phone && $st['n'] === 0 && ($R = self::rule('first'))) $add('first', 'First Order Discount', $R['title'], 'First order rule', (float)($R['cfg']['Discount %'] ?? 0), (float)($R['cfg']['Max TK'] ?? 0));
    if ($qty > 0 && empty($ctx['qprice']) && ($R = self::rule('bundle')) && $qty >= (int)($R['cfg']['Minimum products'] ?? 2)) $add('bundle', 'Bundle Discount', $R['title'], 'Bundle rule', (float)($R['cfg']['Bundle %'] ?? 0));
    // Flash Sale (owner, 12 Nov 2026): the % comes from the saved 'flash' rule (set to 2); it applies only to the Flash Sale products in the cart, and each customer gets it once per day.
    if (($R = self::rule('flashsale')) && !empty($ctx['items'])) {
      try {
        $fid = \App\Http\Controllers\StoreController::flashIds(); $fv = 0.0;
        foreach ((array)$ctx['items'] as $i) if (in_array((int)($i['product_id'] ?? 0), $fid, true)) $fv += (float)($i['price'] ?? 0) * (int)($i['qty'] ?? 0);
        $used = false;
        if ($phone && $fv > 0 && self::hasLines()) { $d0 = now('Asia/Dhaka')->startOfDay()->timezone('UTC'); $used = DB::table('orders')->whereIn('phone', self::alt($phone))->where('status', '!=', 'Cancel')->where('created_at', '>=', $d0)->when($ex, fn($q) => $q->where('id', '!=', $ex))->where('discount_lines', 'like', '%Flash Sale Discount%')->exists(); }
        $fp = (float)($R['cfg']['Discount %'] ?? 0);
        if ($fv > 0 && !$used && $fp > 0) { $amt = round(min($fv, $sub) * $fp / 100, 2); if ($amt > 0) $cand[] = ['key' => 'flashsale', 'type' => 'Flash Sale Discount', 'rule' => $R['title'], 'source' => 'Flash Sale – once per day', 'pct' => $fp, 'amount' => $amt, 'label' => 'Flash Sale Discount – ' . rtrim(rtrim(number_format($fp, 2), '0'), '.') . '%']; }
      } catch (\Throwable $e) { report($e); }
    }
    if ($R = self::rule('amount')) { $pct = 0; foreach (['Over 20000 TK %' => 20000, 'Over 10000 TK %' => 10000, 'Over 5000 TK %' => 5000] as $k => $min) if ($sub > $min) { $pct = (float)($R['cfg'][$k] ?? 0); break; }
      $add('amount', 'Order Amount Discount', $R['title'], 'Order amount rule', $pct, (float)($R['cfg']['Max discount per order TK'] ?? 0)); }
    $coupon = strtoupper(trim((string)$coupon));
    if ($coupon !== '') {
      $cp = DB::table('coupons')->whereRaw('upper(code) = ?', [$coupon])->first(); $today = now('Asia/Dhaka')->toDateString();
      if ($phone && CustomerBenefit::on('coupon') && CustomerBenefit::check($phone, $c, $ex)['status'] === 'Suspended') $cerr = 'This coupon is not available for this account';
      elseif (!$cp || !$cp->active) $cerr = 'Coupon not found or not active';
      elseif (DB::table('winback_events')->where('coupon_code', $coupon)->where('mode', 'fixed')->exists() && !($phone && DB::table('winback_events')->where('coupon_code', $coupon)->where('mode', 'fixed')->whereIn('phone', array_map(fn($x) => substr(preg_replace('/\D/', '', $x), -11), self::alt($phone)))->whereNull('used_at')->where('expires_at', '>', now())->exists())) $cerr = 'This coupon is not valid for this number, was already used, or has expired';
      elseif ($ce = CouponRules::error($cp, $phone, $c, $st, $sub, (array)($ctx['items'] ?? []), $web, $phone && $c ? (self::tier($phone, $c) ? 'm' : null) : null)) $cerr = $ce;
      elseif ($sub < (float)$cp->min_order) $cerr = 'Minimum order for this coupon is ৳'.number_format($cp->min_order);
      elseif ($phone && $cp->per_customer && DB::table('orders')->whereRaw('upper(coupon) = ?', [$coupon])->whereIn('phone', self::alt($phone))->where('status', '!=', 'Cancel')->when($ex, fn($q) => $q->where('id', '!=', $ex))->count() >= (int)$cp->per_customer) $cerr = 'This customer already used the coupon';
      elseif ($cp->type === 'free_delivery' && $phone && CustomerBenefit::on('delivery') && ($fb = CustomerBenefit::check($phone, $c, $ex)) && $fb['total'] > 0 && $fb['received'] < 1) $cerr = 'Free delivery needs at least one successfully received parcel';
      elseif ($cp->type === 'free_delivery') { if ($web) $free = true; else $cerr = 'Free-delivery coupons work on the website checkout only'; }
      else {
        $cb = CouponRules::base($cp, $sub, (array)($ctx['items'] ?? [])); $amt = $cp->type === 'percent' ? round($cb * (float)$cp->value / 100, 2) : min((float)$cp->value, $cb); if ($cp->max_discount) $amt = min($amt, (float)$cp->max_discount);
        if ($amt > 0) $cand[] = ['key' => 'coupon', 'type' => 'Coupon Discount', 'rule' => 'Coupon '.$cp->code, 'source' => 'Coupon – '.$cp->code, 'pct' => $cp->type === 'percent' ? (float)$cp->value : null, 'amount' => $amt, 'code' => $cp->code,
          'label' => 'Coupon '.$cp->code.' – '.($cp->type === 'percent' ? rtrim(rtrim(number_format((float)$cp->value, 2), '0'), '.').'%' : '৳'.number_format($amt))];
      }
    }
    if ($prof && $loy && $phone && Winback::set('mode', 'auto') === 'auto' && ($we = Winback::active($phone) ?: ($ex ? DB::table('winback_events')->where('used_order_id', $ex)->first() : null)) && $sub >= (float)Winback::set('min_order', '0')) {
      $wa = round($sub * (float)$we->pct / 100, 2); $wm = (float)Winback::set('max_discount', '0'); if ($wm > 0) $wa = min($wa, $wm);
      if ($wa > 0) $cand[] = ['key' => 'winback', 'type' => 'Win-Back Discount', 'rule' => 'Customer reactivation', 'source' => 'Win-back offer', 'pct' => (float)$we->pct, 'amount' => $wa, 'wbid' => $we->id, 'label' => 'Welcome Back Discount: '.rtrim(rtrim(number_format((float)$we->pct, 2), '0'), '.').'%'];
    }
    if ($prof && $phone && ($vo = Voice::best($phone, (array)($ctx['items'] ?? []), $sub, $ex))) $cand[] = ['key' => 'voice', 'type' => 'Voice Offer Discount', 'rule' => 'Voice campaign: '.$vo['campaign'], 'source' => 'Voice campaign offer', 'pct' => $vo['pct'], 'amount' => $vo['amount'], 'vcid' => $vo['vcid'], 'label' => 'Voice Offer Discount: '.rtrim(rtrim(number_format($vo['pct'], 2), '0'), '.').'% on '.$vo['product']];
    if ($web && $phone && ($rc = Referral::codeFlow($phone)) && ($rf = Referral::eligible($phone, $rc, $sub))) {
      $ra = min((float)$rf['discount'], $sub); if ($ra > 0) $cand[] = ['key' => 'referral', 'type' => 'Referral Discount', 'rule' => 'Referral '.$rf['code'], 'source' => 'Referral – '.$rf['code'], 'pct' => null, 'amount' => round($ra, 2), 'refcode' => $rf['code'], 'label' => 'Referral discount – ৳'.number_format($ra)];
    }
    $mode = self::mode(); $sel = [];
    usort($cand, fn($a, $b) => array_search($a['key'], self::ORDER) <=> array_search($b['key'], self::ORDER));
    if ($cand) {
      if ($mode === 'stack') $sel = array_column($cand, 'key');
      elseif ($mode === 'priority') $sel = [$cand[0]['key']];
      else { $best = $cand[0]; foreach ($cand as $x) if ($x['amount'] > $best['amount']) $best = $x; $sel = [$best['key']]; }
    }
    if (self::$pick !== null && $mode !== 'stack' && in_array(self::$pick, array_column($cand, 'key'), true) && self::$pick !== 'refcredit') $sel = [self::$pick]; // customer's own choice among valid offers (checkout spec); margin guard still runs after
    if ($web && !empty($ctx['verified']) && $phone && Referral::on() && Referral::set('credit_use', '1') === '1' && (!CustomerBenefit::on('abuse') || CustomerBenefit::check($phone)['status'] !== 'Suspended') && ($bal = Referral::balance($phone)) > 0) {
      $paid = 0; foreach ($cand as $x0) if (in_array($x0['key'], $sel)) $paid += $x0['amount'];
      $ca = round(min($bal, max(0, $sub - $paid)), 2); if ($ca > 0) { $cand[] = ['key' => 'refcredit', 'type' => 'Referral Credit', 'rule' => 'Referral wallet', 'source' => 'Referral credit', 'pct' => null, 'amount' => $ca, 'label' => 'Referral credit – ৳'.number_format($ca)]; $sel[] = 'refcredit'; }
    }
    // Owner rule (8 Nov 2026): no single discount may exceed 3% of the order, including fixed-Taka ones (VIP fixed, referral, returning max, coupons are left to their own limits only when percent based <= 3).
    $cap3 = round($sub * 0.03, 2); foreach ($cand as &$cx) { if (in_array($cx['key'], ['coupon', 'refcredit'], true)) continue; if ((float)$cx['amount'] > $cap3 + 0.004) { $cx['amount'] = $cap3; $cx['pct'] = null; $cx['label'] = ($cx['type'] ?? 'Discount') . ' – ৳' . number_format($cap3); } } unset($cx);
    $sum = 0; foreach ($cand as &$x) { $x['applied'] = in_array($x['key'], $sel); if ($x['applied']) $sum += $x['amount']; } unset($x);
    return ['mode' => $mode, 'lines' => $cand, 'total' => min(round($sum, 2), $sub), 'coupon_error' => $cerr, 'free_ship' => $free, 'profile' => $profile];
  }
  private static ?bool $hl = null;
  /** The lines column may not exist for a moment while a release is rolling out; never break an order for it. */
  public static function hasLines(): bool { return self::$hl ??= \Illuminate\Support\Facades\Schema::hasColumn('orders', 'discount_lines'); }
  /** One normalized entry per discount (what the order page, list and invoice show). */
  public static function lineRows(array $applied, string $by, bool $auto = true, ?string $reason = null): array {
    $out = [];
    foreach ($applied as $x) {
      $pct = $x['pct'] ?? null;
      $out[] = ['name' => (string)($x['type'] ?? 'Discount'), 'kind' => $pct !== null ? 'Percentage' : 'Fixed', 'pct' => $pct !== null ? (float)$pct : null, 'amount' => round((float)($x['amount'] ?? 0), 2),
        'source' => (string)($x['source'] ?? ''), 'rule' => (string)($x['rule'] ?? ''), 'code' => $x['code'] ?? ($x['refcode'] ?? null), 'auto' => $auto, 'by' => $auto ? 'System' : $by, 'reason' => $reason, 'at' => now()->toIso8601String()];
    }
    return $out;
  }
  /** A single manual discount (staff typed it): needs a reason and a name. */
  public static function manualLine(float $amount, string $by, ?string $reason, string $name = 'Manual Discount'): array {
    return ['name' => $name, 'kind' => 'Fixed', 'pct' => null, 'amount' => round($amount, 2), 'source' => 'Manual by '.$by, 'rule' => $reason ?: 'Admin override', 'code' => null, 'auto' => false, 'by' => $by, 'reason' => $reason, 'at' => now()->toIso8601String()];
  }
  public static function linesJson(array $rows): ?string { return $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE) : null; }
  /** Discount lines of an order. Orders from before per-line storage get what was saved on the order (names, one source, one total) and say so. */
  public static function lines(object $o): array {
    $j = json_decode((string)($o->discount_lines ?? ''), true); if (is_array($j) && $j) return $j;
    $d = round((float)($o->discount ?? 0), 2); if ($d <= 0) return [];
    $names = array_values(array_filter(array_map('trim', explode(' + ', (string)($o->discount_type ?? '')))));
    $by = (string)($o->discount_by ?? ''); $manual = $by === 'Manual';
    if (count($names) <= 1) return [['name' => $names[0] ?? 'Discount', 'kind' => $o->discount_pct !== null ? 'Percentage' : 'Fixed', 'pct' => $o->discount_pct !== null ? (float)$o->discount_pct : null, 'amount' => $d, 'source' => (string)($o->discount_source ?? ''), 'rule' => (string)($o->discount_rule ?? ''), 'code' => $o->coupon ?? null, 'auto' => !$manual, 'by' => $manual ? ($by ?: 'Staff') : 'System', 'reason' => null, 'at' => null, 'legacy' => true]];
    $out = []; foreach ($names as $n) $out[] = ['name' => $n, 'kind' => '', 'pct' => null, 'amount' => null, 'source' => (string)($o->discount_source ?? ''), 'rule' => (string)($o->discount_rule ?? ''), 'code' => null, 'auto' => !$manual, 'by' => $manual ? ($by ?: 'Staff') : 'System', 'reason' => null, 'at' => null, 'legacy' => true];
    return $out;
  }
  /** One short text per discount for the compact order-list cell. */
  public static function tips(object $o): array {
    $t = []; foreach (self::lines($o) as $x) $t[] = $x['name'].(!empty($x['pct']) ? ' '.rtrim(rtrim(number_format((float)$x['pct'], 2), '0'), '.').'%' : '').(($x['amount'] ?? null) !== null ? '  -৳'.number_format((float)$x['amount']) : '').(empty($x['auto']) ? ' (manual)' : '');
    return $t;
  }
  /** One colour per kind of discount so staff can tell them apart at a glance: [text, background, border]. */
  public static function colorOf(string $name): array {
    $n = strtolower($name);
    foreach ([['app install', ['#9A3412', '#FFF1E6', '#FDBA74']], ['coupon', ['#6D28D9', '#EDE9FE', '#C4B5FD']], ['win', ['#BE185D', '#FCE7F3', '#F9A8D4']], ['welcome', ['#BE185D', '#FCE7F3', '#F9A8D4']], ['first', ['#166534', '#DCFCE7', '#86EFAC']], ['return', ['#1D4ED8', '#DBEAFE', '#93C5FD']],
      ['member', ['#B45309', '#FEF3C7', '#FCD34D']], ['vip', ['#9A3412', '#FFEDD5', '#FDBA74']], ['birthday', ['#A21CAF', '#FAE8FF', '#E879F9']], ['bundle', ['#0F766E', '#CCFBF1', '#5EEAD4']], ['amount', ['#3730A3', '#E0E7FF', '#A5B4FC']],
      ['special', ['#C2410C', '#FFF7ED', '#FB923C']], ['referral', ['#0E7490', '#CFFAFE', '#67E8F9']], ['manual', ['#B91C1C', '#FEE2E2', '#FCA5A5']], ['extra', ['#B91C1C', '#FEE2E2', '#FCA5A5']]] as [$k, $c]) if (str_contains($n, $k)) return $c;
    return ['#334155', '#F1F5F9', '#CBD5E1'];
  }
  /** Same lines as tips(), with the colour of each: [['text'=>..., 'fg'=>..., 'bg'=>..., 'bd'=>...], ...]. */
  public static function tipRows(object $o): array {
    $r = []; foreach (self::lines($o) as $x) { $c = self::colorOf((string)($x['name'] ?? '').' '.(empty($x['auto']) ? 'manual' : ''));
      $r[] = ['text' => $x['name'].(!empty($x['pct']) ? ' '.rtrim(rtrim(number_format((float)$x['pct'], 2), '0'), '.').'%' : '').(($x['amount'] ?? null) !== null ? '  -৳'.number_format((float)$x['amount']) : '').(empty($x['auto']) ? ' (manual)' : ''), 'fg' => $c[0], 'bg' => $c[1], 'bd' => $c[2], 'd' => self::detail($x), 'label' => (string)($x['name'] ?? 'Discount').(!empty($x['pct']) ? ' '.rtrim(rtrim(number_format((float)$x['pct'], 2), '0'), '.').'%' : '').(empty($x['auto']) ? ' (manual)' : ''), 'amt' => ($x['amount'] ?? null) !== null ? (float)$x['amount'] : null, 'ico' => str_contains(strtolower((string)($x['name'] ?? '')), 'bundle') ? 'bundle' : ((str_contains(strtolower((string)($x['name'] ?? '')), 'amount') || !empty($x['pct'])) ? 'pct' : 'tag')]; }
    return $r;
  }
  /** What the click-to-view popup shows for one discount line: which discount, how much, which area it came from and what it was given for. */
  public static function detail(array $x): array {
    $n = strtolower((string)($x['name'] ?? ''));
    $area = (string)($x['source'] ?? '');
    if ($area === '') {
      $area = str_contains($n, 'coupon') ? 'Coupons (Offers & Coupons)' : (str_contains($n, 'win') || str_contains($n, 'welcome') ? 'Win-Back Offers (Marketing)' : (str_contains($n, 'referral') ? 'Referral programme' : (str_contains($n, 'manual') || str_contains($n, 'extra') || empty($x['auto']) ? 'Manual by staff' : (preg_match('/member|vip|special|return|birthday/', $n) ? 'Customer profile / membership level (Customers)' : 'Automatic discount rules (Discount Center)'))));
    }
    $why = trim((string)($x['reason'] ?? '')) !== '' ? (string)$x['reason'] : (string)($x['rule'] ?? '');
    return ['name' => (string)($x['name'] ?? 'Discount'), 'kind' => (string)($x['kind'] ?? ''), 'pct' => $x['pct'] ?? null, 'amount' => $x['amount'] ?? null, 'area' => $area, 'why' => $why, 'code' => $x['code'] ?? null,
      'by' => !empty($x['auto']) ? 'System (automatic)' : ((string)($x['by'] ?? '') ?: 'Staff'), 'at' => $x['at'] ?? null, 'legacy' => !empty($x['legacy'])];
  }
  /** Staff-visible history when a discount line changes: "Membership Discount: ৳100 → ৳150". */
  public static function logChange(int $orderId, array $old, array $new): void {
    $o = []; foreach ($old as $l) if (isset($l['amount']) && $l['amount'] !== null) $o[$l['name']] = ($o[$l['name']] ?? 0) + (float)$l['amount'];
    $n = []; foreach ($new as $l) $n[$l['name']] = ($n[$l['name']] ?? 0) + (float)($l['amount'] ?? 0);
    foreach (array_unique(array_merge(array_keys($o), array_keys($n))) as $name) {
      $a = $o[$name] ?? 0.0; $b = $n[$name] ?? 0.0; if (abs($a - $b) < 0.009) continue;
      OrderActivity::log($orderId, 'discount_line_changed', '৳'.number_format($a, 2), '৳'.number_format($b, 2), 'Discount Changed — '.$name.': ৳'.number_format($a, 2).' → ৳'.number_format($b, 2));
    }
  }
  /** Columns saved on the order so the reason for a discount is always visible later. */
  public static function meta(array $applied, array $profile, string $by, float $amount): array {
    $j = fn($k) => $applied ? mb_substr(implode(' + ', array_filter(array_column($applied, $k))), 0, 250) : null;
    $pcts = array_filter(array_column($applied, 'pct'), fn($v) => $v !== null);
    return ['discount_type' => $j('type'), 'discount_rule' => $j('rule'), 'discount_pct' => count($applied) === 1 ? ($applied[0]['pct'] ?? null) : null, 'discount_source' => $j('source'),
      'discount_by' => $amount > 0 ? $by : null, 'discount_lines' => self::hasLines() ? self::linesJson(self::lineRows($applied, $by, $by === 'Automatic')) : null, 'customer_category' => $profile['category'] ?? null, 'membership_status' => ($profile['membership'] ?? null) ? $profile['membership'].($profile['membership_name'] ? ' – '.$profile['membership_name'] : '') : null];
  }
}
