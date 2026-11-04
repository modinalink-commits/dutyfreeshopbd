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
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where(fn($w) => $w->where('p.cost', '>', 0)->orWhere('p.avg_cost', '>', 0))->selectRaw('p.id, p.name, p.sku, coalesce(nullif(p.avg_cost,0), p.cost) as cost, p.cost as last_cost, p.price, p.stock, p.published, c.name as cat')->get() as $x) {
      $fl = \App\Support\ProfitFloor::floor((float)$x->cost); if ((float)$x->price + 0.004 >= $fl) continue;
      $rows[] = ['id' => $x->id, 'name' => $x->name, 'sku' => $x->sku ?: $x->id, 'cat' => $x->cat ?: '-', 'cost' => (float)$x->cost, 'price' => (float)$x->price, 'min' => $fl, 'short' => round($fl - (float)$x->price, 2), 'margin' => round(((float)$x->price - (float)$x->cost) / (float)$x->cost * 100, 1), 'stock' => (int)$x->stock, 'pub' => (bool)$x->published];
    }
    usort($rows, fn($a, $b) => $b['short'] <=> $a['short']);
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'SKU', 'Product', 'Category', 'Average price', 'Selling price', 'Minimum allowed price', 'Shortfall', 'Margin on cost %', 'Stock', 'Published']);
      foreach ($rows as $x) fputcsv($o, [$x['id'], $x['sku'], $x['name'], $x['cat'], $x['cost'], $x['price'], $x['min'], $x['short'], $x['margin'], $x['stock'], $x['pub'] ? 'yes' : 'no']);
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="price-review.csv"']);
    }
    return view('price_review', ['rows' => $rows]);
  }

  /** Inventory pricing table: last purchase, AVERAGE purchase price (the fixed cost base), sale price, minimum margin, profit, profit % and status for every product. Admin only. */
  public function inventory(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $q = trim((string)$r->query('q', '')); $f = (string)$r->query('status', ''); $cat = (int)$r->query('category', 0);
    $qb = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->selectRaw('p.id, p.sku, p.name, p.stock, p.price, p.published, p.cost as last_cost, coalesce(nullif(p.avg_cost,0), p.cost) as avg, c.name as cat');
    if ($cat) $qb->where('p.category_id', $cat);
    if ($q !== '') { $like = '%' . $q . '%'; $qb->where(fn($w) => \App\Support\SkuSearch::orSku($w->where('p.name', 'ilike', $like)->orWhere('p.sku', 'ilike', $like), $q, 'p.sku')); }
    $rows = []; $n = ['safe' => 0, 'near' => 0, 'below' => 0, 'none' => 0];
    foreach ($qb->orderBy('p.name')->limit(6000)->get() as $x) {
      $avg = (float)$x->avg; $pr = (float)$x->price; [$lab, $col] = \App\Support\ProfitFloor::state($avg, $pr);
      $k = $avg <= 0 ? 'none' : (str_contains($lab, 'Below') ? 'below' : (str_contains($lab, 'Near') ? 'near' : 'safe')); $n[$k]++;
      if ($f !== '' && $f !== $k) continue;
      $rows[] = ['id' => $x->id, 'sku' => $x->sku ?: $x->id, 'name' => $x->name, 'cat' => $x->cat ?: '-', 'stock' => (int)$x->stock, 'last' => (float)$x->last_cost, 'avg' => $avg, 'price' => $pr, 'min' => $avg > 0 ? \App\Support\ProfitFloor::floor($avg) : 0, 'profit' => $avg > 0 ? $pr - $avg : 0, 'pct' => $avg > 0 ? ($pr - $avg) / $avg * 100 : null, 'state' => $lab, 'col' => $col];
    }
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'SKU', 'Product', 'Category', 'Stock', 'Last Purchase Price', 'Average Purchase Price', 'Sale Price', 'Minimum Margin %', 'Minimum Safe Price', 'Profit', 'Profit %', 'Status']);
      foreach ($rows as $x) fputcsv($o, [$x['id'], $x['sku'], $x['name'], $x['cat'], $x['stock'], $x['last'], round($x['avg'], 2), $x['price'], 15, $x['min'], round($x['profit'], 2), $x['pct'] === null ? '' : round($x['pct'], 2), preg_replace('/^[^A-Za-z]+/u', '', $x['state'])]);
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="inventory-pricing.csv"']);
    }
    return view('inventory_pricing', ['rows' => $rows, 'n' => $n, 'q' => $q, 'f' => $f, 'cat' => $cat, 'cats' => DB::table('categories')->orderBy('name')->get(['id', 'name'])]);
  }
  /** Every change of one product's average purchase price (stock-in / reversal). */
  public function history(int $id) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $p = DB::table('products')->where('id', $id)->first(); abort_unless($p, 404);
    return view('avg_history', ['p' => $p, 'rows' => DB::table('avg_cost_logs')->where('product_id', $id)->orderByDesc('id')->limit(300)->get()]);
  }

  /** Every product with purchase price, selling price, profit and ROI (profit on cost), stock and the price before the one-time raise. */
  private function all(Request $r) {
    $old = []; try { foreach (DB::table('price_floor_backup')->orderBy('id')->get(['product_id', 'old_price']) as $b) if (!isset($old[$b->product_id])) $old[$b->product_id] = (float)$b->old_price; } catch (\Throwable $e) {}
    $hdr = ['Product ID', 'SKU', 'Product', 'Category', 'Average price', 'Last purchase price', 'Selling price', 'Original (crossed-out) price', 'Profit per unit', 'ROI % (profit on cost)', 'Margin % (profit on selling price)', 'Minimum allowed price', 'Stock', 'Stock cost value', 'Stock sale value', 'Published', 'Price before raise'];
    $out = [];
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->orderBy('c.name')->orderBy('p.name')->selectRaw('p.id, p.sku, p.name, coalesce(nullif(p.avg_cost,0), p.cost) as cost, p.cost as last_cost, p.price, p.compare_price, p.stock, p.published, c.name as cat')->get() as $x) {
      $c = (float)$x->cost; $pr = (float)$x->price; $st = (int)$x->stock;
      $out[] = [$x->id, $x->sku ?: $x->id, $x->name, $x->cat ?: '', round($c, 2), (float)$x->last_cost, $pr, $x->compare_price !== null ? (float)$x->compare_price : '', round($pr - $c, 2), $c > 0 ? round(($pr - $c) / $c * 100, 2) : '', $pr > 0 ? round(($pr - $c) / $pr * 100, 2) : '', $c > 0 ? \App\Support\ProfitFloor::floor($c) : '', $st, round($c * max(0, $st), 2), round($pr * max(0, $st), 2), $x->published ? 'yes' : 'no', $old[$x->id] ?? ''];
    }
    if ($r->query('export') === 'xlsx' && \App\Support\MiniXlsx::available()) return response(\App\Support\MiniXlsx::build($hdr, $out, [10, 16, 46, 22, 14, 14, 14, 16, 14, 16, 18, 16, 9, 16, 16, 11, 16], 'All products'), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="all-products-pricing.xlsx"']);
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
