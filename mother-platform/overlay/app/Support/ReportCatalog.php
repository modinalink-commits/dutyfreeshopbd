<?php
namespace App\Support;

use App\Http\Middleware\ModulePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Report Center catalogue: every report page that exists in the admin today, found by reading the routes, controllers and menus.
 * sync() copies it into report_registry. It only adds or refreshes rows, it never deletes one.
 * Export letters: p = PDF (print to PDF counts), x = Excel, c = CSV, n = Print, d = Word.
 * Nothing here is marked Legacy yet: reports that may overlap carry a duplicate_group and wait for a six-field review.
 */
class ReportCatalog
{
  const GROUPS = [
    'EXE' => 'Executive & Analytics', 'SAL' => 'Sales & Orders', 'PRF' => 'Profit & Margin', 'INV' => 'Inventory & Stock', 'PUR' => 'Purchase & Supplier',
    'CUS' => 'Customers & Marketing', 'CUR' => 'Courier, Delivery & Returns', 'FIN' => 'Finance & Accounts', 'STF' => 'Staff Activity & Operations', 'SYS' => 'AI, Call, SMS & System',
  ];
  /** The four formats every active report should offer (PDF, Excel, CSV, Print). */
  const MIN_EXPORT = ['supports_pdf', 'supports_xlsx', 'supports_csv', 'supports_print'];

