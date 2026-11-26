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
      elseif ($stock !== null && $stock <= 0) $st = 'no_stock';
      elseif ($price - $bf < self::MIN_OFF_TK) $st = 'at_floor';
      $off = $st === 'in' ? $price - $bf : 0.0;
      $rows[] = ['id' => (int)$p->id, 'sku' => (string)$p->sku, 'name' => (string)$p->name, 'cat' => (string)($p->cat ?? 'No category'), 'smoke' => $smoke, 'stock' => $stock, 'cost' => round($cost, 2), 'price' => $price,
        'bf' => $st === 'in' ? $bf : null, 'off' => $off, 'off_pct' => $st === 'in' && $price > 0 ? round($off / $price * 100, 1) : 0.0, 'profit' => $st === 'in' ? round($bf - $cost, 2) : null,
        'status' => $st, 'check' => $st === 'in' && $price > 0 && $off / $price * 100 >= self::CHECK_OFF_PCT];
    }
    return ['rows' => $rows, 'pct' => $pct];
  }

  public static function summary(array $rows): array {
    $by = []; $tot = ['all' => count($rows), 'in' => 0, 'no_cost' => 0, 'at_floor' => 0, 'no_stock' => 0, 'twin' => 0, 'check' => 0, 'smoke_in' => 0, 'off_sum' => 0.0, 'price_sum' => 0.0];
    foreach ($rows as $r) {
      $tot[$r['status']]++; $c = $r['cat']; $by[$c] = $by[$c] ?? ['cat' => $c, 'all' => 0, 'in' => 0, 'no_cost' => 0, 'at_floor' => 0, 'no_stock' => 0, 'off_sum' => 0.0, 'price_sum' => 0.0, 'max' => 0.0, 'smoke' => $r['smoke']];
      $by[$c]['all']++; if (isset($by[$c][$r['status']])) $by[$c][$r['status']]++;
      if ($r['status'] === 'in') { $tot['off_sum'] += $r['off']; $tot['price_sum'] += $r['price']; $by[$c]['off_sum'] += $r['off']; $by[$c]['price_sum'] += $r['price']; $by[$c]['max'] = max($by[$c]['max'], $r['off_pct']); if ($r['check']) $tot['check']++; if ($r['smoke']) $tot['smoke_in']++; }
    }
    usort($by, fn($a, $b) => $b['in'] <=> $a['in']);
    return ['tot' => $tot, 'by' => $by];
  }
}
