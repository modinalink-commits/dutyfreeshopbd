<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Reports & Analytics catalogue. Every report is defined once here (title, columns, SQL, cards, chart);
 * the controller and the page are generic. All SQL is plain PostgreSQL with bound parameters.
 * Money columns are product revenue (items minus discount); shipping charged is shown only in the P&L.
 */
class Analytics {
  /** Escape LIKE wildcards so a typed % or _ matches literally. */
  private static function esc(string $v): string { return addcslashes($v, '%_\\'); }
  const DONE = "('Delivered','Partial Delivered','Late Delivery')";
  const RET  = "('Returned','Return Pending')";
  const LIMIT = 5000;
  /** The ONE definition of a valid (counted) order: not cancelled/returned, except partial returns. Dashboard sales, cost, profit, orders and the store/category/day splits all use it. */
  const VALID = "(o.status not in ('Cancel','Returned','Return Pending') or coalesce(o.return_type,'') = 'Partial')";

  /** group => [label, [slug => title]] */
  public static function menu(): array {
    return [
      'overview'  => ['Overview', ['dashboard'=>'Analytics Dashboard']],
      'sales'     => ['Sales Reports', ['sales-summary'=>'Sales Summary','product-sales'=>'Product Wise Sales Report','category-sales'=>'Category Wise Sales Report','daily-sales'=>'Daily Sales Report','monthly-sales'=>'Monthly Sales Report','discount-report'=>'Discount Report (by type)']],
      'profit'    => ['Profit & Loss', ['profit-loss'=>'Profit / Loss Report','sale-profit'=>'Sale Profit Report','margin'=>'Margin Report']],
      'inventory' => ['Inventory Reports', ['stock-tracking'=>'Product Stock Tracking','category-stock'=>'Category Wise Stock Report','stock-log'=>'Stock Log History','low-stock'=>'Low Stock Report','out-of-stock'=>'Out of Stock Report']],
      'purchase'  => ['Purchase & Supplier', ['purchase-report'=>'Purchase Report','supplier-report'=>'Supplier Report']],
      'customer'  => ['Customer Reports', ['new-returning'=>'New vs Returning Customer','repeat-rate'=>'Repeat Customer Rate','top-customers'=>'Top Customers','top-viewed'=>'Top Viewed Products','customer-orders'=>'Customer Order History','cancel-rate'=>'Cancel Rate','return-rate'=>'Return Rate']],
      'courier'   => ['Courier & Delivery', ['courier-wise'=>'Courier Wise Delivery Report','delivered-orders'=>'Delivered Orders','returned-orders'=>'Returned Orders','delivery-success'=>'Delivery Success Rate','cod-collection'=>'COD Collection','courier-settlement'=>'Courier Settlement Report']],
      'accounts'  => ['Accounts', ['income'=>'Income','expense'=>'Expense','receivable'=>'Receivable','payable'=>'Payable','payment-collection'=>'Payment Collection']],
    ];
  }
  public static function groupOf(string $slug): ?string { foreach (self::menu() as $g => [$l, $items]) if (isset($items[$slug])) return $g; return null; }
  public static function title(string $slug): string { foreach (self::menu() as [$l, $items]) if (isset($items[$slug])) return $items[$slug]; return 'Report'; }