  /** [code, name, sub group, type, route path, exports, owner module, duplicate group] */
  private static function rows(): array
  {
    $A = fn($slug) => 'admin/analytics/' . $slug;
    return [
      // ---------- Executive ----------
      ['EXE-001', 'Analytics Dashboard', 'Dashboard', 'dashboard', 'admin/analytics', '', 'Analytics', null],
      // ---------- Sales & Orders ----------
      ['SAL-001', 'Sales Summary', 'Summary', 'grid', $A('sales-summary'), 'pxcn', 'Analytics', null],
      ['SAL-002', 'Daily Sales Report', 'Summary', 'grid', $A('daily-sales'), 'pxcn', 'Analytics', null],
      ['SAL-003', 'Monthly Sales Report', 'Summary', 'grid', $A('monthly-sales'), 'pxcn', 'Analytics', null],
      ['SAL-004', 'Order History', 'Orders', 'grid', 'admin/order-history-report', 'pcn', 'Reports', null],
      ['SAL-005', 'Product Wise Sales Report', 'Breakdowns', 'grid', $A('product-sales'), 'pxcn', 'Analytics', null],
      ['SAL-006', 'Category Wise Sales Report', 'Breakdowns', 'grid', $A('category-sales'), 'pxcn', 'Analytics', null],
      ['SAL-007', 'Discount Report (by type)', 'Breakdowns', 'grid', $A('discount-report'), 'pxcn', 'Analytics', null],
      ['SAL-008', 'Area Orders', 'Breakdowns', 'grid', 'admin/analytics/area-orders', 'c', 'Analytics', null],
      ['SAL-009', 'Orders (Documents)', 'Documents', 'grid', 'admin/m/r-orders', 'pxcd', 'Documents', null],
      // ---------- Profit & Margin ----------
      ['PRF-001', 'Profit / Loss Report', 'Profit & Loss', 'grid', $A('profit-loss'), 'pxcn', 'Analytics', 'profit-loss'],
      ['PRF-002', 'Sale Profit Report', 'Profit & Loss', 'grid', $A('sale-profit'), 'pxcn', 'Analytics', null],
      ['PRF-003', 'Margin Report', 'Margin', 'grid', $A('margin'), 'pxcn', 'Analytics', null],
      ['PRF-004', 'Profit & Loss Statement', 'Profit & Loss', 'document', 'admin/profit-report', 'pcn', 'Reports', 'profit-loss'],
      // ---------- Inventory & Stock ----------
      ['INV-001', 'Product Stock Tracking', 'Stock', 'grid', $A('stock-tracking'), 'pxcn', 'Analytics', 'stock-view'],
      ['INV-002', 'Category Wise Stock Report', 'Stock', 'grid', $A('category-stock'), 'pxcn', 'Analytics', null],
      ['INV-003', 'Stock Log History', 'Movement', 'grid', $A('stock-log'), 'pxcn', 'Analytics', 'stock-movement'],
      ['INV-004', 'Low Stock Report', 'Stock health', 'grid', $A('low-stock'), 'pxcn', 'Analytics', null],
      ['INV-005', 'Out of Stock Report', 'Stock health', 'grid', $A('out-of-stock'), 'pxcn', 'Analytics', null],
      ['INV-006', 'Stock Report', 'Stock', 'grid', 'admin/stock-report', 'pcn', 'Reports', 'stock-view'],
      ['INV-007', 'Stock Movement', 'Movement', 'grid', 'admin/stock-movement-report', 'pcn', 'Reports', 'stock-movement'],
      ['INV-008', 'Damage Report', 'Movement', 'grid', 'admin/stock-movement-report?mode=damage', 'pcn', 'Reports', null],
      ['INV-009', 'Stock & Purchase Summary', 'Summary', 'dashboard', 'admin/report-details', 'pc', 'Reports', null],
      ['INV-010', 'Products (Documents)', 'Documents', 'grid', 'admin/m/r-products', 'pxcd', 'Documents', null],
      // ---------- Purchase & Supplier ----------
      ['PUR-001', 'Purchase Report', 'Purchase', 'grid', $A('purchase-report'), 'pxcn', 'Analytics', 'purchase'],
      ['PUR-002', 'Supplier Report', 'Supplier', 'grid', $A('supplier-report'), 'pxcn', 'Analytics', 'supplier'],
      ['PUR-003', 'Purchase Register', 'Purchase', 'grid', 'admin/purchase-report', 'pcn', 'Reports', 'purchase'],
      ['PUR-004', 'Supplier Ledger', 'Supplier', 'grid', 'admin/supplier-ledger-report', 'pcn', 'Reports', 'supplier'],
      ['PUR-005', 'Purchase Overview', 'Overview', 'dashboard', 'admin/purchase', '', 'Purchase', null],
      ['PUR-006', 'Purchase Reports', 'Purchase', 'grid', 'admin/purchase/reports', 'xcn', 'Purchase', null],
      ['PUR-007', 'Supplier Ledger (Purchase module)', 'Supplier', 'grid', 'admin/purchase/ledger', 'xcn', 'Purchase', 'supplier'],
      // ---------- Customers & Marketing ----------
      ['CUS-001', 'New vs Returning Customer', 'Customers', 'grid', $A('new-returning'), 'pxcn', 'Analytics', null],
      ['CUS-002', 'Repeat Customer Rate', 'Customers', 'grid', $A('repeat-rate'), 'pxcn', 'Analytics', null],
      ['CUS-003', 'Top Customers', 'Customers', 'grid', $A('top-customers'), 'pxcn', 'Analytics', null],
      ['CUS-004', 'Customer Order History', 'Customers', 'grid', $A('customer-orders'), 'pxcn', 'Analytics', null],
      ['CUS-005', 'Win-Back Report', 'Campaigns', 'grid', 'admin/winback/report', '', 'Marketing', null],
      // ---------- Courier, Delivery & Returns ----------
      ['CUR-001', 'Courier Wise Delivery Report', 'Courier', 'grid', $A('courier-wise'), 'pxcn', 'Analytics', 'courier'],
      ['CUR-002', 'Delivered Orders', 'Delivery', 'grid', $A('delivered-orders'), 'pxcn', 'Analytics', null],
      ['CUR-003', 'Returned Orders', 'Returns', 'grid', $A('returned-orders'), 'pxcn', 'Analytics', null],
      ['CUR-004', 'Delivery Success Rate', 'Delivery', 'grid', $A('delivery-success'), 'pxcn', 'Analytics', null],
      ['CUR-005', 'COD Collection', 'COD', 'grid', $A('cod-collection'), 'pxcn', 'Analytics', null],
      ['CUR-006', 'Courier Settlement Report', 'COD', 'grid', $A('courier-settlement'), 'pxcn', 'Analytics', null],
      ['CUR-007', 'Cancel Rate', 'Returns', 'grid', $A('cancel-rate'), 'pxcn', 'Analytics', null],
      ['CUR-008', 'Return Rate', 'Returns', 'grid', $A('return-rate'), 'pxcn', 'Analytics', null],
      ['CUR-009', 'Courier & COD', 'Courier', 'grid', 'admin/courier-report', 'pcn', 'Reports', 'courier'],
      ['CUR-010', 'Courier Dashboard', 'Dashboard', 'dashboard', 'admin/courier-dashboard', 'pxcd', 'Courier', null],
      // ---------- Finance & Accounts ----------
      ['FIN-001', 'Income', 'Income & Expense', 'grid', $A('income'), 'pxcn', 'Analytics', 'cashbook'],
      ['FIN-002', 'Expense', 'Income & Expense', 'grid', $A('expense'), 'pxcn', 'Analytics', 'cashbook'],
      ['FIN-003', 'Receivable', 'Receivable & Payable', 'grid', $A('receivable'), 'pxcn', 'Analytics', null],
      ['FIN-004', 'Payable', 'Receivable & Payable', 'grid', $A('payable'), 'pxcn', 'Analytics', null],
      ['FIN-005', 'Payment Collection', 'Payments', 'grid', $A('payment-collection'), 'pxcn', 'Analytics', null],
      ['FIN-006', 'Income & Expense (Cash Book)', 'Income & Expense', 'grid', 'admin/cashbook-report', 'pcn', 'Reports', 'cashbook'],
      // ---------- Staff Activity & Operations ----------
      ['STF-001', 'Staff Activity & Order Tracking: Dashboard', 'Staff Tracking', 'dashboard', 'admin/staff-tracking/dashboard', '', 'Staff Tracking', null],
      ['STF-002', 'Staff Activity', 'Staff Tracking', 'grid', 'admin/staff-tracking/activity', 'c', 'Staff Tracking', null],
      ['STF-003', 'Order Tracking', 'Staff Tracking', 'grid', 'admin/staff-tracking/orders', '', 'Staff Tracking', null],
      ['STF-004', 'Status Changes', 'Staff Tracking', 'grid', 'admin/staff-tracking/status', 'c', 'Staff Tracking', 'staff-status'],
      ['STF-005', 'Payment Changes', 'Staff Tracking', 'grid', 'admin/staff-tracking/payment', 'c', 'Staff Tracking', null],
      ['STF-006', 'Courier Changes', 'Staff Tracking', 'grid', 'admin/staff-tracking/courier', 'c', 'Staff Tracking', null],
      ['STF-007', 'Price & Discount Changes', 'Staff Tracking', 'grid', 'admin/staff-tracking/price', 'c', 'Staff Tracking', null],
      ['STF-008', 'Customer Communication', 'Staff Tracking', 'grid', 'admin/staff-tracking/comm', 'c', 'Staff Tracking', null],
      ['STF-009', 'Login & Session Activity', 'Staff Tracking', 'grid', 'admin/staff-tracking/logins', 'c', 'Staff Tracking', null],
      ['STF-010', 'Audit Logs', 'Staff Tracking', 'grid', 'admin/staff-tracking/audit', 'c', 'Staff Tracking', null],
      ['STF-011', 'Staff Performance Reports', 'Staff Tracking', 'grid', 'admin/staff-tracking/reports', 'c', 'Staff Tracking', null],
      ['STF-012', 'Live Staff Board', 'Live', 'dashboard', 'admin/staff-log', '', 'Staff', null],
      ['STF-013', 'Staff Order Tracking (Documents)', 'Documents', 'grid', 'admin/m/r-staff-log', 'pxcd', 'Documents', 'staff-status'],
      // ---------- AI, Call, SMS & System ----------
      ['SYS-001', 'Daily AI Report', 'AI', 'dashboard', 'admin/ai-brief', '', 'AI', null],
      ['SYS-002', 'Call History', 'Call', 'grid', 'admin/call-center/history', '', 'Call Center', null],
      ['SYS-003', 'Call Reports', 'Call', 'grid', 'admin/call-center/history?view=report', '', 'Call Center', null],
      ['SYS-004', 'Agent Scoreboard', 'Call', 'grid', 'admin/call-center/agents', '', 'Call Center', null],
      ['SYS-005', 'SMS Dashboard', 'SMS', 'dashboard', 'admin/sms-marketing', '', 'SMS', null],
      ['SYS-006', 'SMS Reports', 'SMS', 'grid', 'admin/sms-marketing/reports', 'c', 'SMS', null],
      ['SYS-007', 'All Documents & Exports', 'Documents', 'dashboard', 'admin/reports', '', 'Documents', null],
    ];
  }

