<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Black Friday 2026 (owner plan, 26 Nov 2026): 27 to 30 November, a separate Black Friday price per product =
 * purchase cost + the protected profit (ProfitFloor, 10% today); everything above that is given to the customer.
 * No other discount applies to a product sold at its Black Friday price.
 * THIS CLASS ONLY CALCULATES. It changes no price and nothing on the storefront: plan() is the list the owner approves first.
 */
class BlackFriday
{
  const MIN_OFF_TK = 1;        // a product joins only if the customer really saves something
  const CHECK_OFF_PCT = 50.0;  // a saving this large usually means the stored purchase cost is wrong: flagged for a look, not hidden

  // ---- campaign state (settings, nothing fixed in code) ----
  public static function set(string $k, string $d = ''): string { return Procure::set('bf_' . $k, $d); }
  public static function on(): bool { return self::set('on', '0') === '1'; }
  public static function minOff(): float { return max(0.0, min(90.0, (float)self::set('min_off', '0'))); }
  public static function window(): array { $tz = 'Asia/Dhaka'; return [\Carbon\Carbon::parse(self::set('start', '2026-11-27 00:00:00'), $tz), \Carbon\Carbon::parse(self::set('end', '2026-11-30 23:59:59'), $tz)]; }
  public static function running(): bool { [$a, $b] = self::window(); $n = now('Asia/Dhaka'); return self::on() && $n->gte($a) && $n->lte($b); }
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = \Illuminate\Support\Facades\Schema::hasTable('black_friday_items'); } catch (\Throwable $e) { $r = false; } } return $r; }
  /** Product ids selling at their Black Friday price right now (for the badge). Cached one minute. */
  public static function ids(): array {
    static $m = null; if ($m !== null) return $m;
    try { return $m = \Illuminate\Support\Facades\Cache::remember('bf.ids', 60, fn() => self::ready() ? array_flip(array_map('intval', DB::table('black_friday_items')->where('status', 'applied')->pluck('product_id')->all())) : []); } catch (\Throwable $e) { return $m = []; }
  }
  /** Preview for the owner before the start: these product ids are shown with the badge on that one page only. Nothing is stored. */
  public static array $preview = []; public static bool $previewOn = false;
  public static function has($pid): bool { return isset(self::ids()[(int)$pid]) || isset(self::$preview[(int)$pid]); }
  /** The badge. Owner, 26 Nov 2026 21:34: during the campaign every other product also carries the badge, at its normal price (bf_badge_all = 1); only Cigarette and Vape gets a Black Friday price. */
  public static function badge($pid): bool {
    if (self::has($pid)) return true; static $all = null;
    if ($all === null) { try { $all = self::set('badge_all', '1') === '1' && self::running() && count(self::ids()) > 0 && !CleanSite::on(); } catch (\Throwable $e) { $all = false; } }
    return $all || (self::$previewOn && self::set('badge_all', '1') === '1');
  }
  private static function flush(): void { try { \Illuminate\Support\Facades\Cache::forget('bf.ids'); BundlePricing::flush(); \App\Http\Controllers\StoreController::bust(); foreach (['store.home4', 'store.home5', 'store.home6', 'store.home8', 'store.todaybest6'] as $k) \Illuminate\Support\Facades\Cache::forget($k); } catch (\Throwable $e) {} }

  /** Owner, 26 Nov 2026 21:33: ONLY Cigarette and Vape takes part. The other categories are products sourced on order whose real purchase cost we do not hold, so no honest Black Friday price can be worked out for them (bf_scope = smoke; 'all' would open every category).
   *  Earlier note, still true inside that scope:
   *  The products that take part (owner, 26 Nov 2026 21:23: every product, also the ones whose saving looks very large; cigarettes too, on the site only).
   *  Left out: a Best Seller, which has its own approved pack offer. bf_min_off (0 today) can set a smallest saving. */
  public static function eligible(): array {
    $min = self::minOff(); $all = self::set('scope', 'smoke') === 'all'; $bs = []; try { $bs = AutoPrice::ids(); } catch (\Throwable $e) {}
    return array_values(array_filter(self::plan()['rows'], fn($r) => $r['status'] === 'in' && $r['off_pct'] >= $min && !in_array($r['id'], $bs, true) && ($all || $r['smoke'])));
  }
  /** Put the Black Friday price on every taking-part product. The normal price is kept in black_friday_items and comes back by itself. Safe to run again: a product already applied is skipped. */
  public static function apply(string $by = 'system'): int {
    if (!self::ready() || !self::running()) return 0; $n = 0; $have = array_flip(array_map('intval', DB::table('black_friday_items')->pluck('product_id')->all()));
    foreach (self::eligible() as $r) {
      if (isset($have[$r['id']])) continue;
      try {
        DB::transaction(function () use ($r, &$n) {
          $p = DB::table('products')->where('id', $r['id'])->lockForUpdate()->first(); if (!$p || abs((float)$p->price - $r['price']) > 0.004 || $r['bf'] >= (float)$p->price) return;
          DB::table('black_friday_items')->insert(['product_id' => $r['id'], 'cost' => $r['cost'], 'price_before' => $p->price, 'compare_before' => $p->compare_price, 'bf_price' => $r['bf'], 'off_pct' => $r['off_pct'], 'status' => 'applied', 'applied_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
          DB::table('products')->where('id', $r['id'])->update(['price' => $r['bf'], 'compare_price' => $p->price, 'updated_at' => now()]); $n++;
        });
      } catch (\Throwable $e) { report($e); }
    }
    if ($n) { self::flush(); try { BundlePricing::sync(); } catch (\Throwable $e) {} Procure::audit('Black Friday prices applied', $n . ' products', null, ['by' => $by, 'min_saving_pct' => self::minOff(), 'profit_pct' => ProfitFloor::percent()]); }
    return $n;
  }
  /** Put the normal prices back. A product whose price somebody changed during the campaign is left as it is (status kept). */
  public static function restore(string $by = 'system'): int {
    if (!self::ready()) return 0; $n = 0; $kept = 0;
    foreach (DB::table('black_friday_items')->where('status', 'applied')->get() as $x) {
      try {
        DB::transaction(function () use ($x, &$n, &$kept) {
          $p = DB::table('products')->where('id', $x->product_id)->lockForUpdate()->first();
          if ($p && abs((float)$p->price - (float)$x->bf_price) < 0.005) { DB::table('products')->where('id', $x->product_id)->update(['price' => $x->price_before, 'compare_price' => $x->compare_before, 'updated_at' => now()]); DB::table('black_friday_items')->where('id', $x->id)->update(['status' => 'restored', 'restored_at' => now(), 'updated_at' => now()]); $n++; }
          else { DB::table('black_friday_items')->where('id', $x->id)->update(['status' => 'kept', 'restored_at' => now(), 'note' => $p ? 'Price was changed during the campaign (' . (float)$p->price . '); left as it is' : 'Product no longer exists', 'updated_at' => now()]); $kept++; }
        });
      } catch (\Throwable $e) { report($e); }
    }
    if ($n || $kept) { self::flush(); try { BundlePricing::sync(); } catch (\Throwable $e) {} Procure::audit('Black Friday prices put back', $n . ' products', null, ['by' => $by, 'left_as_changed' => $kept]); }
    return $n;
  }
  /** While the campaign runs: a product whose purchase cost went up is lifted to its new protected price, or taken out when no saving is left. */
  public static function guard(): int {
    if (!self::ready()) return 0; $n = 0;
    foreach (DB::table('black_friday_items')->where('status', 'applied')->get() as $x) {
      try { $c = ProfitFloor::costs([(int)$x->product_id])[(int)$x->product_id] ?? null; if (!$c || (float)$c->cost <= 0 || abs((float)$c->price - (float)$x->bf_price) > 0.004) continue; $need = self::price((float)$c->cost); if ($need <= (float)$x->bf_price) continue;
        if ($need >= (float)$x->price_before) { DB::table('products')->where('id', $x->product_id)->update(['price' => $x->price_before, 'compare_price' => $x->compare_before, 'updated_at' => now()]); DB::table('black_friday_items')->where('id', $x->id)->update(['status' => 'restored', 'restored_at' => now(), 'note' => 'Purchase cost went up: no saving left', 'updated_at' => now()]); }
        else { DB::table('products')->where('id', $x->product_id)->update(['price' => $need, 'updated_at' => now()]); DB::table('black_friday_items')->where('id', $x->id)->update(['bf_price' => $need, 'cost' => $c->cost, 'note' => 'Lifted: purchase cost went up', 'updated_at' => now()]); }
        $n++; } catch (\Throwable $e) { report($e); }
    }
    if ($n) { self::flush(); Procure::audit('Black Friday price lifted (cost went up)', $n . ' products'); }
    return $n;
  }
  /** Background check, at most once a minute: start on time, end on time, switch off when the owner switches it off. */
  public static function tick(): void {
    try { if (!self::ready() || !\Illuminate\Support\Facades\Cache::add('bf.tick', 1, 60)) return; } catch (\Throwable $e) { return; }
    $applied = DB::table('black_friday_items')->where('status', 'applied')->exists();
    if (!$applied && (!self::running() || self::set('applied_at', '') !== '')) { try { $sid = self::linked('sms_campaigns'); if ($sid && DB::table('sms_campaigns')->where('id', $sid)->where('status', 'scheduled')->where('schedule_at', '<=', now()->addMinutes(20))->exists()) self::holdOutreach('No product is at its Black Friday price shortly before the SMS was due.'); } catch (\Throwable $e) {} }
    if (self::running()) { if (self::set('applied_at', '') === '') { $n = self::apply('worker'); Procure::put('bf_applied_at', now('UTC')->format('Y-m-d H:i:s')); if ($n === 0) self::holdOutreach('The campaign started but no product took the Black Friday price.'); if ($n === 0) DeployHealth::raise('bf.none', 'high', 'Pricing', 'Black Friday started but no product took the price', 'The campaign is on and inside its dates, yet no Black Friday price was applied.', 'No product met the rule, or the price list changed.', 'Open Black Friday in the admin and check the list.'); } elseif (\Illuminate\Support\Facades\Cache::add('bf.guard', 1, 1800)) self::guard(); }
    elseif ($applied) self::restore('worker');
  }
  /** Id of this campaign's SMS run or voice run: the newest one named like the campaign (bf_outreach_name, 'Black Friday 2026'). */
  public static function linked(string $table): int { try { return (int)DB::table($table)->where('name', self::set('outreach_name', 'Black Friday 2026'))->max('id'); } catch (\Throwable $e) { return 0; } }
  /** The campaign's own SMS run and voice-call run (ids kept in settings), with their lists, for the Black Friday page. */
  public static function outreach(): array {
    $o = ['sms' => null, 'voice' => null, 'smsRows' => [], 'voiceRows' => [], 'smsBy' => [], 'voiceBy' => []];
    try { $sid = self::linked('sms_campaigns'); if ($sid && ($c = DB::table('sms_campaigns')->find($sid))) { $o['sms'] = $c;
        $o['smsBy'] = DB::table('sms_logs')->where('campaign_id', $sid)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
        $o['smsRows'] = DB::table('sms_logs')->where('campaign_id', $sid)->orderByDesc('id')->limit(30)->get(['to', 'status', 'created_at'])->all(); } } catch (\Throwable $e) {}
    try { $vid = self::linked('voice_campaigns'); if ($vid && ($c = DB::table('voice_campaigns')->find($vid))) { $o['voice'] = $c;
        $o['voiceBy'] = DB::table('voice_campaign_customers')->where('campaign_id', $vid)->selectRaw('call_status, count(*) n')->groupBy('call_status')->pluck('n', 'call_status')->all();
        $o['voiceRows'] = DB::table('voice_campaign_customers')->where('campaign_id', $vid)->where('call_status', '!=', 'pending')->orderByDesc('updated_at')->limit(30)->get(['name', 'phone', 'call_status', 'call_attempts', 'updated_at'])->all(); } } catch (\Throwable $e) {}
    return $o;
  }
  /** Safety: the SMS and the call both say "Black Friday prices are on". If no price was applied, they must not go out: both are paused and an alert is raised. */
  public static function holdOutreach(string $why): void {
    try { $sid = self::linked('sms_campaigns'); if ($sid) DB::table('sms_campaigns')->where('id', $sid)->where('status', 'scheduled')->update(['status' => 'paused', 'updated_at' => now()]);
      $vid = self::linked('voice_campaigns'); if ($vid) DB::table('voice_campaigns')->where('id', $vid)->where('status', 'active')->update(['status' => 'paused', 'updated_at' => now()]);
      DeployHealth::raise('bf.outreach.hold', 'high', 'Marketing', 'Black Friday SMS and calls paused', $why, 'The Black Friday prices are not on the site, so the SMS and the offer call would promise a price the customer cannot get.', 'Open Black Friday in the admin, fix the prices, then resume the SMS campaign and the voice campaign.');
    } catch (\Throwable $e) { report($e); }
  }
  public static function counts(): array { if (!self::ready()) return ['applied' => 0, 'restored' => 0, 'kept' => 0]; $c = DB::table('black_friday_items')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all(); return ['applied' => (int)($c['applied'] ?? 0), 'restored' => (int)($c['restored'] ?? 0), 'kept' => (int)($c['kept'] ?? 0)]; }

  /** Whole-taka Black Friday price for a purchase cost: never below the protected profit. */
  public static function price(float $cost): float { return $cost > 0 ? (float)ceil(ProfitFloor::floor($cost) - 0.0001) : 0.0; }

  /** Every published product with its place in the plan. status: in | no_cost | at_floor | no_stock | twin */
  public static function plan(): array {
    $cols = array_merge(['p.id', 'p.sku', 'p.name', 'p.price', 'p.compare_price', 'p.stock', 'p.cost', 'p.category_id', 'p.group_key', 'c.name as cat', 'c.slug as cslug'],
      ProfitFloor::hasAvg() ? ['p.avg_cost'] : [], ProfitFloor::hasFifo() ? ['p.fifo_cost'] : [], ProfitFloor::hasTwin() ? ['p.parent_product_id'] : []);
    $rows = []; $pct = ProfitFloor::percent();
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where('p.published', true)->where('p.price', '>', 0)->orderBy('p.id')->get($cols) as $p) {
      $price = (float)$p->price; $cost = ProfitFloor::base($p); $bf = self::price($cost); $stock = $p->stock === null ? null : (int)$p->stock;
      $smoke = ($p->group_key ?? '') === 'smoke' || \App\Http\Controllers\StoreController::isSmokeCat((object)['slug' => $p->cslug, 'name' => $p->cat]);
      $st = 'in';
      if (!empty($p->parent_product_id)) $st = 'twin';
      elseif ($cost <= 0) $st = 'no_cost';
      elseif (!StockGuard::sellable($p)) $st = 'no_stock';   // zero stock in a stock-controlled category; products sourced on order (stock 0, still orderable) stay in
      elseif ($price - $bf < self::MIN_OFF_TK) $st = 'at_floor';
      $off = $st === 'in' ? $price - $bf : 0.0;
      $rows[] = ['id' => (int)$p->id, 'sku' => (string)$p->sku, 'name' => (string)$p->name, 'cat' => (string)($p->cat ?? 'No category'), 'smoke' => $smoke, 'stock' => $stock, 'cost' => round($cost, 2), 'price' => $price,
        'bf' => $st === 'in' ? $bf : null, 'off' => $off, 'off_pct' => $st === 'in' && $price > 0 ? round($off / $price * 100, 1) : 0.0, 'profit' => $st === 'in' ? round($bf - $cost, 2) : null,
        'sourced' => $stock !== null && $stock <= 0, 'status' => $st, 'check' => $st === 'in' && $price > 0 && $off / $price * 100 >= self::CHECK_OFF_PCT];
    }
    return ['rows' => $rows, 'pct' => $pct];
  }

  /** The plan with the real scope applied: a product that would have a saving but is outside the campaign (other categories, Best Sellers) is marked 'normal' = keeps its price, badge only. */
  public static function scoped(): array {
    $d = self::plan(); $el = []; foreach (self::eligible() as $r) $el[$r['id']] = 1;
    foreach ($d['rows'] as &$r) if ($r['status'] === 'in' && !isset($el[$r['id']])) { $r['status'] = 'normal'; $r['bf'] = null; $r['off'] = 0.0; $r['off_pct'] = 0.0; $r['profit'] = null; $r['check'] = false; } unset($r);
    return $d;
  }
  public static function summary(array $rows): array {
    $by = []; $tot = ['all' => count($rows), 'normal' => 0, 'in' => 0, 'no_cost' => 0, 'at_floor' => 0, 'no_stock' => 0, 'twin' => 0, 'check' => 0, 'smoke_in' => 0, 'off_sum' => 0.0, 'price_sum' => 0.0];
    foreach ($rows as $r) {
      $tot[$r['status']]++; $c = $r['cat']; $by[$c] = $by[$c] ?? ['cat' => $c, 'all' => 0, 'normal' => 0, 'in' => 0, 'no_cost' => 0, 'at_floor' => 0, 'no_stock' => 0, 'off_sum' => 0.0, 'price_sum' => 0.0, 'max' => 0.0, 'smoke' => $r['smoke'], 'sourced' => 0, 'b' => [0, 0, 0, 0]];
      $by[$c]['all']++; if (isset($by[$c][$r['status']])) $by[$c][$r['status']]++;
      if ($r['status'] === 'in') { if ($r['sourced']) { $by[$c]['sourced']++; $tot['sourced'] = ($tot['sourced'] ?? 0) + 1; } $by[$c]['b'][$r['off_pct'] < 5 ? 0 : ($r['off_pct'] < 10 ? 1 : ($r['off_pct'] < 20 ? 2 : 3))]++; $tot['off_sum'] += $r['off']; $tot['price_sum'] += $r['price']; $by[$c]['off_sum'] += $r['off']; $by[$c]['price_sum'] += $r['price']; $by[$c]['max'] = max($by[$c]['max'], $r['off_pct']); if ($r['check']) $tot['check']++; if ($r['smoke']) $tot['smoke_in']++; }
    }
    usort($by, fn($a, $b) => $b['in'] <=> $a['in']);
    return ['tot' => $tot, 'by' => $by];
  }
}
