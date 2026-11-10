<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Tells the customer, by SMS and WhatsApp, that their order was cancelled and why. Fed by the DB trigger queue (order_cancel_notices); every value comes from the order row. */
class OrderCancelNotice {
  /** key => [staff label, customer wording]. "suspicious" is never shown to the customer as such. null customer text = no message (the customer never asked for that order / another copy is running). */
  const REASONS = [
    'customer_request' => ['Customer asked to cancel', 'it was cancelled at your request'],
    'unreachable'      => ['Customer not reachable by phone', 'we could not reach you by phone to confirm it'],
    'wrong_address'    => ['Wrong address / number', 'the address or phone number could not be verified'],
    'out_of_stock'     => ['Out of stock', 'the product is currently out of stock'],
    'price_issue'      => ['Price / offer not accepted', 'of a price or offer issue'],
    'cannot_deliver'   => ['Cannot deliver to this area', 'we are unable to deliver to your address'],
    'suspicious'       => ['Suspicious / fake order', 'we could not complete the order verification'],
    'duplicate'        => ['Duplicate order', null],
    'other'            => ['Other (write the reason)', ''],
  ];
  const WA_BODY = "আসসালামু আলাইকুম {{1}}, আপনার অর্ডার {{2}} বাতিল করা হয়েছে।\n\nকারণ: {{3}}\n\nএ বিষয়ে কোনো অভিযোগ বা জানার থাকলে আমাদের হটলাইন {{4}} নম্বরে কল করুন।\n\n— {{5}}";
  const API = 'https://graph.facebook.com/v21.0';

  public static function ready(): bool { try { return Schema::hasTable('order_cancel_notices') && Schema::hasColumn('orders', 'cancel_reason'); } catch (\Throwable $e) { return false; } }
  public static function set(string $k, string $d = ''): string { return Procure::set('cancel_notice_' . $k, $d); }
  public static function labels(): array { return array_map(fn($r) => $r[0], self::REASONS); }
  /** Validates the staff choice. Returns [reasonKey|null, note|null, errorMessage|null]. */
  public static function check(?string $key, ?string $note): array {
    $key = trim((string)$key); $note = trim((string)$note);
    if ($key === '' || !isset(self::REASONS[$key])) return [null, null, 'Choose a cancel reason first. / ক্যান্সেলের কারণ বাছাই করুন।'];
    if ($key === 'other' && mb_strlen($note) < 3) return [null, null, 'Write the reason (it is sent to the customer). / কারণটি লিখুন, এটি কাস্টমারকে পাঠানো হবে।'];
    return [$key, $note !== '' ? mb_substr($note, 0, 300) : null, null];
  }
  public static function reasonText(object $o): ?string {
    $k = (string)($o->cancel_reason ?? '');
    if ($k === 'duplicate') return null;
    if ($k === 'other') { $n = trim((string)($o->cancel_note ?? '')); return $n !== '' ? mb_substr($n, 0, 120) : 'the order was cancelled'; }
    $t = self::REASONS[$k][1] ?? null; if ($k !== '' && isset(self::REASONS[$k])) return $t;
    return 'the order was cancelled'; // automatic cancel (courier / system) without a staff reason
  }
  public static function sms(object $o, string $why): string {
    return 'DutyFreeBD: Your order ' . Inv::show($o->invoice) . ' has been cancelled because ' . rtrim($why, '. ') . '. If you have any concern, please call our hotline ' . Biz::hotline() . '.';
  }

  /** Processes waiting notices (called by the worker every ~30 s and right after a request). */
  public static function tick(int $limit = 20): int {
    if (!self::ready()) return 0; $n = 0;
    try { if (in_array(self::set('wa_tpl_status', ''), ['PENDING', 'IN_APPEAL'], true) && \Illuminate\Support\Facades\Cache::add('cancel_tpl_sync', 1, 600)) self::syncTemplate(); } catch (\Throwable $e) {}
    foreach (DB::table('order_cancel_notices')->where('state', 'pending')->where('tries', '<', 4)->orderBy('id')->limit($limit)->get() as $q) {
      if (!DB::table('order_cancel_notices')->where('id', $q->id)->where('state', 'pending')->update(['tries' => DB::raw('tries + 1')])) continue;
      try { self::one($q); $n++; } catch (\Throwable $e) { report($e); DB::table('order_cancel_notices')->where('id', $q->id)->update(['note' => mb_substr($e->getMessage(), 0, 280)]); }
      if (DB::table('order_cancel_notices')->where('id', $q->id)->where('state', 'pending')->where('tries', '>=', 4)->exists()) DB::table('order_cancel_notices')->where('id', $q->id)->update(['state' => 'failed']);
    }
    return $n;
  }
  private static function done(int $id, string $state, array $x = []): void { DB::table('order_cancel_notices')->where('id', $id)->update(['state' => $state, 'done_at' => now()] + $x); }
  private static function one(object $q): void {
    $o = DB::table('orders')->find($q->order_id);
    if (!$o || $o->status !== 'Cancel') { self::done($q->id, 'skipped', ['note' => 'order is no longer cancelled']); return; }
    if (self::set('enabled', '1') !== '1') { self::done($q->id, 'skipped', ['note' => 'switched off']); return; }
    if (!empty($o->duplicate_of_order_id)) { self::done($q->id, 'skipped', ['note' => 'duplicate order']); return; }
    $why = self::reasonText($o); if ($why === null) { self::done($q->id, 'skipped', ['note' => 'reason: duplicate order']); return; }
    $phone = trim((string)$o->phone); if ($phone === '') { self::done($q->id, 'failed', ['note' => 'no phone number']); return; }
    $upd = [];
    if (self::set('sms', '1') === '1') { Sms::queue(Chat::phone($phone) ?: $phone, self::sms($o, $why), 'order_cancelled', ['type' => 'transactional', 'customer_name' => (string)$o->customer_name, 'ref' => 'cancel:' . (int)$o->id]); $upd['sms_status'] = 'queued'; }
    if (self::set('wa', '1') === '1' && self::set('wa_tpl_status', '') === 'APPROVED') {
      [$ok, $wamid, $err] = self::waCall($o, $why);
      $upd['wa_status'] = $ok ? 'sent' : 'failed'; if ($wamid) $upd['wa_wamid'] = $wamid; if (!$ok) $upd['note'] = mb_substr((string)$err, 0, 280);
    } else $upd['wa_status'] = 'off';
    self::done($q->id, 'done', $upd);
  }

