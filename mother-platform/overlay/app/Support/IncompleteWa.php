<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp reminder for an unfinished checkout (owner request, 24 Nov 2026).
 * Step 1: one approved template WITHOUT any product name or photo (WhatsApp forbids promoting tobacco): "your order is not finished, total ৳X" + a button back to the cart + a "Confirm" quick reply.
 * Step 2: when the customer answers, the normal 24-hour chat opens and the AI / staff continue with the full cart details that are kept in the conversation history (never sent by this class).
 * Limits: once per incomplete order, once per number in 48 h, never during quiet hours, never to a customer who already ordered, opted out or is blocked, daily cap. Nothing is sent until Meta approves the template.
 */
class IncompleteWa
{
  const API = 'https://graph.facebook.com/v21.0';
  const TPL = 'cart_reminder_v1';
  const BODY = "প্রিয় {{1}}, আপনার অর্ডারটি এখনো সম্পূর্ণ হয়নি। মোট ৳{{2}}। পণ্যগুলো আপনার কার্টে রাখা আছে।\n\nঅর্ডার কনফার্ম করতে নিচের বোতাম চাপুন, অথবা এখানেই লিখুন, আমরা সাহায্য করব।";

  // Variant B (owner 24 Nov 2026): the button opens the customer's own saved cart, and a "help" reply sends the chat to the support inbox. Tested against variant A half and half.
  const TPL2 = 'cart_reminder_v2';
  const BODY2 = "আসসালামু আলাইকুম {{1}}। আপনার চেকআউটটি অসম্পূর্ণ আছে, মোট ৳{{2}}। অর্ডার শেষ করতে নিচের বোতাম ব্যবহার করুন।\n\nডেলিভারি চার্জ বা পেমেন্ট নিয়ে প্রশ্ন থাকলে আমরা সাহায্য করতে পারি।";

  public static function set(string $k, string $d = ''): string { return Procure::set('inc_wa_' . $k, $d); }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'inc_wa_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function on(): bool { return self::set('on', '1') === '1'; }
  public static function delayMin(): int { return max(10, min(720, (int)self::set('delay', '30'))); }
  public static function dailyCap(): int { return max(1, min(500, (int)self::set('daily_cap', '100'))); }
  public static function quiet(): bool { $h = (int)now('Asia/Dhaka')->format('H'); return $h >= 22 || $h < 10; }
  public static function state(): array { $s = Cache::get('inc_wa.state'); return (is_array($s) ? $s : []) + ['template' => self::set('tpl_status', 'NOT SUBMITTED'), 'template_b' => self::set('tpl2_status', 'NOT SUBMITTED'), 'note_b' => self::set('tpl2_note', ''), 'on' => self::on(), 'delay_min' => self::delayMin(), 'daily_cap' => self::dailyCap(), 'sent_today' => self::sentToday()]; }
  public static function sentToday(): int { try { return (int)DB::table('incomplete_orders')->where('wa_nudged_at', '>=', now('Asia/Dhaka')->startOfDay()->utc())->count(); } catch (\Throwable $e) { return 0; } }

