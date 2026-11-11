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
    try { $this->orders(); } catch (\Throwable $e) { $this->error('orders: ' . $e->getMessage()); }
    try { $this->chat(); } catch (\Throwable $e) { $this->error('chat: ' . $e->getMessage()); }
    try { \App\Support\Chat::closeIdleTick(); } catch (\Throwable $e) { $this->error('chatclose: ' . $e->getMessage()); }
    try { \App\Support\Chat::backlogTick(); } catch (\Throwable $e) { $this->error('chatbacklog: ' . $e->getMessage()); }
    try { \App\Support\Chat::closeSilentTick(); } catch (\Throwable $e) { $this->error('chatsilent: ' . $e->getMessage()); }
    try { \App\Support\OrderSchedule::tick(); } catch (\Throwable $e) { $this->error('sched: ' . $e->getMessage()); }
    try { \App\Support\OrderCancelNotice::tick(); } catch (\Throwable $e) { $this->error('cancel-notice: ' . $e->getMessage()); }
    \App\Support\DeployHealth::beat(); // heartbeat: shown as "Scheduler" in the Deployment Center
    try { \App\Support\Deployer::tick(); } catch (\Throwable $e) { $this->error('deploy: ' . $e->getMessage()); }
    try { \App\Support\DeployHealth::scan(); } catch (\Throwable $e) { $this->error('health: ' . $e->getMessage()); }
    try { $this->campaigns(); } catch (\Throwable $e) { $this->error('campaigns: ' . $e->getMessage()); }
    try { \App\Support\SmsMarketing::tick(); } catch (\Throwable $e) { $this->error('sms: ' . $e->getMessage()); }
    try { \App\Support\Winback::tick(); } catch (\Throwable $e) { $this->error('winback: ' . $e->getMessage()); }
    try { \App\Support\WhatsApp::tick(); } catch (\Throwable $e) { $this->error('wa: ' . $e->getMessage()); }
    try { \App\Support\Voice::tick(); } catch (\Throwable $e) { $this->error('voice: ' . $e->getMessage()); }
    try { \App\Support\SeoMgr::tick(); } catch (\Throwable $e) { $this->error('seo: ' . $e->getMessage()); }
    try { \App\Support\SeoAutoGenerator::backfillOnce(); } catch (\Throwable $e) { $this->error('seoauto: ' . $e->getMessage()); }
    try { \App\Support\AppReward::resolve(); } catch (\Throwable $e) { $this->error('appreward: ' . $e->getMessage()); }
    try { \App\Support\Referral::tick(); } catch (\Throwable $e) { $this->error('referral: ' . $e->getMessage()); }
    try { \App\Support\AiBrief::tick(); } catch (\Throwable $e) { $this->error('aibrief: ' . $e->getMessage()); }
    try { \App\Support\AiImageAuto::tick(); } catch (\Throwable $e) { $this->error('aiimg: ' . $e->getMessage()); }
    try { \App\Support\AiVerify::rerunStale(); } catch (\Throwable $e) { $this->error('aiverify: ' . $e->getMessage()); }
    try { \App\Support\AiNudge::tick(); } catch (\Throwable $e) { $this->error('ainudge: ' . $e->getMessage()); }
    try { \App\Support\IncompleteNudge::tick(); } catch (\Throwable $e) { $this->error('incnudge: ' . $e->getMessage()); }
    try { \App\Support\Reorder::tick(); } catch (\Throwable $e) { $this->error('reorder: ' . $e->getMessage()); }
    \App\Support\PayrollDue::tick();
    try { \App\Support\RecurringExpense::tick(); } catch (\Throwable $e) { $this->error('recurring: ' . $e->getMessage()); }
    try { \App\Support\Procure::tick(); } catch (\Throwable $e) { $this->error('procure: ' . $e->getMessage()); }
    try { $this->smsAuto(); } catch (\Throwable $e) { $this->error('smsauto: ' . $e->getMessage()); }
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