  /** Normalised entries with all registry columns. */
  public static function all(): array
  {
    $out = []; $i = 0;
    foreach (self::rows() as [$code, $name, $sub, $type, $path, $ex, $owner, $dup]) {
      $i++;
      $out[] = [
        'report_code' => $code, 'name' => $name, 'slug' => strtolower($code), 'group_code' => substr($code, 0, 3), 'sub_group' => $sub, 'report_type' => $type,
        'data_source' => $owner, 'route_path' => $path, 'permission_key' => ModulePermission::moduleFor(strtok($path, '?')),
        // Every registered report page also gets the standard Export bar (report_export_bar view), so all four formats are available on each page.
        // native_export keeps what the page itself offers, so the Audit can show both numbers honestly.
        'native_export' => preg_replace('/[^pxcnd]/', '', $ex),
        'supports_pdf' => true, 'supports_xlsx' => true, 'supports_csv' => true, 'supports_docx' => str_contains($ex, 'd'), 'supports_print' => true,
        'duplicate_group' => $dup, 'owner_module' => $owner, 'sort_order' => $i,
      ];
    }
    return $out;
  }

  /** True when the page already has all four formats by itself (no standard Export bar needed). */
  public static function nativeFull(?string $n): bool { $n = (string)$n; return str_contains($n, 'p') && str_contains($n, 'x') && str_contains($n, 'c') && str_contains($n, 'n'); }

