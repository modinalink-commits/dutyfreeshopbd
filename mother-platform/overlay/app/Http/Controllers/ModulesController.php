<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
class ModulesController extends Controller {
  public function index() {
    $mods = [
      'Order Management (18 stages)' => [['orders','order_items','order_status_logs','order_comments','order_sources'], 'Working', '/admin/orders'],
      'Staff Activity and Tracking' => [['order_status_logs','audit_logs'], 'Working', '/admin/staff-log'],
      'Catalog, Categories, Brands' => [['products','categories','brands','product_status_logs'], 'Working', '/admin/products'],
      'Discount Center and Coupons' => [['discount_rules','coupons'], 'Working (rules apply at checkout in the next step)', '/admin/discounts'],
      'Product Setup and Reviews' => [['attributes','attribute_values','product_variants','reviews','product_views'], 'Working', '/admin/m/reviews'],
      'Purchase, Suppliers, Inventory' => [['suppliers','purchases','purchase_items','stock_logs','damaged_products'], 'Working', '/admin/m/suppliers'],
      'Couriers and Delivery' => [['couriers','districts','sub_districts'], 'Working (courier API booking needs keys)', '/admin/m/couriers'],
      'Accounts' => [['money_accounts','expense_purposes','account_entries','bill_statements','withdraw_requests'], 'Working', '/admin/m/accounts'],
      'Customers and Memberships' => [['customers','membership_levels','loyalty_points','contact_messages','blocked_ips'], 'Working', '/admin/m/customers'],
      'Website and Content' => [['settings','banners','sliders','pages','posts'], 'Working', '/admin/site'],
      'Storefront (dutyfreeshopbd.com)' => [['products','orders'], 'Live', '/'],
      'SMS, Email and Campaigns' => [['sms_logs','email_logs','campaigns'], 'Queued only (needs SMS/email provider)', '/admin/m/campaigns'],
      'HR and Payroll' => [['employees','payroll_entries'], 'Working', '/admin/m/employees'],
      'Stores and Sales Channels' => [['stores','sales_channels'], 'Working', '/admin/m/stores'],
      'Users and Permissions' => [['users','roles'], 'Working', '/admin/permissions'],
      'Reports and Documents' => [['audit_logs'], 'Working', '/admin/reports'],
      'AI Automation' => [['audit_logs'], 'Not built yet (needs AI provider decision)', '/admin/modules'],
    ];
    $rows = [];
    foreach ($mods as $name => [$tables, $state, $link]) {
      $n = 0; foreach ($tables as $t) $n += DB::table($t)->count();
      $rows[] = ['name'=>$name, 'tables'=>count($tables), 'records'=>$n, 'state'=>$state, 'link'=>$link];
    }
    return view('modules', compact('rows'));
  }
}
