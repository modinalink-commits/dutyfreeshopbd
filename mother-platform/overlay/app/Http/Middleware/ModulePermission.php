<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ModulePermission {
  const MODULES = ['orders'=>'Orders','products'=>'Products','purchase'=>'Purchase','accounts'=>'Accounts','reports'=>'Reports','customers'=>'Customers','couriers'=>'Couriers','marketing'=>'Marketing and offers','settings'=>'Settings and website','hr'=>'HR and payroll','stafflog'=>'Staff log','costs'=>'Cost, average price and profit (Inventory Pricing, view only)'];
  const LEVELS = ['none'=>0,'view'=>1,'edit'=>2,'all'=>3];
  private static array $cache = [];
  public static function moduleFor(string $path): ?string {
    $p = preg_replace('#^admin/?#', '', trim($path, '/'));
    if ($p === '' || $p === 'logout') return null;
    $m = str_starts_with($p, 'm/') ? explode('/', substr($p, 2))[0] : null;
    $map = ['orders'=>['order-sources','order-comments','r-orders'], 'stafflog'=>['r-staff-log'], 'products'=>['attributes','attribute-values','variants','reviews','stock-log','r-products'], 'purchase'=>['suppliers','purchases','purchase-items','damaged'], 'accounts'=>['accounts','purposes','entries','bills','withdrawals'], 'customers'=>['customers','memberships','points','messages','blocked-ips'], 'couriers'=>['couriers','districts','sub-districts'], 'settings'=>['settings','banners','sliders','pages','posts','stores','channels'], 'marketing'=>['sms-log','email-log','campaigns','r-coupons'], 'hr'=>['employees','payroll'], 'users'=>['users','roles','audit-log']];
    if ($m !== null) { foreach ($map as $mod => $slugs) if (in_array($m, $slugs)) return $mod; return 'users'; }
    if (preg_match('#^purchase(/|$)#', $p)) return 'purchase';
    if (preg_match('#^(referral|winback|voice|app-rewards)(/|$)#', $p)) return 'marketing';
    if (preg_match('#^(seo|commands|announcements|hero-slider|legacy-import|legacy-products|legacy-orders|shurjopay)(/|$)#', $p)) return 'settings';
    if (preg_match('#^delivery(/|$)#', $p)) return 'couriers';
    if (preg_match('#^(fraud|app-users)(/|$)#', $p)) return 'customers';
    if (preg_match('#^customer-auth(/|$)#', $p)) return 'settings';
    if (preg_match('#^dob-requests(/|$)#', $p)) return 'customers';
    if (preg_match('#^sms-marketing/(quick|history)#', $p)) return 'orders';
    if (str_starts_with($p, 'sms-marketing')) return 'marketing';
    if (str_starts_with($p, 'ai-brief-builder')) return 'orders'; // AI Brief Builder: drafts only; staff who work on orders may use it (40 AI runs per person per day)
    if (str_starts_with($p, 'push')) return 'marketing';
    if (str_starts_with($p, 'chat-settings')) return 'settings';
    if (preg_match('#^chat(/|$)#', $p)) return 'orders';
    if (preg_match('#^call-center/(account|extensions)#', $p)) return 'settings'; // SIP server + per-user extension passwords: settings-level only
    if (str_starts_with($p, 'call-center')) return 'orders';
    if (preg_match('#^(orders|orders-export|incomplete|manual-payments)#', $p)) return 'orders';
    if (str_starts_with($p, 'staff-log') || str_starts_with($p, 'staff-tracking')) return 'stafflog';
    if (preg_match('#^inventory-pricing#', $p)) return 'costs'; // purchase / average price and profit: only roles given this permission (Admin always)
    if (preg_match('#^(products|product-order|taxonomy|categories|brands|inventory|image-audit)#', $p)) return 'products';
    if (preg_match('#^(discounts|coupons|deals|payment-methods)#', $p)) return 'marketing';
    if (preg_match('#^(reports|modules|report-details|analytics|report-center)#', $p)) return 'reports';
    // Report pages that sit in the Reports & Analytics menu but used to fall through to the catch-all 'users' permission. Pages only: the AI Brief run/settings actions stay admin-level.
    if (preg_match('#^(order-history-report|profit-report|stock-report|stock-movement-report|purchase-report|supplier-ledger-report|cashbook-report|ai-brief)$#', $p)) return 'reports';
    // Courier work (send/sync/settle/status map/invoices/dashboard/report) belongs to the Couriers permission; only the API-credential screen (/courier, /courier/connect) stays under Settings.
    if (preg_match('#^courier(-dashboard|-report)?(/|$)#', $p) && !preg_match('#^courier(/connect)?$#', $p)) return 'couriers';
    if (preg_match('#^m-districts#', $p)) return 'couriers';
    if (preg_match('#^m-payroll#', $p)) return 'hr';
    if (preg_match('#^m-customers#', $p)) return 'customers';
    if (str_starts_with($p, 'upload-image') || str_starts_with($p, 'site') || str_starts_with($p, 'sms') || str_starts_with($p, 'courier') || str_starts_with($p, 'ai-chat') || str_starts_with($p, 'media') || str_starts_with($p, 'ai-images') || str_starts_with($p, 'stores')) return 'settings';
    if (str_starts_with($p, 'profit-check/order')) return 'orders'; // live "this rate is not allowed" notice while editing an order
    if (str_starts_with($p, 'profit-check')) return 'products'; // same notice on the product form
    if (str_starts_with($p, 'permissions')) return 'users';
    return 'users';
  }
  public static function rules($user): ?array {
    if (in_array($user->role, ['super','admin'])) return null; // full access
    if (!$user->role_id) return ['orders'=>'view'];
    return self::$cache[$user->role_id] ??= (json_decode(DB::table('roles')->where('id',$user->role_id)->value('permissions') ?? '{}', true) ?: []);
  }
  /** Statuses that need the "Restricted status" permission (Permissions page, Orders row). Absent flag = allowed, so existing roles keep working until the owner unticks it. */
  const RESTRICTED_STATUSES = ['Cancel','Returned','Return Pending'];
  public static function canRestricted($user): bool {
    $r = self::rules($user); if ($r === null) return true;
    return $r['orders_restricted_status'] ?? true;
  }
  public static function can($user, ?string $module, string $need = 'view'): bool {
    if ($module === null) return true;
    $r = self::rules($user); if ($r === null) return true;
    if ($module === 'users') return false;
    if ($need === 'download') return !empty($r[$module.'_download']) && self::LEVELS[$r[$module] ?? 'none'] >= 1;
    return self::LEVELS[$r[$module] ?? 'none'] >= self::LEVELS[$need];
  }
  public function handle(Request $request, Closure $next) {
    $u = $request->user(); if (!$u) return $next($request);
    $path = $request->path(); $mod = self::moduleFor($path);
    $need = 'view';
    if (str_contains($path, '/export/') || str_ends_with($path, '.csv')) $need = 'download';
    elseif ($request->isMethod('post')) $need = str_ends_with($path, '/delete') ? 'all' : 'edit';
    if (!self::can($u, $mod, $need)) abort(403, 'Your role does not allow this.');
    return $next($request);
  }
}