  /** Registry rows keyed by path (without query), cached for a minute. One report per row; a path can hold several (e.g. the Damage Report shares a path with Stock Movement). */
  public static function map(): array
  {
    return \Illuminate\Support\Facades\Cache::remember('report_registry_map_v2', 60, function () {
      if (!self::ready() || !Schema::hasColumn('report_registry', 'native_export')) return [];
      $m = [];
      foreach (DB::table('report_registry')->where('is_active', true)->get(['report_code', 'name', 'group_code', 'route_path', 'native_export']) as $r) $m[self::key(strtok($r->route_path, '?'))][] = (array)$r; // plain arrays: the cache does not unserialize objects
      return $m;
    });
  }

  /** The registry row of the page being viewed, or null when this page is not a registered report. */
  public static function current(Request $r)
  {
    if (!$r->isMethod('GET') || $r->ajax() || $r->query('partial')) return null;
    try {
      $rows = self::map()[self::key($r->path())] ?? null; if (!$rows) return null;
      $rows = array_map(fn($a) => (object)$a, $rows);
      foreach ($rows as $x) { $q = (string)(parse_url($x->route_path, PHP_URL_QUERY) ?? ''); if ($q !== '') { parse_str($q, $a); foreach ($a as $k => $v) if ((string)$r->query($k) === (string)$v) return $x; } }
      foreach ($rows as $x) if (!str_contains($x->route_path, '?')) return $x;
      return $rows[0];
    } catch (\Throwable $e) { return null; }
  }

