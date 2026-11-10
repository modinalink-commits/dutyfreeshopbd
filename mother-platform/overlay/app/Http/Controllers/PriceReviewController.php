<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Products whose selling price is below purchase price x 1.15 (Protected Company Profit). Computed live, so a purchase-price rise shows here at once. */
class PriceReviewController extends Controller {
  public function index(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    if (in_array($r->query('export'), ['all', 'alltxt', 'xlsx'], true)) return $this->all($r);
    if ($r->query('export') === 'raised') return $this->raised();
    $rows = [];
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where(fn($w) => $w->where('p.cost', '>', 0)->orWhere('p.avg_cost', '>', 0))->selectRaw('p.id, p.name, p.sku, '.\App\Support\Costing::fifoSql('p').' as cost, p.cost as last_cost, p.price, p.stock, p.published, c.name as cat')->get() as $x) {
      $fl = \App\Support\ProfitFloor::floor((float)$x->cost); if ((float)$x->price + 0.004 >= $fl) continue;
      $rows[] = ['id' => $x->id, 'name' => $x->name, 'sku' => $x->sku ?: $x->id, 'cat' => $x->cat ?: '-', 'cost' => (float)$x->cost, 'price' => (float)$x->price, 'min' => $fl, 'short' => round($fl - (float)$x->price, 2), 'margin' => round(((float)$x->price - (float)$x->cost) / (float)$x->cost * 100, 1), 'stock' => (int)$x->stock, 'pub' => (bool)$x->published];
    }
    usort($rows, fn($a, $b) => $b['short'] <=> $a['short']);
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'SKU', 'Product', 'Category', 'Current FIFO batch cost', 'Selling price', 'Minimum allowed price', 'Shortfall', 'Margin on cost %', 'Stock', 'Published']);
      foreach ($rows as $x) fputcsv($o, [$x['id'], $x['sku'], $x['name'], $x['cat'], $x['cost'], $x['price'], $x['min'], $x['short'], $x['margin'], $x['stock'], $x['pub'] ? 'yes' : 'no']);
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="price-review.csv"']);
    }
    return view('price_review', ['rows' => $rows]);
  }

  /** Inventory pricing (Hybrid Costing): last purchase price (reference), weighted average cost (reporting), current FIFO batch cost (the real cost the 15% rule uses), sale price, minimum safe sale, profit and status. Admin, and roles given the costs permission (view only). */
  public function inventory(Request $r) {
    abort_unless(\App\Support\Costing::canSee(), 403);
    abort_if($r->query('export') && !\App\Support\ProfitFloor::admin(), 403, 'Download of cost data is for the Admin only.');
    $q = trim((string)$r->query('q', '')); $f = (string)$r->query('status', ''); $cat = (int)$r->query('category', 0);
    $qb = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->selectRaw('p.id, p.sku, p.name, p.stock, p.price, p.published, p.cost as last_cost, '.\App\Support\Costing::avgSql('p').' as avg, '.\App\Support\Costing::fifoSql('p').' as fifo, '.\App\Support\Costing::fifoRawSql('p').' as fifo_raw, p.buy2_price, p.buy3_price, p.buy4_price, p.buy5_price, c.name as cat');
    if ($cat) $qb->where('p.category_id', $cat);
    if ($q !== '') { $like = '%' . $q . '%'; $qb->where(fn($w) => \App\Support\SkuSearch::orSku($w->where('p.name', 'ilike', $like)->orWhere('p.sku', 'ilike', $like), $q, 'p.sku')); }
    $rows = []; $n = ['safe' => 0, 'near' => 0, 'below' => 0, 'none' => 0, 'none_sold' => 0]; $sold = []; $soldAmt = 0.0;
    try { foreach (DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereRaw(\App\Support\Analytics::VALID)->whereNotNull('i.product_id')->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty * i.price) as amt')->get() as $sx) $sold[(int)$sx->pid] = (float)$sx->amt; } catch (\Throwable $e) {}
    foreach ($qb->orderBy('p.name')->limit(6000)->get() as $x) {
      $cost = (float)$x->fifo; $pr = (float)$x->price; $worst = $pr; $bl = '—'; $bc = '#6B7280';
      foreach ([2, 3, 4, 5] as $kk) { $bp = (float)($x->{"buy{$kk}_price"} ?? 0); if ($bp > 0) $worst = min($worst, $bp / $kk); }
      if ($cost > 0 && $worst < $pr - 0.004) { [$bl, $bc] = \App\Support\ProfitFloor::state($cost, $worst); } elseif ($cost > 0) { $bl = 'No bundle'; }
      [$lab, $col] = \App\Support\ProfitFloor::state($cost, $pr); // overall status = the single sale price on the current FIFO batch cost (bundle tiers may sit on the 15% line by design; a bundle BELOW it still turns the status red)
      if ($cost > 0 && $worst < $pr - 0.004 && str_contains($bl, 'Below')) { $lab = $bl; $col = $bc; }
      $k = $cost <= 0 ? 'none' : (str_contains($lab, 'Below') ? 'below' : (str_contains($lab, 'Near') ? 'near' : 'safe')); $n[$k]++; $isNs = $k === 'none' && isset($sold[(int)$x->id]); if ($isNs) { $n['none_sold']++; $soldAmt += $sold[(int)$x->id]; }
      if ($f === 'none_sold') { if (!$isNs) continue; } elseif ($f !== '' && $f !== $k) continue;
      $rows[] = ['id' => $x->id, 'sku' => $x->sku ?: $x->id, 'name' => $x->name, 'cat' => $x->cat ?: '-', 'stock' => (int)$x->stock, 'last' => (float)$x->last_cost, 'avg' => (float)$x->avg, 'fifo' => $cost, 'fifo_raw' => (float)$x->fifo_raw, 'price' => $pr, 'min' => $cost > 0 ? \App\Support\ProfitFloor::floor($cost) : 0, 'disc' => $cost > 0 ? max(0, $pr - \App\Support\ProfitFloor::floor($cost)) : 0, 'maxd' => $cost > 0 && $pr > 0 ? max(0, ($pr - \App\Support\ProfitFloor::floor($cost)) / $pr * 100) : null, 'profit' => $cost > 0 ? $pr - $cost : 0, 'pct' => $cost > 0 ? ($pr - $cost) / $cost * 100 : null, 'state' => $lab, 'col' => $col, 'bundle' => $bl, 'bcol' => $bc];
    }
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'SKU', 'Product', 'Category', 'Stock', 'Last Purchase Price', 'Average Cost', 'Current FIFO Cost', 'Sale Price', 'Minimum Margin %', 'Minimum Safe Sale', 'Estimated Profit', 'Profit %', 'Discountable Amount', 'Max Safe Discount %', 'Status']);
      foreach ($rows as $x) fputcsv($o, [$x['id'], $x['sku'], $x['name'], $x['cat'], $x['stock'], $x['last'], round($x['avg'], 2), round($x['fifo'], 2), $x['price'], \App\Support\ProfitFloor::percent(), $x['min'], round($x['profit'], 2), $x['pct'] === null ? '' : round($x['pct'], 2), round($x['disc'], 2), $x['maxd'] === null ? '' : round($x['maxd'], 2), preg_replace('/^[^A-Za-z]+/u', '', $x['state'])]);
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="inventory-pricing.csv"']);
    }
    $total = count($rows); $per = 200; $page = max(1, min((int)ceil(max(1, $total) / $per), (int)$r->query('page', 1))); $rows = array_slice($rows, ($page - 1) * $per, $per); // 6000 rows at once froze the browser
    return view('inventory_pricing', ['total' => $total, 'page' => $page, 'per' => $per, 'rows' => $rows, 'n' => $n, 'soldAmt' => $soldAmt, 'q' => $q, 'f' => $f, 'cat' => $cat, 'cats' => DB::table('categories')->orderBy('name')->get(['id', 'name']), 'mode' => \App\Support\Costing::shipMode(), 'adm' => \App\Support\ProfitFloor::admin(), 'batches' => \App\Support\Costing::ready()]);
  }
  /** Admin: how shipment treats an order that would sell below actual batch cost x 1.15. */
  public function shipMode(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $m = (string)$r->input('mode'); abort_unless(in_array($m, ['block', 'warn', 'off'], true), 422);
    $old = \App\Support\Costing::shipMode(); \App\Support\Procure::put('costing_ship_profit', $m);
    if ($old !== $m) \App\Support\ProfitFloor::audit('Shipment profit protection changed', 'Shipment profit protection', ['mode' => $old], ['mode' => $m, 'by' => auth()->user()->name ?? null]);
    return redirect('/admin/inventory-pricing')->with('ok', 'Shipment profit protection: ' . ['block' => 'Block (below ' . \App\Support\ProfitFloor::pct() . '% cannot ship)', 'warn' => 'Warn only (ships, but logged)', 'off' => 'Off'][$m]);
  }
  /** Admin: the ONE central Minimum Protected Profit % (Sales & Profit Settings). Takes effect for new orders at once; older orders keep the % that was active when they were placed. */
  /** Admin: raise sale prices of ONE category to a target profit % (on FIFO cost, same as Inventory Pricing "Profit %"). Only raises, rounds up to $step. Preview first, apply with POST. Old price goes to product_price_history. */
  public function categoryMargin(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $cat = (int)$r->input('category', 0); $pct = max(1.0, min(200.0, (float)$r->input('percent', 25))); $step = max(1, (int)$r->input('step', 5)); $apply = $r->isMethod('post');
    $plan = []; $name = '';
    if ($cat) {
      $name = (string)DB::table('categories')->where('id', $cat)->value('name');
      $rows = DB::table('products as p')->where('p.category_id', $cat)->selectRaw('p.id, p.name, p.price, p.compare_price, '.\App\Support\Costing::fifoSql('p').' as fifo')->orderBy('p.name')->get();
      foreach ($rows as $x) { $c = (float)$x->fifo; $o = (float)$x->price; if ($c <= 0 || $o <= 0) continue; $np = ceil(round($c * (1 + $pct / 100) / $step, 6)) * $step; if ($np > $o + 0.004) $plan[] = ['id' => (int)$x->id, 'name' => $x->name, 'cost' => $c, 'old' => $o, 'new' => (float)$np, 'cmp' => $x->compare_price]; }
    }
    if ($apply && $plan) {
      $by = auth()->user()->name ?? 'admin';
      DB::transaction(function () use ($plan, $by, $pct, $name) {
        foreach ($plan as $x) {
          $up = ['price' => $x['new'], 'updated_at' => now()]; if ($x['cmp'] !== null && (float)$x['cmp'] <= $x['new']) $up['compare_price'] = null;
          DB::table('products')->where('id', $x['id'])->update($up);
          DB::table('product_price_history')->insert(['product_id' => $x['id'], 'old_rate' => $x['old'], 'new_rate' => $x['new'], 'purchase_rate' => $x['cost'], 'changed_by' => $by, 'source' => 'Category margin update', 'note' => $name . ' to ' . $pct . '% profit', 'created_at' => now(), 'updated_at' => now()]);
        }
      });
      \App\Support\ProfitFloor::audit('price.category_margin', 'Category ' . $name . ': ' . count($plan) . ' prices raised to ' . $pct . '% profit', null, ['by' => $by, 'count' => count($plan), 'sample' => array_slice(array_map(fn($x) => [$x['id'], $x['old'], $x['new']], $plan), 0, 5)]);
      try { \App\Support\BundlePricing::flush(); \App\Support\BundlePricing::sync(array_column($plan, 'id')); } catch (\Throwable $e) { report($e); }
      \App\Http\Controllers\StoreController::bust();
      return redirect('/admin/category-margin?category=' . $cat . '&percent=' . $pct . '&step=' . $step)->with('ok', count($plan) . ' prices in ' . $name . ' raised to at least ' . $pct . '% profit.');
    }
    return view('category_margin', ['cats' => DB::table('categories')->orderBy('name')->get(['id', 'name']), 'cat' => $cat, 'pct' => $pct, 'step' => $step, 'plan' => $plan, 'name' => $name]);
  }
  public function minMargin(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $d = $r->validate(['percent' => 'required|numeric|min:1|max:60', 'reason' => 'required|string|min:3|max:300']);
    $new = round((float)$d['percent'], 2); $old = \App\Support\ProfitFloor::percent();
    if (abs($old - $new) < 0.0001) return redirect('/admin/inventory-pricing')->with('ok', 'Nothing changed: Minimum Protected Profit is already ' . \App\Support\ProfitFloor::pct() . '%.');
    \App\Support\ProfitFloor::setPercent($new, $d['reason']);
    \App\Support\BundlePricing::flush(); try { [$ch, $cap] = \App\Support\BundlePricing::sync(); } catch (\Throwable $e) { report($e); $ch = 0; $cap = 0; }
    return redirect('/admin/inventory-pricing')->with('ok', 'Minimum Protected Profit changed from ' . rtrim(rtrim(number_format($old, 2, '.', ''), '0'), '.') . '% to ' . \App\Support\ProfitFloor::pct() . '%. New orders use it now; older orders keep their original rule. Bundle prices recalculated for ' . $ch . ' products.');
  }
  /** View Batches: every stock batch of one product (FIFO order), what is left of each, and the shipments that consumed them. */
  public function batches(int $id) {
    abort_unless(\App\Support\Costing::canSee(), 403);
    $p = DB::table('products')->where('id', $id)->first(); abort_unless($p, 404);
    $rows = \App\Support\Costing::ready() ? \App\Support\Costing::batches($id) : collect();
    $used = \App\Support\Costing::ready() ? DB::table('order_item_batches as a')->leftJoin('orders as o', 'o.id', '=', 'a.order_id')->where('a.product_id', $id)->orderByDesc('a.id')->limit(100)->get(['a.*', 'o.invoice', 'o.status']) : collect();
    return view('inventory_batches', ['p' => $p, 'rows' => $rows, 'used' => $used]);
  }
  /** Every change of one product's average purchase price (stock-in / reversal). */
  public function history(int $id) {
    abort_unless(\App\Support\Costing::canSee(), 403);
    $p = DB::table('products')->where('id', $id)->first(); abort_unless($p, 404);
    return view('avg_history', ['p' => $p, 'rows' => DB::table('avg_cost_logs')->where('product_id', $id)->orderByDesc('id')->limit(300)->get()]);
  }

  /** Every product with last purchase price, average cost, current FIFO cost, selling price, profit and ROI (profit on batch cost), stock and the price before the one-time raise. */
  private function all(Request $r) {
    $old = []; try { foreach (DB::table('price_floor_backup')->orderBy('id')->get(['product_id', 'old_price']) as $b) if (!isset($old[$b->product_id])) $old[$b->product_id] = (float)$b->old_price; } catch (\Throwable $e) {}
    $hdr = ['Product ID', 'SKU', 'Product', 'Category', 'Current FIFO cost', 'Average cost', 'Last purchase price', 'Selling price', 'Original (crossed-out) price', 'Profit per unit', 'ROI % (profit on cost)', 'Margin % (profit on selling price)', 'Minimum allowed price', 'Stock', 'Stock cost value (average)', 'Stock sale value', 'Published', 'Price before raise'];
    $out = [];
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->orderBy('c.name')->orderBy('p.name')->selectRaw('p.id, p.sku, p.name, ' . \App\Support\Costing::fifoSql('p') . ' as cost, ' . \App\Support\Costing::avgSql('p') . ' as avgc, p.cost as last_cost, p.price, p.compare_price, p.stock, p.published, c.name as cat')->get() as $x) {
      $c = (float)$x->cost; $pr = (float)$x->price; $st = (int)$x->stock; $av = (float)$x->avgc;
      $out[] = [$x->id, $x->sku ?: $x->id, $x->name, $x->cat ?: '', round($c, 2), round($av, 2), (float)$x->last_cost, $pr, $x->compare_price !== null ? (float)$x->compare_price : '', round($pr - $c, 2), $c > 0 ? round(($pr - $c) / $c * 100, 2) : '', $pr > 0 ? round(($pr - $c) / $pr * 100, 2) : '', $c > 0 ? \App\Support\ProfitFloor::floor($c) : '', $st, round($av * max(0, $st), 2), round($pr * max(0, $st), 2), $x->published ? 'yes' : 'no', $old[$x->id] ?? ''];
    }
    if ($r->query('export') === 'xlsx' && \App\Support\MiniXlsx::available()) return response(\App\Support\MiniXlsx::build($hdr, $out, [10, 16, 46, 22, 14, 14, 14, 14, 16, 14, 16, 18, 16, 9, 18, 16, 11, 16], 'All products'), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="all-products-pricing.xlsx"']);
    if ($r->query('export') === 'alltxt') return response(implode("\n", array_map(fn($row) => implode("\t", array_map(fn($v) => str_replace(["\t", "\n"], ' ', (string)$v), $row)), array_merge([$hdr], $out))), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    $o = fopen('php://temp', 'w+'); fputcsv($o, $hdr); foreach ($out as $row) fputcsv($o, $row); rewind($o);
    return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="all-products-pricing.csv"']);
  }
  private function raised() {
    $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'Product', 'Purchase price', 'Old price', 'New price', 'Old crossed-out price', 'Old Buy 2', 'Old Buy 3', 'Old Buy 4', 'Old Buy 5', 'Raised at']);
    try { foreach (DB::table('price_floor_backup')->orderBy('id')->get() as $b) fputcsv($o, [$b->product_id, $b->name, $b->cost, $b->old_price, $b->new_price, $b->old_compare, $b->old_buy2, $b->old_buy3, $b->old_buy4, $b->old_buy5, $b->created_at]); } catch (\Throwable $e) {}
    rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="prices-raised.csv"']);
  }

  /** Live check while typing a product price (Product form). Staff get a plain notice, admins the details. */
  public function checkProduct(Request $r) {
    $p = DB::table('products')->where('id', (int)$r->query('id'))->first();
    $packs = []; foreach ([2, 3, 4, 5] as $n) $packs[$n] = $r->query("b$n") !== null && $r->query("b$n") !== '' ? (float)$r->query("b$n") : null;
    return response()->json(['warn' => $p ? \App\Support\ProfitFloor::productError(\App\Support\ProfitFloor::base($p), (float)$r->query('price'), $packs) : null]);
  }
  /** Live check on Create Order: per-line price cuts and the total discount. items = idx:productId:price:qty,... */
  public function checkOrder(Request $r) {
    $items = []; foreach (explode(',', (string)$r->query('items', '')) as $t) { $a = explode(':', $t); if (count($a) === 4) $items[(int)$a[0]] = ['product_id' => (int)$a[1], 'price' => (float)$a[2], 'qty' => (int)$a[3]]; }
    $adm = \App\Support\ProfitFloor::admin(); $lines = [];
    foreach (\App\Support\ProfitFloor::lineIssues($items) as $k => $d) $lines[$k] = $adm ? "⚠ এই রেট দেওয়া যাবে না — কোম্পানির লস হবে। " . $d : \App\Support\ProfitFloor::STAFF_MSG;
    $disc = \App\Support\ProfitFloor::discountError($items, (float)$r->query('disc', 0));
    return response()->json(['lines' => (object)$lines, 'disc' => $disc]);
  }
}
