<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pathao key account manager (KAM) follow-up for Late Delivery and courier Hold parcels, over the existing WhatsApp connection.
 * - Automatic: every hour from 10:00 to 18:00 (Dhaka) ONE message lists every parcel that is late or on hold with Pathao (customer name + consignment id + short state),
 *   and asks the KAM to answer in the same chat. Nothing is sent when there is no issue. Owner approved this on 23 Nov 2026.
 * - By hand: staff can tick parcels on the Late Delivery page and send them at once (complaint case; the same consignment is not sent twice within 12 hours).
 * The KAM's answers arrive through the normal WhatsApp webhook and are shown on the Late Delivery page. No customer phone or address is ever sent.
 * WhatsApp allows free text only within 24 h of the KAM's last message; outside it an approved template (setting kam_template) is needed.
 */
class CourierKam
{
  const MAX = 15;
  const GAP_HOURS = 12;
  const AUTO_MAX_MSG = 2;

  public static function wa(): string { return WhatsApp::norm(Procure::set('kam_pathao_wa', '01958112733')); }
  public static function label(): string { return Procure::set('kam_pathao_name', 'Pathao KAM (Ismail Bari)'); }
  public static function autoOn(): bool { return Procure::set('kam_auto', '1') === '1'; }
  public static function fromHour(): int { return max(0, min(23, (int)Procure::set('kam_from', '10'))); }
  public static function toHour(): int { return max(self::fromHour(), min(23, (int)Procure::set('kam_to', '18'))); }

  public static function eligible(object $o): bool {
    if (empty($o->consignment_id) || stripos((string)($o->courier ?? ''), 'pathao') === false) return false;
    $st = (string)$o->status; $stage = (string)($o->courier_stage ?? '');
    return $st === 'Late Delivery' || ($st === 'Shipment' && in_array($stage, ['Hold', 'Failed Delivery'], true));
  }

  /** order_id => last successful send time (UTC string), for the "sent to KAM" mark. */
  public static function lastSent(array $orderIds): array {
    if (!$orderIds) return [];
    try { return DB::table('courier_kam_messages')->whereIn('order_id', $orderIds)->where('ok', true)->groupBy('order_id')->selectRaw('order_id, max(created_at) as t')->pluck('t', 'order_id')->all(); } catch (\Throwable $e) { return []; }
  }

  private static function clean(string $s): string { return trim(mb_substr(preg_replace('/\s+/u', ' ', $s), 0, 40)); }
  private static function tag(object $o): string { $stage = (string)($o->courier_stage ?? ''); return $stage === 'Hold' ? 'Hold' : ($stage === 'Failed Delivery' ? 'Failed' : 'Late'); }

  /** Every parcel that is late or on hold with Pathao right now, longest waiting first. */
  public static function issues(): \Illuminate\Support\Collection {
    return DB::table('orders')->whereIn('status', ['Late Delivery', 'Shipment'])->whereNotNull('consignment_id')->where('courier', 'ilike', '%pathao%')
      ->where(fn($q) => $q->where('status', 'Late Delivery')->orWhereIn('courier_stage', ['Hold', 'Failed Delivery']))
      ->orderByRaw('coalesce(shipment_at, courier_sent_at) asc nulls last')->orderBy('id')->limit(200)
      ->get(['id', 'invoice', 'customer_name', 'consignment_id', 'courier', 'status', 'courier_stage'])->filter(fn($o) => self::eligible($o))->values();
  }

