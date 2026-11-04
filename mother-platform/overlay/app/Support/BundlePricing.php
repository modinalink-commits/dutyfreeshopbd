<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * Global Sales Pricing & Bundle Discount Automation.
 *   Bundle unit price = sale price x (1 - discount %);   bundle total = unit price x qty.
 *   The global ratio (default Buy 2 = 1.5%, Buy 3 = 2.5%, Buy 4 = 3.5%, Buy 5 = 4.5%) is calculated from the CURRENT sale price and is
 *   automatically capped so that the bundle unit price never falls below  FIFO cost x 1.15  (the protected 15% profit).
 *   Per product: rule 'global' (default, automatic), 'custom' (the Buy 2-5 totals typed in the product form, still held at the 15% floor) or 'off'.
 *   Storefront, cart and order always calculate from here (never stale); the Buy 2-5 columns of the products table are a copy kept in sync for the admin lists.
 */
class BundlePricing {
  const DEF = [2 => 1.5, 3 => 2.5, 4 => 3.5, 5 => 4.5];
  private static ?array $cfg = null; private static array $memo = []; private static ?bool $ready = null;

  public static function ready(): bool {
    if (self::$ready !== null) return self::$ready;
    try { self::$ready = Schema::hasColumn('products', 'bundle_rule'); } catch (\Throwable $e) { self::$ready = false; }
    return self::$ready;
  }
  public static function flush(): void { self::$cfg = null; self::$memo = []; self::$ready = null; try { Cache::forget('bundle.cfg'); } catch (\Throwable $e) {} }

  /** Global settings (Sales & Bundle Pricing Settings). */
  public static function cfg(): array {
    if (self::$cfg !== null) return self::$cfg;
    $load = function () {
      $pct = []; foreach ([2, 3, 4, 5] as $n) $pct[$n] = max(0.0, min(50.0, (float)Procure::set("bundle_pct_$n", (string)self::DEF[$n])));
      $un = Procure::set('bundle_unsafe', 'reduce'); $rd = Procure::set('bundle_round', 'none');
      return ['on' => Procure::set('bundle_on', '1') === '1', 'pct' => $pct, 'unsafe' => $un === 'disable' ? 'disable' : 'reduce',
        'round' => in_array($rd, ['1', '5', '10'], true) ? (int)$rd : 0, 'version' => max(1, (int)Procure::set('bundle_rule_version', '1'))];
    };
    try { return self::$cfg = Cache::remember('bundle.cfg', 120, $load); } catch (\Throwable $e) { return self::$cfg = $load(); }
  }
  public static function version(): int { return self::cfg()['version']; }

  /**
   * Tiers for one product. $p needs: price, rule ('global'|'custom'|'off'), cost (FIFO base), buy2_price..buy5_price (custom totals).
   * Returns [n => null | ['req'=>requested %, 'pct'=>applied %, 'unit'=>, 'total'=>, 'save'=>, 'save_pct'=>, 'margin'=>profit % on cost or null, 'status'=>Safe|Capped|Custom]].
   */
  public static function compute(float $price, float $cost, string $rule, array $stored = [], ?array $cfg = null): array {
    $cfg = $cfg ?? self::cfg(); $out = [2 => null, 3 => null, 4 => null, 5 => null];
    if ($price <= 0 || $rule === 'off' || !$cfg['on']) return $out;
    $floor = $cost > 0 ? ProfitFloor::floor($cost) : 0.0; $step = (int)$cfg['round'];
    foreach ([2, 3, 4, 5] as $n) {
      $list = $price * $n; $min = $floor > 0 ? ceil(round($floor * $n * 100, 6)) / 100 : 0.0; $req = (float)$cfg['pct'][$n]; $applied = $req; $status = 'Safe';
      if ($rule === 'custom') {
        $t = (float)($stored[$n] ?? 0); if ($t <= 0 || $t >= $list) continue;
        $total = max($t, $min); $status = 'Custom'; $req = round((1 - $t / $list) * 100, 2); $applied = round((1 - $total / $list) * 100, 2);
      } else {
        $max = $floor > 0 ? max(0.0, ($price - $floor) / $price * 100) : 100.0; // Maximum Safe Discount %
        if ($req > $max + 1e-9) { if ($cfg['unsafe'] === 'disable') continue; $applied = floor($max * 100) / 100; $status = 'Capped'; }
        if ($applied <= 0) continue;
        $total = $price * (1 - $applied / 100) * $n;
        if ($step > 0) { $total = round($total / $step) * $step; if ($min > 0 && $total + 0.004 < $min) $total = ceil($min / $step) * $step; } // round, then the 15% check again
        $total = round($total, 2);
        if ($min > 0 && $total + 0.004 < $min) continue;
      }
      if ($total > $list - 1) continue; // less than ৳1 saved: not a real offer, do not show it
      $unit = round($total / $n, 2); $out[$n] = ['req' => $req, 'pct' => round((1 - $total / $list) * 100, 2), 'unit' => $unit, 'total' => round($total, 2), 'save' => round($list - $total, 2),
        'save_pct' => round((1 - $total / $list) * 100, 2), 'margin' => $cost > 0 ? round(($unit - $cost) / $cost * 100, 2) : null, 'status' => $status];
    }
    return $out;
  }

