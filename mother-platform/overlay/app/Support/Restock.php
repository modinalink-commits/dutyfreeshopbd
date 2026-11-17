<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Stock watch and restock follow-up with the purchase manager (owner, 17 Nov 2026).
 * Scope (owner, same day): the Cigarette and Vape categories only. The scope is a setting (smoke | general | all); Single Packet twins are never bought, so they are never listed.
 * A product that will run out before new stock can arrive gets ONE open task. The purchase manager is told on WhatsApp (photo, usable stock,
 * daily sale, days left, quantity to buy), answers with buttons or plain text, and is reminded every two hours in working time until the
 * goods are recorded as received in Purchase. A WhatsApp message never changes stock: only a recorded purchase does.
 */
class Restock
{
  const API = 'https://graph.facebook.com/v21.0', TPL = 'purchase_manager_update', BY = 'Stock Assistant (auto)';
  // One opener for everything the purchase manager is told (stock, purchase rate, margin). Used only when the 24-hour window is closed.
  const BODY = "আসসালামু আলাইকুম {{1}},\nআমি DutyFreeBD-এর স্বয়ংক্রিয় সহকারী। আপনার জন্য {{2}}টি নতুন আপডেট আছে (স্টক, ক্রয়মূল্য বা মার্জিন)।\n\nবিস্তারিত দেখতে নিচের বোতামে চাপ দিন।";
  const BTN = 'আপডেট দেখান';
  const BAD = ['Cancel', 'Cancelled', 'Returned', 'Return'];
  const OPEN = ['new', 'notified', 'seen', 'ordered', 'problem', 'partial'];
  const LAB = ['new' => 'New', 'notified' => 'Sent', 'seen' => 'Seen', 'ordered' => 'Ordered', 'problem' => 'Problem', 'partial' => 'Part received', 'done' => 'Done', 'cancelled' => 'Cancelled'];

  public static function ready(): bool { static $r = null; return $r ??= (function () { try { return Schema::hasTable('restock_tasks') && Schema::hasTable('restock_events'); } catch (\Throwable $e) { return false; } })(); }
  public static function set(string $k, string $d = ''): string { return Procure::set('restock_' . $k, $d); }
  public static function put(string $k, string $v): void { Procure::put('restock_' . $k, $v); }
  public static function cfg(): array {
    $hm = fn($v, $d) => preg_match('/^(\d{1,2}):(\d{2})$/', trim($v), $m) && ((int)$m[1] < 24 || ((int)$m[1] === 24 && (int)$m[2] === 0)) && (int)$m[2] < 60 ? (int)$m[1] * 60 + (int)$m[2] : $d;
    return ['on' => self::set('on', '0') === '1', 'name' => trim(self::set('name', 'Mamun')) ?: 'Mamun', 'wa' => WhatsApp::norm(self::set('phone', '01824522800')),
      'verified' => self::set('verified', ''), 'cc' => self::ccList(), 'from' => $hm(self::set('from', '10:00'), 600), 'to' => ($hm(self::set('to', '24:00'), 1440) ?: 1440), 'every' => max(30, min(720, (int)self::set('every', '120'))),
      'lead' => max(0, min(60, (int)self::set('lead', '3'))), 'safety' => max(0, min(30, (int)self::set('safety', '2'))), 'cover' => max(3, min(90, (int)self::set('cover', '15'))),
      'scope' => in_array(self::set('scope', 'smoke'), ['smoke', 'general', 'all'], true) ? self::set('scope', 'smoke') : 'smoke', 'min_sold' => max(1, (int)self::set('min_sold', '3')), 'cards' => max(0, min(10, (int)self::set('cards', '5'))), 'exclude' => array_values(array_filter(array_map('intval', explode(',', self::set('exclude', '')))))];
  }
  /** People who get a copy of every round (owner, 17 Nov 2026: the admin and the manager are both kept informed). [[name, 8801XXXXXXXXX], ...] from "Name|number" lines. Only the purchase manager's answers change anything. */
  public static function ccList(): array {
    $out = []; $main = WhatsApp::norm(self::set('phone', '01824522800'));
    foreach (preg_split('/\r\n|\r|\n/', self::set('cc', 'Mohammad Faruk|01884808080')) as $l) {
      [$n, $p] = array_pad(array_map('trim', explode('|', $l, 2)), 2, ''); $wa = WhatsApp::norm($p);
      if (preg_match('/^8801[3-9]\d{8}$/', $wa) && $wa !== $main) $out[$wa] = [$n !== '' ? $n : 'Admin', $wa];
    }
    return array_values($out);
  }
  public static function validWa(string $wa): bool { return (bool)preg_match('/^8801[3-9]\d{8}$/', $wa); }
  public static function isVerified(?array $c = null): bool { $c ??= self::cfg(); return self::validWa($c['wa']) && $c['verified'] === $c['wa']; }
  public static function tplStatus(): string { return self::set('tpl_status', 'NOT SUBMITTED'); }

