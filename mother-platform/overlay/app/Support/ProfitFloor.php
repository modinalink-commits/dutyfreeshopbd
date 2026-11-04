<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** Protected Company Profit: nothing (discount, coupon, membership, bundle, delivery, staff or other cost) may take a product's final price below purchase price x 1.15.
 *  One place for the rule; every price entry and every discount path asks it. Products with no purchase price on file cannot be checked and are left alone. */
class ProfitFloor {
  const RATE = 1.15;
  public static function floor(float $cost): float { return $cost > 0 ? ceil(round($cost * self::RATE * 100, 6)) / 100 : 0.0; }
  public static function ok(float $cost, float $price): bool { return $cost <= 0 || $price + 0.004 >= self::floor($cost); }
  public static function tk(float $v): string { return '৳' . number_format($v, 2); }
  public static function costs(array $ids): array { $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); return $ids ? DB::table('products')->whereIn('id', $ids)->get(['id', 'cost', 'price', 'name'])->keyBy('id')->all() : []; }

  /** Total discount an order can carry without any line dropping below its protected price. Lines with no known cost are not limited. */
  public static function room(array $items): float {
    $pc = self::costs(array_column($items, 'product_id')); $room = 0.0;
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

  /** Only Owner/Super Admin/Admin may see the 15% rule, purchase prices, minimum prices or allowed amounts. Everyone else gets a plain red notice with no numbers. */
  public static function admin(): bool { try { return in_array(auth()->user()->role ?? '', ['super', 'admin'], true); } catch (\Throwable $e) { return false; } }
  const STAFF_MSG = "⚠ এই রেট/ডিসকাউন্ট দেওয়া যাবে না — এতে আমাদের কোম্পানির লস হবে।";

  /** Lines whose price was reduced below the protected price. Only lines cut below the product's own list price count, so an old product priced under the floor can still be sold at its list price. key => detail */
  public static function lineIssues(array $items): array {
    $pc = self::costs(array_column($items, 'product_id')); $bad = [];
    foreach ($items as $k => $i) {
      $p = $pc[(int)($i['product_id'] ?? 0)] ?? null; if (!$p || (float)$p->cost <= 0) continue;
      $pr = (float)($i['price'] ?? 0); $fl = self::floor((float)$p->cost);
      if ($pr + 0.004 < $fl && $pr + 0.004 < (float)$p->price) $bad[$k] = $p->name . ' – Purchase ' . self::tk((float)$p->cost) . ', Minimum allowed ' . self::tk($fl) . ', Entered ' . self::tk($pr);
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
    if ($price + 0.004 < $fl) { $bad = true; $msg[] = "Purchase " . self::tk($cost) . " · Protected 15% " . self::tk($cost * 0.15) . " · Minimum price " . self::tk($fl) . " · Entered " . self::tk($price) . " · Difference " . self::tk($fl - $price) . "."; }
    foreach ($packs as $n => $t) if ($t !== null && (float)$t > 0 && (float)$t + 0.004 < $fl * $n) { $bad = true; $msg[] = "Buy $n pack " . self::tk((float)$t) . " is below the minimum " . self::tk($fl * $n) . "."; }
    if (!$bad) return null;
    return self::admin() ? "⚠ Minimum Company Profit Protection: এই রেট দেওয়া যাবে না, কোম্পানির লস হবে। " . implode(" ", $msg) . " Please adjust the price." : self::STAFF_MSG . " দাম বাড়িয়ে আবার চেষ্টা করুন।";
  }
  public static function audit(string $act, string $subject, $prev = null, $new = null): void { Procure::audit($act, $subject, $prev, $new); }
}
