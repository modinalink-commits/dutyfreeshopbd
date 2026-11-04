<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** Protected Company Profit (Hybrid Costing Model): nothing (discount, coupon, membership, bundle, delivery, staff or other cost) may take a product's final price below the ACTUAL batch cost x 1.15.
 *  The actual batch cost is the cost of the stock batch FIFO will consume next (products.fifo_cost); a quantity that runs over into the next batch is costed batch by batch.
 *  The weighted average (products.avg_cost) is for reporting only. With no stock batch yet the last purchase price is the expected cost. Above the 15% floor there is no upper limit.
 *  One place for the rule; every price entry and every discount path asks it. Products with no purchase price on file cannot be checked and are left alone. */
class ProfitFloor {
  const RATE = 1.15;
  public static function floor(float $cost): float { return $cost > 0 ? ceil(round($cost * self::RATE * 100, 6)) / 100 : 0.0; }
  public static function ok(float $cost, float $price): bool { return $cost <= 0 || $price + 0.004 >= self::floor($cost); }
  public static function tk(float $v): string { return '৳' . number_format($v, 2); }
  /** The cost the 15% rule is counted on: current FIFO batch cost; before batches exist the average, else the last purchase price. */
  public static function base($p): float { $f = (float)($p->fifo_cost ?? 0); if ($f > 0) return $f; $a = (float)($p->avg_cost ?? 0); return $a > 0 ? $a : (float)($p->cost ?? 0); }
  /** Weighted average cost (reporting): average of the stock on hand, else the last purchase price. */
  public static function avg($p): float { $a = (float)($p->avg_cost ?? 0); return $a > 0 ? $a : (float)($p->cost ?? 0); }
  /** Column set for product queries; 'cost' is already the average, 'last_cost' the last purchase price. */
  public static function hasAvg(): bool { static $h = null; if ($h === null) { try { $h = \Illuminate\Support\Facades\Schema::hasColumn('products', 'avg_cost'); } catch (\Throwable $e) { $h = false; } } return $h; }
  public static function hasFifo(): bool { return Costing::ready(); }
  /** Cost per product for the rule. $qty = [productId => total quantity on the order]: more than the next batch holds is costed batch by batch (weighted). */
  public static function costs(array $ids, array $qty = []): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return [];
    $rows = DB::table('products')->whereIn('id', $ids)->get(array_merge(['id', 'cost', 'price', 'name'], self::hasAvg() ? ['avg_cost'] : [], self::hasFifo() ? ['fifo_cost'] : []))->keyBy('id')->all();
    foreach ($rows as $r) {
      $r->last_cost = (float)$r->cost; $r->cost = self::base($r);
      $q = (int)($qty[(int)$r->id] ?? 1); if ($q > 1 && self::hasFifo()) { $e = Costing::expectedUnit((int)$r->id, $q); if ($e > 0) $r->cost = $e; }
    }
    return $rows;
  }
  /** Quantity per product over a list of order lines (the same product on two lines is one stock demand). */
  private static function qtyMap(array $items): array { $m = []; foreach ($items as $i) { $k = (int)($i['product_id'] ?? 0); if ($k) $m[$k] = ($m[$k] ?? 0) + max(0, (int)($i['qty'] ?? 0)); } return $m; }
  /** New average after $qty units arrive at $cost. Old stock at or below zero carries no weight (the new price starts the average). */
  public static function blend($was, int $qty, float $cost): float {
    $old = max(0, (int)($was->stock ?? 0)); $avg = self::avg($was ?? (object)[]);
    if ($qty <= 0) return $avg > 0 ? round($avg, 4) : round($cost, 4);
    if ($old <= 0 || $avg <= 0) return round($cost, 4);
    return round(($old * $avg + $qty * $cost) / ($old + $qty), 4);
  }
  /** Transaction history for every change of the average (stock-in, reversal). */
  public static function logAvg(int $pid, string $source, string $ref, int $qty, float $unit, float $old, float $new): void {
    try { if (abs($old - $new) < 0.00005 && $source === 'purchase-reversal') return; DB::table('avg_cost_logs')->insert(['product_id' => $pid, 'source' => $source, 'reference' => $ref, 'qty' => $qty, 'unit_cost' => $unit, 'old_avg' => $old, 'new_avg' => $new, 'user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? 'system', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) { report($e); }
  }
  /** Margin state for a sale price over the average: [label, colour]. Profit % = (price - avg) / avg. */
  public static function state(float $avg, float $price): array {
    if ($avg <= 0) return ['No cost on file', '#6B7280'];
    $pct = ($price - $avg) / $avg * 100;
    if ($price + 0.004 < self::floor($avg)) return ['🔴 Below Minimum', '#B91C1C'];
    return $pct < 19.995 ? ['🟠 Near Minimum', '#C2410C'] : ['🟢 Safe', '#166534'];
  }
  /** Average after a purchase of $qty units at $cost is reversed. */
  public static function unblend($was, int $qty, float $cost): float {
    $left = (int)($was->stock ?? 0) - $qty; $avg = self::avg($was ?? (object)[]);
    if ($left <= 0 || $avg <= 0) return round($avg, 4);
    $v = (($left + $qty) * $avg - $qty * $cost) / $left; return $v > 0 ? round($v, 4) : round($avg, 4);
  }

  /** Total discount an order can carry without any line dropping below its protected price. Lines with no known cost are not limited. */
  public static function room(array $items): float {
    $pc = self::costs(array_column($items, 'product_id'), self::qtyMap($items)); $room = 0.0;
    foreach ($items as $i) {
      $q = (int)($i['qty'] ?? 0); $pr = (float)($i['price'] ?? 0); if ($q <= 0 || $pr <= 0) continue;
      $c = (float)($pc[(int)($i['product_id'] ?? 0)]->cost ?? 0);
      $room += $c > 0 ? max(0, $pr - self::floor($c)) * $q : $pr * $q;
    }
    return round($room, 2);
  }

  /** Automatic discounts: reduce (or drop) the applied lines so their sum fits the room. $r is OrderDiscount::compute()'s result; $bsave is a bundle saving already taken outside it. */
  public static function enforce(array $r, array $items, float $bsave = 0.0): array {
    if (!$items || empty($r['lines'])) return $r;
    try {
      $bsIn = 0.0; foreach ($r['lines'] as $l) if (($l['key'] ?? '') === 'qprice') $bsIn = (float)$l['amount'];
      $allow = max(0, round(self::room($items) - ($bsIn > 0 ? $bsIn : $bsave), 2));
      $sum = 0.0; foreach ($r['lines'] as $l) if (!empty($l['applied']) && ($l['key'] ?? '') !== 'qprice') $sum += (float)$l['amount'];
      if ($sum <= $allow + 0.004) return $r;
      $f = $allow > 0 ? $allow / $sum : 0; $new = 0.0; $dropCoupon = false;
      foreach ($r['lines'] as &$l) {
        if (empty($l['applied']) || ($l['key'] ?? '') === 'qprice') continue;
        $a = round((float)$l['amount'] * $f, 2);
        if ($a <= 0) { $l['applied'] = false; if (($l['key'] ?? '') === 'coupon') $dropCoupon = true; continue; }
        $l['amount'] = $a; $l['pct'] = null; $l['label'] = ($l['type'] ?? 'Discount') . ' – ৳' . number_format($a); $l['floor'] = true; $new += $a;
      } unset($l);
      $r['total'] = round($new + $bsIn, 2);
      if ($dropCoupon) $r['coupon_error'] = 'This coupon cannot be applied to the items in this order';
    } catch (\Throwable $e) { report($e); }
    return $r;
  }

  /** Only full-access users (type Admin / super - the owner) may see the protected-profit rule, purchase prices, minimum prices or allowed amounts. Every staff-type user, whatever their role (e.g. Manager), gets a plain red notice with no numbers. */
  public static function admin(): bool { try { return in_array(auth()->user()->role ?? '', ['super', 'admin'], true); } catch (\Throwable $e) { return false; } }
  const STAFF_MSG = "⚠ এই রেট/ডিসকাউন্ট দেওয়া যাবে না — এতে আমাদের কোম্পানির লস হবে।";

  /** Lines whose price was reduced below the protected price. Only lines cut below the product's own list price count, so an old product priced under the floor can still be sold at its list price. key => detail */
  public static function lineIssues(array $items): array {
    $pc = self::costs(array_column($items, 'product_id'), self::qtyMap($items)); $bad = [];
    foreach ($items as $k => $i) {
      $p = $pc[(int)($i['product_id'] ?? 0)] ?? null; if (!$p || (float)$p->cost <= 0) continue;
      $pr = (float)($i['price'] ?? 0); $fl = self::floor((float)$p->cost);
      if ($pr + 0.004 < $fl && $pr + 0.004 < (float)$p->price) $bad[$k] = $p->name . ' – Batch cost (FIFO) ' . self::tk((float)$p->cost) . ', Minimum allowed ' . self::tk($fl) . ', Entered ' . self::tk($pr);
    }
    return $bad;
  }
  public static function priceError(array $items): ?string {
    $bad = self::lineIssues($items); if (!$bad) return null;
    return self::admin() ? "⚠ Minimum Company Profit Protection: এই রেট দেওয়া যাবে না, কোম্পানির লস হবে। " . implode("; ", $bad) : self::STAFF_MSG;
  }
  public static function discountError(array $items, float $disc): ?string {
    if ($disc <= 0) return null; $room = self::room($items); if ($disc <= $room + 0.004) return null;
    return self::admin() ? "⚠ Discount Not Allowed: এই discount দিলে company profit ১৫%-এর নিচে যাবে। Maximum Allowed Discount: " . self::tk($room) : self::STAFF_MSG;
  }
  public static function orderError(array $items, float $disc, bool $checkPrices = true): ?string {
    return ($checkPrices ? self::priceError($items) : null) ?: self::discountError($items, $disc);
  }

  /** Product save: single price and the Buy 2-5 pack prices. */
  public static function productError(float $cost, float $price, array $packs = []): ?string {
    if ($cost <= 0) return null; $fl = self::floor($cost); $msg = []; $bad = false;
    if ($price + 0.004 < $fl) { $bad = true; $msg[] = "Batch cost (current FIFO) " . self::tk($cost) . " · Protected profit " . self::tk($cost * 0.15) . " · Minimum price " . self::tk($fl) . " · Entered " . self::tk($price) . " · Difference " . self::tk($fl - $price) . "."; }
    foreach ($packs as $n => $t) if ($t !== null && (float)$t > 0 && (float)$t + 0.004 < $fl * $n) { $bad = true; $msg[] = "Buy $n pack " . self::tk((float)$t) . " is below the minimum " . self::tk($fl * $n) . "."; }
    if (!$bad) return null;
    return self::admin() ? "⚠ Minimum Company Profit Protection: এই রেট দেওয়া যাবে না, কোম্পানির লস হবে। " . implode(" ", $msg) . " Please adjust the price." : self::STAFF_MSG . " দাম বাড়িয়ে আবার চেষ্টা করুন।";
  }
  public static function audit(string $act, string $subject, $prev = null, $new = null): void { Procure::audit($act, $subject, $prev, $new); }
}