  /** Sample message to one number (no order is touched). Returns [ok, text]. */
  public static function testSend(string $phone): array {
    $o = (object)['invoice' => 'INV-TEST', 'customer_name' => 'Test Customer', 'phone' => $phone];
    $why = self::REASONS['unreachable'][1]; $out = [];
    Sms::queue($phone, '[TEST] ' . self::sms($o, $why), 'order_cancelled_test', ['type' => 'transactional', 'customer_name' => 'Test', 'ref' => 'cancel-test']);
    try { Sms::flush(5); } catch (\Throwable $e) {} $out[] = 'SMS queued/sent to ' . $phone;
    if (self::set('wa_tpl_status', '') === 'APPROVED') { [$ok, , $err] = self::waCall($o, $why); $out[] = $ok ? 'WhatsApp sent' : 'WhatsApp failed: ' . $err; } else $out[] = 'WhatsApp skipped (template not approved yet)';
    return [true, implode(' · ', $out)];
  }
  /* ---------- WhatsApp template ---------- */
  public static function tpl(): string { return self::set('wa_tpl', 'order_cancelled'); }
  private static function waVals(object $o, string $why): array {
    $name = trim((string)$o->customer_name) ?: 'গ্রাহক';
    $bn = ['it was cancelled at your request' => 'আপনার অনুরোধে বাতিল করা হয়েছে', 'we could not reach you by phone to confirm it' => 'ফোনে আপনার সাথে যোগাযোগ করা সম্ভব হয়নি', 'the address or phone number could not be verified' => 'ঠিকানা বা মোবাইল নম্বর যাচাই করা যায়নি', 'the product is currently out of stock' => 'পণ্যটি বর্তমানে স্টকে নেই', 'of a price or offer issue' => 'দাম বা অফার সংক্রান্ত কারণে', 'we are unable to deliver to your address' => 'আপনার ঠিকানায় ডেলিভারি করা সম্ভব হচ্ছে না', 'we could not complete the order verification' => 'অর্ডারের যাচাই সম্পন্ন করা যায়নি', 'the order was cancelled' => 'অর্ডারটি বাতিল করা হয়েছে'];
    return [mb_substr($name, 0, 60), Inv::show($o->invoice), $bn[$why] ?? $why, Biz::hotline(), OrderWaConfirm::brand()];
  }
  private static function waCall(object $o, string $why): array {
    $tok = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id'); if (!$tok || !$pid) return [false, null, 'WhatsApp is not connected'];
    $to = WhatsApp::norm((string)$o->phone);
    try {
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => ['name' => self::tpl(), 'language' => ['code' => 'bn'], 'components' => [['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => preg_replace('/[\r\n\t]+/', ' ', (string)$v)], self::waVals($o, $why))]]]]);
      if ($r->successful()) return [true, $r->json('messages.0.id'), null];
      return [false, null, '(' . (int)$r->json('error.code') . ') ' . ($r->json('error.message') ?: $r->body())];
    } catch (\Throwable $e) { return [false, null, 'Network error: ' . $e->getMessage()]; }
  }
  public static function submitTemplate(): array {
    $tok = Chat::sec('chat_wa_token'); if (!$tok) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($tok)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl(), 'language' => 'bn', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => self::WA_BODY, 'example' => ['body_text' => [['মোহাম্মদ ফারুক', 'DFB-102619', 'ফোনে আপনার সাথে যোগাযোগ করা সম্ভব হয়নি', '09613828081', 'DutyFreeBD']]]]]]);
      if ($r->successful()) { Procure::put('cancel_notice_wa_tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $tok = Chat::sec('chat_wa_token'); if (!$tok) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($tok)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl(), 'fields' => 'name,status,language,rejected_reason']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($t) => ($t['language'] ?? '') === 'bn'); $st = $row ? strtoupper((string)$row['status']) : 'NOT_FOUND';
      Procure::put('cancel_notice_wa_tpl_status', $st); return [true, $st];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
