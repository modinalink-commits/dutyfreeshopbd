<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Order follow-up by responsible staff (owner, 17 Nov 2026).
 * Responsible = the person a supervisor handed the order to, otherwise the person who approved it by hand. An order nobody is responsible for
 * (not approved yet, or approved by AI) is on the supervisor's list. The responsibility stays after the parcel is with the courier, until it is settled.
 * Every hour, in the configured hours, each person gets one short report of their own overdue work. A note or "seen" never closes anything:
 * an item leaves the report only when the order itself has moved on. A follow-up time parks the item until that time.
 */
class OrderWatch
{
  const API = 'https://graph.facebook.com/v21.0', TPL = 'order_staff_report', BY = 'Order Watch (auto)';
  const BODY = "DutyFreeBD: {{1}}, {{2}}\n\nআপনার অ্যাসাইন করা অর্ডারগুলোর অগ্রগতি পর্যবেক্ষণ করুন। কুরিয়ারে থাকা অবস্থায়ও কোনো সমস্যা হলে ফলোআপ করে কারণ ও পরবর্তী পদক্ষেপ আপডেট করুন।\n\nবিস্তারিত: https://dutyfreebd.com/admin/my-orders";
  const MAIN = 'আপনার অ্যাসাইন করা অর্ডারগুলোর অগ্রগতি পর্যবেক্ষণ করুন। কুরিয়ারে থাকা অবস্থায়ও কোনো সমস্যা হলে ফলোআপ করে কারণ ও পরবর্তী পদক্ষেপ আপডেট করুন।';
  const LINK = 'https://dutyfreebd.com/admin/my-orders';
  /** key => [name, short name for SMS, what to do, default limit in hours, English label]. Listed most urgent first. */
  const CATS = [
    'courier'    => ['কুরিয়ারে হোল্ড / ডেলিভারি ব্যর্থ', 'কুরিয়ার সমস্যা', 'কুরিয়ার ও গ্রাহকের সঙ্গে যোগাযোগ করুন', 0, 'Courier hold / failed delivery'],
    'late'       => ['বিলম্বিত ডেলিভারি', 'বিলম্বিত', 'সর্বশেষ অবস্থা ও সমাধানের সম্ভাব্য সময় জানান', 0, 'Late delivery'],
    'followup'   => ['ফলোআপের সময় পেরিয়েছে', 'ফলোআপ বাকি', 'পুনরায় যোগাযোগ করুন', 0, 'Follow-up time passed'],
    'return'     => ['আংশিক ডেলিভারি / রিটার্ন পেন্ডিং', 'রিটার্ন পেন্ডিং', 'অবশিষ্ট কাজ ও নিষ্পত্তির অগ্রগতি দেখুন', 24, 'Partial delivery / return pending'],
    'payment'    => ['পেমেন্ট যাচাই বাকি', 'পেমেন্ট যাচাই', 'পেমেন্টের অবস্থা যাচাই করুন', 0.5, 'Payment check pending'],
    'handover'   => ['প্যাকিং / কুরিয়ারে হস্তান্তরে বিলম্ব', 'হস্তান্তরে বিলম্ব', 'কারণ যাচাই করে সমন্বয় করুন', 6, 'Packing / courier handover delayed'],
    'pack'       => ['অনুমোদিত, প্যাকিং শুরু হয়নি', 'প্যাকিং বাকি', 'কাজটি এগিয়ে নিন', 3, 'Approved, packing not started'],
    'notstarted' => ['অ্যাসাইন করা, কাজ শুরু হয়নি', 'কাজ শুরু হয়নি', 'কাজ শুরু করে অগ্রগতি লিখুন', 1, 'Assigned, work not started'],
    'pending'    => ['পেন্ডিং', 'পেন্ডিং', 'আটকে থাকার কারণ ও পরবর্তী পদক্ষেপ লিখুন', 2, 'Pending'],
    'new'        => ['নতুন / নিশ্চিতকরণ বাকি', 'নতুন', 'পর্যালোচনা করে গ্রাহকের সঙ্গে যোগাযোগ করুন', 0.5, 'New / confirmation pending'],
  ];
  const PRE = ['New', 'Assign', 'AI Order', 'Pre Order'];
  const CLOSED = ['Delivered', 'Cancel', 'Cancelled', 'Returned'];