  /** Free text inside the 24 h window, else the approved template when one is set. @return array [bool ok, string message, string via, bool blocked] */
  private static function deliver(string $text, array $lines, string $by): array {
    $wa = self::wa(); if (!preg_match('/^8801\d{9}$/', $wa)) return [false, 'The Pathao KAM WhatsApp number is not valid.', 'text', true];
    if (!WhatsApp::enabled()) return [false, 'WhatsApp is not connected.', 'text', true];
    $c = WhatsApp::conversation($wa, self::label());
    if (WhatsApp::windowOpen($c)) { [$ok, $m] = WhatsApp::sendText($c, $text, $by); return [$ok, $m, 'text', false]; }
    $tpl = trim(Procure::set('kam_template', ''));
    if ($tpl !== '') { [$ok, $m] = WhatsApp::sendTemplate($wa, $tpl, Procure::set('kam_template_lang', 'bn'), [implode(' | ', $lines)], $by); return [$ok, $m, 'template', false]; }
    return [false, 'The 24-hour WhatsApp window with the KAM is closed. Ask the KAM to send any message to our WhatsApp number first (then it works again), or set an approved template.', 'text', true];
  }

  private static function log($orders, string $wa, string $via, bool $ok, string $msg, string $by): void {
    foreach ($orders as $o) {
      try { DB::table('courier_kam_messages')->insert(['order_id' => $o->id, 'consignment_id' => $o->consignment_id, 'customer_name' => self::clean((string)$o->customer_name), 'wa_id' => $wa, 'via' => $via, 'ok' => $ok, 'error' => $ok ? null : mb_substr($msg, 0, 250), 'sent_by' => mb_substr($by, 0, 60), 'created_at' => now()]); } catch (\Throwable $e) {}
      if ($ok) { try { OrderActivity::log((int)$o->id, 'kam_message', null, null, 'Sent to Pathao KAM on WhatsApp (consignment ' . $o->consignment_id . ')', []); } catch (\Throwable $e) {} }
    }
  }