  /** Product rows with everything compute() needs. $ids null = every product. */
  private static function rows(?array $ids = null) {
    $q = DB::table('products as p')->selectRaw('p.id, p.name, p.price, '.(self::ready() ? 'p.bundle_rule' : "'global'").' as rule, '.Costing::fifoSql('p').' as base, p.buy2_price, p.buy3_price, p.buy4_price, p.buy5_price');
    if ($ids !== null) $q->whereIn('p.id', $ids ?: [0]);
    return $q;
  }
  private static function one($x, ?array $cfg = null): array {
    return self::compute((float)$x->price, (float)$x->base, (string)($x->rule ?: 'global'), [2 => $x->buy2_price, 3 => $x->buy3_price, 4 => $x->buy4_price, 5 => $x->buy5_price], $cfg);
  }
  /** Tiers of one product, live (cached for the request). */
  public static function forProduct(int $id): array {
    if (isset(self::$memo[$id])) return self::$memo[$id];
    try { $x = self::rows([$id])->first(); return self::$memo[$id] = $x ? self::one($x) : [2 => null, 3 => null, 4 => null, 5 => null]; }
    catch (\Throwable $e) { report($e); return self::$memo[$id] = self::stored($id); }
  }
  /** Fallback when the live calculation fails: the copy kept in the products table. */
  private static function stored(int $id): array {
    $x = DB::table('products')->where('id', $id)->first(['price', 'buy2_price', 'buy3_price', 'buy4_price', 'buy5_price']); $o = [2 => null, 3 => null, 4 => null, 5 => null]; if (!$x) return $o;
    foreach ([2, 3, 4, 5] as $n) { $t = (float)($x->{"buy{$n}_price"} ?? 0); $l = (float)$x->price * $n; if ($t > 0 && $t < $l) $o[$n] = ['req' => 0, 'pct' => round((1 - $t / $l) * 100, 2), 'unit' => round($t / $n, 2), 'total' => $t, 'save' => round($l - $t, 2), 'save_pct' => round((1 - $t / $l) * 100, 2), 'margin' => null, 'status' => 'Safe']; }
    return $o;
  }
  /** Total price of the Buy-$n pack of a product, or null when that pack does not exist. */
  public static function total(int $id, int $n): ?float { $t = self::forProduct($id)[$n] ?? null; return $t ? (float)$t['total'] : null; }

  /** Keep the Buy 2-5 columns of the products table equal to the live tiers (global/off products only). Returns [changed, capped, disabled]. */
  public static function sync(?array $ids = null): array {
    if (!self::ready()) return [0, 0, 0];
    self::flush(); $cfg = self::cfg(); $ch = 0; $cap = 0; $dis = 0;
    try {
      foreach (self::rows($ids)->orderBy('p.id')->get()->chunk(400) as $chunk) foreach ($chunk as $x) {
        if (($x->rule ?: 'global') === 'custom') continue;
        $t = self::one($x, $cfg); $upd = []; $isCap = false;
        foreach ([2, 3, 4, 5] as $n) {
          $v = $t[$n]['total'] ?? null; $cur = $x->{"buy{$n}_price"}; $cur = $cur === null ? null : (float)$cur;
          if ($v === null ? $cur !== null : ($cur === null || abs($cur - $v) > 0.004)) $upd["buy{$n}_price"] = $v;
          if ($v !== null && ($t[$n]['status'] ?? '') === 'Capped') $isCap = true;
        }
        if ($isCap) $cap++;
        if ($cfg['on'] && ($x->rule ?: 'global') === 'global' && in_array(null, $t, true) && (float)$x->base > 0 && (float)$x->price > 0) $dis++;
        if ($upd) { DB::table('products')->where('id', $x->id)->update($upd); $ch++; }
      }
    } catch (\Throwable $e) { report($e); }
    self::flush(); return [$ch, $cap, $dis];
  }

  // ---------- order snapshot ----------
  public static function snapReady(): bool {
    static $r = null; if ($r === null) { try { $r = Schema::hasColumn('order_items', 'pricing_rule_version'); } catch (\Throwable $e) { $r = false; } } return $r;
  }
  /** Pricing snapshot stored on the order item, so later price / cost / rule changes never alter an old order. $line = what the customer pays for the line after the bundle price. */
  public static function snapshot(?int $pid, int $qty, float $unitPrice, float $line): array {
    if (!self::snapReady() || !$pid || $qty <= 0) return [];
    try {
      $p = DB::table('products as p')->selectRaw(Costing::fifoSql('p').' as base')->where('p.id', $pid)->first(); $cost = $p ? (float)$p->base : 0.0; $floor = $cost > 0 ? ProfitFloor::floor($cost) : 0.0;
      $list = $unitPrice * $qty; $tier = min(5, $qty); $cfg = self::cfg(); $req = $qty >= 2 ? (float)($cfg['pct'][$tier] ?? 0) : 0.0;
      $app = $list > 0 ? max(0.0, round((1 - $line / $list) * 100, 2)) : 0.0; $profit = $cost > 0 ? round($line - $cost * $qty, 2) : null;
      return ['original_sale_price' => round($unitPrice, 2), 'bundle_quantity' => $qty, 'requested_bundle_discount' => $req, 'applied_bundle_discount' => $app, 'bundle_unit_price' => round($line / $qty, 2),
        'bundle_total' => round($line, 2), 'fifo_cost_snapshot' => $cost > 0 ? round($cost, 4) : null, 'minimum_margin_snapshot' => 15, 'minimum_safe_price_snapshot' => $floor > 0 ? $floor : null,
        'profit_snapshot' => $profit, 'profit_percent_snapshot' => $profit !== null && $cost > 0 ? round($profit / ($cost * $qty) * 100, 2) : null, 'pricing_rule_version' => $cfg['version']];
    } catch (\Throwable $e) { report($e); return []; }
  }
}
