<?php
namespace App\Console\Commands;

use App\Support\Push;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MotherPushWorker extends Command
{
  protected $signature = 'mother:push-worker';
  protected $description = 'Send order-status pushes, support replies and due scheduled campaigns';

  private function cursor(string $k, callable $init): int {
    $v = DB::table('settings')->where('key', $k)->value('value');
    if ($v === null) { $v = (int)$init(); $this->setCursor($k, $v); return -1; }
    return (int)$v;
  }
  private function setCursor(string $k, int $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => (string)$v, 'updated_at' => now(), 'created_at' => now()]); }

  private function customerFor(object $o): ?int {
    if ($o->customer_id) return (int)$o->customer_id;
    return DB::table('customers')->where('phone', $o->phone)->value('id');
  }

  public function handle(): int {
    try { \App\Support\AutoHeal::watchdog('worker'); } catch (\Throwable $e) {} // auto recovery: un-stick the other background loops
    try { if (\App\Support\AutoHeal::begin('orders')) { $this->orders(); } } catch (\Throwable $e) { $this->error('orders: ' . $e->getMessage()); \App\Support\LiveBoard::fail('orders', $e); }
    try { if (\App\Support\AutoHeal::begin('chat')) { $this->chat(); } } catch (\Throwable $e) { $this->error('chat: ' . $e->getMessage()); \App\Support\LiveBoard::fail('chat', $e); }
    try { if (\App\Support\AutoHeal::begin('chatclose')) { \App\Support\Chat::closeIdleTick(); } } catch (\Throwable $e) { $this->error('chatclose: ' . $e->getMessage()); \App\Support\LiveBoard::fail('chatclose', $e); }
    try { \App\Support\ChatRules::tick(); } catch (\Throwable $e) { $this->error('chatrules: ' . $e->getMessage()); }
    try { \App\Support\Chat::closeAnsweredTick(); } catch (\Throwable $e) { $this->error('chatclose2: ' . $e->getMessage()); }
    try { if (\App\Support\AutoHeal::begin('chatbacklog')) { \App\Support\Chat::backlogTick(); } } catch (\Throwable $e) { $this->error('chatbacklog: ' . $e->getMessage()); \App\Support\LiveBoard::fail('chatbacklog', $e); }
    try { if (\App\Support\AutoHeal::begin('pbxline')) { \App\Support\AdminAlerts::pbxLine(); } } catch (\Throwable $e) { $this->error('pbxline: ' . $e->getMessage()); \App\Support\LiveBoard::fail('pbxline', $e); }
    try { if (\App\Support\AutoHeal::begin('chatowner')) { \App\Support\ChatOwner::tick(); } } catch (\Throwable $e) { $this->error('chatowner: ' . $e->getMessage()); \App\Support\LiveBoard::fail('chatowner', $e); }
    try { if (\App\Support\AutoHeal::begin('chatstale')) { \App\Support\Chat::staleTick(); } } catch (\Throwable $e) { $this->error('chatstale: ' . $e->getMessage()); \App\Support\LiveBoard::fail('chatstale', $e); }
    try { \App\Support\SmokeFloor::tick(); } catch (\Throwable $e) { $this->error('smokefloor: ' . $e->getMessage()); }
    try { \App\Support\BlackFriday::tick(); } catch (\Throwable $e) { $this->error('blackfriday: ' . $e->getMessage()); }
    try { if (\App\Support\AutoHeal::begin('bscamp')) { \App\Support\BestSellerCampaign::tick(); } } catch (\Throwable $e) { $this->error('bscamp: ' . $e->getMessage()); \App\Support\LiveBoard::fail('bscamp', $e); }
    try { if (\App\Support\AutoHeal::begin('pubsms')) { \App\Support\PublishNotice::tick(); } } catch (\Throwable $e) { $this->error('pubsms: ' . $e->getMessage()); \App\Support\LiveBoard::fail('pubsms', $e); }
    try { if (\App\Support\AutoHeal::begin('bs-stock')) { \App\Support\BestSellerStock::tick(); } } catch (\Throwable $e) { $this->error('bs-stock: ' . $e->getMessage()); \App\Support\LiveBoard::fail('bs-stock', $e); }
    try { if (\App\Support\AutoHeal::begin('autoprice')) { \App\Support\AutoPrice::tick(); } } catch (\Throwable $e) { $this->error('autoprice: ' . $e->getMessage()); \App\Support\LiveBoard::fail('autoprice', $e); }
    try { if (\App\Support\AutoHeal::begin('spfollow')) { if (\Illuminate\Support\Facades\Cache::add('sp.follow', 1, 55)) \App\Support\SinglePacket::followParents(); } } catch (\Throwable $e) { $this->error('spfollow: ' . $e->getMessage()); \App\Support\LiveBoard::fail('spfollow', $e); }
    try { if (\App\Support\AutoHeal::begin('ailearn')) { \App\Support\AiLearn::tick(); } } catch (\Throwable $e) { $this->error('ailearn: ' . $e->getMessage()); }
    try { if (\App\Support\AutoHeal::begin('aimem')) { \App\Support\AiMemory::dailyTick(); } } catch (\Throwable $e) { $this->error('aimem: ' . $e->getMessage()); \App\Support\LiveBoard::fail('aimem', $e); }
    try { if (\App\Support\AutoHeal::begin('chatsilent')) { \App\Support\Chat::closeSilentTick(); } } catch (\Throwable $e) { $this->error('chatsilent: ' . $e->getMessage()); \App\Support\LiveBoard::fail('chatsilent', $e); }
    try { if (\App\Support\AutoHeal::begin('sched')) { \App\Support\OrderSchedule::tick(); } } catch (\Throwable $e) { $this->error('sched: ' . $e->getMessage()); \App\Support\LiveBoard::fail('sched', $e); }
    try { if (\App\Support\AutoHeal::begin('sdesc')) { \App\Support\SourceDesc::tick(); } } catch (\Throwable $e) { $this->error('sdesc: ' . $e->getMessage()); \App\Support\LiveBoard::fail('sdesc', $e); }
    try { if (\App\Support\AutoHeal::begin('pathao-settle')) { \App\Support\PathaoSettleCheck::tick(); } } catch (\Throwable $e) { $this->error('pathao-settle: ' . $e->getMessage()); }
    try { if (\App\Support\AutoHeal::begin('weekly-audit')) { \App\Support\WeeklyAudit::tick(); } } catch (\Throwable $e) { $this->error('weekly-audit: ' . $e->getMessage()); }
    try { if (\App\Support\AutoHeal::begin('svcbal')) { \App\Support\ServiceBalances::tick(); } } catch (\Throwable $e) { $this->error('svcbal: ' . $e->getMessage()); \App\Support\LiveBoard::fail('svcbal', $e); }
    try { if (\App\Support\AutoHeal::begin('cancel-notice')) { \App\Support\OrderCancelNotice::tick(); } } catch (\Throwable $e) { $this->error('cancel-notice: ' . $e->getMessage()); \App\Support\LiveBoard::fail('cancel-notice', $e); }
    try { if (\App\Support\AutoHeal::begin('wa-update')) { $this->waUpdates(); \App\Support\OrderWaUpdate::tick(); } } catch (\Throwable $e) { $this->error('wa-update: ' . $e->getMessage()); \App\Support\LiveBoard::fail('wa-update', $e); }
    try { \App\Support\MarineAis::ensureLoop(); } catch (\Throwable $e) { $this->error('marine-ais: ' . $e->getMessage()); } // marine site: keep the AIS vessel data loop alive
    try { \App\Support\MarineWeather::ensure(); } catch (\Throwable $e) { $this->error('marine-weather: ' . $e->getMessage()); } // marine site: Port Weather refresh when due
    \App\Support\DeployHealth::beat(); // heartbeat: shown as "Scheduler" in the Deployment Center
    try { if (\App\Support\AutoHeal::begin('deploy')) { \App\Support\Deployer::tick(); } } catch (\Throwable $e) { $this->error('deploy: ' . $e->getMessage()); \App\Support\LiveBoard::fail('deploy', $e); }
    try { if (\App\Support\AutoHeal::begin('alerts')) { \App\Support\AdminAlerts::tick(); } } catch (\Throwable $e) { $this->error('alerts: ' . $e->getMessage()); \App\Support\LiveBoard::fail('alerts', $e); }
    try { if (\App\Support\AutoHeal::begin('relaywatch')) { \App\Http\Controllers\CarrierClientController::relayWatch(); } } catch (\Throwable $e) { $this->error('relaywatch: ' . $e->getMessage()); \App\Support\LiveBoard::fail('relaywatch', $e); }
    try { if (\App\Support\AutoHeal::begin('health')) { \App\Support\DeployHealth::scan(); } } catch (\Throwable $e) { $this->error('health: ' . $e->getMessage()); \App\Support\LiveBoard::fail('health', $e); }
    try { if (\App\Support\AutoHeal::begin('campaigns')) { $this->campaigns(); } } catch (\Throwable $e) { $this->error('campaigns: ' . $e->getMessage()); \App\Support\LiveBoard::fail('campaigns', $e); }
    try { if (\App\Support\AutoHeal::begin('wacalls')) { \App\Support\WaCalls::tick(); } } catch (\Throwable $e) { $this->error('wacalls: ' . $e->getMessage()); \App\Support\LiveBoard::fail('wacalls', $e); }
    try { if (\App\Support\AutoHeal::begin('warec')) { \App\Support\WaRec::tick(); } } catch (\Throwable $e) { $this->error('warec: ' . $e->getMessage()); \App\Support\LiveBoard::fail('warec', $e); }
    try { if (\App\Support\AutoHeal::begin('fbcomments')) { \App\Support\ChatComments::tick(); } } catch (\Throwable $e) { $this->error('fbcomments: ' . $e->getMessage()); \App\Support\LiveBoard::fail('fbcomments', $e); }
    try { if (\App\Support\AutoHeal::begin('email')) { \App\Support\Mailer::tick(); } } catch (\Throwable $e) { $this->error('email: ' . $e->getMessage()); \App\Support\LiveBoard::fail('email', $e); }
    try { if (\App\Support\AutoHeal::begin('sms-report')) { \App\Support\SmsGate::tick(); } } catch (\Throwable $e) { $this->error('sms-report: ' . $e->getMessage()); \App\Support\LiveBoard::fail('sms-report', $e); }
    try { if (\App\Support\AutoHeal::begin('sms')) { \App\Support\SmsMarketing::tick(); } } catch (\Throwable $e) { $this->error('sms: ' . $e->getMessage()); \App\Support\LiveBoard::fail('sms', $e); }
    try { if (\App\Support\AutoHeal::begin('winback')) { \App\Support\Winback::tick(); } } catch (\Throwable $e) { $this->error('winback: ' . $e->getMessage()); \App\Support\LiveBoard::fail('winback', $e); }
    try { if (\App\Support\AutoHeal::begin('reorder_reminder')) { \App\Support\CustomerReorder::tick(); } } catch (\Throwable $e) { $this->error('reorder_reminder: ' . $e->getMessage()); \App\Support\LiveBoard::fail('reorder_reminder', $e); }
    try { \App\Support\AddressParse::sweep(); } catch (\Throwable $e) {}
    try { \App\Support\IndexNow::tick(); } catch (\Throwable $e) {}
    try { \App\Support\IncompleteWa::tick(); } catch (\Throwable $e) {}
    try { if (\App\Support\AutoHeal::begin('courier_kam')) { \App\Support\CourierKam::tick(); } } catch (\Throwable $e) { $this->error('courier_kam: ' . $e->getMessage()); \App\Support\LiveBoard::fail('courier_kam', $e); }
    try { if (\App\Support\AutoHeal::begin('wa')) { \App\Support\WhatsApp::tick(); } } catch (\Throwable $e) { $this->error('wa: ' . $e->getMessage()); \App\Support\LiveBoard::fail('wa', $e); }
    // 21 Nov 2026: customers' order-confirmation calls (AI approval) go before the promotional campaign calls so a busy campaign never delays them
    try { if (\App\Support\AutoHeal::begin('ordercall')) { \App\Support\OrderCall::tick(); } } catch (\Throwable $e) { $this->error('ordercall: ' . $e->getMessage()); \App\Support\LiveBoard::fail('ordercall', $e); }
    try { if (\App\Support\AutoHeal::begin('voice')) { \App\Support\Voice::ensureLoop(); } } catch (\Throwable $e) { $this->error('voice: ' . $e->getMessage()); \App\Support\LiveBoard::fail('voice', $e); }
    try { if (\App\Support\AutoHeal::begin('seo')) { \App\Support\SeoMgr::tick(); } } catch (\Throwable $e) { $this->error('seo: ' . $e->getMessage()); \App\Support\LiveBoard::fail('seo', $e); }
    try { if (\App\Support\AutoHeal::begin('seoauto')) { \App\Support\SeoAutoGenerator::backfillOnce(); } } catch (\Throwable $e) { $this->error('seoauto: ' . $e->getMessage()); \App\Support\LiveBoard::fail('seoauto', $e); }
    try { if (\App\Support\AutoHeal::begin('appreward')) { \App\Support\AppReward::resolve(); } } catch (\Throwable $e) { $this->error('appreward: ' . $e->getMessage()); \App\Support\LiveBoard::fail('appreward', $e); }
    try { if (\App\Support\AutoHeal::begin('referral')) { \App\Support\Referral::tick(); } } catch (\Throwable $e) { $this->error('referral: ' . $e->getMessage()); \App\Support\LiveBoard::fail('referral', $e); }
    try { if (\App\Support\AutoHeal::begin('aibrief')) { \App\Support\AiBrief::tick(); } } catch (\Throwable $e) { $this->error('aibrief: ' . $e->getMessage()); \App\Support\LiveBoard::fail('aibrief', $e); }
    try { if (\App\Support\AutoHeal::begin('aiimg')) { \App\Support\AiImageAuto::tick(); } } catch (\Throwable $e) { $this->error('aiimg: ' . $e->getMessage()); \App\Support\LiveBoard::fail('aiimg', $e); }
    try { if (\App\Support\AutoHeal::begin('aiverify')) { \App\Support\AiVerify::rerunStale(); } } catch (\Throwable $e) { $this->error('aiverify: ' . $e->getMessage()); \App\Support\LiveBoard::fail('aiverify', $e); }
    try { if (\App\Support\AutoHeal::begin('autoblog')) { \App\Support\AutoBlog::tick(); } } catch (\Throwable $e) { $this->error('autoblog: ' . $e->getMessage()); \App\Support\LiveBoard::fail('autoblog', $e); }
    try { if (\App\Support\AutoHeal::begin('ainudge')) { \App\Support\AiNudge::tick(); } } catch (\Throwable $e) { $this->error('ainudge: ' . $e->getMessage()); \App\Support\LiveBoard::fail('ainudge', $e); }
    try { if (\App\Support\AutoHeal::begin('incnudge')) { \App\Support\IncompleteNudge::tick(); } } catch (\Throwable $e) { $this->error('incnudge: ' . $e->getMessage()); \App\Support\LiveBoard::fail('incnudge', $e); }
    try { if (\App\Support\AutoHeal::begin('reorder')) { \App\Support\Reorder::tick(); } } catch (\Throwable $e) { $this->error('reorder: ' . $e->getMessage()); \App\Support\LiveBoard::fail('reorder', $e); }
    try { if (\App\Support\AutoHeal::begin('purchase-list')) { \App\Support\PurchaseList::tick(); } } catch (\Throwable $e) { $this->error('purchase-list: ' . $e->getMessage()); \App\Support\LiveBoard::fail('purchase-list', $e); }
    try { if (\App\Support\AutoHeal::begin('restock')) { \App\Support\Restock::tick(); } } catch (\Throwable $e) { $this->error('restock: ' . $e->getMessage()); \App\Support\LiveBoard::fail('restock', $e); }
    try { if (\App\Support\AutoHeal::begin('order-watch')) { \App\Support\OrderWatch::tick(); } } catch (\Throwable $e) { $this->error('order-watch: ' . $e->getMessage()); \App\Support\LiveBoard::fail('order-watch', $e); }
    try { if (\App\Support\AutoHeal::begin('call-daily')) { \App\Support\CallDaily::tick(); } } catch (\Throwable $e) { $this->error('call-daily: ' . $e->getMessage()); \App\Support\LiveBoard::fail('call-daily', $e); }
    \App\Support\PayrollDue::tick();
    try { if (\App\Support\AutoHeal::begin('recurring')) { \App\Support\RecurringExpense::tick(); } } catch (\Throwable $e) { $this->error('recurring: ' . $e->getMessage()); \App\Support\LiveBoard::fail('recurring', $e); }
    try { if (\App\Support\AutoHeal::begin('metaexp')) { \App\Support\MetaAdExpense::tick(); } } catch (\Throwable $e) { $this->error('metaexp: ' . $e->getMessage()); \App\Support\LiveBoard::fail('metaexp', $e); }
    try { if (\App\Support\AutoHeal::begin('procure')) { \App\Support\Procure::tick(); } } catch (\Throwable $e) { $this->error('procure: ' . $e->getMessage()); \App\Support\LiveBoard::fail('procure', $e); }
    try { if (\App\Support\AutoHeal::begin('smsauto')) { $this->smsAuto(); } } catch (\Throwable $e) { $this->error('smsauto: ' . $e->getMessage()); \App\Support\LiveBoard::fail('smsauto', $e); }
    \App\Support\AutoHeal::endOfRun();
    return 0;
  }

  private function orders(): void {
    $cur = $this->cursor('push_log_cursor', fn() => (int)DB::table('order_status_logs')->max('id')); if ($cur < 0) return;
    $logs = DB::table('order_status_logs')->where('id', '>', $cur)->orderBy('id')->limit(100)->get(); if ($logs->isEmpty()) return;
    foreach ($logs as $l) {
      $cur = $l->id;
      $o = DB::table('orders')->find($l->order_id); if (!$o || ($l->user_name === 'Website' && false)) continue;
      $cid = $this->customerFor($o); if (!$cid) continue;
      $st = (string)$l->to_status; $inv = $o->invoice; $url = '/account/orders/' . $inv;
      if (str_starts_with((string)$l->note, 'Payment Verified')) { Push::notify($cid, 'payment_verified', 'Payment Verified', "আপনার অর্ডার $inv-এর পেমেন্ট যাচাই হয়েছে।", $url); continue; }
      if ($l->from_status === $l->to_status) continue;
      $map = ['New' => ['order_received', 'Order Received', "আপনার অর্ডার $inv গ্রহণ করা হয়েছে। ধন্যবাদ!"], 'Approved' => ['order_confirmed', 'Order Confirmed', "আপনার অর্ডার $inv কনফার্ম হয়েছে।"], 'Packaging' => ['order_packed', 'Order Packed', "আপনার অর্ডার $inv প্যাক করা হচ্ছে।"], 'Shipment' => ['order_shipped', 'Order Shipped', "আপনার অর্ডার $inv কুরিয়ারে দেওয়া হয়েছে।"], 'Delivered' => ['order_delivered', 'Order Delivered', "আপনার অর্ডার $inv ডেলিভারি সম্পন্ন হয়েছে। কেনাকাটার জন্য ধন্যবাদ!"]];
      if (str_contains(strtolower((string)$o->courier_status), 'out for delivery') && $st === 'Shipment') $map['Shipment'] = ['out_for_delivery', 'Out for Delivery', "আপনার অর্ডার $inv আজ ডেলিভারির জন্য বের হয়েছে।"];
      if (!isset($map[$st])) continue;
      [$type, $title, $body] = $map[$st];
      Push::notify($cid, $type, $title, $body, $url);
    }
    $this->setCursor('push_log_cursor', (int)$cur);
  }

  /** WhatsApp "handed to courier" / "delivered" updates. Starts from the newest log row, so old orders never get a late message. */
  private function waUpdates(): void {
    $cur = $this->cursor('wa_upd_cursor', fn() => (int)DB::table('order_status_logs')->max('id')); if ($cur < 0) return;
    $logs = DB::table('order_status_logs')->where('id', '>', $cur)->orderBy('id')->limit(100)->get(['id', 'order_id', 'from_status', 'to_status']); if ($logs->isEmpty()) return;
    foreach ($logs as $l) { $cur = $l->id; if ($l->from_status !== $l->to_status && isset(\App\Support\OrderWaUpdate::STAGES[(string)$l->to_status])) \App\Support\OrderWaUpdate::onStatus((int)$l->order_id, (string)$l->to_status); }
    $this->setCursor('wa_upd_cursor', (int)$cur);
  }

  private function chat(): void {
    $cur = $this->cursor('push_chat_cursor', fn() => (int)DB::table('chat_messages')->max('id')); if ($cur < 0) return;
    $ms = DB::table('chat_messages as m')->join('chat_threads as t', 't.id', '=', 'm.thread_id')->where('m.id', '>', $cur)->where('m.dir', 'out')->where('t.channel', 'web')->orderBy('m.id')->limit(100)->get(['m.id', 'm.body', 't.customer_id', 't.phone']);
    $max = $cur;
    $seen = [];
    foreach (DB::table('chat_messages')->where('id', '>', $cur)->orderBy('id')->limit(100)->pluck('id') as $i) $max = max($max, $i);
    foreach ($ms as $m) {
      $cid = $m->customer_id ?: ($m->phone ? DB::table('customers')->where('phone', $m->phone)->value('id') : null); if (!$cid || isset($seen[$cid])) continue; $seen[$cid] = 1;
      Push::notify((int)$cid, 'support_reply', 'Support Reply', mb_substr((string)$m->body, 0, 140) ?: 'আপনার জন্য একটি নতুন মেসেজ আছে।', '/account/support');
    }
    $this->setCursor('push_chat_cursor', (int)$max);
  }

  private function campaigns(): void {
    foreach (DB::table('push_campaigns')->whereIn('status', ['scheduled', 'sending'])->where(fn($q) => $q->whereNull('schedule_at')->orWhere('schedule_at', '<=', now()))->orderBy('id')->limit(3)->pluck('id') as $id) Push::runCampaign((int)$id, 200);
  }

  /** SMS automations: order status changes, new customers, incomplete-order recovery, win-back. */
  private function smsAuto(): void {
    if (!\Illuminate\Support\Facades\Schema::hasTable('sms_automations') || !DB::table('sms_automations')->where('active', true)->exists()) return;
    $M = \App\Support\SmsMarketing::class;
    $cur = $this->cursor('sms_log_cursor', fn() => (int)DB::table('order_status_logs')->max('id'));
    if ($cur >= 0) {
      $map = ['Approved' => 'order_confirmed', 'Packaging' => 'order_processing', 'Shipment' => 'order_shipped', 'Delivered' => 'order_delivered', 'Cancel' => 'order_cancelled', 'Returned' => 'order_returned'];
      foreach (DB::table('order_status_logs')->where('id', '>', $cur)->orderBy('id')->limit(100)->get() as $l) {
        $cur = $l->id; $o = DB::table('orders')->find($l->order_id); if (!$o || !$o->phone) continue;
        $v = ['customer_name' => $o->customer_name, 'order_id' => \App\Support\Inv::show($o->invoice), 'invoice' => \App\Support\Inv::show($o->invoice), 'total' => number_format((float)$o->total), 'tracking_id' => (string)($o->consignment_id ?? ''), 'delivery_status' => (string)($o->courier_status ?? $o->status), 'discount' => number_format((float)($o->discount ?? 0))];
        if (str_starts_with((string)$l->note, 'Payment Verified')) { $M::auto('payment_verified', $o->phone, $v, 'pv'.$o->id, (int)$o->id); continue; }
        if ($l->from_status !== $l->to_status && isset($map[$l->to_status])) $M::auto($map[$l->to_status], $o->phone, $v, $map[$l->to_status].$o->id, (int)$o->id);
      }
      $this->setCursor('sms_log_cursor', (int)$cur);
    }
    $cc = $this->cursor('sms_cust_cursor', fn() => (int)DB::table('customers')->max('id'));
    if ($cc >= 0) {
      foreach (DB::table('customers')->where('id', '>', $cc)->orderBy('id')->limit(50)->get() as $c) { $cc = $c->id; if ($c->phone) $M::auto('welcome', $c->phone, ['customer_name' => $c->name], 'w'.$c->id, null, (int)$c->id); }
      $this->setCursor('sms_cust_cursor', (int)$cc);
    }
    $rec = null; // Incomplete checkouts never get any message to the customer (owner rule), so the recovery SMS is switched off here.
    if ($rec) {
      $mins = max(15, (int)($rec->param ?: 60));
      foreach (DB::table('incomplete_orders')->where('status', 'open')->where('last_activity_at', '<=', now()->subMinutes($mins))->where('last_activity_at', '>=', now()->subDay())->limit(30)->get() as $i)
        $M::auto('incomplete_recovery', $i->phone, ['customer_name' => $i->name ?: 'Customer', 'total' => number_format((float)$i->total), 'checkout_link' => url('/checkout')], 'ir'.$i->id);
    }
    $in = DB::table('sms_automations')->where('trigger', 'inactive')->where('active', true)->first();
    if ($in && $this->cursor('sms_inactive_day', fn() => 0) !== (int)date('Ymd')) {
      $this->setCursor('sms_inactive_day', (int)date('Ymd'));
      foreach ($M::audience([], ['no_order_days' => max(1, (int)($in->param ?: 90)), 'min_orders' => 1]) as $r) $M::auto('inactive', $r->num, $M::vars($r), 'in'.$r->id.date('Ym'), null, $r->id);
    }
  }
}
