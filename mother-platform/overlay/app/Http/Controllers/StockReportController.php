<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stock report in grid layout: one group per category with stock, cost value and sale value. */
class StockReportController extends Controller {
  public function index(Request $r) {
    $cat = (int)$r->query('category', 0); $q = trim((string)$r->query('q', '')); $show = (string)$r->query('show', 'instock');
    $pq = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id');
    if ($cat) $pq->where('p.category_id', $cat);
    if ($show === 'instock') $pq->where('p.stock', '>', 0); elseif ($show === 'out') $pq->where('p.stock', '<=', 0); elseif ($show === 'published') $pq->where('p.published', true);
    if ($q !== '') { $like = '%' . $q . '%'; $pq->where(fn($w) => \App\Support\SkuSearch::orSku($w->where('p.name', 'ilike', $like)->orWhere('p.sku', 'ilike', $like), $q, 'p.sku')); }
    $rows = $pq->orderBy('c.name')->orderBy('p.name')->limit(4000)->get(['p.id', 'p.name', 'p.sku', 'p.stock', 'p.cost', 'p.price', 'p.published', 'c.name as cat']);
    $groups = []; $g = ['n' => 0, 'qty' => 0, 'cost' => 0, 'sale' => 0];
    foreach ($rows->groupBy('cat') as $name => $list) {
      $gr = ['name' => $name ?: 'No category', 'rows' => [], 'qty' => 0, 'cost' => 0, 'sale' => 0];
      foreach ($list as $x) { $qty = (int)$x->stock; $cv = $qty * (float)$x->cost; $sv = $qty * (float)$x->price;
        $gr['rows'][] = ['id' => $x->id, 'sku' => $x->sku ?: $x->id, 'name' => $x->name, 'qty' => $qty, 'cost' => (float)$x->cost, 'cv' => $cv, 'price' => (float)$x->price, 'sv' => $sv, 'pub' => (bool)$x->published, 'nc' => (float)$x->cost == 0];
        $gr['qty'] += $qty; $gr['cost'] += $cv; $gr['sale'] += $sv; }
      $groups[] = $gr; $g['n'] += count($gr['rows']); $g['qty'] += $gr['qty']; $g['cost'] += $gr['cost']; $g['sale'] += $gr['sale'];
    }
    $cats = DB::table('categories')->orderBy('name')->get(['id', 'name']);
    $data = ['groups' => $groups, 'g' => $g, 'cat' => $cat, 'q' => $q, 'show' => $show, 'cats' => $cats, 'capped' => $rows->count() >= 4000];
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Category', 'Product ID', 'Product', 'Stock', 'Cost', 'Stock cost value', 'Selling price', 'Stock sale value']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, [$gr['name'], $x['sku'], $x['name'], $x['qty'], $x['cost'], $x['cv'], $x['price'], $x['sv']]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="stock-report.csv"']);
    }
    if ($r->query('export') === 'doc') { $html = view('stock_report', $data)->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="stock-report.doc"']); }
    return view('stock_report', $data);
  }
}
