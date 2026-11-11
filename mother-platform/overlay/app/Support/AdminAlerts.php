<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Admin alert detectors (the bell in the admin). Alerts live in the existing deploy_issues table (DeployHealth::raise/resolve) so there is one Issues Center. Detectors only RAISE/CLEAR alerts; they never change business data. */
class AdminAlerts
{
  /** Run every ~5 minutes from the scheduler worker. */
  public static function tick(): void {
    if (!Cache::add('adminalerts.lock', 1, 280)) return;
    foreach (['orders', 'sms', 'whatsapp'] as $d) { try { self::$d(); } catch (\Throwable $e) {} }
  }

  /** No new order for 3 hours in the daytime while the same 3 hours normally bring orders (average of the last 7 days). */
  private static function orders(): void {
    $h = (int)now('Asia/Dhaka')->format('G'); if ($h < 11 || $h > 23) { DeployHealth::resolve('orders_drop', 'System (night hours)'); return; }
    $now = now(); $recent = DB::table('orders')->where('created_at', '>=', $now->copy()->subHours(3))->count();
    $past = 0; for ($d = 1; $d <= 7; $d++) $past += DB::table('orders')->whereBetween('created_at', [$now->copy()->subDays($d)->subHours(3), $now->copy()->subDays($d)])->count();
    $avg = $past / 7;
    if ($recent === 0 && $avg >= 3) DeployHealth::raise('orders_drop', 'high', 'Orders', 'No new orders for 3 hours', 'Normally about ' . round($avg) . ' orders come in this time window, but none arrived in the last 3 hours.', 'Checkout, the website, an ad campaign or a payment/courier step may have stopped working.', 'Place a test order, check the site and the ad campaigns, and open Deployment Center > Issues.');
    else DeployHealth::resolve('orders_drop', 'System (orders are coming in)');
  }

  /** Five or more failed SMS in the last 30 minutes. */
  private static function sms(): void {
    if (!Schema::hasTable('sms_logs')) return;
    $n = DB::table('sms_logs')->where('status', 'failed')->where('updated_at', '>=', now()->subMinutes(30))->count();
    if ($n >= 5) DeployHealth::raise('sms_failing', 'high', 'SMS', 'SMS sending is failing', $n . ' SMS failed in the last 30 minutes.', 'The SMS provider, its balance/credentials or the sender ID may have a problem.', 'Open SMS settings and the SMS log, check the provider balance and credentials.');
    else DeployHealth::resolve('sms_failing', 'System (SMS is working again)');
  }

  /** Five or more failed outgoing WhatsApp messages in the last 30 minutes. */
  private static function whatsapp(): void {
    if (!Schema::hasTable('wa_messages')) return;
    $n = DB::table('wa_messages')->where('status', 'failed')->where('ts', '>=', now()->subMinutes(30))->count();
    if ($n >= 5) DeployHealth::raise('wa_failing', 'high', 'Messages', 'WhatsApp sending is failing', $n . ' WhatsApp messages failed in the last 30 minutes.', 'The WhatsApp connection, token or message template may have a problem.', 'Open the WhatsApp inbox/settings and check the connection and the last error.');
    else DeployHealth::resolve('wa_failing', 'System (WhatsApp is working again)');
  }
}