  public static function submitTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'language' => 'bn', 'category' => 'MARKETING', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['রহিম', '2,450']]]],
        ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'অর্ডার সম্পূর্ণ করুন', 'url' => Domain::main() . '/checkout'], ['type' => 'QUICK_REPLY', 'text' => 'কনফার্ম করুন']]]]]);
      if ($r->successful()) { self::put('tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      $m = mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300); self::put('tpl_status', 'SUBMIT FAILED'); self::put('tpl_note', $m); return [false, $m];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function submitB(): void {
    $t = Chat::sec('chat_wa_token'); if (!$t) return;
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL2, 'language' => 'bn', 'category' => 'MARKETING', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY2, 'example' => ['body_text' => [['রহিম', '2,450']]]],
        ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'চেকআউটে ফিরুন', 'url' => Domain::main() . '/checkout?rc={{1}}', 'example' => [Domain::main() . '/checkout?rc=abcdefghijklmnopqrstuvwx']], ['type' => 'QUICK_REPLY', 'text' => 'সহায়তা চাই']]]]]);
      if ($r->successful()) { self::put('tpl2_status', strtoupper((string)$r->json('status', 'PENDING'))); self::put('tpl2_note', ''); return; }
      self::put('tpl2_status', 'SUBMIT FAILED'); self::put('tpl2_note', mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300));
    } catch (\Throwable $e) {}
  }
  public static function syncB(): void {
    $t = Chat::sec('chat_wa_token'); if (!$t) return;
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL2, 'fields' => 'name,status,rejected_reason,category']); if (!$r->successful()) return;
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::TPL2);
      self::put('tpl2_status', $row ? strtoupper((string)$row['status']) : 'NOT SUBMITTED');
      self::put('tpl2_note', $row ? ('category ' . ($row['category'] ?? '?') . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ', rejected: ' . $row['rejected_reason'] : '')) : '');
    } catch (\Throwable $e) {}
  }
  private static function hasVar(): bool { static $h = false; if (!$h) { try { $h = Schema::hasColumn('incomplete_orders', 'wa_variant'); } catch (\Throwable $e) {} } return $h; }
  /** A against B: reminders sent, customers who then ordered within 72 hours, and how many of those orders were delivered. */
  public static function ab(): array {
    try {
      if (!self::hasVar()) return [];
      return array_map(fn($r) => (array)$r, DB::select("select i.wa_variant as variant, count(*) as sent, count(o.id) as ordered, count(o.id) filter (where o.status in ('Delivered','Partial Delivered','Late Delivery')) as delivered
        from incomplete_orders i left join lateral (select o.id, o.status from orders o where right(o.phone, 11) = right(i.phone, 11) and o.created_at >= i.wa_nudged_at and o.created_at <= i.wa_nudged_at + interval '72 hours' and o.status <> 'Cancel' order by o.id limit 1) o on true
        where i.wa_variant is not null group by i.wa_variant order by i.wa_variant"));
    } catch (\Throwable $e) { return []; }
  }
  public static function syncTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::TPL);
      $s = $row ? strtoupper((string)$row['status']) : 'NOT SUBMITTED'; self::put('tpl_status', $s);
      self::put('tpl_note', $row ? ('category ' . ($row['category'] ?? '?') . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ', rejected: ' . $row['rejected_reason'] : '')) : '');
      return [true, $s];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }

  /** What the customer sees, plus the cart details for whoever continues the chat (the bracketed part is NOT sent to the customer). */
  private static function logBody(object $i, string $name, string $total, string $tpl = ''): string {
    $items = []; foreach ((array)(json_decode((string)$i->items, true) ?: []) as $it) { $n = trim((string)($it['name'] ?? '')); if ($n !== '') $items[] = $n . ' ×' . (int)($it['qty'] ?? 1); }
    $b = str_replace(['{{1}}', '{{2}}'], [$name, $total], $tpl === 'B' ? self::BODY2 : self::BODY);
    return $b . "\n\n[ভেতরের তথ্য, গ্রাহককে পাঠানো হয়নি — অসম্পূর্ণ অর্ডার #" . $i->id . ': ' . (implode(', ', array_slice($items, 0, 12)) ?: 'পণ্যের তালিকা নেই') . ' · মোট ৳' . $total . (trim((string)$i->address) !== '' ? ' · ঠিকানা: ' . mb_substr(trim((string)$i->address), 0, 160) : ' · ঠিকানা এখনো দেননি') . ']';
  }

  public static function tick(): void {
    if (!Schema::hasTable('incomplete_orders') || !Schema::hasColumn('incomplete_orders', 'wa_nudged_at')) return;
    // template life cycle: submit once, then ask Meta for the status every 30 minutes until it is approved
    $st = self::set('tpl_status', 'NOT SUBMITTED');
    if ($st === 'NOT SUBMITTED' || $st === 'SUBMIT FAILED') { if (Cache::add('inc_wa.submit2', 1, 3600)) self::submitTemplate(); return; }
    if ($st !== 'APPROVED') { if (Cache::add('inc_wa.sync2', 1, 1800)) self::syncTemplate(); return; }
    // variant B has its own approval; it never holds variant A back
    $st2 = self::set('tpl2_status', 'NOT SUBMITTED');
    if ($st2 === 'NOT SUBMITTED' || $st2 === 'SUBMIT FAILED') { if (Cache::add('inc_wa.submitB', 1, 3600)) self::submitB(); }
    elseif ($st2 !== 'APPROVED') { if (Cache::add('inc_wa.syncB', 1, 1800)) self::syncB(); }
    $bOk = self::set('tpl2_status', '') === 'APPROVED' && self::hasVar();
    if (!self::on() || self::quiet()) return;
    $since = self::set('since', ''); if ($since === '') { self::put('since', now()->utc()->toDateTimeString()); return; } // only checkouts left unfinished after the reminder went live
    if (!Cache::add('inc_wa.slot', 1, 240)) return; // one small batch every 4 minutes
    $room = min(8, self::dailyCap() - self::sentToday()); if ($room <= 0) return;
    $tok = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id'); if (!$tok || !$pid) return;
    $rows = DB::table('incomplete_orders')->where('status', 'open')->whereNull('wa_nudged_at')->where('created_at', '>=', $since)->where('created_at', '>=', now()->subHours(12))->where('total', '>', 0)
      ->whereRaw('coalesce(last_activity_at, created_at) <= ?', [now()->subMinutes(self::delayMin())])->orderBy('id')->limit(40)->get();
    $sent = 0; $info = Cache::get('inc_wa.state'); $info = is_array($info) ? $info : [];
    foreach ($rows as $i) {
      if ($sent >= $room) break;
      if (!DB::table('incomplete_orders')->where('id', $i->id)->whereNull('wa_nudged_at')->update(['wa_nudged_at' => now()])) continue; // claim: never twice
      try {
        $d = substr(preg_replace('/\D/', '', (string)$i->phone), -11); if (!preg_match('/^01[3-9]\d{8}$/', $d)) continue;
        if (DB::table('orders')->whereRaw('right(phone,11) = ?', [$d])->where('created_at', '>=', $i->created_at)->exists()) continue; // already ordered
        $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$d])->first(['blocked', 'promo_sms']); if ($c && (!empty($c->blocked) || (isset($c->promo_sms) && $c->promo_sms === false))) continue;
        try { if (DB::table('sms_optouts')->whereRaw('right(phone,11) = ?', [$d])->exists()) continue; } catch (\Throwable $e) {} // asked us to stop messages
        if (DB::table('incomplete_orders')->whereRaw('right(phone,11) = ?', [$d])->where('id', '<>', $i->id)->where('wa_nudged_at', '>=', now()->subHours(48))->exists()) continue; // one per number in 48 h
        $to = WhatsApp::norm($d); $name = mb_substr(trim((string)$i->name) ?: 'প্রিয় গ্রাহক', 0, 40); $total = number_format((float)$i->total, 0, '.', ',');
        $var = $bOk && ((int)$i->id % 2 === 1) && preg_match('/^[A-Za-z0-9]{16,40}$/', (string)$i->token) ? 'B' : 'A'; // half and half, by the unfinished order's number
        $comp = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $name], ['type' => 'text', 'text' => $total]]]];
        if ($var === 'B') $comp[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => (string)$i->token]]];
        $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
          'template' => ['name' => $var === 'B' ? self::TPL2 : self::TPL, 'language' => ['code' => 'bn'], 'components' => $comp]]);
        if ($r->successful()) { $sent++; $info['total_sent'] = (int)($info['total_sent'] ?? 0) + 1; $info['last_sent_at'] = now()->toIso8601String(); Chat::logOut($to, self::logBody($i, $name, $total, $var), 'Campaign: cart reminder', $r->json('messages.0.id')); if (self::hasVar()) DB::table('incomplete_orders')->where('id', $i->id)->update(['wa_variant' => $var]); }
        else { $info['last_error'] = mb_substr('(' . (int)$r->json('error.code') . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 240); $info['failed'] = (int)($info['failed'] ?? 0) + 1; if ((int)$r->json('error.code') === 132001) { self::put('tpl_status', 'NOT APPROVED YET'); break; } }
      } catch (\Throwable $e) { $info['last_error'] = mb_substr($e->getMessage(), 0, 200); }
    }
    $info['last_run'] = now()->toIso8601String(); Cache::forever('inc_wa.state', $info);
  }
}