  /** Category ids with everything under them. */
  private static function tree(array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids))); if (!$ids) return [];
    try { $all = DB::table('categories')->get(['id', 'parent_id']); for ($i = 0; $i < 4; $i++) { $more = $all->filter(fn($x) => !empty($x->parent_id) && in_array((int)$x->parent_id, $ids, true) && !in_array((int)$x->id, $ids, true))->pluck('id')->map(fn($x) => (int)$x)->all(); if (!$more) break; $ids = array_merge($ids, $more); } } catch (\Throwable $e) {}
    return $ids;
  }
  /** The Cigarette / Vape categories (and everything under them). Empty = they could not be found, and then nothing is scanned at all. */
  public static function smoke(): array { return PurchaseList::smokeCats(); }
  /** Categories left out of the scan. Null = the smoke categories could not be found (safety stop). */
  public static function blocked(?array $c = null): ?array {
    $c ??= self::cfg(); $smoke = self::smoke(); if (!$smoke) return null;
    if ($c['scope'] !== 'general') return self::tree($c['exclude']);
    $ids = array_values(array_unique(array_merge($smoke, $c['exclude'])));
    try { $all = DB::table('categories')->get(['id', 'parent_id']); for ($i = 0; $i < 4; $i++) { $more = $all->filter(fn($x) => !empty($x->parent_id) && in_array((int)$x->parent_id, $ids, true) && !in_array((int)$x->id, $ids, true))->pluck('id')->map(fn($x) => (int)$x)->all(); if (!$more) break; $ids = array_merge($ids, $more); } } catch (\Throwable $e) {}
    return $ids;
  }

  /** Every watched product with its figures. Row: id, name, image, stock, usable, committed, on_order, q7, q30, per_day, days_left, lead, need (bool), qty, reason. */
  public static function figures(?array $only = null): array {
    $c = self::cfg(); $blocked = self::blocked($c); if ($blocked === null) return [];
    $since30 = now()->subDays(30); $since7 = now()->subDays(7);
    // sales speed: cancelled and returned orders are left out, so they never raise the need
    $sold = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.product_id')->where('o.created_at', '>=', $since30)->whereNotIn('o.status', self::BAD)
      ->when(Schema::hasColumn('orders', 'duplicate_of_order_id'), fn($q) => $q->whereNull('o.duplicate_of_order_id'))->when($only !== null, fn($q) => $q->whereIn('i.product_id', $only ?: [0]))
      ->groupBy('i.product_id')->selectRaw('i.product_id, sum(i.qty) q30, sum(case when o.created_at >= ? then i.qty else 0 end) q7', [$since7])->get()->keyBy('product_id');
    if ($sold->isEmpty()) return [];
    $smoke = self::smoke();
    $prods = DB::table('products')->whereIn('id', $sold->keys()->all())->when($blocked, fn($q) => $q->whereNotIn('category_id', $blocked))->when($c['scope'] === 'smoke', fn($q) => $q->whereIn('category_id', $smoke))->whereNotNull('category_id')->whereNull('parent_product_id')->whereNotNull('stock')
      ->where(fn($q) => $q->whereNull('sub')->orWhereRaw('lower(sub) <> ?', ['single packet']))->whereRaw("lower(name) not like 'single %'")->where(fn($q) => $q->whereNull('sku')->orWhereRaw("sku not ilike '%-SP'")) // Single Packet twins are never bought: their stock comes only from a received partial return
      ->where(fn($q) => $q->whereNull('restock_off')->orWhere('restock_off', false))->get(['id', 'name', 'sku', 'image', 'stock', 'low_stock_alert', 'reorder_target', 'restock_lead_days', 'category_id'])->keyBy('id');
    if ($prods->isEmpty()) return [];
    $ids = $prods->keys()->all();
    // stock leaves at Shipment: units in confirmed orders that have not shipped yet are no longer free to sell
    $commit = [];
    try {
      $commit = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereIn('i.product_id', $ids)->where('o.created_at', '>=', now()->subDays(45))->whereNull('o.stock_deducted_at')
        ->whereNotIn('o.status', array_merge(self::BAD, ['Delivered', 'Shipment']))->when(Schema::hasColumn('orders', 'duplicate_of_order_id'), fn($q) => $q->whereNull('o.duplicate_of_order_id'))->groupBy('i.product_id')->selectRaw('i.product_id, sum(i.qty) q')->pluck('q', 'product_id')->all();
    } catch (\Throwable $e) { $commit = []; }
    $onOrder = [];
    try {
      if (Schema::hasTable('supplier_po_items')) $onOrder = DB::table('supplier_po_items as pi')->join('supplier_pos as po', 'po.id', '=', 'pi.po_id')->whereIn('pi.product_id', $ids)->whereIn('po.status', ['sent', 'accepted', 'revised', 'partial'])->where('po.created_at', '>=', now()->subDays(60))
        ->whereRaw('not exists (select 1 from supplier_deliveries d where d.po_id = po.id and d.received_at is not null)')->groupBy('pi.product_id')->selectRaw('pi.product_id, sum(coalesce(pi.accepted_qty, pi.qty)) q')->pluck('q', 'product_id')->all();
    } catch (\Throwable $e) { $onOrder = []; }
    $out = [];
    foreach ($prods as $pid => $p) {
      $s = $sold[$pid]; $q30 = (int)$s->q30; $q7 = (int)$s->q7; if ($q30 < $c['min_sold']) continue;
      $per = max($q30 / 30, $q7 / 7); if ($per <= 0) continue;
      $stock = (int)$p->stock; $cm = (int)($commit[$pid] ?? 0); $usable = max(0, $stock - $cm); $oo = (int)($onOrder[$pid] ?? 0);
      $lead = $p->restock_lead_days !== null ? (int)$p->restock_lead_days : $c['lead']; $left = $usable / $per; $limit = (int)($p->low_stock_alert ?? 0);
      $byTime = ($usable + $oo) / $per <= $lead + $c['safety']; $byLimit = $limit > 0 && $usable + $oo <= $limit;
      $target = (int)ceil($per * ($lead + $c['cover'])); if ((int)($p->reorder_target ?? 0) > $target) $target = (int)$p->reorder_target;
      $qty = max(0, $target - $usable - $oo);
      $reason = $usable <= 0 ? 'স্টক শেষ' : ($byTime ? 'প্রায় ' . self::num($left) . ' দিনে শেষ হবে, আনতে লাগে ' . $lead . ' দিন' : ($byLimit ? 'স্টক সীমার (' . $limit . ') নিচে' : ''));
      $out[$pid] = ['id' => (int)$pid, 'name' => (string)$p->name, 'sku' => (string)($p->sku ?? ''), 'image' => (string)($p->image ?? ''), 'stock' => $stock, 'committed' => $cm, 'usable' => $usable, 'on_order' => $oo, 'q7' => $q7, 'q30' => $q30,
        'per_day' => round($per, 2), 'days_left' => round($left, 1), 'lead' => $lead, 'limit' => $limit, 'rt' => (int)($p->reorder_target ?? 0), 'need' => ($byTime || $byLimit) && $qty > 0, 'qty' => $qty, 'reason' => $reason];
    }
    uasort($out, fn($a, $b) => [$a['need'] ? 0 : 1, $a['days_left']] <=> [$b['need'] ? 0 : 1, $b['days_left']]);
    return $out;
  }
  public static function num($n): string { $n = (float)$n; return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.'); }

  public static function log(?int $task, string $kind, string $body, ?string $wamid = null, ?string $by = null): void {
    try { DB::table('restock_events')->insert(['task_id' => $task, 'kind' => $kind, 'body' => mb_substr($body, 0, 4000), 'wamid' => $wamid, 'by_name' => $by ?? (auth()->user()->name ?? 'System'), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  public static function openTasks() {
    return DB::table('restock_tasks as t')->join('products as p', 'p.id', '=', 't.product_id')->whereNull('t.closed_at')->orderBy('t.id')->get(['t.*', 'p.name', 'p.image', 'p.stock']);
  }

  /** Opens tasks for products that need buying, refreshes the figures of open ones, and closes or re-opens by what was really received. Returns [opened, closed]. */
  public static function scan(): array {
    if (!self::ready()) return [0, 0];
    $c = self::cfg(); $open = DB::table('restock_tasks')->whereNull('closed_at')->get()->keyBy('product_id'); $opened = 0; $closed = 0;
    $fig = self::figures();
    foreach ($fig as $pid => $f) {
      if (!$f['need'] || isset($open[$pid])) continue;
      try {
        $id = DB::table('restock_tasks')->insertGetId(['product_id' => $pid, 'status' => 'new', 'usable' => $f['usable'], 'on_order' => $f['on_order'], 'per_day' => $f['per_day'], 'days_left' => $f['days_left'], 'lead_days' => $f['lead'],
          'suggest_qty' => $f['qty'], 'need_qty' => $f['qty'], 'reason' => $f['reason'], 'assignee_name' => $c['name'], 'assignee_wa' => $c['wa'], 'created_at' => now(), 'updated_at' => now()]);
        self::log($id, 'detect', $f['name'] . ': usable ' . $f['usable'] . ', ' . $f['per_day'] . '/day, ' . $f['days_left'] . ' days left, buy ' . $f['qty'] . ' (' . $f['reason'] . ')', null, 'System'); $opened++;
      } catch (\Throwable $e) { /* the unique index says another process opened it first */ }
    }
    foreach ($open as $pid => $t) {
      $f = $fig[$pid] ?? null;
      $got = 0; try { $got = (int)DB::table('purchase_items')->where('product_id', $pid)->where('created_at', '>', $t->created_at)->sum('qty'); } catch (\Throwable $e) {}
      $upd = ['updated_at' => now()];
      if ($f) $upd += ['usable' => $f['usable'], 'on_order' => $f['on_order'], 'per_day' => $f['per_day'], 'days_left' => $f['days_left'], 'lead_days' => $f['lead']];
      if ($got > (int)$t->received_qty) {
        $upd['received_qty'] = $got; self::log((int)$t->id, 'receive', 'Recorded in Purchase: ' . ($got - (int)$t->received_qty) . ' more, ' . $got . ' in total', null, 'System');
        $still = $f && $f['need'] ? (int)$f['qty'] : 0;
        if ($still > 0) { $upd += ['status' => 'partial', 'need_qty' => $still, 'snooze_until' => null, 'eta_at' => null]; self::log((int)$t->id, 'status', 'Part received, still needs ' . $still, null, 'System'); }
        else { $upd += ['status' => 'done', 'closed_at' => now(), 'closed_why' => 'received']; self::log((int)$t->id, 'status', 'Closed: need covered by received stock', null, 'System'); $closed++; }
      } elseif (!$f || !$f['need']) {
        // nothing was received but the need is gone (sales slowed, a return came back, the product was switched off or moved to a blocked category)
        if (in_array($t->status, ['new', 'notified', 'seen', 'problem', 'partial'], true)) { $upd += ['status' => 'done', 'closed_at' => now(), 'closed_why' => 'no longer needed']; self::log((int)$t->id, 'status', 'Closed: no longer needed', null, 'System'); $closed++; }
      } elseif (in_array($t->status, ['new', 'notified', 'seen', 'partial'], true)) $upd['need_qty'] = (int)$f['qty'];
      DB::table('restock_tasks')->where('id', $t->id)->update($upd);
    }
    return [$opened, $closed];
  }

  // ---------- WhatsApp ----------
  private static function post(array $json): array {
    $t = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id'); if (!$t || !$pid) return [false, null, 'WhatsApp is not connected.'];
    try {
      $r = Http::timeout(20)->withToken($t)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp'] + $json);
      if ($r->successful()) return [true, (string)$r->json('messages.0.id'), null];
      return [false, null, mb_substr('(' . (int)$r->json('error.code') . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 250)];
    } catch (\Throwable $e) { return [false, null, 'Network error: ' . mb_substr($e->getMessage(), 0, 120)]; }
  }
  /** The 24-hour window is open when this number wrote to us in the last 24 hours (free text and cards are allowed only then). */
  public static function windowOpen(string $wa): bool {
    try {
      $t = DB::table('chat_threads')->where(['channel' => 'whatsapp', 'external_id' => $wa])->first(['id']); if (!$t) return false;
      $last = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->max('created_at');
      return $last && strtotime((string)$last . ' UTC') > time() - 23 * 3600;
    } catch (\Throwable $e) { return false; }
  }
  public static function sendText(string $wa, string $text, ?int $task = null): bool {
    [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 4000)]]);
    self::log($task, $ok ? 'msg_out' : 'msg_fail', $ok ? $text : $text . "\n[not sent: " . $err . ']', $id, self::BY);
    if ($ok) Chat::logOut($wa, $text, self::BY, $id);
    return $ok;
  }
  public static function sendTemplate(string $wa, string $name, int $count): array {
    [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'template', 'template' => ['name' => self::TPL, 'language' => ['code' => 'bn'], 'components' => [
      ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $name], ['type' => 'text', 'text' => (string)$count]]],
      ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0', 'parameters' => [['type' => 'payload', 'payload' => 'rs:list']]]]]]);
    $body = strtr(self::BODY, ['{{1}}' => $name, '{{2}}' => (string)$count]);
    self::log(null, $ok ? 'msg_out' : 'msg_fail', $ok ? $body : $body . "\n[not sent: " . $err . ']', $id, self::BY);
    if ($ok) Chat::logOut($wa, $body, self::BY, $id);
    return [$ok, $ok ? 'sent' : (string)$err];
  }
  /** A JPEG of the product photo that WhatsApp can load (our own files only). */
  public static function imgUrl(int $pid): ?string {
    try {
      $p = DB::table('products')->where('id', $pid)->value('image'); $path = strtok((string)$p, '?');
      if ($path === '' || !str_starts_with($path, '/uploads/') || str_contains($path, '..') || !is_file(public_path(ltrim($path, '/')))) return null;
      return 'https://dutyfreebd.com/rs-img/' . $pid . '/' . substr(hash_hmac('sha256', 'rs-img:' . $pid, (string)config('app.key')), 0, 24) . '.jpg';
    } catch (\Throwable $e) { return null; }
  }
  public static function imgOk(int $pid, string $sig): bool { return hash_equals(substr(hash_hmac('sha256', 'rs-img:' . $pid, (string)config('app.key')), 0, 24), $sig); }
  public static function line(object $t, int $n): string {
    $st = ['ordered' => ' · অর্ডার করা', 'problem' => ' · সমস্যা', 'partial' => ' · আংশিক এসেছে'][$t->status] ?? '';
    return $n . ') ' . $t->name . "\n   স্টক " . (int)$t->usable . ' · দৈনিক বিক্রি ' . self::num($t->per_day) . ' · ' . ((int)$t->usable <= 0 ? 'স্টক শেষ' : 'চলবে ~' . self::num($t->days_left) . ' দিন') . ' · কিনুন ' . (int)$t->need_qty . $st;
  }
  /** One product as a card: photo, figures and three answer buttons. */
  public static function sendCard(string $wa, object $t, int $n): bool {
    $body = "#" . $n . ' ' . $t->name . "\n\nব্যবহারযোগ্য স্টক: " . (int)$t->usable . "\nদৈনিক গড় বিক্রি: " . self::num($t->per_day) . "\n" . ((int)$t->usable <= 0 ? 'স্টক শেষ হয়ে গেছে' : 'আনুমানিক চলবে: ' . self::num($t->days_left) . ' দিন')
      . "\nপ্রস্তাবিত ক্রয়: " . (int)$t->need_qty . "\nকারণ: " . ($t->reason ?: 'স্টক কম');
    $inter = ['type' => 'button', 'body' => ['text' => mb_substr($body, 0, 1000)], 'action' => ['buttons' => [
      ['type' => 'reply', 'reply' => ['id' => 'rs:' . $t->id . ':seen', 'title' => 'দেখেছি']], ['type' => 'reply', 'reply' => ['id' => 'rs:' . $t->id . ':ord', 'title' => 'অর্ডার করেছি']], ['type' => 'reply', 'reply' => ['id' => 'rs:' . $t->id . ':prob', 'title' => 'সমস্যা আছে']]]]];
    $img = self::imgUrl((int)$t->product_id); if ($img) $inter['header'] = ['type' => 'image', 'image' => ['link' => $img]];
    [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'interactive', 'interactive' => $inter]);
    if (!$ok && $img) { unset($inter['header']); [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'interactive', 'interactive' => $inter]); } // the photo could not be loaded: the card still goes
    self::log((int)$t->id, $ok ? 'msg_out' : 'msg_fail', $ok ? $body : $body . "\n[not sent: " . $err . ']', $id, self::BY);
    if ($ok) Chat::logOut($wa, $body . "\n[ দেখেছি ] [ অর্ডার করেছি ] [ সমস্যা আছে ]", self::BY, $id, 'text', 'sent', null, $img, $img ? 'image' : null);
    return $ok;
  }
  /** Tasks that still wait for the purchase manager right now (an order with a future arrival time is left alone until that time passes). */
  public static function pending() {
    return self::openTasks()->filter(fn($t) => !($t->snooze_until && strtotime((string)$t->snooze_until . ' UTC') > time()))->values();
  }
  /** Numbered list of what is waiting, in one message, then a card for each product that has not been shown yet. $force = asked for (button or admin), not the timed round. */
  public static function digest(string $wa, bool $force = false): string {
    $c = self::cfg(); $all = self::pending(); if ($all->isEmpty()) { if ($force) self::sendText($wa, 'এই মুহূর্তে কেনার মতো কোনো পণ্য বাকি নেই। ধন্যবাদ।'); return 'nothing pending'; }
    if (!self::windowOpen($wa)) {
      if (self::tplStatus() !== 'APPROVED') { self::log(null, 'msg_fail', 'Reminder not sent: the 24-hour window is closed and the template is ' . self::tplStatus(), null, 'System'); return 'template not approved'; }
      if (!Cache::add('rs_tpl_sent', 1, max(1800, $c['every'] * 60 - 120))) return 'waiting for an answer to the opener';
      [$ok, $m] = self::sendTemplate($wa, $c['name'], $all->count() + self::notices()->count()); return 'template ' . $m;
    }
    // numbered once for the whole message, then grouped by where each product stands, so the question fits what was already answered
    $nums = []; $grp = ['new' => [], 'seen' => [], 'late' => [], 'problem' => [], 'partial' => []];
    foreach ($all->take(40) as $i => $t) { $nums[$i + 1] = (int)$t->id; $grp[$t->status === 'ordered' ? 'late' : (in_array($t->status, ['new', 'notified'], true) ? 'new' : $t->status)][] = self::line($t, $i + 1); }
    self::put('nums_' . $wa, json_encode($nums));
    $round = (int)self::set('rounds', '0') + 1; self::put('rounds', (string)$round);
    $hello = $force ? 'আপনার চাওয়া তালিকা:' : ['আসসালামু আলাইকুম ' . $c['name'] . ' ভাই, কয়েকটা পণ্যের খোঁজ নিতে এলাম।', $c['name'] . ' ভাই, আগের পণ্যগুলোর আপডেট জানতে চাইছি।', 'আবার একটু মনে করিয়ে দিচ্ছি, ' . $c['name'] . ' ভাই।', $c['name'] . ' ভাই, নিচের পণ্যগুলো এখনো বাকি আছে।'][($round - 1) % 4];
    $parts = [];
    if ($grp['new']) $parts[] = "কেনা দরকার (" . count($grp['new']) . "টি):\n" . implode("\n", $grp['new']) . "\nএগুলো কবে অর্ডার করতে পারবেন?";
    if ($grp['seen']) $parts[] = "আপনি দেখেছেন, অর্ডারের খবর পাইনি (" . count($grp['seen']) . "টি):\n" . implode("\n", $grp['seen']) . "\nঅর্ডার দেওয়া হয়েছে কি? না হলে কবে দেবেন?";
    if ($grp['late']) $parts[] = "অর্ডার করা, আসার সময় পেরিয়ে গেছে (" . count($grp['late']) . "টি):\n" . implode("\n", $grp['late']) . "\nপণ্য এসেছে কি? না এলে নতুন তারিখটা জানান।";
    if ($grp['partial']) $parts[] = "আংশিক এসেছে, বাকিটা দরকার (" . count($grp['partial']) . "টি):\n" . implode("\n", $grp['partial']) . "\nবাকিটা কবে আসবে?";
    if ($grp['problem']) $parts[] = "সমস্যা জানিয়েছিলেন (" . count($grp['problem']) . "টি):\n" . implode("\n", $grp['problem']) . "\nসমাধান হয়েছে কি?";
    $text = "DutyFreeBD স্টক সহকারী (স্বয়ংক্রিয়)\n" . $hello . "\n\n" . implode("\n\n", $parts) . ($all->count() > 40 ? "\n\n… আরও " . ($all->count() - 40) . 'টি' : '')
      . ($round <= 2 || $force ? "\n\nউত্তর দিতে নম্বরসহ লিখুন, যেমন:\n1 অর্ডার করেছি, পরশু আসবে\n2 সমস্যা: সাপ্লায়ারের কাছে নেই\nব্যস্ত থাকলে লিখুন: 2 ঘণ্টা পরে" : "\n\nনম্বরসহ উত্তর দিন (যেমন: 1 কাল আসবে)।");
    if (!self::sendText($wa, $text)) return 'send failed';
    self::copy($text); // the admin gets the same list (read-only: no buttons)
    $sent = 0; $now = now();
    foreach ($all as $i => $t) {
      if ($t->status !== 'new' || $sent >= $c['cards'] || $i >= 40) continue;
      if (self::sendCard($wa, $t, $i + 1)) $sent++;
    }
    foreach ($all as $t) { $u = ['last_msg_at' => $now, 'reminders' => DB::raw('reminders + 1'), 'updated_at' => $now]; if ($t->status === 'new') $u += ['status' => 'notified', 'notified_at' => $now]; DB::table('restock_tasks')->where('id', $t->id)->update($u); }
    return 'list of ' . $all->count() . ', ' . $sent . ' cards';
  }

  // ---------- copies for the admin ----------
  /** Where everything stands, as one read-only message. */
  public static function summary(): string {
    $c = self::cfg(); $open = self::openTasks(); $lab = ['new' => 'নতুন', 'notified' => 'জানানো হয়েছে, উত্তর নেই', 'seen' => 'দেখেছেন, অর্ডার হয়নি', 'ordered' => 'অর্ডার করা', 'problem' => 'সমস্যা', 'partial' => 'আংশিক এসেছে'];
    $by = []; foreach ($open as $t) $by[$t->status][] = $t;
    $parts = [];
    foreach ($lab as $st => $name) { if (empty($by[$st])) continue; $ls = [];
      foreach (array_slice($by[$st], 0, 12) as $t) $ls[] = '• ' . $t->name . ' (স্টক ' . (int)$t->usable . ', কিনতে হবে ' . (int)$t->need_qty . ($t->eta_at ? ', আসবে ' . \Carbon\Carbon::parse($t->eta_at, 'UTC')->timezone('Asia/Dhaka')->format('d M') : '') . ')';
      $parts[] = $name . ' (' . count($by[$st]) . "টি):\n" . implode("\n", $ls) . (count($by[$st]) > 12 ? "\n… আরও " . (count($by[$st]) - 12) . 'টি' : ''); }
    $since = now('Asia/Dhaka')->startOfDay()->utc();
    $nt = DB::table('restock_events')->whereIn('kind', ['notice_q', 'notice'])->where('created_at', '>=', $since)->orderBy('id')->limit(8)->pluck('body')->all();
    return "DutyFreeBD স্টক সহকারী (স্বয়ংক্রিয়) · অ্যাডমিন কপি\nদায়িত্বে: " . $c['name'] . "\nখোলা কাজ: " . $open->count() . 'টি'
      . ($parts ? "\n\n" . implode("\n\n", $parts) : '') . ($nt ? "\n\nআজকের ক্রয়মূল্য / মার্জিন আপডেট:\n" . implode("\n\n", $nt) : '') . (!$parts && !$nt ? "\nএই মুহূর্তে নতুন কিছু নেই।" : '');
  }
  /** One line or a whole summary to everyone on the copy list. Free text inside their 24-hour window; otherwise the opener, at most once per round. */
  public static function copy(?string $text = null): void {
    try {
      $c = self::cfg(); if (!$c['cc']) return; $n = self::pending()->count() + self::notices()->count();
      foreach ($c['cc'] as [$name, $wa]) {
        if (self::windowOpen($wa)) { $body = mb_substr($text ?? self::summary(), 0, 3900); [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]]);
          self::log(null, $ok ? 'msg_cc' : 'msg_fail', 'Copy to ' . $name . ($ok ? '' : ' not sent: ' . $err) . "\n" . mb_substr($body, 0, 600), $id, self::BY); if ($ok) Chat::logOut($wa, $body, self::BY, $id); }
        elseif (self::tplStatus() === 'APPROVED' && Cache::add('rs_tpl_cc_' . $wa, 1, max(1800, $c['every'] * 60 - 120))) self::sendTemplate($wa, $name, max(1, $n));
      }
    } catch (\Throwable $e) { report($e); }
  }

  // ---------- answers ----------
  /** "Not now": 2 ঘণ্টা পরে, বিকাল 5টায়, পরে জানাব, ব্যস্ত, বিরতি. Returns the UTC time to come back, or null when the text does not ask to wait. */
  public static function later(string $s): ?\Carbon\Carbon {
    $s = strtr(mb_strtolower($s), ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']); $now = now('Asia/Dhaka');
    if (preg_match('/(\d{1,2})\s*(ঘণ্টা|ঘন্টা|ghonta|ghanta|hour|hr)/u', $s, $m)) return $now->copy()->addHours(max(1, min(48, (int)$m[1])))->utc();
    if (preg_match('/(\d{1,3})\s*(মিনিট|minute|min)/u', $s, $m)) return $now->copy()->addMinutes(max(15, min(600, (int)$m[1])))->utc();
    if (preg_match('/(\d{1,2})\s*(টায়|টার|টা(?![\p{L}\p{M}])|ta\b|pm|am)/u', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 12) {
      $h = (int)$m[1]; $pm = (bool)preg_match('/বিকাল|বিকেল|সন্ধ্যা|রাত|দুপুর|pm/u', $s); $am = (bool)preg_match('/সকাল|am/u', $s);
      if ($pm && $h < 12) $h += 12; elseif (!$am && !$pm && $h < 10) $h += 12; // "5টায়" in a working day means the afternoon
      $d = $now->copy()->setTime($h % 24, 0); if (preg_match('/(?<![\p{L}\p{M}])কাল|kal\b|tomorrow/u', $s) || $d->lte($now)) $d->addDay(); return $d->utc();
    }
    if (preg_match('/পরে|ব্যস্ত|বিরতি|স্থগিত|থামুন|থামো|later|busy|pause|wait/u', $s)) return preg_match('/(?<![\p{L}\p{M}])কাল|kal\b|tomorrow/u', $s) ? $now->copy()->addDay()->setTime(10, 0)->utc() : $now->copy()->addHours(2)->utc();
    return null;
  }
  /** Arrival time from plain words: আজ, কাল, পরশু, "3 দিন", "20 তারিখ", 20/11. Null when no date is in the text. */
  public static function eta(string $s): ?\Carbon\Carbon {
    $s = strtr(mb_strtolower($s), ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']); $now = now('Asia/Dhaka'); $d = null;
    if (preg_match('/পরশু|porshu|day after/u', $s)) $d = $now->copy()->addDays(2);
    elseif (preg_match('/(?<![\p{L}\p{M}])(আগামীকাল|আগামী কাল|কালকে|কাল)|kalke|kal\b|tomorrow/u', $s)) $d = $now->copy()->addDay();
    elseif (preg_match('/আজকে|আজ|ajke|aj\b|today/u', $s)) $d = $now->copy();
    elseif (preg_match('/(\d{1,2})\s*(দিন|din|day)/u', $s, $m)) $d = $now->copy()->addDays(min(60, (int)$m[1]));
    elseif (preg_match('/(\d{1,2})\s*(সপ্তাহ|week)/u', $s, $m)) $d = $now->copy()->addDays(min(60, 7 * (int)$m[1]));
    elseif (preg_match('#(\d{1,2})[/\-.](\d{1,2})#', $s, $m) && checkdate((int)$m[2], (int)$m[1], (int)$now->year)) { $d = $now->copy()->setDate($now->year, (int)$m[2], (int)$m[1]); if ($d->lt($now->copy()->startOfDay())) $d->addYear(); }
    elseif (preg_match('/(\d{1,2})\s*(তারিখ|tarikh|tarik)/u', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 31) { $d = $now->copy()->day(min((int)$m[1], $now->daysInMonth)); if ($d->lt($now->copy()->startOfDay())) $d = $now->copy()->addMonthNoOverflow()->day(min((int)$m[1], $now->copy()->addMonthNoOverflow()->daysInMonth)); }
    return $d ? $d->setTime(20, 0)->utc() : null; // followed up again from the evening of that day
  }
  public static function apply(int $taskId, string $act, string $text, string $by): ?object {
    $t = DB::table('restock_tasks')->where('id', $taskId)->whereNull('closed_at')->first(); if (!$t) return null; $u = ['updated_at' => now()];
    if ($act === 'seen') { if (in_array($t->status, ['new', 'notified'], true)) $u['status'] = 'seen'; $u['seen_at'] = $t->seen_at ?: now(); }
    elseif ($act === 'ord') { $u += ['status' => 'ordered', 'ordered_at' => now(), 'po_qty' => (int)$t->need_qty, 'seen_at' => $t->seen_at ?: now()]; $e = self::eta($text); if ($e) $u += ['eta_at' => $e, 'snooze_until' => $e]; }
    elseif ($act === 'eta') { $e = self::eta($text); if (!$e) return null; $u += ['eta_at' => $e, 'snooze_until' => $e]; if ($t->status !== 'ordered') $u += ['status' => 'ordered', 'ordered_at' => now(), 'po_qty' => (int)$t->need_qty]; }
    elseif ($act === 'prob') { $u += ['status' => 'problem', 'snooze_until' => null, 'note' => mb_substr(trim(($t->note ? $t->note . "\n" : '') . now('Asia/Dhaka')->format('d M h:i A') . ': ' . ($text !== '' ? $text : 'সমস্যা আছে')), 0, 2000)]; }
    elseif ($act === 'note') { $u['note'] = mb_substr(trim(($t->note ? $t->note . "\n" : '') . now('Asia/Dhaka')->format('d M h:i A') . ': ' . $text), 0, 2000); }
    else return null;
    DB::table('restock_tasks')->where('id', $t->id)->update($u);
    self::log((int)$t->id, 'status', ['seen' => 'Seen', 'ord' => 'Purchase order placed', 'eta' => 'Arrival time given', 'prob' => 'Problem reported', 'note' => 'Note'][$act] . (isset($u['eta_at']) ? ' · arrives ' . \Carbon\Carbon::parse($u['eta_at'], 'UTC')->timezone('Asia/Dhaka')->format('d M Y') : '') . ($text !== '' ? ' · "' . mb_substr($text, 0, 300) . '"' : ''), null, $by);
    if (in_array($act, ['seen', 'ord', 'eta', 'prob'], true) && str_ends_with($by, '(WhatsApp)')) self::copy(self::cfg()['name'] . ' জানিয়েছেন · ' . self::nameOf($t) . ': ' . ['seen' => 'দেখেছেন', 'ord' => 'অর্ডার করেছেন', 'eta' => 'আসার তারিখ দিয়েছেন', 'prob' => 'সমস্যা জানিয়েছেন'][$act] . (isset($u['eta_at']) ? ' (আসবে ' . \Carbon\Carbon::parse($u['eta_at'], 'UTC')->timezone('Asia/Dhaka')->format('d M') . ')' : '') . ($text !== '' ? ' · "' . mb_substr($text, 0, 200) . '"' : ''));
    if ($act === 'prob') { try { $n = DB::table('products')->where('id', $t->product_id)->value('name'); DeployHealth::raise('restock_problem_' . $t->id, 'medium', 'Restock', 'Restock problem: ' . $n, 'The purchase manager reported a problem for "' . $n . '": ' . ($text !== '' ? $text : 'no detail given'), 'Reported on WhatsApp', 'Open Restock Follow-up and decide what to do.'); } catch (\Throwable $e) {} }
    return DB::table('restock_tasks')->where('id', $t->id)->first();
  }
  private static function nameOf(object $t): string { try { return (string)DB::table('products')->where('id', $t->product_id)->value('name'); } catch (\Throwable $e) { return 'পণ্য'; } }

  /**
   * An incoming WhatsApp message from the purchase manager's number. True = it was a restock answer and has been dealt with (the customer AI must not reply).
   * $payload is the button id when a button was pressed, $ctx the id of the message that was answered (swipe-reply), when there is one.
   */
  public static function inbound(string $from, string $type, string $body, ?string $payload, ?string $ctx): bool {
    try {
      if (!self::ready()) return false; $c = self::cfg(); $wa = WhatsApp::norm($from);
      foreach ($c['cc'] as [$ccName, $ccWa]) if ($ccWa === $wa) { // the admin's copy: read-only, their words never change a task
        if ($payload !== 'rs:list' && !preg_match('/^(আপডেট|স্টক আপডেট|stock update)\s*$/iu', trim($body))) return false;
        self::log(null, 'msg_in', 'Summary asked by ' . $ccName, null, $ccName); self::copy(self::summary()); return true;
      }
      if (!self::validWa($c['wa']) || $wa !== $c['wa']) return false;
      $body = trim($body); self::log(null, 'msg_in', ($payload ? '[button ' . $payload . '] ' : '') . $body, null, $c['name']);
      if ($c['verified'] !== $wa) { self::put('verified', $wa); self::log(null, 'setting', 'WhatsApp number …' . substr($wa, -4) . ' verified by its own reply', null, 'System'); }
      $by = $c['name'] . ' (WhatsApp)';
      if ($payload === 'rs:list' || preg_match('/^(list|তালিকা|লিস্ট|আপডেট|update|status)(?![\p{L}\p{M}])/iu', $body)) { $n = self::flush($wa); if (!($n > 0 && self::pending()->isEmpty())) self::digest($wa, true); return true; }
      if ($payload && preg_match('/^rs:(\d+):(seen|ord|prob)$/', $payload, $m)) {
        $t = self::apply((int)$m[1], $m[2], '', $by);
        if (!$t) { self::sendText($wa, 'এই পণ্যের কাজটি আগেই বন্ধ হয়ে গেছে।'); return true; }
        $n = self::nameOf($t);
        if ($m[2] === 'seen') { self::put('ask_' . $wa, $t->id . ':when:' . time()); self::sendText($wa, 'ধন্যবাদ, "' . $n . '" দেখেছেন বলে নোট করলাম। কাজটা কিন্তু খোলা থাকছে। কবে অর্ডার করতে পারবেন, বা কখন আবার জানতে চাইব?', (int)$t->id); }
        elseif ($m[2] === 'ord') { self::put('ask_' . $wa, $t->id . ':eta:' . time()); self::sendText($wa, 'ধন্যবাদ। "' . $n . '" অর্ডার করা হয়েছে বলে নোট করলাম। কবে আসবে? লিখুন, যেমন: কাল, পরশু, 3 দিন, 20 তারিখ।', (int)$t->id); }
        else { self::put('ask_' . $wa, $t->id . ':prob:' . time()); self::sendText($wa, '"' . $n . '" নিয়ে সমস্যাটা এক লাইনে লিখে দিন, অফিসকে জানিয়ে দেব।', (int)$t->id); }
        return true;
      }
      $open = DB::table('restock_tasks')->whereNull('closed_at')->count(); if ($body === '' || (!$open && !$payload)) return false;
      if (preg_match('/সুপারভাইজার|ম্যানেজার|মালিক|বসকে|স্যারকে|supervisor|manager|boss/iu', $body)) {
        self::log(null, 'escalate', $body, null, $by);
        DeployHealth::raise('restock_escalate_' . date('Ymd'), 'high', 'Restock', 'Purchase manager asks for the supervisor', $c['name'] . ' wrote on WhatsApp: "' . mb_substr($body, 0, 600) . '"', 'Asked for in the restock follow-up chat', 'Open Restock Follow-up, read the conversation and contact ' . $c['name'] . '.');
        self::sendText($wa, 'ঠিক আছে, বিষয়টা সুপারভাইজারকে জানিয়ে দিলাম। তিনি আপনার সঙ্গে যোগাযোগ করবেন।'); return true;
      }
      if (!preg_match('/^#?[0-9০-৯]{1,2}\s/u', $body) && preg_match('/দাম|মূল্য|রেট|price|rate|মার্জিন|margin/iu', $body) && !preg_match('/অর্ডার|order|আসবে|এসেছে/u', $body)) {
        self::log(null, 'price_talk', $body, null, $by);
        self::sendText($wa, 'আপনার কথাটা নোট করে রাখলাম। আমি নিজে কোনো দাম বদলাই না; দাম বদলাতে অ্যাডমিন প্যানেলে পণ্যের Edit পাতা থেকে করুন। ন্যূনতম নিরাপদ দামের নিচে সিস্টেম সেভ নেবে না।'); return true;
      }
      // which product: a leading number from the last list, the card that was answered, or the question we asked last
      $task = null; $rest = $body; $nums = json_decode(self::set('nums_' . $wa, '{}'), true) ?: [];
      $lead = strtr($body, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
      if (preg_match('/^#?(\d{1,2})\s*[\).:,\-]?\s+(.*)$/us', $lead, $m) && isset($nums[(int)$m[1]]) && !preg_match('/^(ঘণ্টা|ঘন্টা|দিন|মিনিট|সপ্তাহ|তারিখ|টা|ghonta|ghanta|hour|hr|din|day|min|week|tarikh|pm|am)/iu', trim($m[2]))) { $task = (int)$nums[(int)$m[1]]; $rest = trim($m[2]); }
      if (!$task && $ctx) $task = (int)DB::table('restock_events')->where('wamid', $ctx)->whereNotNull('task_id')->value('task_id') ?: null;
      $ask = explode(':', self::set('ask_' . $wa, '')); $asked = count($ask) === 3 && time() - (int)$ask[2] < 6 * 3600 ? [(int)$ask[0], $ask[1]] : null;
      if (!$task && $asked) $task = $asked[0];
      if (!$task && $open === 1) $task = (int)DB::table('restock_tasks')->whereNull('closed_at')->value('id');
      $low = mb_strtolower($rest);
      // "busy, later": with a product number the product waits, without one the whole conversation waits
      $numbered = $rest !== $body; $pz = self::later($rest);
      $dateAns = $asked && in_array($asked[1], ['eta', 'when'], true) && self::eta($rest) !== null && !preg_match('/ঘণ্টা|ঘন্টা|মিনিট|টায়|টার|hour|min/u', $low); // "3 দিন পরে" after we asked for a date is the date, not a pause
      if ($pz && !$dateAns && !preg_match('/অর্ডার|order|আসবে|ashbe|asbe|এসেছে/u', $low)) {
        $till = $pz->copy()->timezone('Asia/Dhaka')->format('d M h:i A');
        if ($numbered && $task) { DB::table('restock_tasks')->where('id', $task)->whereNull('closed_at')->update(['snooze_until' => $pz, 'updated_at' => now()]); self::log($task, 'status', 'Asked to wait until ' . $till . ' · "' . mb_substr($rest, 0, 200) . '"', null, $by); self::sendText($wa, 'ঠিক আছে, এই পণ্যটা নিয়ে ' . $till . ' পর্যন্ত আর জানতে চাইব না।', $task); }
        else { self::put('pause_until', (string)$pz->getTimestamp()); self::put('ask_' . $wa, ''); self::log(null, 'pause', 'Conversation paused until ' . $till . ' · "' . mb_substr($body, 0, 200) . '"', null, $by); self::sendText($wa, 'ঠিক আছে, ' . $till . ' পর্যন্ত আর মেসেজ দেব না। তারপর আবার খোঁজ নেব।'); }
        return true;
      }
      $isArr = (bool)preg_match('/এসেছে|এসে গেছে|চলে এসেছে|পৌঁছেছে|পেয়েছি|esheche|eseche|received|arrived/u', $low);
      $isProb = (bool)preg_match('/সমস্যা|নেই|নাই|পাওয়া যাচ্ছে না|পাচ্ছি না|somossa|problem|nai\b|out of stock/u', $low);
      $isOrd = (bool)preg_match('/অর্ডার|order|কিনেছি|কিনে ফেলেছি|kinechi|po\b/u', $low);
      $isSeen = (bool)preg_match('/দেখেছি|দেখলাম|dekhechi|dekhlam|seen|ok\b|ওকে|ঠিক আছে/u', $low);
      $hasEta = self::eta($rest) !== null;
      if (!$task) { self::sendText($wa, 'কোন পণ্যের কথা বলছেন বুঝতে পারিনি। তালিকার নম্বরসহ লিখুন, যেমন: 2 অর্ডার করেছি, কাল আসবে। তালিকা দেখতে লিখুন: তালিকা'); return true; }
      $t0 = DB::table('restock_tasks')->where('id', $task)->whereNull('closed_at')->first();
      if (!$t0) { self::sendText($wa, 'এই পণ্যের কাজটি আগেই বন্ধ হয়ে গেছে। তালিকা দেখতে লিখুন: তালিকা'); return true; }
      $n = self::nameOf($t0); self::put('ask_' . $wa, '');
      if ($isArr) { self::apply($task, 'note', 'WhatsApp-এ জানিয়েছেন: ' . $rest, $by); self::sendText($wa, 'ধন্যবাদ। "' . $n . '" এসেছে বলে নোট করলাম। স্টক বাড়বে পণ্য গ্রহণের পরিমাণ সিস্টেমে (Purchase) তোলার পর; তখনই এই কাজ বন্ধ হবে।', $task); return true; }
      if ($isProb || ($asked && $asked[1] === 'prob' && $asked[0] === $task && !$isOrd && !$hasEta)) { self::apply($task, 'prob', $rest, $by); self::sendText($wa, '"' . $n . '" এর সমস্যা নোট করে অফিসকে জানিয়ে দিয়েছি।', $task); return true; }
      if ($isOrd) {
        $t = self::apply($task, 'ord', $rest, $by);
        if ($t && $t->eta_at) self::sendText($wa, 'ধন্যবাদ। "' . $n . '" অর্ডার করা, আসবে ' . \Carbon\Carbon::parse($t->eta_at, 'UTC')->timezone('Asia/Dhaka')->format('d M') . '। ততদিন মনে করাব না।', $task);
        else { self::put('ask_' . $wa, $task . ':eta:' . time()); self::sendText($wa, 'ধন্যবাদ। "' . $n . '" অর্ডার করা হয়েছে বলে নোট করলাম। কবে আসবে? লিখুন, যেমন: কাল, পরশু, 3 দিন, 20 তারিখ।', $task); }
        return true;
      }
      if ($hasEta && !$isOrd && $t0->status !== 'ordered' && !($asked && $asked[1] === 'eta' && $asked[0] === $task) && !preg_match('/আসবে|ashbe|asbe|পৌঁছ/u', $low)) {
        $e = self::eta($rest)->copy()->timezone('Asia/Dhaka')->setTime(10, 0)->utc(); if ($e->getTimestamp() < time()) $e = now()->addHours(2);
        DB::table('restock_tasks')->where('id', $task)->update(['snooze_until' => $e, 'updated_at' => now()]); self::log($task, 'status', 'Plans to order · "' . mb_substr($rest, 0, 200) . '" · asked again ' . $e->copy()->timezone('Asia/Dhaka')->format('d M h:i A'), null, $by);
        self::sendText($wa, 'ঠিক আছে। "' . $n . '" নিয়ে ' . $e->copy()->timezone('Asia/Dhaka')->format('d M h:i A') . ' এ আবার জানতে চাইব। অর্ডার দিলে আগেই জানাতে পারেন।', $task); return true;
      }
      if ($hasEta) { $t = self::apply($task, 'eta', $rest, $by); if ($t) { self::sendText($wa, 'ঠিক আছে। "' . $n . '" আসবে ' . \Carbon\Carbon::parse($t->eta_at, 'UTC')->timezone('Asia/Dhaka')->format('d M') . '। ততদিন মনে করাব না; সময় পেরোলে আবার খোঁজ নেব।', $task); return true; } }
      if ($isSeen) { self::apply($task, 'seen', '', $by); self::put('ask_' . $wa, $task . ':when:' . time()); self::sendText($wa, 'ধন্যবাদ, "' . $n . '" দেখেছেন বলে নোট করলাম। কাজটা কিন্তু খোলা থাকছে। কবে অর্ডার করতে পারবেন, বা কখন আবার জানতে চাইব?', $task); return true; }
      self::apply($task, 'note', $rest, $by); self::sendText($wa, '"' . $n . '" এর জন্য আপনার কথাটা নোট করে রাখলাম।', $task); return true;
    } catch (\Throwable $e) { report($e); return false; }
  }

  // ---------- purchase rate and margin (told to the purchase manager, never changed by the assistant) ----------
  private static function scopeCats(?array $c = null): ?array { $c ??= self::cfg(); $sm = self::smoke(); if (!$sm) return null; return $c['scope'] === 'smoke' ? $sm : []; }
  /** Products of the scope that are really bought (no Single Packet twins). */
  private static function scoped($q, string $a = 'p.') {
    $c = self::cfg(); $sm = self::smoke(); $bl = self::blocked($c) ?: [];
    return $q->when($c['scope'] === 'smoke', fn($w) => $w->whereIn($a . 'category_id', $sm))->when($bl, fn($w) => $w->whereNotIn($a . 'category_id', $bl))->whereNull($a . 'parent_product_id')
      ->where(fn($w) => $w->whereNull($a . 'sub')->orWhereRaw('lower(' . $a . 'sub) <> ?', ['single packet']))->whereRaw('lower(' . $a . "name) not like 'single %'")->where(fn($w) => $w->whereNull($a . 'restock_off')->orWhere($a . 'restock_off', false));
  }
  /** A notice waits here until it can be sent. The same key is never queued twice. */
  public static function queue(string $key, string $text): bool {
    try {
      if (DB::table('restock_events')->where('wamid', $key)->whereIn('kind', ['notice_q', 'notice'])->exists()) return false;
      DB::table('restock_events')->insert(['task_id' => null, 'kind' => 'notice_q', 'body' => mb_substr($text, 0, 3000), 'wamid' => $key, 'by_name' => 'System', 'created_at' => now(), 'updated_at' => now()]); return true;
    } catch (\Throwable $e) { return false; }
  }
  public static function notices() { try { return DB::table('restock_events')->where('kind', 'notice_q')->orderBy('id')->limit(40)->get(); } catch (\Throwable $e) { return collect(); } }
  /** Send the waiting notices in as few messages as possible. Returns how many notices went out, 0 when there was nothing, -1 when only the opener could be sent. */
  public static function flush(string $wa): int {
    $rows = self::notices(); if ($rows->isEmpty()) return 0; $c = self::cfg();
    if (!self::windowOpen($wa)) {
      if (self::tplStatus() !== 'APPROVED' || !Cache::add('rs_tpl_sent', 1, max(1800, $c['every'] * 60 - 120))) return -1;
      self::sendTemplate($wa, $c['name'], $rows->count() + self::pending()->count()); return -1;
    }
    $sent = 0; $chunk = []; $len = 0; $ids = [];
    $go = function () use (&$chunk, &$len, &$ids, &$sent, $wa) {
      if (!$chunk) return true;
      $ok = self::sendText($wa, "DutyFreeBD স্টক সহকারী (স্বয়ংক্রিয়)\nক্রয়মূল্য ও মার্জিনের আপডেট:\n\n" . implode("\n\n", $chunk));
      if ($ok) { DB::table('restock_events')->whereIn('id', $ids)->update(['kind' => 'notice', 'updated_at' => now()]); $sent += count($ids); self::copy("DutyFreeBD স্টক সহকারী (স্বয়ংক্রিয়)\nক্রয়মূল্য ও মার্জিনের আপডেট:\n\n" . implode("\n\n", $chunk)); }
      $chunk = []; $len = 0; $ids = []; return $ok;
    };
    foreach ($rows as $r) { $l = mb_strlen((string)$r->body) + 2; if ($len + $l > 3300 && !$go()) return $sent; $chunk[] = (string)$r->body; $ids[] = $r->id; $len += $l; }
    $go(); return $sent;
  }
  /** A purchase recorded at a different rate than the one before it: tell the purchase manager, with what it does to the margin. */
  public static function rateWatch(): void {
    try {
      $last = (int)self::set('pi_last', '-1'); $max = (int)DB::table('purchase_items')->max('id');
      if ($last < 0) { self::put('pi_last', (string)$max); return; } // first run: start from now, old purchases are not announced
      if ($max <= $last) return; if (self::scopeCats() === null) return;
      $rows = self::scoped(DB::table('purchase_items as pi')->join('products as p', 'p.id', '=', 'pi.product_id')->where('pi.id', '>', $last)->where('pi.cost', '>', 0))->orderBy('pi.id')->limit(300)->get(['pi.id', 'pi.product_id', 'pi.cost', 'p.name', 'p.price']);
      $costs = ProfitFloor::costs($rows->pluck('product_id')->all());
      foreach ($rows as $r) {
        $prev = (float)DB::table('purchase_items')->where('product_id', $r->product_id)->where('id', '<', $r->id)->where('cost', '>', 0)->orderByDesc('id')->value('cost'); $new = (float)$r->cost;
        if ($prev <= 0 || abs($new - $prev) < max(1.0, $prev * 0.005)) continue;
        $up = $new > $prev; $price = (float)$r->price; $base = (float)($costs[$r->product_id]->cost ?? 0); if ($base <= 0) $base = $new; $floor = ProfitFloor::floor(max($base, $up ? $new : 0));
        // owner 17 Nov: up = red, down = green, never the same colour
        $t = ($up ? '🔴⬆️ ক্রয়মূল্য বেড়েছে: ' : '🟢⬇️ ক্রয়মূল্য কমেছে: ') . $r->name . "\n   আগে " . SmsText::tk($prev) . ' → এখন ' . SmsText::tk($new) . ' (' . ($up ? '+' : '−') . SmsText::tk(abs($new - $prev)) . ')'
          . "\n   বিক্রয়মূল্য " . SmsText::tk($price) . ' · নতুন রেটে লাভ ' . self::num(($price - $new) / $new * 100) . '%';
        if ($price + 0.004 < ProfitFloor::floor($new)) $t .= "\n   ন্যূনতম নিরাপদ দাম " . SmsText::tk(ProfitFloor::floor($new)) . '। বিক্রয়মূল্য বাড়ানো দরকার।';
        elseif (!$up) $t .= "\n   দাম কমানোর সুযোগ আছে; ন্যূনতম নিরাপদ দাম " . SmsText::tk($floor) . '।';
        self::queue('rate:' . $r->id, $t);
      }
      self::put('pi_last', (string)($rows->isEmpty() ? $max : max($last, (int)$rows->max('id'))));
      if ($rows->count() < 300 && (int)self::set('pi_last', '0') < $max) self::put('pi_last', (string)$max);
    } catch (\Throwable $e) { report($e); }
  }
  /** Once a day: published products selling under the protected margin. Told when a product is new on the list; the whole list again only once a week. */
  public static function marginWatch(): void {
    try {
      $day = now('Asia/Dhaka')->format('Ymd'); if (self::set('margin_day', '') === $day || self::scopeCats() === null) return; self::put('margin_day', $day);
      $ps = self::scoped(DB::table('products as p')->where('p.published', true)->where('p.price', '>', 0))->limit(3000)->get(['p.id', 'p.name', 'p.price']); if ($ps->isEmpty()) return;
      $costs = ProfitFloor::costs($ps->pluck('id')->all()); $low = [];
      foreach ($ps as $p) { $cost = (float)($costs[$p->id]->cost ?? 0); if ($cost <= 0) continue; $price = (float)$p->price; if ($price + 0.004 >= ProfitFloor::floor($cost)) continue; $low[(int)$p->id] = [(string)$p->name, $price, $cost, ProfitFloor::floor($cost)]; }
      $was = array_map('intval', json_decode(self::set('margin_ids', '[]'), true) ?: []); $new = array_values(array_diff(array_keys($low), $was)); self::put('margin_ids', json_encode(array_keys($low)));
      if (!$low) return;
      $weekly = (int)self::set('margin_full', '0') < time() - 7 * 86400; if (!$new && !$weekly) return;
      $show = $new ?: array_keys($low); if (!$new) self::put('margin_full', (string)time());
      $lines = []; foreach (array_slice($show, 0, 25) as $i => $id) { [$n, $price, $cost, $floor] = $low[$id]; $lines[] = ($i + 1) . ') ' . $n . "\n   বিক্রয়মূল্য " . SmsText::tk($price) . ' · খরচ ' . SmsText::tk($cost) . ' · লাভ ' . self::num(($price - $cost) / $cost * 100) . '% · ন্যূনতম ' . SmsText::tk($floor); }
      $more = count($show) - count($lines); $old = count($low) - count($new);
      self::queue('margin:' . $day, ($new ? '⚠️ মার্জিন ' . ProfitFloor::pct() . '%-এর নিচে নেমেছে (' . count($new) . 'টি নতুন):' : '⚠️ মার্জিন ' . ProfitFloor::pct() . '%-এর নিচে আছে (' . count($low) . 'টি, সাপ্তাহিক স্মরণ):') . "\n" . implode("\n", $lines)
        . ($more > 0 ? "\n… আরও " . $more . 'টি' : '') . ($new && $old > 0 ? "\nআগে থেকে নিচে আছে আরও " . $old . 'টি।' : '') . "\nএগুলোর বিক্রয়মূল্য ন্যূনতম দামে তোলা দরকার।");
    } catch (\Throwable $e) { report($e); }
  }

  // ---------- worker ----------
  public static function tick(): void {
    try {
      if (!self::ready()) return;
      if (self::tplStatus() === 'PENDING' && Cache::add('rs_tpl_sync', 1, 600)) self::syncTemplate();
      $c = self::cfg(); if (!$c['on']) return;
      if (Cache::add('rs_scan', 1, 900)) { self::scan(); self::rateWatch(); self::marginWatch(); }
      if (!self::isVerified($c)) return; // nothing is sent by itself until the number has answered once (Send verification message)
      $now = now('Asia/Dhaka'); $min = $now->hour * 60 + $now->minute; if ($min < $c['from'] || $min >= $c['to']) return;
      if ((int)self::set('pause_until', '0') > time()) return; // the purchase manager asked us to wait
      if (self::notices()->isNotEmpty() && Cache::add('rs_notice', 1, 1800)) { $n = self::flush($c['wa']); if ($n !== 0) { self::log(null, 'round', 'Rate / margin updates: ' . ($n > 0 ? $n . ' sent' : 'opener sent, waiting for an answer'), null, 'System'); } }
      $last = (int)self::set('last_round', '0'); if (time() - $last < $c['every'] * 60) return;
      if (!Cache::add('rs_round', 1, 300)) return;
      if (self::pending()->isEmpty()) return;
      self::put('last_round', (string)time()); // set first: a failed send is not retried every minute
      $res = self::digest($c['wa']); self::log(null, 'round', 'Reminder round: ' . $res, null, 'System'); if (str_starts_with($res, 'template')) self::copy();
    } catch (\Throwable $e) { report($e); }
  }

  public static function submitTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'language' => 'bn', 'category' => 'UTILITY', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['মামুন', '4']]]], ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => self::BTN]]]]]);
      if ($r->successful()) { self::put('tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  /** Removes the first, unused template (restock_followup) from the WhatsApp account. Owner approved 17 Nov 2026. Only this one name can be removed here. */
  public static function dropOldTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->delete(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => 'restock_followup']);
      if ($r->successful()) return [true, 'restock_followup removed'];
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::TPL);
      $s = $row ? strtoupper((string)$row['status']) : 'NOT SUBMITTED'; self::put('tpl_status', $s);
      return [true, $s . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
