<?php
namespace App\Http\Controllers;
use App\Support\Analytics as A;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller {
  /** Which filters each report understands. */
  private const F_ORDERS = ['date','store','category','product','customer','courier','status','pay'];
  private function filterSet(string $slug): array {
    return match ($slug) {
      'stock-tracking','stock-log' => ['date','category','product'],
      'category-stock' => ['category'],
      'low-stock','out-of-stock','margin' => ['category','product'] + ($slug === 'margin' ? [2=>'date'] : []),
      'purchase-report','supplier-report','payable' => ($slug === 'payable' ? ['customer'] : ['date','customer']),
      'income','expense' => ['date','customer'],
      'new-returning','repeat-rate','cancel-rate','return-rate' => ['date','store','customer'],
      'top-customers','customer-orders' => ['date','store','customer','status'],
      'courier-wise','delivery-success','courier-settlement','receivable' => ['date','store','courier'],
      'delivered-orders','returned-orders','cod-collection' => ['date','store','courier','customer'],
      'payment-collection' => ['date','store','pay'],
      'profit-loss' => ['date','store'],
      'dashboard' => ['date','store','category'],
      default => self::F_ORDERS,
    };
  }
  private function labelFor(string $slug): array {
    return ['customer' => in_array($slug, ['purchase-report','supplier-report','payable']) ? 'Supplier' : (in_array($slug, ['income','expense']) ? 'Search note / purpose' : 'Customer / mobile')];
  }

  public function index(Request $r) { return $this->page($r, 'dashboard'); }
  public function show(Request $r, string $slug) { abort_unless(A::groupOf($slug), 404); return $this->page($r, $slug); }

  private function page(Request $r, string $slug) {
    $f = A::filters($r->query());
    $set = $this->filterSet($slug);
    $d = $this->data($slug, $f);
    return view('analytics', $d + ['slug'=>$slug, 'f'=>$f, 'fset'=>$set, 'opts'=>A::options(), 'menu'=>A::menu(), 'title'=>A::title($slug), 'group'=>A::groupOf($slug), 'flabel'=>$this->labelFor($slug), 'qs'=>$r->getQueryString()]);
  }

  private function data(string $slug, array $f): array {
    if ($slug === 'dashboard') {
      $x = A::dashboard($f); $trend = A::trend($f); $stores = A::byStore($f);
      $sales = (float)$x['sales']; $cost = (float)$x['cost']; $net = $sales - $cost - (float)$x['cfee'];
      $cards = [['Total sales', $sales, 'money'], ['Total orders', (int)$x['orders'], 'num'], ['Net profit', $net, 'money'], ['Total cost', $cost, 'money'], ['Delivered', (int)$x['delivered'], 'num'], ['Returned', (int)$x['returned'], 'num'], ['Pending', (int)$x['pending'], 'num'], ['Average order value', (int)$x['orders'] ? round($sales / $x['orders']) : 0, 'money']];
      $courier = A::courierChart($f);
      $charts = [
        $this->line('Sales trend', $trend, 'sales'), $this->line('Profit trend', $trend, 'profit'), $this->line('Orders trend', $trend, 'orders'),
        $this->bars('Delivered vs returned', array_column($trend, 'date'), [['Delivered', array_column($trend, 'delivered')], ['Returned', array_column($trend, 'returned')]]),
        $this->bars('Category wise sales', array_column(A::categorySalesChart($f), 'label'), [['Sales', array_map('floatval', array_column(A::categorySalesChart($f), 'value'))]]),
        $this->bars('Store wise sales', array_column($stores, 'store'), [['Sales', array_map('floatval', array_column($stores, 'sales'))]]),
        $this->bars('Courier performance (success %)', array_column($courier, 'courier'), [['Success %', array_map('floatval', array_column($courier, 'success'))]]),
      ];
      return ['cols'=>[], 'rows'=>[], 'cards'=>$cards, 'charts'=>$charts, 'stores'=>$stores, 'truncated'=>false, 'dash'=>true,
        'summaryRows'=>array_map(fn($r) => $r, array_reverse($trend)), 'summaryCols'=>['date'=>['Date','date'],'orders'=>['Orders','num'],'sales'=>['Sales','money'],'profit'=>['Profit (after courier)','money'],'delivered'=>['Delivered','num'],'returned'=>['Returned','num']]];
    }
    $res = A::rows($slug, $f); $rows = $res['rows']; $cols = A::columns($slug);
    if ($f['view'] === 'store' && !isset($cols['store'])) $cols = $cols;
    $stores = ($f['view'] === 'compare' || count($f['stores']) > 1) ? A::byStore($f) : [];
    return ['cols'=>$cols, 'rows'=>$rows, 'cards'=>$this->cards($slug, $rows), 'charts'=>$this->charts($slug, $rows, $f, $stores), 'stores'=>$stores, 'truncated'=>$res['truncated'], 'dash'=>false, 'summaryRows'=>[], 'summaryCols'=>[]];
  }

  /* ---------- chart specs ---------- */
  private function line(string $t, array $trend, string $key): array { return ['title'=>$t, 'type'=>'line', 'labels'=>array_column($trend, 'date'), 'series'=>[['name'=>ucfirst($key), 'data'=>array_map('floatval', array_column($trend, $key))]]]; }
  private function bars(string $t, array $labels, array $series): array {
    return ['title'=>$t, 'type'=>'bar', 'labels'=>array_values($labels), 'series'=>array_map(fn($s) => ['name'=>$s[0], 'data'=>array_map('floatval', array_values($s[1]))], $series)];
  }
  private function charts(string $slug, array $rows, array $f, array $stores): array {
    $out = []; $col = fn($k) => array_column($rows, $k); $rev = fn($a) => array_reverse($a);
    $needTrend = in_array($slug, ['sales-summary','sale-profit','profit-loss','daily-sales','monthly-sales','product-sales','category-sales','customer-orders','delivered-orders','returned-orders']);
    if ($needTrend) {
      $t = A::trend($f);
      if (in_array($slug, ['delivered-orders','returned-orders'])) $out[] = $this->bars('Delivered vs returned', array_column($t, 'date'), [['Delivered', array_column($t, 'delivered')], ['Returned', array_column($t, 'returned')]]);
      elseif ($slug === 'customer-orders') $out[] = $this->line('Orders trend', $t, 'orders');
      else { $out[] = $this->line('Sales trend', $t, 'sales'); $out[] = $this->line('Profit trend', $t, 'profit'); if ($slug !== 'profit-loss') $out[] = $this->line('Orders trend', $t, 'orders'); }
    }
    switch ($slug) {
      case 'product-sales': $top = array_slice($rows, 0, 8); $out[] = $this->bars('Top products by sales', array_column($top, 'product'), [['Gross sales', array_column($top, 'gross')]]); break;
      case 'category-sales': $out[] = $this->bars('Category wise sales', $col('category'), [['Sales', $col('sales')]]); break;
      case 'category-stock': $out[] = $this->bars('Units in stock by category', $col('category'), [['Units', $col('units')]]); break;
      case 'purchase-report': case 'supplier-report': $g = []; foreach ($rows as $r) { $k = $r['supplier'] ?: 'Unknown'; $g[$k] = ($g[$k] ?? 0) + (float)$r['total']; } arsort($g); $out[] = $this->bars('Purchases by supplier', array_keys(array_slice($g, 0, 8, true)), [['Total', array_values(array_slice($g, 0, 8, true))]]); break;
      case 'new-returning': $out[] = $this->bars('New customers vs returning orders', $rev($col('date')), [['New customers', $rev($col('new_customers'))], ['Returning orders', $rev($col('returning_orders'))]]); break;
      case 'repeat-rate': $out[] = $this->bars('Repeat customer rate % by month', $rev($col('date')), [['Repeat rate %', $rev($col('rate'))]]); break;
      case 'cancel-rate': case 'return-rate': $out[] = ['title'=>($slug === 'cancel-rate' ? 'Cancel' : 'Return').' rate % by day', 'type'=>'line', 'labels'=>$rev($col('date')), 'series'=>[['name'=>'Rate %', 'data'=>array_map('floatval', $rev($col('rate')))]]]; break;
      case 'top-customers': $top = array_slice($rows, 0, 8); $out[] = $this->bars('Top customers by spend', array_column($top, 'name'), [['Spend', array_column($top, 'spend')]]); break;
      case 'courier-wise': $out[] = $this->bars('Courier performance (success %)', $col('courier'), [['Success %', $col('success')]]); $out[] = $this->bars('Delivered vs returned', $col('courier'), [['Delivered', $col('delivered')], ['Returned', $col('returned')]]); break;
      case 'delivery-success': $out[] = $this->bars('Delivery success rate %', $col('courier'), [['Success %', $col('success')]]); break;
      case 'courier-settlement': $out[] = $this->bars('Settlement by courier', $col('courier'), [['Pending', $col('pending')], ['Received', $col('received')]]); break;
      case 'receivable': $out[] = $this->bars('Receivable by courier', $col('courier'), [['Receivable', $col('receivable')]]); break;
      case 'payable': $out[] = $this->bars('Payable by supplier', $col('supplier'), [['Payable', $col('due')]]); break;
      case 'payment-collection': $out[] = $this->bars('Order value by payment method', $col('method'), [['Order value', $col('amount')], ['Collected', $col('collected')]]); break;
      case 'income': case 'expense': $g = []; foreach ($rows as $r) $g[$r['date']] = ($g[$r['date']] ?? 0) + (float)$r['amount']; ksort($g); $out[] = $this->bars(ucfirst($slug).' by day', array_keys($g), [[ucfirst($slug), array_values($g)]]); break;
    }
    if ($stores) $out[] = $this->bars('Store wise sales', array_column($stores, 'store'), [['Sales', array_column($stores, 'sales')], ['Profit', array_column($stores, 'profit')]]);
    return $out;
  }

  /* ---------- summary cards ---------- */
  private function cards(string $slug, array $rows): array {
    $sum = fn($k) => array_sum(array_map(fn($r) => (float)($r[$k] ?? 0), $rows)); $n = count($rows);
    $cnt = fn($k, $v) => count(array_filter($rows, fn($r) => ($r[$k] ?? null) === $v));
    $M = 'money'; $N = 'num'; $P = 'pct';
    $rate = fn($a, $b) => $b > 0 ? round($a / $b * 100, 1) : 0;
    return match ($slug) {
      'sales-summary' => [['Total sales', $sum('sales'), $M], ['Total orders', $n, $N], ['Total cost', $sum('cost'), $M], ['Gross profit', $sum('profit'), $M], ['Qty sold', $sum('qty'), $N], ['Average order value', $n ? round($sum('sales') / $n) : 0, $M]],
      'sale-profit' => [['Sales', $sum('sales'), $M], ['Product cost', $sum('cost'), $M], ['Courier charge', $sum('courier_fee'), $M], ['Net profit', $sum('profit'), $M], ['Margin', $rate($sum('profit'), $sum('sales')), $P], ['Orders', $n, $N]],
      'product-sales' => [['Qty sold', $sum('qty'), $N], ['Gross sales', $sum('gross'), $M], ['Discount', $sum('discount'), $M], ['Cost', $sum('cost'), $M], ['Profit', $sum('profit'), $M], ['Margin', $rate($sum('profit'), $sum('gross')), $P]],
      'category-sales' => [['Total sales', $sum('sales'), $M], ['Qty sold', $sum('qty'), $N], ['Profit', $sum('profit'), $M], ['Categories', $n, $N]],
      'daily-sales','monthly-sales' => [['Total sales', $sum('sales'), $M], ['Total orders', $sum('orders'), $N], ['Total cost', $sum('cost'), $M], ['Profit', $sum('profit'), $M], ['Average order value', $sum('orders') ? round($sum('sales') / $sum('orders')) : 0, $M]],
      'profit-loss' => (function () use ($rows) { $g = fn($l) => (float)(collect($rows)->firstWhere('line', $l)['amount'] ?? 0); $ns = $g('Net sales'); return [['Net sales', $ns, 'money'], ['Product cost', abs($g('Product cost')), 'money'], ['Gross profit', $g('Gross profit'), 'money'], ['Net profit', $g('Net profit'), 'money'], ['Net margin', $ns > 0 ? round($g('Net profit') / $ns * 100, 1) : 0, 'pct']]; })(),
      'margin' => [['Products', $n, $N], ['Average margin', $n ? round($sum('margin') / $n, 1) : 0, $P], ['Below 10% margin', count(array_filter($rows, fn($r) => (float)$r['margin'] < 10)), $N], ['Negative margin', count(array_filter($rows, fn($r) => (float)$r['margin'] < 0)), $N]],
      'stock-tracking' => [['Products', $n, $N], ['Units in stock', $sum('current'), $N], ['Stock in', $sum('stock_in'), $N], ['Stock out', $sum('stock_out'), $N], ['Sold', $sum('sold'), $N], ['Returned', $sum('returned'), $N]],
      'category-stock' => [['Units in stock', $sum('units'), $N], ['Stock value (cost)', $sum('cost_value'), $M], ['Stock value (sale)', $sum('sale_value'), $M], ['Out of stock', $sum('out'), $N]],
      'stock-log' => [['Entries', $n, $N], ['Stock in', array_sum(array_filter(array_map(fn($r) => (float)$r['change'], $rows), fn($v) => $v > 0)), $N], ['Stock out', abs(array_sum(array_filter(array_map(fn($r) => (float)$r['change'], $rows), fn($v) => $v < 0))), $N], ['Net change', $sum('change'), $N]],
      'low-stock','out-of-stock' => [['Products', $n, $N], ['Units left', $sum('stock'), $N], ['Cost to restock (1 unit each)', $sum('cost'), $M]],
      'purchase-report' => [['Purchases', $n, $N], ['Total', $sum('total'), $M], ['Paid', $sum('paid'), $M], ['Due', $sum('due'), $M]],
      'supplier-report' => [['Suppliers', $n, $N], ['Total purchased', $sum('total'), $M], ['Paid', $sum('paid'), $M], ['Due', $sum('due'), $M]],
      'new-returning' => [['New customers', $sum('new_customers'), $N], ['Returning orders', $sum('returning_orders'), $N], ['Orders', $sum('orders'), $N], ['New share', $rate($sum('new_customers'), $sum('orders')), $P]],
      'repeat-rate' => [['Customers (all months)', $sum('customers'), $N], ['Repeat customers', $sum('repeat'), $N], ['Repeat rate', $rate($sum('repeat'), $sum('customers')), $P]],
      'top-customers' => [['Customers', $n, $N], ['VIP', $cnt('category', 'VIP'), $N], ['High risk', $cnt('risk', 'High'), $N], ['Total spend', $sum('spend'), $M]],
      'customer-orders' => [['Orders', $n, $N], ['Order value', $sum('total'), $M], ['Delivered', count(array_filter($rows, fn($r) => in_array($r['status'], ['Delivered','Partial Delivered','Late Delivery']))), $N], ['Returned', count(array_filter($rows, fn($r) => in_array($r['status'], ['Returned','Return Pending']))), $N], ['Cancelled', $cnt('status', 'Cancel'), $N]],
      'cancel-rate' => [['Orders', $sum('orders'), $N], ['Cancelled', $sum('cancelled'), $N], ['Cancel rate', $rate($sum('cancelled'), $sum('orders')), $P]],
      'return-rate' => [['Orders', $sum('orders'), $N], ['Returned', $sum('returned'), $N], ['Return rate', $rate($sum('returned'), $sum('orders')), $P]],
      'courier-wise' => [['Total parcels', $sum('parcels'), $N], ['Delivered', $sum('delivered'), $N], ['Returned', $sum('returned'), $N], ['Pending', $sum('pending'), $N], ['Success rate', $rate($sum('delivered'), $sum('delivered') + $sum('returned')), $P], ['COD collected', $sum('cod'), $M]],
      'delivered-orders','returned-orders' => [['Orders', $n, $N], ['Order value', $sum('total'), $M]],
      'delivery-success' => [['Delivered', $sum('delivered'), $N], ['Returned', $sum('returned'), $N], ['Success rate', $rate($sum('delivered'), $sum('done')), $P]],
      'cod-collection' => [['Orders', $n, $N], ['COD amount', $sum('expected'), $M], ['Collected', $sum('collected'), $M], ['Courier charge', $sum('fee'), $M], ['Net payable', $sum('net'), $M]],
      'courier-settlement' => [['Delivered parcels', $sum('parcels'), $N], ['COD collected', $sum('collected'), $M], ['Settlement pending', $sum('pending'), $M], ['Settlement received', $sum('received'), $M]],
      'income','expense' => [['Entries', $n, $N], ['Total '.$slug, $sum('amount'), $M]],
      'receivable' => [['Couriers', $n, $N], ['Delivered parcels', $sum('parcels'), $N], ['Total receivable', $sum('receivable'), $M]],
      'payable' => [['Suppliers', $n, $N], ['Open invoices', $sum('invoices'), $N], ['Total payable', $sum('due'), $M]],
      'payment-collection' => [['Orders', $sum('orders'), $N], ['Order value', $sum('amount'), $M], ['Collected', $sum('collected'), $M], ['Verification pending', $sum('pending'), $N]],
      default => [],
    };
  }

  /* ---------- export ---------- */
  public function export(Request $r, string $slug, string $fmt) {
    abort_unless(A::groupOf($slug) && $slug !== 'dashboard' && in_array($fmt, ['csv','xls','print']), 404);
    $f = A::filters($r->query()); $res = A::rows($slug, $f); $cols = A::columns($slug); $rows = $res['rows'];
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'action'=>'export', 'subject'=>'report-'.$slug.'.'.$fmt, 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    $name = 'report-'.$slug.'-'.$f['from'].'-to-'.$f['to'];
    $cells = fn($row) => array_map(fn($k) => in_array($cols[$k][1], ['img']) ? '' : (string)($row[$k] ?? ''), array_keys($cols));
    $keys = array_values(array_filter(array_keys($cols), fn($k) => $cols[$k][1] !== 'img'));
    $head = array_map(fn($k) => $cols[$k][0], $keys); $body = array_map(fn($row) => array_map(fn($k) => (string)($row[$k] ?? ''), $keys), $rows);
    if ($fmt === 'csv') {
      $out = fopen('php://temp', 'r+'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, $head); foreach ($body as $b) fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', $v) && !is_numeric($v) ? "'".$v : $v, $b)); rewind($out);
      return response(stream_get_contents($out), 200, ['Content-Type'=>'text/csv; charset=utf-8', 'Content-Disposition'=>'attachment; filename="'.$name.'.csv"']);
    }
    if ($fmt === 'xls') {
      $h = '<html><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>'.implode('', array_map(fn($c) => '<th>'.e($c).'</th>', $head)).'</tr></thead><tbody>';
      foreach ($body as $b) $h .= '<tr>'.implode('', array_map(fn($v) => '<td>'.e($v).'</td>', $b)).'</tr>';
      return response($h.'</tbody></table></body></html>', 200, ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8', 'Content-Disposition'=>'attachment; filename="'.$name.'.xls"']);
    }
    return view('analytics_print', ['title'=>A::title($slug), 'f'=>$f, 'head'=>$head, 'body'=>$body, 'cards'=>$this->cards($slug, $rows), 'truncated'=>$res['truncated'], 'auto'=>true]);
  }
}
