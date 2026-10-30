<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
class ModulesController extends Controller {
  public function index() {
    $mods = [
      'Order Management' => [['orders','order_items','order_status_logs','order_comments','order_sources'], 'Working'],
      'Staff Tracking' => [['order_status_logs','audit_logs','roles'], 'Working'],
      'Catalog and Discounts' => [['products','categories','brands','coupons','discount_rules','product_status_logs'], 'Working'],
      'Product Setup and Reviews' => [['attributes','attribute_values','product_variants','product_images','reviews','product_views'], 'Structure ready'],
      'Purchase and Suppliers' => [['suppliers','purchases','purchase_items','stock_logs','damaged_products'], 'Structure ready'],
      'Couriers and Delivery' => [['couriers','districts','sub_districts'], 'Structure ready'],
      'Accounts' => [['money_accounts','expense_purposes','account_entries','bill_statements','withdraw_requests'], 'Structure ready'],
      'Customers' => [['customers','membership_levels','loyalty_points','contact_messages','blocked_ips'], 'Structure ready'],
      'Website and Content' => [['settings','banners','sliders','pages'], 'Structure ready'],
      'SMS, Email and Campaigns' => [['sms_logs','email_logs','campaigns'], 'Structure ready'],
      'HR and Payroll' => [['employees','payroll_entries'], 'Structure ready'],
      'Sales Channels' => [['sales_channels','stores'], 'Structure ready'],
    ];
    $rows = [];
    foreach ($mods as $name => [$tables, $state]) {
      $n = 0; foreach ($tables as $t) $n += DB::table($t)->count();
      $rows[] = ['name'=>$name, 'tables'=>count($tables), 'records'=>$n, 'state'=>$state];
    }
    return view('modules', compact('rows'));
  }
}