  /** By hand (Late Delivery page). @return array [bool ok, string message for the screen, string text to copy when it could not be sent] */
  public static function send(array $ids, string $by): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return [false, 'Select at least one parcel.', ''];
    $rows = DB::table('orders')->whereIn('id', $ids)->get(['id', 'invoice', 'customer_name', 'consignment_id', 'courier', 'status', 'courier_stage']);
    $good = $rows->filter(fn($o) => self::eligible($o)); $bad = $rows->count() - $good->count();
    $recent = $good->isEmpty() ? [] : DB::table('courier_kam_messages')->whereIn('consignment_id', $good->pluck('consignment_id')->all())->where('ok', true)->where('created_at', '>', now()->subHours(self::GAP_HOURS))->pluck('consignment_id')->flip()->all();
    $send = $good->reject(fn($o) => isset($recent[$o->consignment_id]))->values(); $dup = $good->count() - $send->count();
    $more = max(0, $send->count() - self::MAX); $send = $send->take(self::MAX);
    $note = ($bad ? " $bad not eligible (not Pathao, no consignment, or not late/on hold)." : '') . ($dup ? " $dup already sent in the last " . self::GAP_HOURS . ' hours.' : '') . ($more ? " $more more left for the next send." : '');
    if ($send->isEmpty()) return [false, 'Nothing to send.' . $note, ''];
    $lines = []; foreach ($send->values() as $i => $o) $lines[] = ($i + 1) . ') ' . (self::clean((string)$o->customer_name) ?: 'Customer') . ' — ' . $o->consignment_id;
    $text = "Pathao KAM, আমাদের কাস্টমার অভিযোগ করেছেন। নিচের পার্সেলগুলো দ্রুত দেখে আপডেট দিন:\n" . implode("\n", $lines) . "\n— DutyFreeBD";
    try { [$ok, $msg, $via] = self::deliver($text, $lines, $by); } catch (\Throwable $e) { $ok = false; $via = 'text'; $msg = 'Could not send: ' . mb_substr($e->getMessage(), 0, 120); }
    self::log($send, self::wa(), $via, $ok, $msg, $by);
    try { Procure::audit('courier.kam_message', ($ok ? 'Sent ' : 'Failed ') . $send->count() . ' parcel(s) to Pathao KAM', null, ['by' => $by]); } catch (\Throwable $e) {}
    return [$ok, $ok ? 'Sent ' . $send->count() . ' parcel(s) to ' . self::label() . '.' . $note : $msg . $note, $ok ? '' : $text];
  }

  /** Worker tick: once per hour between the set hours (Dhaka), one message (at most two) with every late / on-hold Pathao parcel. */
  public static function tick(): void {
    if (!self::autoOn()) return;
    $d = now('Asia/Dhaka'); $h = (int)$d->format('H'); if ($h < self::fromHour() || $h > self::toHour()) return;
    $key = $d->format('Y-m-d-H'); if (Procure::set('kam_last_hour', '') === $key) return; // already done this hour
    $rows = self::issues(); if ($rows->isEmpty()) { Procure::put('kam_last_hour', $key); Procure::put('kam_state', 'No late or hold parcel at ' . $d->format('H:i')); return; }
    $total = $rows->count(); $per = self::MAX; $take = $rows->take($per * self::AUTO_MAX_MSG)->values(); $left = $total - $take->count(); $by = 'Auto hourly';
    $okAll = true; $msgs = $take->chunk($per); $sentN = 0;
    foreach ($msgs as $i => $chunk) {
      $lines = []; foreach ($chunk->values() as $j => $o) $lines[] = ($i * $per + $j + 1) . ') ' . (self::clean((string)$o->customer_name) ?: 'Customer') . ' — ' . $o->consignment_id . ' (' . self::tag($o) . ')';
      $head = $i === 0 ? "Pathao KAM, এই মুহূর্তে আমাদের $total টি পার্সেল লেট/হোল্ডে আছে:" : 'পরবর্তী পার্সেল:';
      $tail = ($i === $msgs->count() - 1) ? ($left > 0 ? "\n+$left টি আরও আছে।" : '') . "\nদয়া করে কারণ ও আজকের ডেলিভারি আপডেট এই চ্যাটেই লিখে জানান। — DutyFreeBD" : '';
      try { [$ok, $msg, $via, $blocked] = self::deliver($head . "\n" . implode("\n", $lines) . $tail, $lines, $by); } catch (\Throwable $e) { $ok = false; $msg = 'Could not send: ' . mb_substr($e->getMessage(), 0, 120); $via = 'text'; $blocked = false; }
      if ($blocked) { Procure::put('kam_state', $msg); return; } // window closed: nothing sent, tried again next worker round (cheap, no API call)
      self::log($chunk, self::wa(), $via, $ok, $msg, $by); if (!$ok) { $okAll = false; Procure::put('kam_state', 'Send failed: ' . mb_substr($msg, 0, 120)); break; } $sentN += $chunk->count();
    }
    if ($okAll) { Procure::put('kam_last_hour', $key); Procure::put('kam_state', 'Sent ' . $sentN . ' parcel(s) at ' . $d->format('H:i')); }
    else Procure::put('kam_last_hour', $key); // a failed send is not repeated every minute; the next hour tries again
  }

  /** The KAM's latest answers (from the WhatsApp inbox) for the Late Delivery page. */
  public static function replies(int $n = 8): array {
    try {
      $c = DB::table('wa_conversations')->where('wa_id', self::wa())->first(); if (!$c) return [];
      return DB::table('wa_messages')->where('conversation_id', $c->id)->where('direction', 'in')->orderByDesc('id')->limit($n)->get(['body', 'type', 'ts', 'created_at'])->map(fn($m) => ['t' => $m->ts ?: $m->created_at, 'body' => $m->body !== '' ? $m->body : '[' . $m->type . ']'])->all();
    } catch (\Throwable $e) { return []; }
  }
  public static function windowOpen(): bool { try { $c = DB::table('wa_conversations')->where('wa_id', self::wa())->first(); return $c ? WhatsApp::windowOpen($c) : false; } catch (\Throwable $e) { return false; } }
}
