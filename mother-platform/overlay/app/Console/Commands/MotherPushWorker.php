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
    try { $this->campaigns(); } catch (\Throwable $e) { $this->error('campaigns: ' . $e->getMessage()); }
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
}
