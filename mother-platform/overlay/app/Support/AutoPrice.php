<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Best Sellers automatic pack offer (owner, 14 Nov 2026). The flagship products keep their normal single-carton price; the offer is the pack of 2 to 5 cartons:
 *    pack total = cartons x actual batch cost (FIFO) + our parcel cost once (packaging + delivery) + cartons x fixed net profit, rounded up to a whole taka.
 *  The owner fixed the net profit per carton (75 to start, on trial) and the parcel cost (30 packaging + 90 delivery); both are settings on the Best Sellers page, nothing is fixed in code.
 *  This is the ONE approved exception to the Minimum Protected Profit rule: only for these products, only for their pack prices, never below cost + parcel cost + the fixed profit.
 *  The pack follows the cost by itself (new purchase, or the next batch coming into use). It is stored as the product's Buy 2-5 pack prices (bundle rule "custom"), so cart, checkout and invoices use the existing bundle logic.
 *  A product can be put on a custom price per carton on the Best Sellers page; it then stays there until someone switches it back to automatic. */
class AutoPrice {
  const ON = 'autoprice_on', CUSTOM = 'autoprice_custom', PARCEL = 'autoprice_parcel_cost', PROFIT = 'autoprice_profit', JUMP = 15.0; // a single move above JUMP % is not applied without the owner
  private static ?array $ids = null;
  public static function on(): bool { return Procure::set(self::ON, '0') === '1'; }
  /** Our own cost of sending one parcel (packaging + courier), counted once per pack. */
  public static function parcel(): float { return max(0.0, (float)Procure::set(self::PARCEL, '120')); }
  /** Fixed net profit per carton inside a pack. */
  public static function profit(): float { return max(0.0, (float)Procure::set(self::PROFIT, '75')); }
  public static function ids(): array { if (self::$ids !== null) return self::$ids; try { return self::$ids = \App\Http\Controllers\StoreController::bestSellers()->pluck('id')->map(fn($i) => (int)$i)->all(); } catch (\Throwable $e) { return []; } }
  public static function has(int $pid): bool { return in_array($pid, self::ids(), true); }
  /** Products whose pack prices are allowed under the protected-profit floor. */
  public static function exempt(int $pid): bool { try { return $pid > 0 && self::on() && self::has($pid); } catch (\Throwable $e) { return false; } }
  public static function customIds(): array { $j = json_decode(Procure::set(self::CUSTOM, '[]'), true); return is_array($j) ? array_values(array_unique(array_map('intval', $j))) : []; }
  public static function isCustom(int $pid): bool { return in_array($pid, self::customIds(), true); }
  public static function setCustom(int $pid, bool $custom, string $why = ''): void {
    $c = array_values(array_diff(self::customIds(), [$pid])); if ($custom) $c[] = $pid;
    Procure::put(self::CUSTOM, json_encode($c));
    Procure::audit($custom ? 'Auto price paused (custom price)' : 'Auto price resumed', 'Product #'.$pid, null, ['reason' => $why, 'by' => self::who()]);
  }
  private static function who(): string { try { return auth()->user()->name ?? 'system'; } catch (\Throwable $e) { return 'system'; } }
  /** Cost of one carton when $n are taken (batch by batch when the pack runs over into the next batch). */
  public static function unitCost($p, int $n = 1): float { try { if ($n > 1 && Costing::ready()) { $e = Costing::expectedUnit((int)$p->id, $n); if ($e > 0) return (float)$e; } } catch (\Throwable $e) {} return ProfitFloor::base($p); }
  /** Automatic total for a pack of $n; 0 when the product has no cost on file. */
  public static function offer($p, int $n): float { $c = self::unitCost($p, $n); return $c > 0 ? (float)ceil($c * $n + self::parcel() + self::profit() * $n - 0.004) : 0.0; }
  /** Lowest total the exception allows for $n cartons: cost + fixed profit (the parcel cost is part of the automatic price, not of this limit). Used by the checks at order entry and at shipment. */
  public static function minTotal(int $pid, int $n): ?float {
    if ($n < 2 || !self::exempt($pid)) return null; $p = DB::table('products')->find($pid); if (!$p) return null; $c = self::unitCost($p, $n); return $c > 0 ? round(($c + self::profit()) * $n, 2) : null;
  }
  /** Pack totals to store: [2..5 => total | null]. A pack that would not be cheaper than buying singles is not offered. */
  public static function packs($p, ?float $unit = null): array { $o = []; foreach ([2, 3, 4, 5] as $n) { $t = $unit !== null ? (float)ceil($unit * $n - 0.004) : self::offer($p, $n); $o[$n] = ($t > 0 && $t <= (float)$p->price * $n - 1) ? $t : null; } return $o; }
  /** Write the pack prices of one product ($unit = custom price per carton, null = automatic). Returns true when something changed. */
  public static function write(object $p, ?float $unit = null): bool {
    $upd = []; foreach (self::packs($p, $unit) as $n => $v) { $cur = $p->{"buy{$n}_price"} ?? null; $cur = $cur === null ? null : (float)$cur; if ($v === null ? $cur !== null : ($cur === null || abs($cur - $v) > 0.004)) $upd["buy{$n}_price"] = $v; }
    if (BundlePricing::ready() && ($p->bundle_rule ?? 'global') !== 'custom') $upd['bundle_rule'] = 'custom';
    if (!$upd) return false;
    DB::table('products')->where('id', $p->id)->update($upd + ['updated_at' => now()]); BundlePricing::flush(); return true;
  }
  /** Tiers for the bundle engine, straight from the stored pack prices (no protected-floor clamp: this is the approved exception). Same shape as BundlePricing::compute(). */
  public static function tiers($x): array {
    $o = [2 => null, 3 => null, 4 => null, 5 => null]; $price = (float)$x->price; $cost = (float)($x->base ?? 0);
    foreach ([2, 3, 4, 5] as $n) { $t = (float)($x->{"buy{$n}_price"} ?? 0); $l = $price * $n; if ($t <= 0 || $t > $l - 1) continue; $u = round($t / $n, 2);
      $o[$n] = ['req' => round((1 - $t / $l) * 100, 2), 'pct' => round((1 - $t / $l) * 100, 2), 'unit' => $u, 'total' => round($t, 2), 'save' => round($l - $t, 2), 'save_pct' => round((1 - $t / $l) * 100, 2), 'margin' => $cost > 0 ? round(($u - $cost) / $cost * 100, 2) : null, 'status' => 'Custom']; }
    return $o;
  }
  /** Total of the stored pack of two (0 = none). */
  public static function pack2($p): float { $t = (float)($p->buy2_price ?? 0); return $t > 0 && $t < (float)$p->price * 2 ? $t : 0.0; }
  /** Bring every automatic product's pack prices to the target. Returns [pid => [old pack of 2, new pack of 2]] for the ones that moved. */
  public static function sync(string $src = 'auto', bool $force = false): array {
    $out = []; if (!self::on()) return $out;
    foreach (self::ids() as $pid) {
      try {
        if (self::isCustom($pid)) continue;
        $p = DB::table('products')->find($pid); if (!$p) continue;
        $new = self::offer($p, 2); if ($new <= 0) continue; $old = self::pack2($p);
        $move = $old > 0 ? abs($new - $old) / $old * 100 : 0;
        if (!$force && $move > self::JUMP) {
          DeployHealth::raise('autoprice.jump.'.$pid, 'high', 'Pricing', 'Best Seller pack price not changed: '.$p->name, 'The automatic 2-carton price would move from '.number_format($old).' to '.number_format($new).' TK ('.round($move, 1).'%).', 'The batch cost changed by more than '.(int)self::JUMP.'% at once, which is often a typing mistake in a purchase.', 'Check the last purchase cost, then open Best Sellers and press Apply.');
          continue;
        }
        if (!self::write($p)) { self::clear($pid); continue; }
        Procure::audit('Auto price updated', 'Product '.$p->name, ['price' => $old], ['price' => $new, 'packs' => self::packs($p), 'batch_cost' => ProfitFloor::base($p), 'parcel_cost' => self::parcel(), 'profit_per_carton' => self::profit(), 'source' => $src, 'by' => self::who()]);
        self::clear($pid); $out[$pid] = [$old, $new];
      } catch (\Throwable $e) { report($e); }
    }
    if ($out) { try { Cache::forget('store.home6'); \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {} }
    return $out;
  }
  private static function clear(int $pid): void { try { DeployHealth::resolve('autoprice.jump.'.$pid, 'System (price in line)'); } catch (\Throwable $e) {} }
  /** Background check, at most once a minute. */
  public static function tick(): void { if (!self::on()) return; try { if (!Cache::add('autoprice.tick', 1, 60)) return; } catch (\Throwable $e) {} self::sync('worker'); }
  /** The single price is not managed here; a typed single price changes nothing in the offer (a pack that stops being cheaper than singles is dropped by itself). */
  public static function typed(int $pid, float $price): void {}
  /** Rows for the admin page. */
  public static function rows(): array {
    $r = []; foreach (self::ids() as $pid) { $p = DB::table('products')->find($pid); if (!$p) continue;
      $r[] = (object)['id' => $pid, 'name' => $p->name, 'cost' => ProfitFloor::base($p), 'offer' => [2 => self::offer($p, 2), 3 => self::offer($p, 3), 4 => self::offer($p, 4), 5 => self::offer($p, 5)], 'now' => [2 => (float)($p->buy2_price ?? 0), 3 => (float)($p->buy3_price ?? 0), 4 => (float)($p->buy4_price ?? 0), 5 => (float)($p->buy5_price ?? 0)], 'price' => (float)$p->price, 'stock' => (int)$p->stock, 'custom' => self::isCustom($pid)]; }
    return $r;
  }
}
