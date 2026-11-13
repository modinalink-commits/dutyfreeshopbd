<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Old site (staged copy) versus the live tables, so the move can be checked number by number. */
class MigrationDashboardController extends Controller {
  private function has(string $t, ?string $c = null): bool { return Schema::hasTable($t) && ($c === null || Schema::hasColumn($t, $c)); }
  public function index() {
    $d = request()->query('fresh') ? null : \Illuminate\Support\Facades\Cache::get('mig_dash_v1');
    if (!$d) { @set_time_limit(170); /* the old-vs-new counts scan large tables; the default 30 s limit ended in a 500 page */ $d = $this->build(); \Illuminate\Support\Facades\Cache::put('mig_dash_v1', $d, 86400); }
    return view('migration_dashboard', $d);
  }
  private function build(): array {
    $n = fn($v) => number_format((float)$v); $m = fn($v) => '৳' . number_format((float)$v);
    $rows = []; $add = function ($group, $label, $old, $new, $money = false) use (&$rows, $n, $m) {
      $f = $money ? $m : $n; $diff = ($old === null || $new === null) ? null : (float)$new - (float)$old;
      $rows[] = ['g' => $group, 'l' => $label, 'old' => $old === null ? '—' : $f($old), 'new' => $new === null ? '—' : $f($new), 'diff' => $diff === null ? '—' : ($diff == 0 ? 'OK' : ($diff > 0 ? '+' : '−') . $f(abs($diff))), 'ok' => $diff !== null && abs($diff) < 0.5];
    };
    // Customers
    if ($this->has('legacy_customers')) {
      $stg = DB::table('legacy_customers')->count(); $done = DB::table('legacy_customers')->where('merged', true)->count();
      $add('Customers', 'Old customers staged / marked as moved', $stg, $done); $add('Customers', 'Customers in the new system (old + created on new site)', $stg, DB::table('customers')->count());
      $add('Customers', 'Old customers who have orders (staged)', DB::table('legacy_customers')->where('orders_count', '>', 0)->count(), DB::table('orders')->whereNotNull('legacy_order_id')->distinct()->count('phone'));
    }
    // Orders
    if ($this->has('legacy_orders')) {
      $so = DB::table('legacy_orders'); $skipped = (clone $so)->where('action', 'skip')->count();
      $add('Orders', 'Orders staged from old site', (clone $so)->count(), (clone $so)->count());
      $imp = DB::table('orders')->whereNotNull('legacy_order_id');
      $add('Orders', 'Old orders now in the new system', (clone $so)->where(fn($q) => $q->whereNull('action')->orWhere('action', '<>', 'skip'))->count(), (clone $imp)->count());
      $add('Orders', 'Order value, excluding cancelled (old status 6)', (float)(clone $so)->where('status', '<>', 6)->sum('total'), (float)(clone $imp)->where('status', '<>', 'Cancel')->sum('total'), true);
      $add('Orders', 'Cancelled orders (old status 6)', (clone $so)->where('status', 6)->count(), (clone $imp)->where('status', 'Cancel')->count());
      $add('Orders', 'Order lines (items) of old orders', null, DB::table('order_items')->whereIn('order_id', DB::table('orders')->whereNotNull('legacy_order_id')->select('id'))->count());
      $add('Orders', 'Skipped on import (bad / duplicate rows)', $skipped, null);
      $add('Orders', 'Orders created on the new site (not from old)', null, DB::table('orders')->whereNull('legacy_order_id')->count());
    }
    // Reviews / products
    if ($this->has('reviews')) $add('Catalogue', 'Reviews in the new system', null, DB::table('reviews')->count());
    if ($this->has('products', 'legacy_product_id')) $add('Catalogue', 'Products with an old-site ID', null, DB::table('products')->whereNotNull('legacy_product_id')->count());
    // Suppliers
    if ($this->has('suppliers', 'legacy_supplier_id')) $add('Suppliers', 'Suppliers from old site', null, DB::table('suppliers')->whereNotNull('legacy_supplier_id')->count());
    if ($this->has('purchases', 'legacy_purchase_id')) { $p = DB::table('purchases')->whereNotNull('legacy_purchase_id'); $add('Suppliers', 'Purchase invoices', null, (clone $p)->count());
      $add('Suppliers', 'Purchase value', null, (float)(clone $p)->sum('total'), true); $add('Suppliers', 'Paid (marked on purchase invoices)', null, (float)(clone $p)->sum('paid'), true);
      if ($this->has('supplier_payments', 'legacy_payment_id')) { $sp = DB::table('supplier_payments')->whereNotNull('legacy_payment_id'); $add('Suppliers', 'Old payments imported (count)', null, (clone $sp)->count()); $add('Suppliers', 'Old payments imported (amount)', null, (float)(clone $sp)->sum('amount'), true); } }
    // Accounts
    if ($this->has('account_entries', 'reference')) { $a = DB::table('account_entries')->where('reference', 'like', 'OLD-%');
      $add('Accounts', 'Income and expense entries (OLD-)', null, (clone $a)->count());
      $add('Accounts', 'Net balance of imported entries', null, (float)(clone $a)->where('direction', 'income')->sum('amount') - (float)(clone $a)->where('direction', 'expense')->sum('amount'), true); }
    $sup = [];
    if ($this->has('suppliers') && $this->has('purchases') && $this->has('supplier_payments')) {
      $sup = DB::select("select s.id, s.name, coalesce(p.t,0) bought, coalesce(y.t,0) paid, coalesce(p.t,0)-coalesce(y.t,0) due from suppliers s left join (select supplier_id, sum(total) t from purchases group by 1) p on p.supplier_id=s.id left join (select supplier_id, sum(amount) t from supplier_payments where coalesce(status,'paid')='paid' group by 1) y on y.supplier_id=s.id where s.legacy_supplier_id is not null order by due desc");
      $add('Suppliers', 'Total supplier due (purchases − payments)', 11702964, (float)collect($sup)->sum('due'), true);
    }
    $noimg = [];
    if ($this->has('products', 'legacy_product_id')) {
      $q = DB::table('products')->whereNotNull('legacy_product_id')->where(fn($x) => $x->whereNull('image')->orWhere('image', ''));
      $add('Catalogue', 'Imported products without any image', 0, (clone $q)->count());
      $noimg = (clone $q)->orderByDesc('published')->orderBy('id')->limit(80)->get(['id', 'name', 'legacy_product_id', 'published', 'stock']);
    }
    $stat = $this->has('legacy_orders') ? DB::select("select coalesce(l.status::text,'—') s, count(*) n, coalesce(sum(l.total),0) t from legacy_orders l group by 1 order by 2 desc") : [];
    $live = DB::select("select status s, count(*) n, coalesce(sum(total),0) t from orders where legacy_order_id is not null group by 1 order by 2 desc");
    return ['rows' => $rows, 'stat' => $stat, 'live' => $live, 'sup' => $sup, 'noimg' => $noimg];
  }
}