  public static function ready(): bool { static $r = null; return $r ??= (function () { try { return Schema::hasTable('order_watch_reports') && Schema::hasTable('order_watch_staff') && Schema::hasColumn('orders', 'owner_id'); } catch (\Throwable $e) { return false; } })(); }
  public static function set(string $k, string $d = ''): string { return Procure::set('owatch_' . $k, $d); }
  public static function put(string $k, string $v): void { Procure::put('owatch_' . $k, $v); }
  public static function cfg(): array {
    $hm = fn($v, $d) => preg_match('/^(\d{1,2}):(\d{2})$/', trim($v), $m) && ((int)$m[1] < 24 || ((int)$m[1] === 24 && (int)$m[2] === 0)) && (int)$m[2] < 60 ? (int)$m[1] * 60 + (int)$m[2] : $d;
    $lim = []; foreach (self::CATS as $k => $c) { $v = self::set('lim_' . $k, (string)$c[3]); $lim[$k] = is_numeric($v) ? max(0, min(720, (float)$v)) : (float)$c[3]; }
    $ch = self::set('channel', 'whatsapp');
    return ['on' => self::set('on', '0') === '1', 'from' => $hm(self::set('from', '10:00'), 600), 'to' => ($hm(self::set('to', '21:00'), 1260) ?: 1440), 'channel' => in_array($ch, ['whatsapp', 'sms'], true) ? $ch : 'whatsapp',
      'sms_fallback' => self::set('sms_fallback', '0') === '1', 'lim' => $lim, 'inc' => max(0, (float)self::set('lim_incomplete', '0.5')), 'retries' => max(0, min(3, (int)self::set('retries', '1')))];
  }
  public static function tplStatus(): string { return self::set('tpl_status', 'NOT SUBMITTED'); }
  public static function supervisor($u): bool { return $u && in_array((string)($u->role ?? ''), ['super', 'admin'], true); }
  /** Staff who get a report: [user_id => {user_id, name, phone (8801…), active}]. */
  public static function staff(): array {
    $out = []; try { foreach (DB::table('order_watch_staff as s')->join('users as u', 'u.id', '=', 's.user_id')->orderBy('u.name')->get(['s.user_id', 's.phone', 's.active', 'u.name', 'u.active as user_active']) as $r) $out[(int)$r->user_id] = (object)['user_id' => (int)$r->user_id, 'name' => (string)$r->name, 'phone' => WhatsApp::norm((string)$r->phone), 'active' => (bool)$r->active && (bool)$r->user_active]; } catch (\Throwable $e) {}
    return $out;
  }
  public static function validWa(string $wa): bool { return (bool)preg_match('/^8801[3-9]\d{8}$/', $wa); }

  /** Who is responsible for an order row (needs owner_id, approval_source, approved_by). Null = nobody: the supervisor's list. */
  public static function ownerOf(object $o): ?int {
    if (!empty($o->owner_id)) return (int)$o->owner_id > 0 ? (int)$o->owner_id : null; // -1 = a supervisor put it back on the unassigned list
    if (($o->approval_source ?? null) === 'MANUAL' && !empty($o->approved_by)) return (int)$o->approved_by;
    if (($o->approval_source ?? null) === 'AI') { static $ai = false; if ($ai === false) $ai = (int)self::set('ai_owner', '0') ?: null; return $ai; } // owner 17 Nov: orders approved by the AI automation are watched by one named person
    return null;
  }
  /** Hand an order to a person (or to nobody). Supervisor only; the caller checks that. Every change is logged with who and why. */
  public static function assign(int $orderId, ?int $to, string $reason, ?object $by): array {
    $o = DB::table('orders')->where('id', $orderId)->first(['id', 'invoice', 'owner_id', 'approval_source', 'approved_by']); if (!$o) return [false, 'Order not found.'];
    if ($to !== null && !DB::table('users')->where('id', $to)->where('active', true)->exists()) return [false, 'That staff member is not active.'];
    $from = self::ownerOf($o); if ($from === $to) return [false, 'This order is already with that person.'];
    // "nobody" on an order somebody approved by hand: -1 is stored so the approver is not picked up again
    DB::table('orders')->where('id', $orderId)->update(['owner_id' => $to ?? -1, 'owner_at' => now(), 'updated_at' => DB::raw('updated_at')]);
    DB::table('order_owner_log')->insert(['order_id' => $orderId, 'from_id' => $from, 'to_id' => $to, 'by_id' => $by->id ?? null, 'by_name' => $by->name ?? 'System', 'reason' => mb_substr(trim($reason), 0, 300), 'created_at' => now(), 'updated_at' => now()]);
    try { OrderActivity::log($orderId, 'owner_changed', $from ? ChatOwner::name($from) : 'Nobody', $to ? ChatOwner::name($to) : 'Nobody', 'Responsible staff changed. Reason: ' . trim($reason)); } catch (\Throwable $e) {}
    return [true, 'Order ' . Inv::show($o->invoice) . ' is now with ' . ($to ? ChatOwner::name($to) : 'nobody (supervisor list)') . '.'];
  }

