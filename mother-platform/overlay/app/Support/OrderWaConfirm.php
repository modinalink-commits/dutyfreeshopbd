<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Automatic WhatsApp order confirmation. Reuses the connected WhatsApp Cloud API credentials (chat_wa_token / chat_wa_phone_id).
 *  Every value comes from the orders table; nothing is invented. A failure here never touches the order itself. */
class OrderWaConfirm {
  const TYPE = 'ORDER_APPROVED';
  const API = 'https://graph.facebook.com/v21.0';
  const BODY = "আসসালামু আলাইকুম {{1}}, আপনার অর্ডারটি সফলভাবে অনুমোদিত হয়েছে।\n\nঅর্ডার: {{2}}\nমোট: ৳{{3}}\nপেমেন্ট: {{4}}\nস্ট্যাটাস: Approved\n\nআপনার অর্ডারের পরবর্তী আপডেট WhatsApp-এ জানানো হবে।\n\nধন্যবাদ, {{5}} টিম";
  const RANK = ['PENDING' => 0, 'QUEUED' => 1, 'FAILED' => 1, 'SENT' => 2, 'DELIVERED' => 3, 'READ' => 4];
  const PERMANENT = [131026, 131021, 131030, 132000, 132001, 132005, 132007, 132012, 132015, 132016, 190, 100, 131031, 368, 131047];

  public static function ready(): bool { try { return Schema::hasTable('order_whatsapp_confirmations'); } catch (\Throwable $e) { return false; } }
  public static function cfg(string $k, string $d = ''): string { return Procure::set('wa_conf_' . $k, $d); }
  public static function tpl(): string { return self::cfg('template', 'order_confirmation'); }
  public static function lang(): string { return self::cfg('lang', 'bn'); }
  public static function brand(): string { return self::cfg('brand', 'DutyFreeBD'); }

  /** Called from ApprovalSource::stamp right after an order is approved. Queues one confirmation per order (idempotent). */
  public static function onApproved(int $orderId, string $source): void {
    try {
      if (!self::ready() || self::cfg('enabled', '1') !== '1') return;
      $src = strtoupper($source);
      $go = $src === 'AI' ? self::cfg('on_ai', '1') === '1' : ($src === 'MANUAL' ? self::cfg('on_manual', '0') === '1' : false);
      if (!$go) return;
      $id = self::create($orderId, $src, 1);
      if ($id) app()->terminating(function () use ($id) { try { self::send($id); } catch (\Throwable $e) { report($e); } });
    } catch (\Throwable $e) { report($e); }
  }

  private static function create(int $orderId, string $src, int $seq, ?int $by = null, ?string $reason = null): ?int {
    $o = DB::table('orders')->find($orderId); if (!$o) return null;
    $n = DB::table('order_whatsapp_confirmations')->insertOrIgnore([
      'order_id' => $orderId, 'customer_id' => $o->customer_id, 'store_id' => $o->store_id ?? null, 'confirmation_type' => self::TYPE, 'seq' => $seq,
      'mobile' => WhatsApp::norm((string)$o->phone), 'approval_source' => $src, 'template_name' => self::tpl(), 'send_status' => 'QUEUED',
      'resend_by' => $by, 'resend_at' => $by ? now() : null, 'resend_reason' => $reason ? mb_substr($reason, 0, 300) : null, 'created_at' => now(), 'updated_at' => now()]);
    if (!$n) return null; // already exists: never a second automatic message
    $id = (int)DB::table('order_whatsapp_confirmations')->where('order_id', $orderId)->where('confirmation_type', self::TYPE)->where('seq', $seq)->value('id');
    self::ev($id, $orderId, $seq > 1 ? 'resend_queued' : 'queued', $seq > 1 ? 'Manual resend' . ($reason ? ': ' . $reason : '') : 'Approved by ' . $src);
    return $id;
  }

