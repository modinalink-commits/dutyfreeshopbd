<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Best Seller low-stock notice (owner, 14 Nov 2026): the moment a Best Seller's stock reaches the limit (100 cartons unless changed), the purchase manager gets a WhatsApp message about that product.
 *  One message per product per dip: it is sent again only after the stock has gone back above the limit and come down again. Stock is read from the database at send time.
 *  WhatsApp needs an approved template for a message that starts a conversation; while Meta has not approved it the same text goes by SMS, so the notice is never lost. */
class BestSellerStock {
  const API = 'https://graph.facebook.com/v21.0', TPL = 'bestseller_low_stock';
  const BODY = "আসসালামু আলাইকুম {{1}},\nবেস্ট সেলার পণ্যের স্টক কমে গেছে।\n\nপণ্য: {{2}}\nবর্তমান স্টক: {{3}} কার্টন\nসতর্ক সীমা: {{4}} কার্টন\n\nদয়া করে দ্রুত ক্রয়ের ব্যবস্থা করুন।\nDutyFreeBD";
  public static function set(string $k, string $d = ''): string { return Procure::set('bs_stock_' . $k, $d); }
  public static function on(): bool { return self::set('on', '1') === '1'; }
  public static function limit(): int { return max(1, (int)self::set('limit', '100')); }
  public static function status(): string { return self::set('tpl_status', 'NOT SUBMITTED'); }
  public static function recipients(): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', self::set('to', "Mamun|01824522800")) as $l) {
      [$n, $p] = array_pad(array_map('trim', explode('|', $l, 2)), 2, ''); $wa = WhatsApp::norm($p);
      if (preg_match('/^8801[3-9]\d{8}$/', $wa)) $out[] = [$n !== '' ? $n : 'Team', $wa];
    }
    return $out;
  }
  private static function sent(): array { $j = json_decode(self::set('sent', '{}'), true); return is_array($j) ? $j : []; }
  public static function text(string $name, string $product, int $stock): string { return strtr(self::BODY, ['{{1}}' => $name, '{{2}}' => $product, '{{3}}' => (string)$stock, '{{4}}' => (string)self::limit()]); }
  /** One recipient: WhatsApp template when approved, otherwise SMS with the same text. Returns [ok, channel, detail]. */
  public static function call(string $to, string $name, string $product, int $stock): array {
    if (self::status() === 'APPROVED') {
      $t = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id');
      if ($t && $pid) try {
        $r = Http::timeout(20)->withToken($t)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => ['name' => self::TPL, 'language' => ['code' => 'bn'], 'components' => [
          ['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string)$v], [$name, $product, $stock, self::limit()])]]]]);
        if ($r->successful()) return [true, 'WhatsApp', (string)$r->json('messages.0.id')];
        $err = mb_substr('(' . (int)$r->json('error.code') . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 200);
      } catch (\Throwable $e) { $err = 'Network error: ' . mb_substr($e->getMessage(), 0, 120); }
    }
    try { $s = Sms::send('0' . substr($to, 3), self::text($name, $product, $stock)); $ok = (bool)($s[0] ?? $s['ok'] ?? false); return [$ok, 'SMS', $ok ? 'sent' . (isset($err) ? ' (WhatsApp failed: ' . $err . ')' : '') : mb_substr((string)($s[1] ?? $s['msg'] ?? 'failed'), 0, 160)]; }
    catch (\Throwable $e) { return [false, 'SMS', mb_substr($e->getMessage(), 0, 160)]; }
  }
  /** Worker, once a minute. */
  public static function tick(): void {
    try {
      if (!self::on() || !Cache::add('bs_stock.tick', 1, 60)) return;
      if (self::status() === 'PENDING' && Cache::add('bs_stock.sync', 1, 600)) self::syncTemplate();
      $ids = AutoPrice::ids(); if (!$ids) return; $lim = self::limit(); $sent = self::sent(); $dirty = false;
      foreach (DB::table('products')->whereIn('id', $ids)->get(['id', 'name', 'stock']) as $p) {
        $k = (string)$p->id; $st = (int)$p->stock;
        if ($st > $lim) { if (isset($sent[$k])) { unset($sent[$k]); $dirty = true; } continue; }
        if (isset($sent[$k]) || !Cache::add('bs_stock.try.' . $k, 1, 600)) continue; // already told for this dip, or a failed attempt is waiting ten minutes
        $res = []; $any = false;
        foreach (self::recipients() as [$name, $wa]) { [$ok, $ch, $m] = self::call($wa, $name, (string)$p->name, $st); $any = $any || $ok; $res[] = $name . ' …' . substr($wa, -4) . ': ' . $ch . ' ' . ($ok ? 'sent' : 'failed · ' . $m); }
        if ($any) { $sent[$k] = ['at' => now()->toDateTimeString(), 'stock' => $st]; $dirty = true; }
        try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'System', 'action' => 'bestseller.low_stock', 'subject' => $p->name . ': stock ' . $st . ' (limit ' . $lim . ') · ' . (implode('; ', $res) ?: 'no recipient set'), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
        if (!$any) DeployHealth::raise('bs_stock.fail.' . $k, 'high', 'Inventory', 'Low-stock notice not delivered: ' . $p->name, 'Stock is ' . $st . ' (limit ' . $lim . '). ' . (implode('; ', $res) ?: 'No recipient is set.'), 'The WhatsApp template is not approved yet or the number could not be reached, and SMS also failed.', 'Tell the purchase manager directly; check Products & Inventory > Best Sellers.');
        else { try { DeployHealth::resolve('bs_stock.fail.' . $k, 'System (delivered)'); } catch (\Throwable $e) {} }
      }
      if ($dirty) Procure::put('bs_stock_sent', json_encode($sent));
    } catch (\Throwable $e) { report($e); }
  }
  public static function submitTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'language' => 'bn', 'category' => 'UTILITY', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['মামুন', 'Oris Silver Nano Super Slims', '98', '100']]]]]]);
      if ($r->successful()) { Procure::put('bs_stock_tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::TPL, 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::TPL);
      $s = $row ? strtoupper((string)$row['status']) : 'NOT SUBMITTED'; Procure::put('bs_stock_tpl_status', $s);
      return [true, $s . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