  /**
   * Every order that needs attention now, from the database. Row: id, invoice, status, cat, owner (id or null), age_h (hours overdue-relevant age),
   * since (UTC time the order entered this state), stage, synced_at, parked_until (when a follow-up time holds it back), last_note.
   */
  public static function issues(): array {
    $c = self::cfg(); $lim = $c['lim']; $now = time();
    $cols = ['id', 'invoice', 'status', 'created_at', 'updated_at', 'approved_at', 'approval_source', 'approved_by', 'owner_id', 'owner_at', 'scheduled_at', 'shipment_at', 'courier_sent_at', 'courier_stage', 'courier_status', 'courier_synced_at', 'return_type', 'return_received_at', 'consignment_id', 'courier'];
    if (Schema::hasColumn('orders', 'pay_status')) $cols[] = 'pay_status';
    $rows = DB::table('orders')->whereNotIn('status', self::CLOSED)->where('created_at', '>=', now()->subDays(120))->when(Schema::hasColumn('orders', 'duplicate_of_order_id'), fn($q) => $q->whereNull('duplicate_of_order_id'))->orderBy('id')->limit(8000)->get($cols);
    if ($rows->isEmpty()) return [];
    $ids = $rows->pluck('id')->all(); $since = []; $notes = []; $acted = [];
    foreach (array_chunk($ids, 1000) as $ch) {
      foreach (DB::table('order_status_logs')->whereIn('order_id', $ch)->groupBy('order_id')->selectRaw('order_id, max(created_at) t')->get() as $l) $since[$l->order_id] = (string)$l->t;
      foreach (DB::table('order_watch_notes')->whereIn('order_id', $ch)->orderBy('id')->get(['order_id', 'note', 'action', 'next_followup_at', 'user_name', 'created_at']) as $n) $notes[$n->order_id] = $n; // the last one wins
    }
    $out = [];
    foreach ($rows as $o) {
      $st = (string)$o->status; $t0 = strtotime((string)($since[$o->id] ?? $o->created_at) . ' UTC'); $h = ($now - $t0) / 3600; $stage = (string)($o->courier_stage ?? ''); $cat = null; $pay = (string)($o->pay_status ?? '');
      $owner = self::ownerOf($o);
      if (in_array($st, ['Shipment', 'Late Delivery'], true) && in_array($stage, ['Hold', 'Failed Delivery'], true)) $cat = 'courier';
      elseif ($st === 'Late Delivery') $cat = 'late';
      elseif ($st === 'Scheduled') { if ($o->scheduled_at && strtotime((string)$o->scheduled_at . ' UTC') < $now) { $cat = 'followup'; $t0 = strtotime((string)$o->scheduled_at . ' UTC'); $h = ($now - $t0) / 3600; } }
      elseif ($st === 'Return Pending' || ($st === 'Partial Delivered' && empty($o->return_received_at))) { if ($h >= $lim['return']) $cat = 'return'; }
      elseif ($pay === 'Verification Pending' && in_array($st, array_merge(self::PRE, ['Pending', 'WFP']), true)) { if ($h >= $lim['payment']) $cat = 'payment'; }
      elseif ($st === 'Packaging') { if ($h >= $lim['handover']) $cat = 'handover'; }
      elseif ($st === 'Approved') { if ($o->approved_at) { $t0 = max($t0, strtotime((string)$o->approved_at . ' UTC')); $h = ($now - $t0) / 3600; } if ($h >= $lim['pack']) $cat = 'pack'; }
      elseif (in_array($st, ['Pending', 'WFP'], true)) { if ($h >= $lim['pending']) $cat = 'pending'; }
      elseif (in_array($st, self::PRE, true)) { if ($h >= $lim['new']) $cat = 'new'; }
      // handed to someone by a supervisor and nothing has moved since: "assigned, work not started"
      if (in_array($cat, ['new', 'pending', null], true) && !empty($o->owner_id) && (int)$o->owner_id > 0 && $o->owner_at && in_array($st, array_merge(self::PRE, ['Pending', 'WFP']), true)) {
        $ta = strtotime((string)$o->owner_at . ' UTC'); if ($t0 <= $ta && ($now - $ta) / 3600 >= $lim['notstarted']) { $cat = 'notstarted'; $t0 = $ta; $h = ($now - $ta) / 3600; }
      }
      if (!$cat) continue;
      $n = $notes[$o->id] ?? null; $parked = null;
      if ($n && $n->next_followup_at) { $tf = strtotime((string)$n->next_followup_at . ' UTC'); if ($tf > $now) $parked = (string)$n->next_followup_at; elseif (strtotime((string)$n->created_at . ' UTC') >= $t0 - 60) { $cat = 'followup'; $t0 = $tf; $h = ($now - $tf) / 3600; } }
      $out[] = (object)['id' => (int)$o->id, 'invoice' => Inv::show($o->invoice), 'status' => $st, 'cat' => $cat, 'owner' => $owner, 'age_h' => round($h, 1), 'since' => gmdate('Y-m-d H:i:s', $t0), 'stage' => $stage, 'courier' => (string)($o->courier ?? ''), 'has_parcel' => !empty($o->consignment_id),
        'synced_at' => $o->courier_synced_at ? (string)$o->courier_synced_at : null, 'parked_until' => $parked, 'last_note' => $n];
    }
    $rank = array_flip(array_keys(self::CATS));
    usort($out, fn($a, $b) => [$rank[$a->cat], -$a->age_h] <=> [$rank[$b->cat], -$b->age_h]);
    return $out;
  }
  /** One person's open items (parked ones left out), counts by category, and the most urgent invoices. $uid null = the supervisor's unassigned list. */
  public static function forUser(?int $uid, ?array $all = null): array {
    $all ??= self::issues(); $mine = array_values(array_filter($all, fn($i) => $i->owner === $uid)); $live = array_values(array_filter($mine, fn($i) => !$i->parked_until));
    $counts = []; foreach ($live as $i) $counts[$i->cat] = ($counts[$i->cat] ?? 0) + 1;
    $ordered = []; foreach (array_keys(self::CATS) as $k) if (!empty($counts[$k])) $ordered[$k] = $counts[$k];
    $urgent = array_slice(array_map(fn($i) => $i->invoice, array_values(array_filter($live, fn($i) => in_array($i->cat, ['courier', 'late', 'followup', 'return'], true)))), 0, 4);
    return ['items' => $mine, 'live' => $live, 'counts' => $ordered, 'total' => count($live), 'parked' => count($mine) - count($live), 'urgent' => $urgent];
  }
  public static function bn($n): string { return (string)$n; }
  /** [whatsapp text, template summary line, sms text] for one person, with the change against their last sent report. Null when there is nothing to report. */
  public static function compose(object $s, array $d): ?array {
    if ($d['total'] < 1) return null;
    $prev = DB::table('order_watch_reports')->where('user_id', $s->user_id)->where('status', 'sent')->orderByDesc('id')->value('total');
    $delta = $prev === null ? '' : ((int)$prev === $d['total'] ? 'আগের রিপোর্টের সমান' : ($d['total'] > (int)$prev ? 'আগের চেয়ে ' . ($d['total'] - (int)$prev) . 'টি বেশি' : 'আগের চেয়ে ' . ((int)$prev - $d['total']) . 'টি কম'));
    $lines = []; $short = []; $sms = [];
    foreach ($d['counts'] as $k => $n) { $lines[] = '• ' . self::CATS[$k][0] . ': ' . $n . 'টি (' . self::CATS[$k][2] . ')'; $short[] = self::CATS[$k][0] . ' ' . $n . 'টি'; $sms[] = self::CATS[$k][1] . ' ' . $n; }
    $urg = $d['urgent'] ? 'জরুরি অর্ডার: ' . implode(', ', $d['urgent']) : '';
    $text = 'DutyFreeBD: ' . $s->name . ', আপনার বকেয়া কাজ ' . $d['total'] . 'টি' . ($delta !== '' ? ' (' . $delta . ')' : '') . "।\n" . implode("\n", $lines) . ($urg !== '' ? "\n" . $urg : '') . "\n\n" . self::MAIN . "\nবিস্তারিত: " . self::LINK;
    $line = mb_substr('আপনার বকেয়া কাজ ' . $d['total'] . 'টি: ' . implode(', ', $short) . '।' . ($urg !== '' ? ' ' . $urg . '।' : '') . ($delta !== '' ? ' ' . $delta . '।' : ''), 0, 600);
    $smsT = 'DutyFreeBD: ' . $s->name . ', বকেয়া ' . $d['total'] . 'টি (' . implode(', ', array_slice($sms, 0, 4)) . ')। ফলোআপ করে কারণ ও পরবর্তী পদক্ষেপ আপডেট করুন। dutyfreebd.com/admin/my-orders';
    return [$text, $line, $smsT];
  }

