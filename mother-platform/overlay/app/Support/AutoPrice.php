<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Best Sellers automatic 2-carton offer (owner, 14 Nov 2026): the flagship products keep their normal single price; the offer is the pack of two or more, sold at the protected floor per carton,
 *  i.e. actual batch cost (FIFO) x (1 + Minimum Protected Profit %), rounded up to a whole taka. Delivery and packaging are our cost, so the floor price is not offered on a single carton.
 *  The pack price follows the cost by itself: a new purchase or the next batch coming into use moves it. No fixed price or percentage is kept here; both come from ProfitFloor.
 *  It is stored as the product's own Buy 2-5 pack prices (bundle rule "custom"), so cart, checkout and invoices use the existing bundle logic.
 *  A product can be put on a custom pack price on the Best Sellers page; it then stays there until someone switches it back to automatic. */
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
  /** Pack totals for a per-carton price: [2..5 => total | null]. A pack that would not be cheaper than buying singles is not offered. */
  public static function packs(float $unit, float $single): array { $o = []; foreach ([2, 3, 4, 5] as $n) $o[$n] = ($unit > 0 && $unit * $n <= $single * $n - 1) ? round($unit * $n, 2) : null; return $o; }
  /** Write the pack prices of one product. Returns true when something changed. */
  public static function write(object $p, float $unit): bool {
    $upd = []; foreach (self::packs($unit, (float)$p->price) as $n => $v) { $cur = $p->{"buy{$n}_price"} ?? null; $cur = $cur === null ? null : (float)$cur; if ($v === null ? $cur !== null : ($cur === null || abs($cur - $v) > 0.004)) $upd["buy{$n}_price"] = $v; }
    if (BundlePricing::ready() && ($p->bundle_rule ?? 'global') !== 'custom') $upd['bundle_rule'] = 'custom';
    if (!$upd) return false;
    DB::table('products')->where('id', $p->id)->update($upd + ['updated_at' => now()]); BundlePricing::flush(); return true;
  }
  /** Per-carton price inside the pack of two as stored now (0 = no pack price). */
  public static function unitNow($p): float { $t = (float)($p->buy2_price ?? 0); return $t > 0 && $t < (float)$p->price * 2 ? round($t / 2, 2) : 0.0; }
  /** Bring every automatic product's pack prices to the target. Returns [pid => [old unit, new unit]] for the ones that moved. */
  public static function sync(string $src = 'auto', bool $force = false): array {
    $out = []; if (!self::on()) return $out;
    foreach (self::ids() as $pid) {
      try {
        if (self::isCustom($pid)) continue;
        $p = DB::table('products')->find($pid); if (!$p) continue;
        $t = self::target($p); if ($t <= 0) continue; $old = self::unitNow($p);
        $move = $old > 0 ? abs($t - $old) / $old * 100 : 0;
        if (!$force && $move > self::JUMP) {
          DeployHealth::raise('autoprice.jump.'.$pid, 'high', 'Pricing', 'Best Seller pack price not changed: '.$p->name, 'The automatic 2-carton price would move from '.number_format($old).' to '.number_format($t).' TK per carton ('.round($move, 1).'%).', 'The batch cost changed by more than '.(int)self::JUMP.'% at once, which is often a typing mistake in a purchase.', 'Check the last purchase cost, then open Best Sellers and press Apply.');
          continue;
        }
        if (!self::write($p, $t)) { self::clear($pid); continue; }
        Procure::audit('Auto price updated', 'Product '.$p->name, ['price' => $old], ['price' => $t, 'pack_of_2' => $t * 2, 'batch_cost' => ProfitFloor::base($p), 'percent' => ProfitFloor::percent(), 'source' => $src, 'by' => self::who()]);
        self::clear($pid); $out[$pid] = [$old, $t];
      } catch (\Throwable $e) { report($e); }
    }
    if ($out) { try { Cache::forget('store.home6'); \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {} }
    return $out;
  }
  private static function clear(int $pid): void { try { DeployHealth::resolve('autoprice.jump.'.$pid, 'System (price in line)'); } catch (\Throwable $e) {} }
  /** Background check, at most once a minute. */
  public static function tick(): void { if (!self::on()) return; try { if (!Cache::add('autoprice.tick', 1, 60)) return; } catch (\Throwable $e) {} self::sync('worker'); }
  /** The single price is not managed here; a typed single price changes nothing in the offer (the pack follows the cost, and is dropped by itself if it stops being cheaper than singles). */
  public static function typed(int $pid, float $price): void {}
  /** Rows for the admin page. */
  public static function rows(): array {
    $r = []; foreach (self::ids() as $pid) { $p = DB::table('products')->find($pid); if (!$p) continue; $c = ProfitFloor::base($p);
      $r[] = (object)['id' => $pid, 'name' => $p->name, 'cost' => $c, 'target' => self::target($p), 'unit' => self::unitNow($p), 'price' => (float)$p->price, 'compare' => $p->compare_price, 'stock' => (int)$p->stock, 'custom' => self::isCustom($pid)]; }
    return $r;
  }
}
