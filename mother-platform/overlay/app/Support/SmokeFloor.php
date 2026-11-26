<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** Owner rule, 27 Nov 2026: no Cigarette or Vape product is ever listed below purchase cost + the protected profit (10%). Even if it does not sell. No clearance, no old price, no manual entry.
 *  Every few minutes (all categories since 27 Nov, Cigarette/Vape without any exception) any such product whose list price is under its floor is lifted to the floor. A Black Friday row follows the lift. Single Packet twins stay exempt (owner, 15 Nov). */
class SmokeFloor {
  public static function isSmoke($p): bool { return ($p->group_key ?? '') === 'smoke' || \App\Http\Controllers\StoreController::isSmokeCat((object)['slug' => $p->cslug ?? null, 'name' => $p->cat ?? null]); }
  public static function tick(): int {
    try { if (!\Illuminate\Support\Facades\Cache::add('smoke.floor', 1, 240)) return 0; } catch (\Throwable $e) { return 0; }
    $n = 0; $cols = array_merge(['p.id', 'p.name', 'p.price', 'p.compare_price', 'p.cost', 'p.group_key', 'c.name as cat', 'c.slug as cslug'], ProfitFloor::hasAvg() ? ['p.avg_cost'] : [], ProfitFloor::hasFifo() ? ['p.fifo_cost'] : [], ProfitFloor::hasTwin() ? ['p.parent_product_id'] : []);
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where('p.price', '>', 0)->get($cols) as $p) {
      try {
        if (ProfitFloor::isTwin($p)) continue; $smoke = self::isSmoke($p); $cost = ProfitFloor::base($p); if ($cost <= 0) continue;
        $need = (float)ceil(ProfitFloor::floor($cost) - 0.0001); if ((float)$p->price + 0.004 >= ProfitFloor::floor($cost)) continue;
        /* Every other product follows the same floor (owner, 27 Nov); only an approved active clearance may sit below it. */
        if (!$smoke && Clearance::ready() && DB::table('product_clearances')->where('product_id', $p->id)->where('status', 'active')->exists()) continue;
        DB::transaction(function () use ($p, $need, $cost, &$n) {
          $q = DB::table('products')->where('id', $p->id)->lockForUpdate()->first(); if (!$q || (float)$q->price + 0.004 >= $need) return;
          $upd = ['price' => $need, 'updated_at' => now()]; if ($q->compare_price !== null && (float)$q->compare_price <= $need) $upd['compare_price'] = null;
          DB::table('products')->where('id', $p->id)->update($upd);
          if (Clearance::ready()) DB::table('product_clearances')->where('product_id', $p->id)->whereIn('status', ['active', 'pending'])->update(['status' => 'ended', 'updated_at' => now()]);
          try { if (\Illuminate\Support\Facades\Schema::hasTable('black_friday_items')) { $x = DB::table('black_friday_items')->where('product_id', $p->id)->where('status', 'applied')->first();
            if ($x) { if ($need >= (float)$x->price_before) DB::table('black_friday_items')->where('id', $x->id)->update(['status' => 'restored', 'restored_at' => now(), 'note' => 'Lifted to the protected price: no saving left', 'updated_at' => now()]); else DB::table('black_friday_items')->where('id', $x->id)->update(['bf_price' => $need, 'cost' => $cost, 'note' => 'Lifted to the protected price', 'updated_at' => now()]); } } } catch (\Throwable $e) {}
          Procure::audit('Price lifted to protected profit', $p->name, ['price' => (float)$q->price], ['price' => $need, 'cost' => $cost, 'profit_pct' => ProfitFloor::percent()]); $n++;
        });
      } catch (\Throwable $e) { report($e); }
    }
    if ($n) { try { \Illuminate\Support\Facades\Cache::forget('bf.ids'); BundlePricing::flush(); \App\Http\Controllers\StoreController::bust(); BundlePricing::sync(); } catch (\Throwable $e) {} }
    return $n;
  }
}