  private static function post(array $json): array {
    $t = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id'); if (!$t || !$pid) return [false, null, 'WhatsApp is not connected.'];
    try {
      $r = Http::timeout(20)->withToken($t)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp'] + $json);
      if ($r->successful()) return [true, (string)$r->json('messages.0.id'), null];
      return [false, null, mb_substr('(' . (int)$r->json('error.code') . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 250)];
    } catch (\Throwable $e) { return [false, null, 'Network error: ' . mb_substr($e->getMessage(), 0, 120)]; }
  }
  /** Send one composed report to a number. Returns [ok, channel, wamid, error]. Free text inside the 24-hour window, the approved template outside it, SMS when that is the channel or the fallback. */
  public static function deliver(string $wa, string $name, array $msg, ?array $c = null): array {
    $c ??= self::cfg(); [$text, $line, $sms] = $msg;
    if ($c['channel'] === 'sms') { [$ok, $m] = Sms::configured() ? Sms::send('0' . substr($wa, 3), $sms) : [false, 'SMS is not configured']; return [$ok, 'sms', null, $ok ? null : $m]; }
    if (Restock::windowOpen($wa)) { [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 3900)]]); if ($ok) Chat::logOut($wa, $text, self::BY, $id); }
    elseif (self::tplStatus() === 'APPROVED') {
      [$ok, $id, $err] = self::post(['to' => $wa, 'type' => 'template', 'template' => ['name' => self::TPL, 'language' => ['code' => 'bn'], 'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $name], ['type' => 'text', 'text' => $line]]]]]]);
      if ($ok) Chat::logOut($wa, strtr(self::BODY, ['{{1}}' => $name, '{{2}}' => $line]), self::BY, $id);
    } else { $ok = false; $id = null; $err = 'The 24-hour window is closed and the template is ' . self::tplStatus(); }
    if (!$ok && $c['sms_fallback'] && Sms::configured()) { [$ok2, $m] = Sms::send('0' . substr($wa, 3), $sms); return [$ok2, 'sms', null, $ok2 ? null : 'WhatsApp: ' . $err . ' · SMS: ' . $m]; }
    return [$ok, 'whatsapp', $id, $ok ? null : $err];
  }
  /** The hourly round. One row per person per hour is claimed first (unique index), so a report can never go twice. Returns how many were sent. */
  public static function round(?string $hourKey = null): int {
    $c = self::cfg(); $key = $hourKey ?? now('Asia/Dhaka')->format('Ymd-H'); $all = self::issues(); $sent = 0;
    foreach (self::staff() as $s) {
      if (!$s->active || !self::validWa($s->phone)) continue;
      $d = self::forUser($s->user_id, $all); $msg = self::compose($s, $d); if (!$msg) continue;
      try { $rid = DB::table('order_watch_reports')->insertGetId(['user_id' => $s->user_id, 'hour_key' => $key, 'phone' => $s->phone, 'status' => 'queued', 'total' => $d['total'], 'counts' => json_encode($d['counts']), 'body' => $msg[0], 'created_at' => now(), 'updated_at' => now()]); }
      catch (\Throwable $e) { continue; } // already claimed for this hour
      [$ok, $ch, $id, $err] = self::deliver($s->phone, $s->name, $msg, $c);
      DB::table('order_watch_reports')->where('id', $rid)->update(['status' => $ok ? 'sent' : 'failed', 'channel' => $ch, 'wamid' => $id, 'error' => $err ? mb_substr($err, 0, 300) : null, 'attempts' => 1, 'body' => $ch === 'sms' ? $msg[2] : $msg[0], 'updated_at' => now()]);
      if ($ok) $sent++;
    }
    return $sent;
  }
  /** A failed report of the current hour is tried again, up to the configured limit. */
  public static function retry(): void {
    $c = self::cfg(); if ($c['retries'] < 1) return; $key = now('Asia/Dhaka')->format('Ymd-H'); $staff = self::staff();
    foreach (DB::table('order_watch_reports')->where('hour_key', $key)->where('status', 'failed')->where('attempts', '<=', $c['retries'])->where('updated_at', '<', now()->subMinutes(8))->get() as $r) {
      $s = $staff[(int)$r->user_id] ?? null; if (!$s || !$s->active || !self::validWa($s->phone)) continue;
      if (!DB::table('order_watch_reports')->where('id', $r->id)->where('attempts', $r->attempts)->update(['attempts' => $r->attempts + 1, 'updated_at' => now()])) continue;
      $msg = self::compose($s, self::forUser($s->user_id)); if (!$msg) { DB::table('order_watch_reports')->where('id', $r->id)->update(['status' => 'skipped', 'error' => 'Nothing left to report']); continue; }
      [$ok, $ch, $id, $err] = self::deliver($s->phone, $s->name, $msg, $c);
      DB::table('order_watch_reports')->where('id', $r->id)->update(['status' => $ok ? 'sent' : 'failed', 'channel' => $ch, 'wamid' => $id, 'error' => $err ? mb_substr($err, 0, 300) : null, 'updated_at' => now()]);
    }
  }
  public static function tick(): void {
    try {
      if (!self::ready()) return;
      if (self::tplStatus() === 'PENDING' && Cache::add('ow_tpl_sync', 1, 600)) self::syncTemplate();
      $c = self::cfg(); if (!$c['on']) return;
      $now = now('Asia/Dhaka'); $min = $now->hour * 60 + $now->minute; if ($min < $c['from'] || $min >= $c['to']) return;
      $key = $now->format('Ymd-H');
      if (self::set('last_hour', '') !== $key && Cache::add('ow_round_' . $key, 1, 3700)) { self::put('last_hour', $key); self::round($key); }
      elseif (Cache::add('ow_retry', 1, 300)) self::retry();
    } catch (\Throwable $e) { report($e); }
  }
  /** Delivery state of sent reports, read from the inbox record of the same WhatsApp message. */
  public static function receipts(array $wamids): array {
    $wamids = array_values(array_filter($wamids)); if (!$wamids) return [];
    try { return DB::table('chat_messages')->whereIn('ext_id', $wamids)->pluck('status', 'ext_id')->all(); } catch (\Throwable $e) { return []; }
  }
  public static function submitTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'language' => 'bn', 'category' => 'UTILITY', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['Sakib', 'আপনার বকেয়া কাজ 9টি: বিলম্বিত ডেলিভারি 4টি, পেন্ডিং 3টি, প্যাকিং বাকি 2টি। জরুরি অর্ডার: 104959, 104960। আগের চেয়ে 2টি বেশি।']]]]]]);
      if ($r->successful()) { self::put('tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::TPL); $s = $row ? strtoupper((string)$row['status']) : 'NOT SUBMITTED'; self::put('tpl_status', $s);
      return [true, $s . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
