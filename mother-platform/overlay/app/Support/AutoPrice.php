<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Best Sellers automatic sale price (owner, 14 Nov 2026): the flagship products sell at the protected floor, i.e. actual batch cost (FIFO) x (1 + Minimum Protected Profit %), rounded up to a whole taka.
 *  The price follows the cost by itself: a new purchase or the next batch coming into use moves it. No fixed price or percentage is kept here; both come from ProfitFloor.
 *  A product can be put on a custom price (this page, or a hand-typed price on the product form); it then stays there until someone switches it back to automatic. */
class AutoPrice {
  const ON = 'autoprice_on', CUSTOM = 'autoprice_custom', JUMP = 15.0; // a single move above JUMP % is not applied without the owner
  public static function on(): bool { return Procure::set(self::ON, '0') === '1'; }
  public static function ids(): array { try { return \App\Http\Controllers\StoreController::bestSellers()->pluck('id')->map(fn($i) => (int)$i)->all(); } catch (\Throwable $e) { return []; } }
  public static function has(int $pid): bool { return in_array($pid, self::ids(), true); }
  public static function customIds(): array { $j = json_decode(Procure::set(self::CUSTOM, '[]'), true); return is_array($j) ? array_values(array_unique(array_map('intval', $j))) : []; }
  public static function isCustom(int $pid): bool { return in_array($pid, self::customIds(), true); }
  public static function setCustom(int $pid, bool $custom, string $why = ''): void {
    $c = array_values(array_diff(self::customIds(), [$pid])); if ($custom) $c[] = $pid;
    Procure::put(self::CUSTOM, json_encode($c));
    Procure::audit($custom ? 'Auto price paused (custom price)' : 'Auto price resumed', 'Product #'.$pid, null, ['reason' => $why, 'by' => self::who()]);
  }
  private static function who(): string { try { return auth()->user()->name ?? 'system'; } catch (\Throwable $e) { return 'system'; } }
  /** Protected floor in whole taka; 0 when the product has no cost on file. */
  public static function target($p): float { $c = ProfitFloor::base($p); return $c > 0 ? (float)ceil(ProfitFloor::floor($c) - 0.004) : 0.0; }
  /** Bring every automatic product to its target. Returns [pid => [old, new]] for the prices that moved. */
  public static function sync(string $src = 'auto', bool $force = false): array {
    $out = []; if (!self::on()) return $out;
    foreach (self::ids() as $pid) {
      try {
        if (self::isCustom($pid)) continue;
        $p = DB::table('products')->find($pid); if (!$p) continue;
        $t = self::target($p); $old = (float)$p->price; if ($t <= 0 || abs($old - $t) < 0.005) { if ($t > 0) self::clear($pid); continue; }
        $move = $old > 0 ? abs($t - $old) / $old * 100 : 0;
        if (!$force && $move > self::JUMP) {
          DeployHealth::raise('autoprice.jump.'.$pid, 'high', 'Pricing', 'Best Seller price not changed: '.$p->name, 'The automatic price would move from '.number_format($old).' to '.number_format($t).' TK ('.round($move, 1).'%).', 'The batch cost changed by more than '.(int)self::JUMP.'% at once, which is often a typing mistake in a purchase.', 'Check the last purchase cost, then open Best Sellers pricing and press Apply.');
          continue;
        }
        $upd = ['price' => $t, 'updated_at' => now()];
        if ($p->compare_price !== null && (float)$p->compare_price <= $t) $upd['compare_price'] = null;
        DB::table('products')->where('id', $pid)->update($upd);
        Procure::audit('Auto price updated', 'Product '.$p->name, ['price' => $old], ['price' => $t, 'batch_cost' => ProfitFloor::base($p), 'percent' => ProfitFloor::percent(), 'source' => $src, 'by' => self::who()]);
        try { BundlePricing::sync([$pid]); } catch (\Throwable $e) { report($e); }
        self::clear($pid); $out[$pid] = [$old, $t];
      } catch (\Throwable $e) { report($e); }
    }
    if ($out) { try { Cache::forget('store.home6'); \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {} }
    return $out;
  }
  private static function clear(int $pid): void { try { DeployHealth::resolve('autoprice.jump.'.$pid, 'System (price in line)'); } catch (\Throwable $e) {} }
  /** Background check, at most once a minute. */
  public static function tick(): void { if (!self::on()) return; try { if (!Cache::add('autoprice.tick', 1, 60)) return; } catch (\Throwable $e) {} self::sync('worker'); }
  /** A hand-typed price on the product form puts the product on a custom price. */
  public static function typed(int $pid, float $price): void {
    try { if (!self::on() || !self::has($pid) || self::isCustom($pid)) return; $p = DB::table('products')->find($pid); if ($p && abs(self::target($p) - $price) < 0.005) return; self::setCustom($pid, true, 'price typed on the product form'); } catch (\Throwable $e) { report($e); }
  }
  /** Rows for the admin page. */
  public static function rows(): array {
    $r = []; foreach (self::ids() as $pid) { $p = DB::table('products')->find($pid); if (!$p) continue; $c = ProfitFloor::base($p);
      $r[] = (object)['id' => $pid, 'name' => $p->name, 'cost' => $c, 'target' => self::target($p), 'price' => (float)$p->price, 'compare' => $p->compare_price, 'stock' => (int)$p->stock, 'custom' => self::isCustom($pid)]; }
    return $r;
  }
}
