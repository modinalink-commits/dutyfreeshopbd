<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Old site (staged copy) versus the live tables, so the move can be checked number by number. */
class MigrationDashboardController extends Controller {
  private function has(string $t, ?string $c = null): bool { return Schema::hasTable($t) && ($c === null || Schema::hasColumn($t, $c)); }
  public function index() {
    $n = fn($v) => number_format((float)$v); $m = fn($v) => '৳' . number_format((float)$v);
    $rows = []; $add = function ($group, $label, $old, $new, $money = false) use (&$rows, $n, $m) {
      $f = $money ? $m : $n; $diff = ($old === null || $new === null) ? null : (float)$new - (float)$old;
      $rows[] = ['g' => $group, 'l' => $label, 'old' => $old === null ? '—' : $f($old), 'new' => $new === null ? '—' : $f($new), 'diff' => $diff === null ? '—' : ($diff == 0 ? 'OK' : ($diff > 0 ? '+' : '−') . $f(abs($diff))), 'ok' => $diff !== null && abs($diff) < 0.5];
    };
    // Customers
    if ($this->has('legacy_customers')) {
      $stg = DB::table('legacy_customers')->count(); $real = DB::table('legacy_customers')->where(fn($q) => $q->whereNull('merged')->orWhere('merged', false))->count();
      $add('Customers', 'Customer rows staged from old site', $stg, $stg); $add('Customers', 'Customers in the new system', $real, DB::table('customers')->count());
      $add('Customers', 'Customers carrying old order history', DB::table('legacy_customers')->where('orders_count', '>', 0)->count(), DB::table('customers')->where('legacy_orders', '>', 0)->count());
    }
    // Orders
    if ($this->has('legacy_orders')) {
      $so = DB::table('legacy_orders'); $skipped = (clone $so)->where('action', 'skip')->count();
      $add('Orders', 'Orders staged from old site', (clone $so)->count(), (clone $so)->count());
      $imp = DB::table('orders')->whereNotNull('legacy_order_id');
      $add('Orders', 'Old orders now in the new system', (clone $so)->where(fn($q) => $q->whereNull('action')->orWhere('action', '<>', 'skip'))->count(), (clone $imp)->count());
      $add('Orders', 'Order value (total of old orders)', (float)(clone $so)->where(fn($q) => $q->whereNull('action')->orWhere('action', '<>', 'skip'))->sum('total'), (float)(clone $imp)->sum('total'), true);
      $add('Orders', 'Order lines (items) of old orders', null, DB::table('order_items')->whereIn('order_id', DB::table('orders')->whereNotNull('legacy_order_id')->select('id'))->count());
      $add('Orders', 'Skipped on import (bad / duplicate rows)', $skipped, null);
      $add('Orders', 'Orders created on the new site (not from old)', null, DB::table('orders')->whereNull('legacy_order_id')->count());
    }
    // Reviews / products
    if ($this->has('reviews')) $add('Catalogue', 'Reviews', 22, DB::table('reviews')->count());
    if ($this->has('products', 'legacy_product_id')) $add('Catalogue', 'Products with an old-site ID', null, DB::table('products')->whereNotNull('legacy_product_id')->count());
    // Suppliers
    if ($this->has('suppliers', 'legacy_supplier_id')) $add('Suppliers', 'Suppliers from old site', 40, DB::table('suppliers')->whereNotNull('legacy_supplier_id')->count());
    if ($this->has('purchases', 'legacy_purchase_id')) { $p = DB::table('purchases')->whereNotNull('legacy_purchase_id'); $add('Suppliers', 'Purchase invoices', 1892, (clone $p)->count());
      $add('Suppliers', 'Purchase value', 323800000, (float)(clone $p)->sum('total'), true); $add('Suppliers', 'Paid to suppliers', 312100000, (float)(clone $p)->sum('paid'), true); }
    // Accounts
    if ($this->has('account_entries', 'reference')) { $a = DB::table('account_entries')->where('reference', 'like', 'OLD-%');
      $add('Accounts', 'Income and expense entries', 3648, (clone $a)->count());
      $add('Accounts', 'Net balance of imported entries', null, (float)(clone $a)->where('direction', 'income')->sum('amount') - (float)(clone $a)->where('direction', 'expense')->sum('amount'), true); }
    $stat = $this->has('legacy_orders') ? DB::select("select coalesce(l.status,'—') s, count(*) n, coalesce(sum(l.total),0) t from legacy_orders l group by 1 order by 2 desc") : [];
    $live = DB::select("select status s, count(*) n, coalesce(sum(total),0) t from orders where legacy_order_id is not null group by 1 order by 2 desc");
    return view('migration_dashboard', ['rows' => $rows, 'stat' => $stat, 'live' => $live]);
  }
}