  /** Remembers that the signed-in person opened this report (at most once every 10 minutes per report per session). */
  public static function touch(string $code): void
  {
    try {
      $uid = \Illuminate\Support\Facades\Auth::id(); if (!$uid || !Schema::hasTable('report_views')) return;
      $k = 'rv_' . $code; $last = (int)session($k, 0); if ($last && time() - $last < 600) return;
      session([$k => time()]);
      DB::table('report_views')->updateOrInsert(['user_id' => $uid, 'report_code' => $code], ['viewed_at' => now()]);
    } catch (\Throwable $e) {}
  }

  public static function ready(): bool { try { return Schema::hasTable('report_registry'); } catch (\Throwable $e) { return false; } }

  /**
   * Adds new catalogue rows and refreshes the descriptive fields of existing ones. Never deletes, never touches status flags
   * (is_active, is_legacy, is_hidden_from_sidebar, replacement_report_id) once a row exists, so a later review decision is kept.
   */
  public static function sync(): int
  {
    if (!self::ready()) return 0;
    $n = 0; $now = now(); $hasNative = Schema::hasColumn('report_registry', 'native_export');
    foreach (self::all() as $r) {
      if (!$hasNative) unset($r['native_export']);
      $cur = DB::table('report_registry')->where('report_code', $r['report_code'])->first();
      if ($cur) {
        DB::table('report_registry')->where('id', $cur->id)->update(array_merge(array_diff_key($r, ['report_code' => 1, 'slug' => 1]), ['updated_at' => $now]));
      } else {
        DB::table('report_registry')->insert(array_merge($r, ['created_at' => $now, 'updated_at' => $now])); $n++;
      }
    }
    return $n;
  }

  /** Makes sure the registry matches the catalogue before a Report Center page reads it (runs again whenever the catalogue changes in a deploy). */
  public static function ensure(): void
  {
    if (!self::ready()) return;
    try {
      $h = md5(json_encode(self::all()));
      if (\Illuminate\Support\Facades\Cache::get('report_catalog_hash') !== $h) { self::sync(); \Illuminate\Support\Facades\Cache::forever('report_catalog_hash', $h); \Illuminate\Support\Facades\Cache::forget('report_registry_map_v2'); }
    } catch (\Throwable $e) {}
  }

  /** Normalised key of a path: no leading slash, query string kept (it separates e.g. the Damage Report from Stock Movement). */
  public static function key(string $p): string { return strtolower(trim($p, '/')); }

  /** True when a GET route exists for the path (query string ignored). */
  public static function routeExists(string $path): bool
  {
    try {
      $req = Request::create('/' . trim(strtok($path, '?'), '/'), 'GET');
      return !Route::getRoutes()->match($req)->isFallback; // the site has a catch-all 404 route, so a match alone proves nothing
    } catch (\Throwable $e) { return false; }
  }

  /** Report-like GET routes found in the application, as keys. Parameters and export/helper URLs are left out. */
  public static function discoveredRoutes(): array
  {
    $found = [];
    foreach (Route::getRoutes() as $r) {
      if (!in_array('GET', $r->methods(), true)) continue;
      $u = $r->uri();
      if (!str_starts_with($u, 'admin/') || str_contains($u, '{') || preg_match('#(export|print|csv|xls|partial|area-orders/customers|report-center)#', $u)) continue;
      if (preg_match('#(report|analytics|staff-tracking|ledger|cashbook|courier-dashboard|staff-log|ai-brief$|report-details)#', $u)) $found[self::key($u)] = $u;
    }
    // The staff-tracking pages share one route with a {page} parameter.
    foreach (array_keys(\App\Http\Controllers\StaffTrackingController::PAGES) as $pg) $found[self::key('admin/staff-tracking/' . $pg)] = 'admin/staff-tracking/' . $pg;
    // The analytics pages share one route pattern, so each page in the analytics menu counts as its own report.
    foreach (Analytics::menu() as [$label, $items]) foreach (array_keys($items) as $slug) {
      $found[self::key($slug === 'dashboard' ? 'admin/analytics' : 'admin/analytics/' . $slug)] = 'admin/analytics/' . $slug;
    }
    return $found;
  }
}
