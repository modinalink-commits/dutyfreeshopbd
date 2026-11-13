<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** WhatsApp order updates after approval: "handed to courier" and "delivered". Same credentials, table and status webhook as OrderWaConfirm.
 *  One automatic message per order per stage. Nothing goes out until Meta has approved that stage's template. Values come from the order record only;
 *  product names are never included. A failure here never touches the order. */
class OrderWaUpdate {
  const API = 'https://graph.facebook.com/v21.0';
  /** to_status => [confirmation_type, setting key, default template name, body, example values, staff label] */
  const STAGES = [
    'Shipment' => ['ORDER_SHIPPED', 'ship', 'order_shipped_update',
      "আসসালামু আলাইকুম {{1}}, আপনার অর্ডার {{2}} কুরিয়ারে হস্তান্তর করা হয়েছে।\n\nকুরিয়ার: {{3}}\nট্র্যাকিং নম্বর: {{4}}\nমোট: ৳{{5}}\n\nডেলিভারির সময় ফোন খোলা রাখার অনুরোধ রইল। ধন্যবাদ, {{6}}।",
      ['মোহাম্মদ ফারুক', 'DFB-102619', 'Pathao', 'DT1411263ABCD', '1,250', 'DutyFreeBD'], 'কুরিয়ারে দেওয়া হয়েছে'],
    'Delivered' => ['ORDER_DELIVERED', 'dlv', 'order_delivered_update',
      "আসসালামু আলাইকুম {{1}}, আপনার অর্ডার {{2}} সফলভাবে ডেলিভারি হয়েছে।\n\nঅর্ডার নিয়ে কোনো সমস্যা হলে এই নম্বরেই আমাদের জানাতে পারেন। {{3}}-এর সাথে থাকার জন্য ধন্যবাদ।",
      ['মোহাম্মদ ফারুক', 'DFB-102619', 'DutyFreeBD'], 'ডেলিভারি হয়েছে'],
  ];

  public static function set(string $k, string $d = ''): string { return Procure::set('wa_upd_' . $k, $d); }
  public static function tpl(string $st): string { return self::set(self::STAGES[$st][1] . '_tpl', self::STAGES[$st][2]); }
  public static function tplStatus(string $st): string { return self::set(self::STAGES[$st][1] . '_tpl_status', 'NOT SUBMITTED'); }
  public static function on(string $st): bool { return self::set('enabled', '1') === '1' && self::set(self::STAGES[$st][1] . '_on', '1') === '1'; }

  public static function params(string $st, object $o): array {
    $name = mb_substr(trim((string)($o->customer_name ?? '')) ?: 'গ্রাহক', 0, 60); $inv = Inv::show($o->invoice); $brand = OrderWaConfirm::brand();
    if ($st === 'Delivered') return [$name, $inv, $brand];
    return [$name, $inv, mb_substr(trim((string)($o->courier ?? '')) ?: 'কুরিয়ার', 0, 40), mb_substr(trim((string)($o->consignment_id ?? '')) ?: 'শীঘ্রই জানানো হবে', 0, 60), number_format((float)$o->total, 0, '.', ','), $brand];
  }
  public static function render(string $st, array $p): string { $b = self::STAGES[$st][3]; foreach ($p as $i => $v) $b = str_replace('{{' . ($i + 1) . '}}', $v, $b); return $b; }