  public static function ev(int $cid, int $oid, string $event, ?string $detail = null): void {
    try { DB::table('order_whatsapp_events')->insert(['confirmation_id' => $cid, 'order_id' => $oid, 'event' => $event, 'detail' => $detail ? mb_substr($detail, 0, 500) : null, 'created_at' => now()]); } catch (\Throwable $e) {}
    $label = ['queued' => 'WhatsApp Confirmation Queued', 'resend_queued' => 'WhatsApp Confirmation Resend Queued', 'sent' => 'WhatsApp Confirmation Sent', 'delivered' => 'WhatsApp Delivered', 'read' => 'WhatsApp Read', 'failed' => 'WhatsApp Confirmation Failed', 'retry' => 'WhatsApp Retry'][$event] ?? null;
    if ($label) { try { OrderActivity::log($oid, 'wa_confirm_' . $event, null, null, $label . ($detail ? ' · ' . $detail : '')); } catch (\Throwable $e) {} }
  }

  /** The five template values, taken from the database only. */
  public static function params(object $o): array {
    $name = trim((string)($o->customer_name ?? '')) ?: 'গ্রাহক';
    $total = number_format((float)$o->total, 0, '.', ',');
    $pay = trim((string)($o->payment_method ?? '')) ?: 'Cash on Delivery';
    return [mb_substr($name, 0, 60), (string)$o->invoice, $total, mb_substr($pay, 0, 40), self::brand()];
  }
  public static function render(array $p): string { $b = self::BODY; foreach ($p as $i => $v) $b = str_replace('{{' . ($i + 1) . '}}', $v, $b); return $b; }

