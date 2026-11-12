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
    foreach (['orders', 'sms', 'whatsapp', 'courier', 'payments', 'deploystuck'] as $d) { try { self::$d(); } catch (\Throwable $e) {} }
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

  /** Courier: the parcel-status sync has not run for 60+ minutes while parcels are open, or Pathao sent a status nobody mapped yet. */
  private static function courier(): void {
    $open = DB::table('orders')->whereNotNull('consignment_id')->where('status', 'Shipment')->count();
    $last = Cache::get('courier.last_sync'); $age = $last ? now()->diffInMinutes(\Carbon\Carbon::parse($last)) : null;
    if ($open > 0 && ($age === null || $age >= 60)) DeployHealth::raise('courier_sync_stale', 'high', 'Courier', 'Courier status sync is not running', $open . ' parcels are in Shipment but their courier status was ' . ($age === null ? 'never' : $age . ' minutes ago') . ' updated.', 'The courier API (credentials, token expiry or the courier service) or the background worker may have stopped.', 'Open Courier settings, check the API connection, then run a manual courier sync.');
    else DeployHealth::resolve('courier_sync_stale', 'System (courier sync is running)');
    $m = DB::table('orders')->where('courier_flag', 'Courier Status Mapping Required')->count();
    if ($m > 0) DeployHealth::raise('courier_unmapped', 'warning', 'Courier', 'Courier sent an unknown status', $m . ' orders have a courier status that is not mapped.', 'The courier introduced a new status word.', 'Map the status in the courier status map so these orders update correctly.');
    else DeployHealth::resolve('courier_unmapped', 'System (all statuses mapped)');
  }

  /** Ads: Meta Conversions API (CAPI) sends (capi_log) – most of the recent ones failed, so ad tracking is losing events. */
  private static function ads(): void {
    if (!\App\Support\Capi::enabled()) { DeployHealth::resolve('capi_failing', 'System (CAPI off)'); return; }
    $a = json_decode((string)DB::table('settings')->where('key', 'capi_log')->value('value'), true) ?: [];
    $cut = now('Asia/Dhaka')->subMinutes(60); $recent = [];
    foreach ($a as $x) { try { $t = \Carbon\Carbon::createFromFormat('d M H:i:s', (string)($x['t'] ?? ''), 'Asia/Dhaka'); } catch (\Throwable $e) { continue; } if ($t && $t->gte($cut)) $recent[] = $x; }
    $bad = count(array_filter($recent, fn($x) => empty($x['ok'])));
    if (count($recent) >= 5 && $bad / count($recent) >= 0.6) { $last = collect($recent)->first(fn($x) => empty($x['ok'])); DeployHealth::raise('capi_failing', 'high', 'Ads', 'Meta tracking (CAPI) is failing', $bad . ' of the last ' . count($recent) . ' server events to Meta failed in the past hour. Last error: ' . mb_substr((string)($last['m'] ?? ''), 0, 120), 'The Meta access token may have expired or the pixel id is wrong, so ads lose purchase data.', 'Open Pixel settings, paste a fresh Conversions API token and send a test event.'); }
    else DeployHealth::resolve('capi_failing', 'System (CAPI sends are succeeding)');
  }

  /** Payment: manual payments waiting for verification for more than 24 hours (5 or more). */
  private static function payments(): void {
    if (!Schema::hasTable('order_payments')) return;
    $n = DB::table('order_payments')->where('status', 'pending')->where('created_at', '<', now()->subHours(24))->count();
    if ($n >= 5) DeployHealth::raise('payments_unverified', 'warning', 'Payment', 'Payments waiting for verification', $n . ' payments have been pending verification for more than 24 hours.', 'Staff have not verified or rejected the submitted payments.', 'Open the pending payments list and verify or reject them.');
    else DeployHealth::resolve('payments_unverified', 'System (payments are being verified)');
  }

  /** A release that has sat in an active state (Queued, Downloading, Building...) for 15+ minutes: the host-side deploy worker is stuck. */
  private static function deploystuck(): void {
    $row = DB::table('deployments')->whereIn('status', \App\Support\Deployer::ACTIVE)->where('updated_at', '<', now()->subMinutes(15))->orderByDesc('id')->first();
    if ($row) DeployHealth::raise('deploy_stuck', 'high', 'Deploy', 'A release is stuck', 'Release ' . ($row->version ?: $row->sha) . ' has been "' . $row->status . '" for more than 15 minutes.', 'The host-side deploy worker is not picking up the request.', 'Open Deployment Center, cancel the pending release and run Quick Deploy; if it stays stuck restart the motherplatform project in Docker Manager.');
    else DeployHealth::resolve('deploy_stuck', 'System (no stuck release)');
    $hb = '/srv/deploy/hb';
    if (is_file($hb) && time() - filemtime($hb) > 300 && !\App\Support\Deployer::lockHeld()) DeployHealth::raise('deploy_worker_dead', 'high', 'Deploy', 'Deploy worker stopped', 'The background deploy worker has not responded for over 5 minutes and no release is running.', 'The worker process inside the app container was killed; its watchdog should restart it automatically.', 'If this stays open, restart the motherplatform project in Docker Manager.');
    else DeployHealth::resolve('deploy_worker_dead', 'System (deploy worker alive)');
  }
}