  /** Low-level template call. Returns [ok, wamid|null, error|null, code|null]. */
  public static function call(string $st, string $to, array $p): array {
    $tok = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id');
    if (!$tok || !$pid) return [false, null, 'WhatsApp is not connected (Chat Settings).', 190];
    try {
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
        'template' => ['name' => self::tpl($st), 'language' => ['code' => 'bn'], 'components' => [['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => preg_replace('/[\r\n\t]+/', ' ', (string)$v)], array_values($p))]]]]);
      if ($r->successful()) return [true, $r->json('messages.0.id'), null, null];
      $c = (int)$r->json('error.code'); return [false, null, mb_substr('(' . $c . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 280), $c];
    } catch (\Throwable $e) { return [false, null, 'Network error: ' . mb_substr($e->getMessage(), 0, 120), 0]; }
  }

  /** Called by the worker for each order status change. Sends at most one message per order per stage. */
  public static function onStatus(int $orderId, string $to): void {
    try {
      if (!isset(self::STAGES[$to]) || !OrderWaConfirm::ready() || !self::on($to) || self::tplStatus($to) !== 'APPROVED') return;
      $o = DB::table('orders')->find($orderId); if (!$o || (string)$o->status !== $to) return; // status moved on again: say nothing stale
      [$type] = self::STAGES[$to]; $wa = WhatsApp::norm((string)$o->phone);
      if (!preg_match('/^8801[3-9]\d{8}$/', $wa)) return;
      $p = self::params($to, $o); $body = self::render($to, $p);
      $n = DB::table('order_whatsapp_confirmations')->insertOrIgnore(['order_id' => $orderId, 'customer_id' => $o->customer_id, 'store_id' => $o->store_id ?? null, 'confirmation_type' => $type, 'seq' => 1,
        'mobile' => $wa, 'approval_source' => $o->approval_source ?? null, 'template_name' => self::tpl($to), 'message_body' => $body, 'send_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
      if (!$n) return; // already sent for this stage
      $id = (int)DB::table('order_whatsapp_confirmations')->where('order_id', $orderId)->where('confirmation_type', $type)->where('seq', 1)->value('id');
      [$ok, $wamid, $err, $code] = self::call($to, $wa, $p); $label = self::STAGES[$to][5];
      if ($ok) {
        DB::table('order_whatsapp_confirmations')->where('id', $id)->update(['send_status' => 'SENT', 'external_message_id' => $wamid, 'sent_at' => now(), 'updated_at' => now()]);
        try { OrderActivity::log($orderId, 'wa_update_sent', null, null, 'WhatsApp আপডেট পাঠানো হয়েছে · ' . $label); } catch (\Throwable $e) {}
        Chat::logOut($wa, $body, 'Order update (auto)', $wamid, 'invoice');
      } else {
        DB::table('order_whatsapp_confirmations')->where('id', $id)->update(['send_status' => 'FAILED', 'failure_reason' => mb_substr((string)$err, 0, 400), 'permanent' => true, 'failed_at' => now(), 'updated_at' => now()]);
        try { OrderActivity::log($orderId, 'wa_update_failed', null, null, 'WhatsApp আপডেট যায়নি · ' . $label . ' · ' . mb_substr((string)$err, 0, 160)); } catch (\Throwable $e) {}
      }
    } catch (\Throwable $e) { report($e); }
  }

  public static function submitTemplate(string $st): array {
    if (!isset(self::STAGES[$st])) return [false, 'Unknown stage'];
    $tok = Chat::sec('chat_wa_token'); if (!$tok) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($tok)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl($st), 'language' => 'bn', 'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => self::STAGES[$st][3], 'example' => ['body_text' => [self::STAGES[$st][4]]]]]]);
      if ($r->successful()) { Procure::put('wa_upd_' . self::STAGES[$st][1] . '_tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(string $st): array {
    if (!isset(self::STAGES[$st])) return [false, 'Unknown stage'];
    $tok = Chat::sec('chat_wa_token'); if (!$tok) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($tok)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl($st), 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($t) => ($t['name'] ?? '') === self::tpl($st) && ($t['language'] ?? '') === 'bn');
      $s = $row ? strtoupper((string)$row['status']) : 'NOT_FOUND'; Procure::put('wa_upd_' . self::STAGES[$st][1] . '_tpl_status', $s);
      return [true, $s . ($row ? ' · ' . ($row['category'] ?? '') : '') . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  /** Pending templates are re-checked every 10 minutes so sending starts by itself once Meta approves. */
  public static function tick(): void {
    try {
      foreach (array_keys(self::STAGES) as $st) { if (self::tplStatus($st) !== 'PENDING') continue;
        if (!\Illuminate\Support\Facades\Cache::add('wa_upd_sync_' . $st, 1, 600)) continue; self::syncTemplate($st); }
    } catch (\Throwable $e) {}
  }
}