  /* ---------- filters ---------- */
  public static function filters(array $in): array {
    $tz = new \DateTimeZone('Asia/Dhaka');
    $d = fn($k, $def) => (isset($in[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$in[$k])) ? $in[$k] : $def;
    $today = (new \DateTime('now', $tz))->format('Y-m-d');
    $from = $d('from', (new \DateTime('now', $tz))->modify('-29 days')->format('Y-m-d')); $to = $d('to', $today);
    if ($from > $to) [$from, $to] = [$to, $from];
    $stores = array_values(array_unique(array_filter(array_map('intval', (array)($in['stores'] ?? [])))));
    $s = fn($k) => trim((string)($in[$k] ?? ''));
    $view = in_array($in['view'] ?? '', ['store','compare'], true) ? $in['view'] : 'combined';
    return ['from'=>$from, 'to'=>$to, 'stores'=>$stores, 'category'=>(int)($in['category'] ?? 0), 'product'=>mb_substr($s('product'), 0, 80), 'customer'=>mb_substr($s('customer'), 0, 80),
      'courier'=>mb_substr($s('courier'), 0, 40), 'source'=>mb_substr($s('source'), 0, 40), 'status'=>mb_substr($s('status'), 0, 40), 'pay'=>mb_substr($s('pay'), 0, 40), 'device'=>in_array($s('device'), ['mobile','desktop','tablet'], true) ? $s('device') : '', 'vsource'=>mb_substr($s('vsource'), 0, 20), 'view'=>$view];
  }
  private static function utc(string $date, bool $end): string {
    $d = new \DateTime($date.' 00:00:00', new \DateTimeZone('Asia/Dhaka')); if ($end) $d->modify('+1 day');
    return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }
  /** WHERE for order-based reports. basis: valid | nocancel | all. item: also filter item rows directly (alias i, p). */
  private static function w(array $f, string $basis = 'valid', bool $item = false): array {
    $c = ['o.created_at >= ?', 'o.created_at < ?']; $b = [self::utc($f['from'], false), self::utc($f['to'], true)];
    if ($f['stores']) $c[] = 'o.store_id in ('.implode(',', array_map('intval', $f['stores'])).')';
    if ($f['courier'] !== '') { if ($f['courier'] === 'Unassigned') $c[] = "coalesce(o.courier,'') = ''"; else { $c[] = 'o.courier = ?'; $b[] = $f['courier']; } }
    if (($f['source'] ?? '') !== '') { $c[] = 'o.source = ?'; $b[] = $f['source']; }
    if ($f['status'] !== '') { $c[] = 'o.status = ?'; $b[] = $f['status']; }
    elseif ($basis === 'valid') $c[] = "(o.status not in ('Cancel','Returned','Return Pending') or o.return_type = 'Partial')";
    elseif ($basis === 'nocancel') $c[] = "o.status <> 'Cancel'";
    if ($f['pay'] !== '') { $c[] = "coalesce(o.pay_status,'') = ?"; $b[] = $f['pay'] === 'None' ? '' : $f['pay']; }
    if ($f['customer'] !== '') { $c[] = '(o.phone ilike ? or o.customer_name ilike ?)'; $b[] = '%'.self::esc((string)$f['customer']).'%'; $b[] = '%'.self::esc((string)$f['customer']).'%'; }
    if ($f['category'] || $f['product'] !== '') {
      $x = []; $xb = [];
      if ($f['category']) { $x[] = 'p.category_id = ?'; $xb[] = $f['category']; }
      if ($f['product'] !== '') { $x[] = '(coalesce(p.name,i.name) ilike ? or p.sku ilike ?)'; $xb[] = '%'.self::esc((string)$f['product']).'%'; $xb[] = '%'.self::esc((string)$f['product']).'%'; }
      if ($item) { $c = array_merge($c, $x); $b = array_merge($b, $xb); }
      else { $c[] = 'exists (select 1 from order_items i join products p on p.id = i.product_id where i.order_id = o.id and '.implode(' and ', $x).')'; $b = array_merge($b, $xb); }
    }
    return [' where '.implode(' and ', $c), $b];
  }
  private static function lat(): string {
    return " left join lateral (select coalesce(sum(i.qty),0) qty, coalesce(sum(i.qty*i.price),0) items, coalesce(sum(coalesce(i.cogs, i.qty*coalesce(p.cost,0))),0) cost, string_agg(i.name, ', ' order by i.id) names from order_items i left join products p on p.id = i.product_id where i.order_id = o.id) x on true";
  }
  private static function day(string $fmt = 'YYYY-MM-DD'): string { return "to_char(o.created_at + interval '6 hours','$fmt')"; }
  private static function sel(string $sql, array $b): array { return DB::select($sql, $b); }
  private static function arr($rows): array { return array_map(fn($r) => (array)$r, $rows); }

  /* ---------- report definitions ---------- */
  public static function columns(string $slug): array {
    $T = 'text'; $N = 'num'; $M = 'money'; $P = 'pct'; $I = 'img'; $S = 'badge'; $Dt = 'date';
    return match ($slug) {
      'sales-summary' => ['date'=>['Date',$Dt],'invoice'=>['Invoice',$T],'customer'=>['Customer',$T],'product'=>['Product',$T],'store'=>['Store',$T],'qty'=>['Qty',$N],'sales'=>['Sales',$M],'cost'=>['Cost',$M],'profit'=>['Gross profit',$M],'status'=>['Status',$S]],
      'discount-report' => ['discount'=>['Discount type',$T],'orders'=>['Orders',$N],'amount'=>['Discount given',$M],'share'=>['Share of all discount',$P]],
      'product-sales' => ['image'=>['',$I],'product'=>['Product',$T],'sku'=>['SKU',$T],'store'=>['Store',$T],'qty'=>['Qty sold',$N],'gross'=>['Gross sales',$M],'discount'=>['Discount',$M],'cost'=>['Cost',$M],'profit'=>['Gross profit',$M],'margin'=>['Margin %',$P]],
      'category-sales' => ['category'=>['Category',$T],'store'=>['Store',$T],'products'=>['Total products',$N],'qty'=>['Qty sold',$N],'sales'=>['Total sales',$M],'profit'=>['Gross profit',$M],'return_rate'=>['Return rate',$P]],
      'daily-sales','monthly-sales' => ['date'=>[$slug === 'daily-sales' ? 'Date' : 'Month',$Dt],'store'=>['Store',$T],'orders'=>['Orders',$N],'qty'=>['Qty',$N],'sales'=>['Sales',$M],'cost'=>['Cost',$M],'profit'=>['Gross profit',$M],'aov'=>['Avg order value',$M]],
      'profit-loss' => ['line'=>['Line item',$T],'amount'=>['Amount',$M],'note'=>['How it is counted',$T]],
      'sale-profit' => ['date'=>['Date',$Dt],'invoice'=>['Invoice',$T],'customer'=>['Customer',$T],'store'=>['Store',$T],'sales'=>['Sales',$M],'cost'=>['Product cost',$M],'courier_fee'=>['Courier charge',$M],'profit'=>['Net profit',$M],'margin'=>['Margin %',$P],'status'=>['Status',$S]],
      'margin' => ['image'=>['',$I],'product'=>['Product',$T],'sku'=>['SKU',$T],'category'=>['Category',$T],'price'=>['Sale price',$M],'cost'=>['Cost',$M],'unit_profit'=>['Profit / unit',$M],'margin'=>['Margin %',$P],'sold'=>['Sold (period)',$N]],
      'stock-tracking' => ['image'=>['',$I],'product'=>['Product',$T],'sku'=>['SKU',$T],'store'=>['Store',$T],'current'=>['Current stock',$N],'opening'=>['Opening',$N],'sold'=>['Sold',$N],'returned'=>['Returned',$N],'stock_in'=>['Stock in',$N],'stock_out'=>['Stock out',$N],'closing'=>['Closing',$N]],
      'category-stock' => ['category'=>['Category',$T],'products'=>['Products',$N],'units'=>['Units in stock',$N],'cost_value'=>['Stock value (cost)',$M],'sale_value'=>['Stock value (sale)',$M],'out'=>['Out of stock',$N]],
      'stock-log' => ['date'=>['Date',$Dt],'product'=>['Product',$T],'source'=>['Source',$T],'reference'=>['Reference',$T],'before'=>['Before',$N],'change'=>['Change',$N],'after'=>['After',$N],'by'=>['By',$T]],
      'top-viewed' => ['image'=>['',$I],'product'=>['Product',$T],'sku'=>['SKU',$T],'category'=>['Category',$T],'brand'=>['Brand',$T],'views'=>['Total views',$N],'uniq'=>['Unique visitors',$N],'mobile'=>['Mobile',$N],'desktop'=>['Desktop',$N],'tablet'=>['Tablet',$N],'search'=>['Search clicks',$N],'cart'=>['Add to cart',$N],'checkout'=>['Checkout',$N],'orders'=>['Orders',$N],'cart_rate'=>['Cart rate %',$P],'conv'=>['Conversion %',$P],'growth'=>['View growth %',$P],'stock'=>['Stock',$N],'price'=>['Sale price',$M],'status'=>['Demand status',$S]],
      'low-stock','out-of-stock' => ['image'=>['',$I],'product'=>['Product',$T],'sku'=>['SKU',$T],'category'=>['Category',$T],'stock'=>['Stock',$N],'alert'=>['Alert at',$N],'cost'=>['Cost',$M],'price'=>['Price',$M]],
      'purchase-report' => ['date'=>['Date',$Dt],'invoice'=>['Invoice',$T],'supplier'=>['Supplier',$T],'qty'=>['Qty',$N],'total'=>['Total',$M],'paid'=>['Paid',$M],'due'=>['Due',$M]],
      'supplier-report' => ['supplier'=>['Supplier',$T],'purchases'=>['Purchases',$N],'total'=>['Total purchased',$M],'paid'=>['Paid',$M],'due'=>['Due',$M],'last'=>['Last purchase',$Dt]],
      'new-returning' => ['date'=>['Date',$Dt],'new_customers'=>['New customers',$N],'returning_orders'=>['Returning orders',$N],'orders'=>['Orders',$N],'new_share'=>['New share',$P]],
      'repeat-rate' => ['date'=>['Month',$Dt],'customers'=>['Customers',$N],'repeat'=>['Repeat customers',$N],'rate'=>['Repeat rate',$P]],
      'top-customers','customer-orders-summary' => ['name'=>['Customer',$T],'phone'=>['Mobile',$T],'category'=>['Category',$S],'orders'=>['Orders',$N],'delivered'=>['Delivered',$N],'returned'=>['Returned',$N],'cancelled'=>['Cancelled',$N],'spend'=>['Total spend',$M],'score'=>['Reliability',$P],'risk'=>['Risk',$S]],
      'customer-orders' => ['date'=>['Date',$Dt],'invoice'=>['Invoice',$T],'customer'=>['Customer',$T],'phone'=>['Mobile',$T],'product'=>['Products',$T],'store'=>['Store',$T],'items'=>['Order value',$M],'discount'=>['Discount received',$M],'dtext'=>['Discount breakdown',$T],'total'=>['Final order value',$M],'status'=>['Status',$S]],
      'cancel-rate' => ['date'=>['Date',$Dt],'orders'=>['Orders',$N],'cancelled'=>['Cancelled',$N],'rate'=>['Cancel rate',$P]],
      'return-rate' => ['date'=>['Date',$Dt],'orders'=>['Orders',$N],'returned'=>['Returned',$N],'rate'=>['Return rate',$P]],
      'courier-wise' => ['courier'=>['Courier',$T],'parcels'=>['Total parcels',$N],'delivered'=>['Delivered',$N],'returned'=>['Returned',$N],'pending'=>['Pending',$N],'success'=>['Success rate',$P],'cod'=>['COD collected',$M],'settle_pending'=>['Settlement pending',$M],'settle_received'=>['Settlement received',$M]],
      'delivered-orders','returned-orders' => ['date'=>['Date',$Dt],'invoice'=>['Invoice',$T],'customer'=>['Customer',$T],'phone'=>['Mobile',$T],'store'=>['Store',$T],'courier'=>['Courier',$T],'total'=>['Order total',$M],'status'=>['Status',$S]],
      'delivery-success' => ['courier'=>['Courier',$T],'delivered'=>['Delivered',$N],'returned'=>['Returned',$N],'done'=>['Completed',$N],'success'=>['Success rate',$P],'avg_days'=>['Avg days to deliver',$N]],
      'cod-collection' => ['date'=>['Date',$Dt],'invoice'=>['Invoice',$T],'courier'=>['Courier',$T],'expected'=>['COD amount',$M],'collected'=>['Collected',$M],'fee'=>['Courier charge',$M],'net'=>['Net payable',$M],'settlement'=>['Settlement',$S]],
      'courier-settlement' => ['courier'=>['Courier',$T],'parcels'=>['Delivered parcels',$N],'collected'=>['COD collected',$M],'fees'=>['Courier charges',$M],'pending'=>['Settlement pending',$M],'received'=>['Settlement received',$M]],
      'income','expense' => ['date'=>['Date',$Dt],'reference'=>['Reference',$T],'account'=>['Account',$T],'purpose'=>['Purpose',$T],'amount'=>['Amount',$M],'comment'=>['Comment',$T],'by'=>['By',$T]],
      'receivable' => ['courier'=>['Courier',$T],'parcels'=>['Delivered parcels',$N],'collected'=>['COD collected',$M],'fees'=>['Courier charges',$M],'receivable'=>['Receivable',$M],'oldest'=>['Oldest delivery',$Dt]],
      'payable' => ['supplier'=>['Supplier',$T],'invoices'=>['Open invoices',$N],'total'=>['Total purchased',$M],'paid'=>['Paid',$M],'due'=>['Payable',$M]],
      'payment-collection' => ['method'=>['Payment method',$T],'orders'=>['Orders',$N],'amount'=>['Order value',$M],'collected'=>['Collected',$M],'verified'=>['Verified payments',$N],'pending'=>['Verification pending',$N]],
      default => [],
    };
  }

  /** Run a report: returns ['rows'=>[], 'truncated'=>bool]. */
  public static function rows(string $slug, array $f): array {
    $split = $f['view'] === 'store';
    $rows = match ($slug) {
      'sales-summary','sale-profit','customer-orders','delivered-orders','returned-orders' => self::orderList($slug, $f),
      'discount-report' => self::discountReport($f),
      'product-sales' => self::productSales($f, $split),
      'category-sales' => self::categorySales($f, $split),
      'daily-sales' => self::periodSales($f, $split, 'YYYY-MM-DD'),
      'monthly-sales' => self::periodSales($f, $split, 'YYYY-MM'),
      'profit-loss' => self::profitLoss($f),
      'margin' => self::margin($f),
      'stock-tracking' => self::stockTracking($f),
      'category-stock' => self::categoryStock($f),
      'stock-log' => self::stockLog($f),
      'low-stock' => self::lowStock($f, false),
      'out-of-stock' => self::lowStock($f, true),
      'purchase-report' => self::purchaseReport($f),
      'supplier-report' => self::supplierReport($f),
      'new-returning' => self::newReturning($f),
      'repeat-rate' => self::repeatRate($f),
      'top-customers' => self::customers($f),
      'top-viewed' => self::topViewed($f),
      'cancel-rate' => self::rate($f, "('Cancel')", 'cancelled'),
      'return-rate' => self::rate($f, self::RET, 'returned'),
      'courier-wise' => self::courierWise($f),
      'delivery-success' => self::deliverySuccess($f),
      'cod-collection' => self::cod($f),
      'courier-settlement' => self::settlement($f),
      'income' => self::entries($f, 'income'),
      'expense' => self::entries($f, 'expense'),
      'receivable' => self::receivable($f),
      'payable' => self::payable($f),
      'payment-collection' => self::paymentCollection($f),
      default => [],
    };
    return ['rows'=>$rows, 'truncated'=>count($rows) >= self::LIMIT];
  }

  private static function orderList(string $slug, array $f): array {
    $basis = match ($slug) { 'delivered-orders','returned-orders','customer-orders' => 'all', default => 'valid' };
    $ff = $f;
    [$w, $b] = self::w($ff, $basis);
    if ($slug === 'delivered-orders') $w .= ' and o.status in '.self::DONE;
    if ($slug === 'returned-orders') $w .= ' and o.status in '.self::RET;
    $dlc = \App\Support\OrderDiscount::hasLines() ? 'o.discount_lines' : 'null';
    $sql = "select ".self::day()." date, o.invoice, o.customer_name customer, o.phone, x.names product, st.name store, x.qty qty, (x.items - o.discount) sales, x.cost cost, coalesce(o.courier_fee,0) courier_fee, (x.items - o.discount - x.cost) profit, o.total total, x.items items, o.discount discount, o.discount_type dtype, $dlc dlines, coalesce(o.courier,'') courier, o.status status from orders o left join stores st on st.id = o.store_id".self::lat().$w." order by o.created_at desc limit ".self::LIMIT;
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) {
      if ($slug === 'customer-orders') { $r['items'] = round((float)$r['items']); $r['discount'] = round((float)$r['discount']); $r['dtext'] = (float)$r['discount'] > 0 ? implode('; ', \App\Support\OrderDiscount::tips((object)['discount'=>$r['discount'], 'discount_lines'=>$r['dlines'], 'discount_type'=>$r['dtype'], 'discount_pct'=>null, 'discount_source'=>null, 'discount_rule'=>null, 'discount_by'=>null])) : ''; }
      if ($slug === 'sale-profit') { $r['profit'] = (float)$r['profit'] - (float)$r['courier_fee']; $r['margin'] = (float)$r['sales'] > 0 ? round($r['profit'] / (float)$r['sales'] * 100, 1) : 0; }
    }
    return $rows;
  }

