<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Products whose selling price is below purchase price x 1.15 (Protected Company Profit). Computed live, so a purchase-price rise shows here at once. */
class PriceReviewController extends Controller {
  public function index(Request $r) {
    if (in_array($r->query('export'), ['all', 'alltxt'], true)) return $this->all($r);
    if ($r->query('export') === 'raised') return $this->raised();
    $rows = [];
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where('p.cost', '>', 0)->get(['p.id', 'p.name', 'p.sku', 'p.cost', 'p.price', 'p.stock', 'p.published', 'c.name as cat']) as $x) {
      $fl = \App\Support\ProfitFloor::floor((float)$x->cost); if ((float)$x->price + 0.004 >= $fl) continue;
      $rows[] = ['id' => $x->id, 'name' => $x->name, 'sku' => $x->sku ?: $x->id, 'cat' => $x->cat ?: '-', 'cost' => (float)$x->cost, 'price' => (float)$x->price, 'min' => $fl, 'short' => round($fl - (float)$x->price, 2), 'margin' => round(((float)$x->price - (float)$x->cost) / (float)$x->cost * 100, 1), 'stock' => (int)$x->stock, 'pub' => (bool)$x->published];
    }
    usort($rows, fn($a, $b) => $b['short'] <=> $a['short']);
    if ($r->query('export') === 'csv') {
      $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'SKU', 'Product', 'Category', 'Purchase price', 'Selling price', 'Minimum allowed price', 'Shortfall', 'Margin on cost %', 'Stock', 'Published']);
      foreach ($rows as $x) fputcsv($o, [$x['id'], $x['sku'], $x['name'], $x['cat'], $x['cost'], $x['price'], $x['min'], $x['short'], $x['margin'], $x['stock'], $x['pub'] ? 'yes' : 'no']);
      rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="price-review.csv"']);
    }
    return view('price_review', ['rows' => $rows]);
  }

  /** Every product with purchase price, selling price, profit and ROI (profit on cost), stock and the price before the one-time raise. */
  private function all(Request $r) {
    $old = []; try { foreach (DB::table('price_floor_backup')->orderBy('id')->get(['product_id', 'old_price']) as $b) if (!isset($old[$b->product_id])) $old[$b->product_id] = (float)$b->old_price; } catch (\Throwable $e) {}
    $hdr = ['Product ID', 'SKU', 'Product', 'Category', 'Purchase price', 'Selling price', 'Original (crossed-out) price', 'Profit per unit', 'ROI % (profit on cost)', 'Margin % (profit on selling price)', 'Minimum allowed price', 'Stock', 'Stock cost value', 'Stock sale value', 'Published', 'Price before raise'];
    $out = [];
    foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->orderBy('c.name')->orderBy('p.name')->get(['p.id', 'p.sku', 'p.name', 'p.cost', 'p.price', 'p.compare_price', 'p.stock', 'p.published', 'c.name as cat']) as $x) {
      $c = (float)$x->cost; $pr = (float)$x->price; $st = (int)$x->stock;
      $out[] = [$x->id, $x->sku ?: $x->id, $x->name, $x->cat ?: '', $c, $pr, $x->compare_price !== null ? (float)$x->compare_price : '', round($pr - $c, 2), $c > 0 ? round(($pr - $c) / $c * 100, 2) : '', $pr > 0 ? round(($pr - $c) / $pr * 100, 2) : '', $c > 0 ? \App\Support\ProfitFloor::floor($c) : '', $st, round($c * max(0, $st), 2), round($pr * max(0, $st), 2), $x->published ? 'yes' : 'no', $old[$x->id] ?? ''];
    }
    if ($r->query('export') === 'alltxt') return response(implode("\n", array_map(fn($row) => implode("\t", array_map(fn($v) => str_replace(["\t", "\n"], ' ', (string)$v), $row)), array_merge([$hdr], $out))), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    $o = fopen('php://temp', 'w+'); fputcsv($o, $hdr); foreach ($out as $row) fputcsv($o, $row); rewind($o);
    return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="all-products-pricing.csv"']);
  }
  private function raised() {
    $o = fopen('php://temp', 'w+'); fputcsv($o, ['Product ID', 'Product', 'Purchase price', 'Old price', 'New price', 'Old crossed-out price', 'Old Buy 2', 'Old Buy 3', 'Old Buy 4', 'Old Buy 5', 'Raised at']);
    try { foreach (DB::table('price_floor_backup')->orderBy('id')->get() as $b) fputcsv($o, [$b->product_id, $b->name, $b->cost, $b->old_price, $b->new_price, $b->old_compare, $b->old_buy2, $b->old_buy3, $b->old_buy4, $b->old_buy5, $b->created_at]); } catch (\Throwable $e) {}
    rewind($o); return response("\xEF\xBB\xBF" . stream_get_contents($o), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="prices-raised.csv"']);
  }
}