  /** Low-level template call. Returns [ok, wamid|null, error|null, code|null]. */
  public static function call(string $to, array $p): array {
    $tok = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id');
    if (!$tok || !$pid) return [false, null, 'WhatsApp is not connected (Chat Settings).', 190];
    try {
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
        'template' => ['name' => self::tpl(), 'language' => ['code' => self::lang()], 'components' => [['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => preg_replace('/[\r\n\t]+/', ' ', (string)$v)], array_values($p))]]]]);
      if ($r->successful()) return [true, $r->json('messages.0.id'), null, null];
      $c = (int)$r->json('error.code'); return [false, null, mb_substr('(' . $c . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 280), $c];
    } catch (\Throwable $e) { return [false, null, 'Network error: ' . mb_substr($e->getMessage(), 0, 120), 0]; }
  }

  /** Send one queued/failed confirmation. Safe to call twice: only one caller can claim it. */
  public static function send(int $id): void {
    $claim = DB::table('order_whatsapp_confirmations')->where('id', $id)->whereIn('send_status', ['QUEUED', 'FAILED'])->whereNull('external_message_id')->where('permanent', false)
      ->update(['send_status' => 'PENDING', 'updated_at' => now()]);
    if (!$claim) return;
    $c = DB::table('order_whatsapp_confirmations')->find($id); $o = DB::table('orders')->find($c->order_id);
    if (!$o) { self::fail($c, 'Order not found', true); return; }
    $to = WhatsApp::norm((string)$o->phone);
    if (!preg_match('/^8801[3-9]\d{8}$/', $to)) { self::fail($c, 'Invalid/Unavailable Number', true, 'Not Sent'); return; }
    if (in_array($o->status, ['Cancel', 'Cancelled'], true)) { self::fail($c, 'Order cancelled before sending', true); return; }
    $p = self::params($o); $body = self::render($p);
    DB::table('order_whatsapp_confirmations')->where('id', $id)->update(['mobile' => $to, 'message_body' => $body, 'template_name' => self::tpl()]);
    $attempts = self::cfg('retry', '1') === '1' ? 3 : 1; $err = null; $code = null;
    for ($i = 1; $i <= $attempts; $i++) {
      [$ok, $wamid, $err, $code] = self::call($to, $p);
      if ($ok) {
        DB::table('order_whatsapp_confirmations')->where('id', $id)->update(['send_status' => 'SENT', 'external_message_id' => $wamid, 'sent_at' => now(), 'failure_reason' => null, 'failed_at' => null, 'retry_count' => $i - 1, 'updated_at' => now()]);
        self::ev($id, (int)$c->order_id, 'sent', 'Message ID ' . $wamid);
        Chat::logOut($to, $body, 'Order confirmation (auto)', $wamid, 'invoice'); return;
      }
      if (in_array((int)$code, self::PERMANENT, true)) break;
      DB::table('order_whatsapp_confirmations')->where('id', $id)->update(['retry_count' => $i]);
      if ($i < $attempts) { self::ev($id, (int)$c->order_id, 'retry', "Attempt $i failed: $err"); sleep($i === 1 ? 3 : 8); }
    }
    self::fail($c, (string)$err, in_array((int)$code, self::PERMANENT, true));
    Chat::logOut($to, $body, 'Order confirmation (auto)', null, 'invoice', 'failed', mb_substr((string)$err, 0, 250));
  }

  private static function fail(object $c, string $why, bool $perm, ?string $note = null): void {
    DB::table('order_whatsapp_confirmations')->where('id', $c->id)->update(['send_status' => 'FAILED', 'failure_reason' => mb_substr($why, 0, 400), 'permanent' => $perm, 'failed_at' => now(), 'updated_at' => now()]);
    self::ev((int)$c->id, (int)$c->order_id, 'failed', ($note ? $note . ' · ' : '') . $why . ($perm ? '' : ' · Failed — Manual Review'));
  }

  /** The customer's number has no WhatsApp (or WhatsApp failed for good): send the same "order approved" news by SMS, once per order. Respects the order-confirmation SMS switch. */
  public static function smsFallback(int $orderId, int $cid = 0): void {
    try {
      if ((DB::table('settings')->where('key', 'sms_order_confirm')->value('value') ?? '1') !== '1') return;
      if (DB::table('sms_logs')->where('order_id', $orderId)->where('event', 'order_approved_sms')->exists()) return;
      $o = DB::table('orders')->find($orderId); if (!$o || !$o->phone) return;
      $pay = !empty($o->pay_status) ? (string)$o->pay_status : 'Cash on Delivery';
      $txt = 'DutyFreeBD: Order '.Inv::show($o->invoice).' approved. Total: Tk '.number_format((float)$o->total).' ('.$pay.'). We will update you on shipment. Track: '.preg_replace('#^https?://#', '', Inv::trackUrl($o->invoice)).' Help: '.Biz::hotline();
      Sms::queue(Chat::phone($o->phone) ?: $o->phone, $txt, 'order_approved_sms', ['order_id' => $orderId]);
      self::ev($cid, $orderId, 'sms_fallback', 'WhatsApp not reachable — approval sent by SMS');
    } catch (\Throwable $e) {}
  }
  /** Meta status webhook: sent / delivered / read / failed for a message we sent. Never downgrades a status. */
  public static function status(array $s): void {
    try {
      if (!self::ready()) return;
      $wamid = (string)($s['id'] ?? ''); if ($wamid === '') return;
      $c = DB::table('order_whatsapp_confirmations')->where('external_message_id', $wamid)->first(); if (!$c) return;
      $st = strtolower((string)($s['status'] ?? '')); $ts = !empty($s['timestamp']) ? \Carbon\Carbon::createFromTimestampUTC((int)$s['timestamp']) : now();
      if ($st === 'failed') { $msg = mb_substr((string)(($s['errors'][0]['title'] ?? '') . ' ' . ($s['errors'][0]['message'] ?? '')), 0, 300);
        DB::table('order_whatsapp_confirmations')->where('id', $c->id)->update(['send_status' => 'FAILED', 'failed_at' => $ts, 'failure_reason' => trim($msg) ?: 'Delivery failed', 'updated_at' => now()]); self::ev((int)$c->id, (int)$c->order_id, 'failed', trim($msg));
        try { DB::table('chat_messages')->where('ext_id', $wamid)->update(['status' => 'failed', 'error' => trim($msg) ?: 'Number not reachable on WhatsApp', 'updated_at' => now()]); } catch (\Throwable $e) {} if ($c->confirmation_type === self::TYPE) self::smsFallback((int)$c->order_id, (int)$c->id); return; }
      $new = strtoupper($st); if (!in_array($new, ['SENT', 'DELIVERED', 'READ'], true)) return;
      if ((self::RANK[$new] ?? 0) <= (self::RANK[$c->send_status] ?? 0) && $c->send_status !== 'FAILED') return;
      $col = ['SENT' => 'sent_at', 'DELIVERED' => 'delivered_at', 'READ' => 'read_at'][$new];
      $up = ['send_status' => $new, $col => $ts, 'updated_at' => now()];
      if ($new !== 'SENT' && !$c->sent_at) $up['sent_at'] = $ts;
      if ($new === 'READ' && !$c->delivered_at) $up['delivered_at'] = $ts;
      DB::table('order_whatsapp_confirmations')->where('id', $c->id)->update($up + ($c->send_status === 'FAILED' ? ['failure_reason' => null] : []));
      if ($new === 'READ') { try { DB::table('chat_messages')->where('ext_id', $wamid)->update(['read_at' => $ts]); } catch (\Throwable $e) {} }
      if ($new !== 'SENT') self::ev((int)$c->id, (int)$c->order_id, strtolower($new), null);
    } catch (\Throwable $e) { report($e); }
  }

  /** Manual, audited resend: a NEW attempt row; the original stays untouched. Returns [ok, message]. */
  public static function resend(int $orderId, int $by, string $reason): array {
    if (!self::ready()) return [false, 'Not ready'];
    $last = DB::table('order_whatsapp_confirmations')->where('order_id', $orderId)->where('confirmation_type', self::TYPE)->orderByDesc('seq')->first();
    if ($last && in_array($last->send_status, ['QUEUED', 'PENDING'], true) && $last->updated_at > now()->subMinutes(2)) return [false, 'A send is already in progress.'];
    $o = DB::table('orders')->find($orderId); if (!$o) return [false, 'Order not found'];
    if ($last && $last->send_status === 'FAILED' && !$last->permanent && !$last->external_message_id && $last->seq == 1 && !$reason) { // plain retry of the same attempt
      DB::table('order_whatsapp_confirmations')->where('id', $last->id)->update(['send_status' => 'QUEUED', 'updated_at' => now()]); self::send((int)$last->id);
      $n = DB::table('order_whatsapp_confirmations')->find($last->id); return [$n->send_status !== 'FAILED', $n->send_status];
    }
    $seq = $last ? $last->seq + 1 : 1;
    $id = self::create($orderId, (string)($o->approval_source ?: 'MANUAL'), $seq, $by, $reason ?: 'Manual resend'); if (!$id) return [false, 'Could not queue'];
    self::send($id); $n = DB::table('order_whatsapp_confirmations')->find($id);
    return [$n->send_status !== 'FAILED', $n->send_status === 'FAILED' ? (string)$n->failure_reason : $n->send_status];
  }

  /** Meta template: submit / refresh status. */
  public static function waba(): string { return self::cfg('waba', '1728673528206378'); }
  public static function submitTemplate(): array {
    $tok = Chat::sec('chat_wa_token'); if (!$tok) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($tok)->post(self::API . '/' . self::waba() . '/message_templates', ['name' => self::tpl(), 'language' => self::lang(), 'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['মোহাম্মদ ফারুক', 'DFB-102619', '1,250', 'Cash on Delivery', 'DutyFreeBD']]]]]]);
      if ($r->successful()) { Procure::put('wa_conf_tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $tok = Chat::sec('chat_wa_token'); if (!$tok) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($tok)->get(self::API . '/' . self::waba() . '/message_templates', ['name' => self::tpl(), 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($t) => ($t['language'] ?? '') === self::lang()) ?: collect($r->json('data', []))->first();
      $st = $row ? strtoupper((string)$row['status']) : 'NOT_FOUND'; Procure::put('wa_conf_tpl_status', $st);
      return [true, $st . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