  private static function productSales(array $f, bool $split): array {
    [$w, $b] = self::w($f, 'valid', true);
    $st = $split ? ', st.name store' : ", coalesce(string_agg(distinct st.name, ', '),'') store"; $g = $split ? ', st.name' : '';
    $sql = "select coalesce(p.name,i.name) product, max(coalesce(p.sku,'')) sku, max(coalesce(p.image,'')) image $st, sum(i.qty) qty, sum(i.qty*i.price) gross, coalesce(sum(o.discount * (i.qty*i.price) / nullif(t.items,0)),0) discount, sum(coalesce(i.cogs, i.qty*coalesce(p.cost,0))) cost from order_items i join orders o on o.id = i.order_id left join products p on p.id = i.product_id left join stores st on st.id = o.store_id left join lateral (select sum(qty*price) items from order_items where order_id = o.id) t on true $w group by coalesce(p.name,i.name) $g order by sum(i.qty*i.price) desc limit ".self::LIMIT;
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) { $r['discount'] = round((float)$r['discount']); $r['profit'] = (float)$r['gross'] - $r['discount'] - (float)$r['cost']; $r['margin'] = (float)$r['gross'] > 0 ? round($r['profit'] / (float)$r['gross'] * 100, 1) : 0; }
    return $rows;
  }

  private static function categorySales(array $f, bool $split): array {
    [$w, $b] = self::w($f, 'nocancel', true);
    $st = $split ? ', st.name store' : ", '' store"; $g = $split ? ', st.name' : '';
    $v = "o.status not in ('Returned','Return Pending')";
    $sql = "select coalesce(c.name,'Uncategorised') category, max(c.id) cid $st, sum(case when $v then i.qty else 0 end) qty, sum(case when $v then i.qty*i.price - coalesce(o.discount * (i.qty*i.price) / nullif(t.items,0),0) else 0 end) sales, sum(case when $v then coalesce(i.cogs, i.qty*coalesce(p.cost,0)) else 0 end) cost, sum(case when o.status in ".self::RET." then i.qty else 0 end) ret_qty, sum(i.qty) all_qty from order_items i join orders o on o.id = i.order_id left join products p on p.id = i.product_id left join categories c on c.id = p.category_id left join stores st on st.id = o.store_id left join lateral (select sum(qty*price) items from order_items where order_id = o.id) t on true $w group by coalesce(c.name,'Uncategorised') $g order by 5 desc";
    $rows = self::arr(self::sel($sql, $b));
    $cnt = []; foreach (self::arr(DB::select('select category_id, count(*) n from products group by category_id')) as $x) $cnt[$x['category_id']] = $x['n'];
    foreach ($rows as &$r) { $r['products'] = (int)($cnt[$r['cid']] ?? 0); $r['sales'] = round((float)$r['sales']); $r['profit'] = $r['sales'] - (float)$r['cost']; $r['return_rate'] = (float)$r['all_qty'] > 0 ? round($r['ret_qty'] / $r['all_qty'] * 100, 1) : 0; }
    return $rows;
  }

  /** Total discount given, split by discount type (First Order, Membership, Coupon, Manual…). Orders from before per-offer storage are shown under the type name saved on the order. */
  private static function discountReport(array $f): array {
    [$w, $b] = self::w($f, 'valid');
    $lines = \App\Support\OrderDiscount::hasLines();
    $new = $lines ? "select o.id, e->>'name' nm, coalesce((e->>'amount')::numeric, 0) amt from orders o cross join lateral jsonb_array_elements(o.discount_lines::jsonb) e$w and coalesce(o.discount_lines,'') <> ''" : null;
    $old = "select o.id, coalesce(nullif(o.discount_type,''), 'Discount (type not recorded)') nm, o.discount amt from orders o$w and o.discount > 0".($lines ? " and coalesce(o.discount_lines,'') = ''" : '');
    $sql = "select nm discount, count(distinct id) orders, sum(amt) amount from (".($new ? "$new union all " : '')."$old) t group by nm order by 3 desc";
    $rows = self::arr(self::sel($sql, $lines ? array_merge($b, $b) : $b));
    $tot = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
    foreach ($rows as &$r) { $r['amount'] = round((float)$r['amount']); $r['share'] = $tot > 0 ? round(100 * (float)$r['amount'] / $tot, 1) : 0; }
    return $rows;
  }

  private static function periodSales(array $f, bool $split, string $fmt): array {
    [$w, $b] = self::w($f, 'valid');
    $st = $split ? ', st.name store' : ", '' store"; $g = $split ? ', st.name' : '';
    $d = self::day($fmt);
    $sql = "select $d date $st, count(*) orders, sum(x.qty) qty, sum(x.items - o.discount) sales, sum(x.cost) cost, sum(x.items - o.discount - x.cost) profit from orders o left join stores st on st.id = o.store_id".self::lat()."$w group by $d $g order by 1 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) $r['aov'] = (int)$r['orders'] > 0 ? round((float)$r['sales'] / (int)$r['orders']) : 0;
    return $rows;
  }

  /** Statement rows for Profit / Loss. */
  private static function profitLoss(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $v = "(o.status not in ('Cancel','Returned','Return Pending') or o.return_type = 'Partial')";
    $sql = "select coalesce(sum(case when $v then x.items end),0) items, coalesce(sum(case when $v then o.discount end),0) disc, coalesce(sum(case when $v then x.cost end),0) cost, coalesce(sum(case when $v then o.shipping end),0) ship, coalesce(sum(case when $v then o.delivery_free_discount end),0) dfree, coalesce(sum(case when $v then o.delivery_manual_adj end),0) dadj, coalesce(sum(case when $v then coalesce(o.courier_fee,0) end),0) cfee, coalesce(sum(case when o.status in ".self::RET." then coalesce(o.courier_return_fee,0) end),0) rfee, coalesce(sum(case when o.status in ".self::RET." then x.items - o.discount end),0) ret from orders o".self::lat().$w;
    $r = (array)(self::sel($sql, $b)[0] ?? []);
    // real operating expenses only: purchase / supplier payments and transfers are NOT expenses (the product cost is already in COGS), so entries whose purpose is marked "not an expense" are left out
    $exp = (float)(DB::select("select coalesce(sum(e.amount),0) s from account_entries e left join expense_purposes pu on pu.id = e.purpose_id where e.direction = 'expense' and coalesce(e.reference,'') not like 'TRF-%' and coalesce(pu.is_expense, true) = true and e.entry_date between ? and ?", [$f['from'], $f['to']])[0]->s ?? 0);
    $rev = (float)$r['items'] - (float)$r['disc']; $gross = $rev - (float)$r['cost'];
    $net = $gross + (float)$r['ship'] - (float)$r['cfee'] - (float)$r['rfee'] - $exp;
    $L = fn($l, $a, $n = '') => ['line'=>$l, 'amount'=>$a, 'note'=>$n, 'bold'=>0];
    $B = fn($l, $a, $n = '') => ['line'=>$l, 'amount'=>$a, 'note'=>$n, 'bold'=>1];
    return [
      $L('Sales revenue (items)', (float)$r['items'], 'Delivered, pending and shipped orders. Cancelled and returned orders are left out.'),
      $L('Discounts', -(float)$r['disc'], 'Coupons and offer discounts given on those orders.'),
      $B('Net sales', $rev),
      $L('Product cost', -(float)$r['cost'], 'Cost price of the products sold.'),
      $B('Gross profit', $gross),
      $L('Delivery charged to customers', (float)$r['ship'], 'Shipping amount collected from customers.'),
      $L('Courier charge', -(float)$r['cfee'], 'Courier delivery fee recorded on orders.'),
      $L('Return courier fee', -(float)$r['rfee'], 'Fee paid for returned parcels.'),
      $L('Other expense', -$exp, 'Expense entries from Accounts in the same dates.'),
      $B('Net profit', $net),
      ['line'=>'Memo: free delivery given (waived)', 'amount'=>(float)($r['dfree'] ?? 0), 'note'=>'Delivery charge waived by free-delivery rules; not part of net sales.', 'bold'=>0],
      ['line'=>'Memo: manual delivery adjustments', 'amount'=>(float)($r['dadj'] ?? 0), 'note'=>'Admin changes to the calculated delivery charge.', 'bold'=>0],
      ['line'=>'Memo: returned orders value', 'amount'=>(float)$r['ret'], 'note'=>'Already excluded from revenue above.', 'bold'=>0],
      ['line'=>'Memo: refunds', 'amount'=>0, 'note'=>'Refunds are not recorded separately yet. Returns are shown above.', 'bold'=>0],
    ];
  }

  private static function margin(array $f): array {
    $c = ['1=1']; $b = [self::utc($f['from'], false), self::utc($f['to'], true)];
    if ($f['category']) { $c[] = 'p.category_id = ?'; $b[] = $f['category']; }
    if ($f['product'] !== '') { $c[] = '(p.name ilike ? or p.sku ilike ?)'; $b[] = '%'.self::esc((string)$f['product']).'%'; $b[] = '%'.self::esc((string)$f['product']).'%'; }
    $sql = "select p.name product, coalesce(p.sku,'') sku, coalesce(p.image,'') image, coalesce(c.name,'') category, p.price price, p.cost cost, coalesce(s.sold,0) sold from products p left join categories c on c.id = p.category_id left join (select i.product_id, sum(i.qty - case when (o.status in ('Return Pending','Returned') and o.return_type = 'Partial') then coalesce(i.ret_qty,0) else 0 end) sold from order_items i join orders o on o.id = i.order_id where o.created_at >= ? and o.created_at < ? and (o.status not in ('Cancel','Returned','Return Pending') or o.return_type = 'Partial') group by i.product_id) s on s.product_id = p.id where ".implode(' and ', $c)." order by case when p.price > 0 then (p.price - p.cost) / p.price end asc nulls last limit ".self::LIMIT;
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) { $r['unit_profit'] = (float)$r['price'] - (float)$r['cost']; $r['margin'] = (float)$r['price'] > 0 ? round($r['unit_profit'] / (float)$r['price'] * 100, 1) : 0; }
    return $rows;
  }

  private static function stockTracking(array $f): array {
    $c = ['1=1']; $b = [];
    $lo = self::utc($f['from'], false); $hi = self::utc($f['to'], true);
    $b = [$lo, $lo, $hi, $lo, $hi, $lo, $hi, $lo, $hi];
    if ($f['category']) { $c[] = 'p.category_id = ?'; $b[] = $f['category']; }
    if ($f['product'] !== '') { $c[] = '(p.name ilike ? or p.sku ilike ?)'; $b[] = '%'.self::esc((string)$f['product']).'%'; $b[] = '%'.self::esc((string)$f['product']).'%'; }
    $sql = "select p.name product, coalesce(p.sku,'') sku, coalesce(p.image,'') image, 'All stores (one shared stock)' store, p.stock current, coalesce(l.since,0) since, coalesce(l.net,0) net, coalesce(l.inn,0) stock_in, coalesce(l.outt,0) stock_out, coalesce(s.sold,0) sold, coalesce(s.ret,0) returned
      from products p
      left join (select product_id, sum(case when created_at >= ? then change_qty end) since, sum(case when created_at >= ? and created_at < ? then change_qty end) net, sum(case when created_at >= ? and created_at < ? and change_qty > 0 then change_qty end) inn, sum(case when created_at >= ? and created_at < ? and change_qty < 0 then -change_qty end) outt from stock_logs group by product_id) l on l.product_id = p.id
      left join (select i.product_id, sum(case when (o.status not in ('Cancel','Returned','Return Pending') or o.return_type = 'Partial') then (i.qty - case when (o.status in ('Return Pending','Returned') and o.return_type = 'Partial') then coalesce(i.ret_qty,0) else 0 end) end) sold, sum(case when (o.status in ('Return Pending','Returned') and o.return_type = 'Partial') then coalesce(i.ret_qty,0) when o.status in ".self::RET." then i.qty end) ret from order_items i join orders o on o.id = i.order_id where o.created_at >= ? and o.created_at < ? group by i.product_id) s on s.product_id = p.id
      where ".implode(' and ', $c)." order by p.name limit ".self::LIMIT;
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) { $r['opening'] = (int)$r['current'] - (int)$r['since']; $r['closing'] = $r['opening'] + (int)$r['net']; }
    return $rows;
  }

  private static function categoryStock(array $f): array {
    $c = ['1=1']; $b = [];
    if ($f['category']) { $c[] = 'c.id = ?'; $b[] = $f['category']; }
    $sql = "select coalesce(c.name,'Uncategorised') category, count(p.id) products, coalesce(sum(greatest(p.stock,0)),0) units, coalesce(sum(greatest(p.stock,0)*coalesce(nullif(p.avg_cost,0),p.cost)),0) cost_value, coalesce(sum(greatest(p.stock,0)*p.price),0) sale_value, sum(case when p.stock <= 0 then 1 else 0 end) out from products p left join categories c on c.id = p.category_id where ".implode(' and ', $c)." group by c.id, c.name order by 4 desc";
    return self::arr(self::sel($sql, $b));
  }

  private static function stockLog(array $f): array {
    $c = ['l.created_at >= ?', 'l.created_at < ?']; $b = [self::utc($f['from'], false), self::utc($f['to'], true)];
    if ($f['category']) { $c[] = 'p.category_id = ?'; $b[] = $f['category']; }
    if ($f['product'] !== '') { $c[] = '(p.name ilike ? or p.sku ilike ?)'; $b[] = '%'.self::esc((string)$f['product']).'%'; $b[] = '%'.self::esc((string)$f['product']).'%'; }
    $sql = "select to_char(l.created_at + interval '6 hours','YYYY-MM-DD HH24:MI') date, coalesce(p.name,'Deleted product') product, l.source, coalesce(l.reference,'') reference, l.before_qty before, l.change_qty change, l.after_qty after, coalesce(l.user_name,'') by from stock_logs l left join products p on p.id = l.product_id where ".implode(' and ', $c)." order by l.id desc limit ".self::LIMIT;
    return self::arr(self::sel($sql, $b));
  }

  /** Top Viewed Products: valid anonymous product views (bots filtered, 10-minute repeat protection) against real orders. Add-to-cart / search / checkout columns arrive with phase 2 tracking. */
  private static function topViewed(array $f): array {
    $a = self::utc($f['from'], false); $z = self::utc($f['to'], true);
    if (($f['device'] ?? '') !== '') { $c_dev = true; }
    $days = max(1, (int)((strtotime($z) - strtotime($a)) / 86400)); $pa = date('Y-m-d H:i:s', strtotime($a) - $days * 86400);
    $c = ["e.event_type = 'product_view'", 'e.created_at >= ?', 'e.created_at < ?']; $b = [$a, $z];
    if ($f['category']) { $c[] = 'p.category_id = ?'; $b[] = $f['category']; }
    if ($f['product'] !== '') { $c[] = '(p.name ilike ? or p.sku ilike ?)'; $b[] = '%'.self::esc((string)$f['product']).'%'; $b[] = '%'.self::esc((string)$f['product']).'%'; }
    if (!empty($c_dev)) { $c[] = 'e.device_type = ?'; $b[] = $f['device']; }
    if (($f['vsource'] ?? '') !== '') { $c[] = 'e.source = ?'; $b[] = $f['vsource']; }
    $sql = "select p.id, p.name product, coalesce(p.sku,'') sku, coalesce(p.image,'') image, coalesce(c.name,'') category, coalesce(p.sub,'') brand, count(*) views, count(distinct e.visitor_id) uniq,
      sum((e.device_type = 'mobile')::int) mobile, sum((e.device_type = 'desktop')::int) desktop, sum((e.device_type = 'tablet')::int) tablet,
      coalesce(p.stock,0) stock, coalesce(p.price,0) price, coalesce(p.low_stock_alert,10) alert,
      (select count(*) from product_analytics_events x where x.event_type = 'product_view' and x.product_id = p.id and x.created_at >= ? and x.created_at < ?) prev_views,
      (select count(distinct o.id) from order_items i join orders o on o.id = i.order_id where i.product_id = p.id and o.created_at >= ? and o.created_at < ? and ".self::VALID.") orders,
      (select count(*) from product_analytics_events x where x.event_type = 'add_to_cart' and x.product_id = p.id and x.created_at >= ? and x.created_at < ?) cart,
      (select count(*) from product_analytics_events x where x.event_type = 'checkout_started' and x.product_id = p.id and x.created_at >= ? and x.created_at < ?) checkout,
      (select count(*) from product_analytics_events x where x.event_type = 'search_click' and x.product_id = p.id and x.created_at >= ? and x.created_at < ?) search
      from product_analytics_events e join products p on p.id = e.product_id left join categories c on c.id = p.category_id where ".implode(' and ', $c)." group by p.id, c.name order by count(*) desc limit ".self::LIMIT;
    $rows = self::arr(self::sel($sql, [$pa, $a, $a, $z, $a, $z, $a, $z, $a, $z, ...$b]));
    foreach ($rows as &$r) {
      $views = (int)$r['views']; $uniq = (int)$r['uniq']; $orders = (int)$r['orders']; $prev = (int)$r['prev_views'];
      $r['conv'] = $uniq > 0 ? round(min(100, $orders / $uniq * 100), 2) : 0;
      $r['growth'] = $prev > 0 ? round(($views - $prev) / $prev * 100, 1) : ($views >= 20 ? 100 : 0);
      $r['cart_rate'] = $uniq > 0 ? round(min(100, (int)$r['cart'] / $uniq * 100), 2) : 0;
      $r['status'] = ProductTrack::status($views, $uniq, $orders, (float)$r['conv'], (int)$r['stock'], (int)$r['alert'], (float)$r['growth']);
      unset($r['prev_views'], $r['alert'], $r['id']);
    }
    return $rows;
  }

  private static function lowStock(array $f, bool $out): array {
    $c = [$out ? 'p.stock <= 0' : 'p.stock > 0 and p.stock <= coalesce(p.low_stock_alert, 10)']; $b = [];
    if ($f['category']) { $c[] = 'p.category_id = ?'; $b[] = $f['category']; }
    if ($f['product'] !== '') { $c[] = '(p.name ilike ? or p.sku ilike ?)'; $b[] = '%'.self::esc((string)$f['product']).'%'; $b[] = '%'.self::esc((string)$f['product']).'%'; }
    $sql = "select p.name product, coalesce(p.sku,'') sku, coalesce(p.image,'') image, coalesce(c.name,'') category, p.stock stock, coalesce(p.low_stock_alert,10) alert, p.cost cost, p.price price from products p left join categories c on c.id = p.category_id where ".implode(' and ', $c)." order by p.stock, p.name limit ".self::LIMIT;
    return self::arr(self::sel($sql, $b));
  }

  private static function purchaseReport(array $f): array {
    $c = ['p.purchase_date between ? and ?']; $b = [$f['from'], $f['to']];
    if ($f['customer'] !== '') { $c[] = 's.name ilike ?'; $b[] = '%'.self::esc((string)$f['customer']).'%'; }
    $sql = "select to_char(p.purchase_date,'YYYY-MM-DD') date, p.invoice, coalesce(s.name,'') supplier, coalesce((select sum(qty) from purchase_items i where i.purchase_id = p.id),0) qty, p.total total, p.paid paid, (p.total - p.paid) due from purchases p left join suppliers s on s.id = p.supplier_id where ".implode(' and ', $c)." order by p.purchase_date desc, p.id desc limit ".self::LIMIT;
    return self::arr(self::sel($sql, $b));
  }

  private static function supplierReport(array $f): array {
    $c = ['1=1']; $b = [$f['from'], $f['to']];
    if ($f['customer'] !== '') { $c[] = 's.name ilike ?'; $b[] = '%'.self::esc((string)$f['customer']).'%'; }
    $sql = "select s.name supplier, count(p.id) purchases, coalesce(sum(p.total),0) total, coalesce(sum(p.paid),0) paid, coalesce(sum(p.total - p.paid),0) due, to_char(max(p.purchase_date),'YYYY-MM-DD') last from suppliers s left join purchases p on p.supplier_id = s.id and p.purchase_date between ? and ? where ".implode(' and ', $c)." group by s.id, s.name order by 3 desc";
    return self::arr(self::sel($sql, $b));
  }

  private static function newReturning(array $f): array {
    [$w, $b] = self::w($f, 'nocancel');
    $d = self::day();
    $sql = "select $d date, sum(case when o.created_at = fo.first_at then 1 else 0 end) new_customers, sum(case when o.created_at > fo.first_at then 1 else 0 end) returning_orders, count(*) orders from orders o join (select phone, min(created_at) first_at from orders where status <> 'Cancel' group by phone) fo on fo.phone = o.phone $w group by $d order by 1 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) $r['new_share'] = (int)$r['orders'] > 0 ? round($r['new_customers'] / $r['orders'] * 100, 1) : 0;
    return $rows;
  }

  private static function repeatRate(array $f): array {
    [$w, $b] = self::w($f, 'nocancel');
    $d = self::day('YYYY-MM');
    $sql = "select $d date, count(distinct o.phone) customers, count(distinct case when c.n > 1 then o.phone end) repeat from orders o join (select phone, count(*) n from orders where status <> 'Cancel' group by phone) c on c.phone = o.phone $w group by $d order by 1 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) $r['rate'] = (int)$r['customers'] > 0 ? round($r['repeat'] / $r['customers'] * 100, 1) : 0;
    return $rows;
  }

  /** Customers who ordered in the period, with all-time behaviour. */
  private static function customers(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $sql = "select max(a.customer_name) name, a.phone, count(*) orders, sum(case when a.status in ".self::DONE." then 1 else 0 end) delivered, sum(case when a.status in ".self::RET." then 1 else 0 end) returned, sum(case when a.status = 'Cancel' then 1 else 0 end) cancelled, coalesce(sum(case when a.status in ".self::DONE." then a.total end),0) spend from orders a where a.phone in (select o.phone from orders o $w) group by a.phone order by 7 desc limit 500";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) {
      $fin = (int)$r['delivered'] + (int)$r['returned'] + (int)$r['cancelled'];
      $r['score'] = $fin > 0 ? round($r['delivered'] / $fin * 100) : 100;
      $bad = (int)$r['returned'] + (int)$r['cancelled'];
      $r['risk'] = ($fin >= 2 && $r['score'] < 50) || $bad >= 3 ? 'High' : ($fin >= 2 && $r['score'] < 75 ? 'Medium' : 'Low');
      $r['category'] = (int)$r['delivered'] >= 5 || ((int)$r['delivered'] >= 1 && (float)$r['spend'] >= 20000) ? 'VIP' : ((int)$r['delivered'] >= 1 ? 'Regular' : 'New'); // owner rule 2026-11-06: a real customer = received a parcel; orders alone count for nothing
    }
    return $rows;
  }

  private static function rate(array $f, string $set, string $key): array {
    [$w, $b] = self::w($f, 'all');
    $d = self::day();
    $sql = "select $d date, count(*) orders, sum(case when o.status in $set then 1 else 0 end) $key from orders o $w group by $d order by 1 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) $r['rate'] = (int)$r['orders'] > 0 ? round($r[$key] / $r['orders'] * 100, 1) : 0;
    return $rows;
  }

  private static function courierWise(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $sql = "select coalesce(nullif(o.courier,''),'Unassigned') courier, count(*) parcels, sum(case when o.status in ".self::DONE." then 1 else 0 end) delivered, sum(case when o.status in ".self::RET." then 1 else 0 end) returned, sum(case when o.status not in ".self::DONE." and o.status not in ".self::RET." and o.status <> 'Cancel' then 1 else 0 end) pending, coalesce(sum(coalesce(o.collected_amount,0)),0) cod, coalesce(sum(case when o.status in ".self::DONE." and o.settlement_status <> 'Paid' then coalesce(o.collected_amount,0) - coalesce(o.courier_fee,0) end),0) settle_pending, coalesce(sum(case when o.status in ".self::DONE." and o.settlement_status = 'Paid' then coalesce(o.collected_amount,0) - coalesce(o.courier_fee,0) end),0) settle_received from orders o $w and o.status <> 'Cancel' group by 1 order by 2 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) { $done = (int)$r['delivered'] + (int)$r['returned']; $r['success'] = $done > 0 ? round($r['delivered'] / $done * 100, 1) : 0; }
    return $rows;
  }

  private static function deliverySuccess(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $sql = "select coalesce(nullif(o.courier,''),'Unassigned') courier, sum(case when o.status in ".self::DONE." then 1 else 0 end) delivered, sum(case when o.status in ".self::RET." then 1 else 0 end) returned, avg(case when o.status in ".self::DONE." and o.courier_delivered_at is not null then extract(epoch from (o.courier_delivered_at - o.created_at)) / 86400 end) avg_days from orders o $w group by 1 order by 2 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) { $r['done'] = (int)$r['delivered'] + (int)$r['returned']; $r['success'] = $r['done'] > 0 ? round($r['delivered'] / $r['done'] * 100, 1) : 0; $r['avg_days'] = $r['avg_days'] !== null ? round((float)$r['avg_days'], 1) : 0; }
    return $rows;
  }

  private static function cod(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $sql = "select ".self::day()." date, o.invoice, coalesce(nullif(o.courier,''),'Unassigned') courier, coalesce(o.courier_cod, o.total) expected, coalesce(o.collected_amount,0) collected, coalesce(o.courier_fee,0) fee, o.settlement_status settlement from orders o $w and o.status in ".self::DONE." order by o.created_at desc limit ".self::LIMIT;
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) $r['net'] = (float)$r['collected'] - (float)$r['fee'];
    return $rows;
  }

  private static function settlement(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $sql = "select coalesce(nullif(o.courier,''),'Unassigned') courier, count(*) parcels, coalesce(sum(coalesce(o.collected_amount,0)),0) collected, coalesce(sum(coalesce(o.courier_fee,0)),0) fees, coalesce(sum(case when o.settlement_status <> 'Paid' then coalesce(o.collected_amount,0) - coalesce(o.courier_fee,0) end),0) pending, coalesce(sum(case when o.settlement_status = 'Paid' then coalesce(o.collected_amount,0) - coalesce(o.courier_fee,0) end),0) received from orders o $w and o.status in ".self::DONE." group by 1 order by 3 desc";
    return self::arr(self::sel($sql, $b));
  }

  private static function entries(array $f, string $dir): array {
    $c = ['e.direction = ?', 'e.entry_date between ? and ?']; $b = [$dir, $f['from'], $f['to']];
    if ($f['customer'] !== '') { $c[] = '(e.comment ilike ? or pu.name ilike ?)'; $b[] = '%'.self::esc((string)$f['customer']).'%'; $b[] = '%'.self::esc((string)$f['customer']).'%'; }
    $sql = "select to_char(e.entry_date,'YYYY-MM-DD') date, e.reference, coalesce(a.name,'') account, coalesce(pu.name,'') purpose, e.amount amount, coalesce(e.comment,'') comment, coalesce(e.user_name,'') by from account_entries e left join money_accounts a on a.id = e.account_id left join expense_purposes pu on pu.id = e.purpose_id where ".implode(' and ', $c)." order by e.entry_date desc, e.id desc limit ".self::LIMIT;
    return self::arr(self::sel($sql, $b));
  }

  private static function receivable(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $sql = "select coalesce(nullif(o.courier,''),'Unassigned') courier, count(*) parcels, coalesce(sum(coalesce(o.collected_amount,0)),0) collected, coalesce(sum(coalesce(o.courier_fee,0)),0) fees, coalesce(sum(coalesce(o.collected_amount,0) - coalesce(o.courier_fee,0)),0) receivable, to_char(min(o.created_at + interval '6 hours'),'YYYY-MM-DD') oldest from orders o $w and o.status in ".self::DONE." and o.settlement_status <> 'Paid' group by 1 order by 5 desc";
    return self::arr(self::sel($sql, $b));
  }

  private static function payable(array $f): array {
    $c = ['(p.total - p.paid) > 0']; $b = [];
    if ($f['customer'] !== '') { $c[] = 's.name ilike ?'; $b[] = '%'.self::esc((string)$f['customer']).'%'; }
    $sql = "select coalesce(s.name,'Unknown') supplier, count(*) invoices, sum(p.total) total, sum(p.paid) paid, sum(p.total - p.paid) due from purchases p left join suppliers s on s.id = p.supplier_id where ".implode(' and ', $c)." group by s.id, s.name order by 5 desc";
    return self::arr(self::sel($sql, $b));
  }

  private static function paymentCollection(array $f): array {
    [$w, $b] = self::w($f, 'nocancel');
    $sql = "select coalesce(nullif(o.payment_method,''),'Cash on delivery') method, count(*) orders, coalesce(sum(o.total),0) amount, coalesce(sum(case when o.status in ".self::DONE." then coalesce(o.collected_amount, o.total) else coalesce(o.collected_amount,0) end),0) collected, sum(case when o.pay_status = 'Verified' then 1 else 0 end) verified, sum(case when o.pay_status = 'Verification Pending' then 1 else 0 end) pending from orders o $w group by 1 order by 3 desc";
    return self::arr(self::sel($sql, $b));
  }

  /* ---------- shared helpers for the page ---------- */
  /** Daily trend used by charts on sales/profit style pages and the dashboard. */
  public static function trend(array $f): array {
    [$w, $b] = self::w($f, 'all');
    $d = self::day(); $v = self::VALID;
    $sql = "select $d date, count(*) placed, sum(case when $v then 1 else 0 end) orders, coalesce(sum(case when $v then x.items - o.discount end),0) sales, coalesce(sum(case when $v then x.cost end),0) cost, coalesce(sum(case when $v then coalesce(o.courier_fee,0) end),0) cfee, coalesce(sum(case when $v then x.items - o.discount - x.cost - coalesce(o.courier_fee,0) end),0) profit, sum(case when o.status in ".self::DONE." then 1 else 0 end) delivered, sum(case when $v and o.status not in ".self::DONE." then 1 else 0 end) pending, sum(case when not $v and o.status <> 'Cancel' then 1 else 0 end) returned, sum(case when not $v and o.status = 'Cancel' then 1 else 0 end) cancelled from orders o".self::lat()."$w group by $d order by 1";
    return self::arr(self::sel($sql, $b));
  }
  /** Per-store totals for the Comparison view and the store chart. */
  public static function byStore(array $f): array {
    [$w, $b] = self::w($f, 'valid');
    $sql = "select coalesce(st.name,'No store') store, count(*) orders, coalesce(sum(x.items - o.discount),0) sales, coalesce(sum(x.cost),0) cost, coalesce(sum(x.items - o.discount - x.cost - coalesce(o.courier_fee,0)),0) profit, coalesce(sum(coalesce(o.courier_fee,0)),0) cfee from orders o left join stores st on st.id = o.store_id".self::lat()."$w group by st.id, st.name order by 3 desc";
    $rows = self::arr(self::sel($sql, $b));
    foreach ($rows as &$r) { $r['margin'] = (float)$r['sales'] > 0 ? round($r['profit'] / $r['sales'] * 100, 1) : 0; $r['aov'] = (int)$r['orders'] > 0 ? round($r['sales'] / $r['orders']) : 0; }
    return $rows;
  }
  public static function dashboard(array $f): array {
    [$w, $b] = self::w($f, 'all'); $v = self::VALID;
    $sql = "select count(*) placed, sum(case when $v then 1 else 0 end) orders, coalesce(sum(case when $v then x.items - o.discount end),0) sales, coalesce(sum(case when $v then x.cost end),0) cost, coalesce(sum(case when $v then coalesce(o.courier_fee,0) end),0) cfee, coalesce(sum(case when $v then x.qty end),0) qty, sum(case when o.status in ".self::DONE." then 1 else 0 end) delivered, sum(case when $v and o.status not in ".self::DONE." then 1 else 0 end) pending, sum(case when not $v and o.status <> 'Cancel' then 1 else 0 end) returned, sum(case when not $v and o.status = 'Cancel' then 1 else 0 end) cancelled from orders o".self::lat().$w;
    return (array)(self::sel($sql, $b)[0] ?? []);
  }
  /** Category sales after the order discount (the discount is shared across the order's items by value), so the categories add up to Total sales. */
  public static function categorySalesChart(array $f): array {
    [$w, $b] = self::w($f, 'valid', true);
    $sql = "select coalesce(c.name,'Uncategorised') label, coalesce(sum(i.qty*i.price*(1 - coalesce(o.discount,0)/nullif(x.items,0))),0) value from order_items i join orders o on o.id = i.order_id left join products p on p.id = i.product_id left join categories c on c.id = p.category_id".self::lat()." $w group by 1 order by 2 desc";
    return self::arr(self::sel($sql, $b));
  }
  public static function courierChart(array $f): array { return self::courierWise($f); }
  public static function options(): array {
    return [
      'stores' => self::arr(DB::select('select id, name from stores order by name')),
      'categories' => self::arr(DB::select('select id, name from categories where parent_id is null order by position, name')),
      'couriers' => array_merge(['Unassigned'], array_map(fn($r) => $r->courier, DB::select("select distinct courier from orders where coalesce(courier,'') <> '' order by 1"))),
      'statuses' => \App\Models\Mother::STAGES,
      'pays' => ['Verified','Verification Pending','Rejected','None'],
    ];
  }
}
