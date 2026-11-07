<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** WhatsApp Business (Meta Cloud API): inbox storage, sending, webhook intake, templates and campaigns. Credentials live in settings (wa_*). */
class WhatsApp {
  const API = 'https://graph.facebook.com/v21.0';
  public static function ready(): bool { try { return Schema::hasTable('wa_conversations'); } catch (\Throwable $e) { return false; } }
  public static function cfg(string $k): string { return trim(Procure::set('wa_' . $k, '')); }
  public static function enabled(): bool { return self::cfg('phone_id') !== '' && self::cfg('token') !== ''; }
  public static function verifyToken(): string { $v = self::cfg('verify'); if ($v === '') { $v = bin2hex(random_bytes(12)); Procure::put('wa_verify', $v); } return $v; }
  public static function mask(string $s): string { return $s === '' ? '' : (strlen($s) <= 8 ? '••••' : substr($s, 0, 4) . '••••••••' . substr($s, -3)); }

  /** 01XXXXXXXXX / +8801XXXXXXXXX / 8801XXXXXXXXX -> 8801XXXXXXXXX (digits only). */
  public static function norm(string $p): string {
    $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '00')) $d = substr($d, 2);
    if (preg_match('/^01\d{9}$/', $d)) return '880' . substr($d, 1);
    return $d;
  }
  public static function variants(string $wa): array { $v = [$wa, '+' . $wa]; if (str_starts_with($wa, '880')) { $v[] = '0' . substr($wa, 3); $v[] = substr($wa, 3); } return array_values(array_unique($v)); }

  private static function call(string $method, string $path, ?array $json = null): array {
    $t = self::cfg('token'); if ($t === '') return [false, ['error' => ['message' => 'WhatsApp access token is not set']]];
    try {
      $r = Http::timeout(25)->acceptJson()->withToken($t)->send($method, self::API . $path, $json === null ? [] : ['json' => $json]);
      return [$r->successful(), (array)$r->json()];
    } catch (\Throwable $e) { return [false, ['error' => ['message' => 'Network error: ' . $e->getMessage()]]]; }
  }
  private static function err(array $j): string { $m = (string)($j['error']['message'] ?? 'Unknown error'); $c = $j['error']['code'] ?? null; return mb_substr(($c ? '(' . $c . ') ' : '') . $m, 0, 290); }

  public static function windowOpen(object $c): bool { return $c->last_inbound_at && strtotime((string)$c->last_inbound_at . ' UTC') > time() - 86400; }

  public static function conversation(string $wa, ?string $name = null): object {
    $c = DB::table('wa_conversations')->where('wa_id', $wa)->first();
    if (!$c) {
      $cust = null; try { $cust = DB::table('customers')->whereIn('phone', self::variants($wa))->first(['id', 'name']); } catch (\Throwable $e) {}
      $id = DB::table('wa_conversations')->insertGetId(['wa_id' => $wa, 'name' => $name ?: ($cust->name ?? null), 'customer_id' => $cust->id ?? null, 'status' => 'open', 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
      $c = DB::table('wa_conversations')->find($id);
    } elseif ($name && !$c->name) { DB::table('wa_conversations')->where('id', $c->id)->update(['name' => $name]); $c->name = $name; }
    return $c;
  }

  private static function store(int $cid, array $m): int {
    return (int)DB::table('wa_messages')->insertGetId($m + ['conversation_id' => $cid, 'created_at' => now(), 'updated_at' => now()]);
  }

  /** Free-text reply (only inside the 24h customer-service window). Returns [ok, message]. */
  public static function sendText(object $c, string $text, string $by): array {
    $text = trim($text); if ($text === '') return [false, 'Message is empty'];
    if (!self::enabled()) return [false, 'WhatsApp is not connected yet (Settings tab)'];
    if (!self::windowOpen($c)) return [false, 'The 24-hour window is closed. Send an approved template instead.'];
    [$ok, $j] = self::call('POST', '/' . self::cfg('phone_id') . '/messages', ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $c->wa_id, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 4000)]]);
    $wamid = $ok ? ($j['messages'][0]['id'] ?? null) : null;
    self::store($c->id, ['wamid' => $wamid, 'direction' => 'out', 'type' => 'text', 'body' => $text, 'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : self::err($j), 'sent_by' => $by, 'ts' => now()]);
    DB::table('wa_conversations')->where('id', $c->id)->update(['last_message_at' => now(), 'last_text' => mb_substr($text, 0, 190), 'updated_at' => now()]);
    return [$ok, $ok ? 'Sent' : self::err($j)];
  }

  /** Approved template message (works outside the 24h window). $params = body variable values in order. */
  public static function sendTemplate(string $wa, string $name, string $lang, array $params, string $by, ?int $campaignId = null): array {
    if (!self::enabled()) return [false, 'WhatsApp is not connected yet', null];
    $tpl = ['name' => $name, 'language' => ['code' => $lang]];
    if ($params) $tpl['components'] = [['type' => 'body', 'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => mb_substr((string)$p, 0, 900)], array_values($params))]];
    [$ok, $j] = self::call('POST', '/' . self::cfg('phone_id') . '/messages', ['messaging_product' => 'whatsapp', 'to' => $wa, 'type' => 'template', 'template' => $tpl]);
    $c = self::conversation($wa); $wamid = $ok ? ($j['messages'][0]['id'] ?? null) : null;
    $row = DB::table('wa_templates')->where('name', $name)->where('language', $lang)->first(); $body = $row->body ?? $name;
    foreach (array_values($params) as $i => $p) $body = str_replace('{{' . ($i + 1) . '}}', (string)$p, (string)$body);
    self::store($c->id, ['wamid' => $wamid, 'direction' => 'out', 'type' => 'template', 'body' => $body, 'template_name' => $name, 'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : self::err($j), 'sent_by' => $by, 'campaign_id' => $campaignId, 'ts' => now()]);
    DB::table('wa_conversations')->where('id', $c->id)->update(['last_message_at' => now(), 'last_text' => mb_substr('📋 ' . $body, 0, 190), 'updated_at' => now()]);
    return [$ok, $ok ? 'Sent' : self::err($j), $wamid];
  }

  public static function markRead(string $wamid): void { if (self::enabled() && $wamid !== '') self::call('POST', '/' . self::cfg('phone_id') . '/messages', ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $wamid]); }

  /** Download a media file by id for the inbox (proxy, token never leaves the server). Returns [bytes, mime] or null. */
  public static function media(string $id): ?array {
    [$ok, $j] = self::call('GET', '/' . rawurlencode($id)); $url = $j['url'] ?? null; if (!$ok || !$url || !str_starts_with($url, 'https://')) return null;
    try { $r = Http::timeout(30)->withToken(self::cfg('token'))->get($url); return $r->successful() ? [$r->body(), $j['mime_type'] ?? $r->header('Content-Type')] : null; } catch (\Throwable $e) { return null; }
  }

  /** Webhook payload -> messages and delivery statuses. */
  public static function receive(array $p): void {
    foreach ($p['entry'] ?? [] as $en) foreach ($en['changes'] ?? [] as $ch) {
      $v = $ch['value'] ?? []; $names = [];
      foreach ($v['contacts'] ?? [] as $ct) $names[self::norm((string)($ct['wa_id'] ?? ''))] = $ct['profile']['name'] ?? null;
      foreach ($v['messages'] ?? [] as $m) {
        $wa = self::norm((string)($m['from'] ?? '')); $wamid = (string)($m['id'] ?? ''); if ($wa === '' || $wamid === '') continue;
        if (DB::table('wa_messages')->where('wamid', $wamid)->exists()) continue;
        $type = (string)($m['type'] ?? 'text'); $body = ''; $media = null; $mime = null;
        switch ($type) {
          case 'text': $body = (string)($m['text']['body'] ?? ''); break;
          case 'image': case 'video': case 'audio': case 'document': case 'sticker': $x = $m[$type] ?? []; $media = $x['id'] ?? null; $mime = $x['mime_type'] ?? null; $body = (string)($x['caption'] ?? ($x['filename'] ?? '')); break;
          case 'button': $body = (string)($m['button']['text'] ?? ''); break;
          case 'interactive': $x = $m['interactive'] ?? []; $body = (string)($x['button_reply']['title'] ?? $x['list_reply']['title'] ?? ''); break;
          case 'location': $body = '📍 ' . ($m['location']['name'] ?? '') . ' ' . ($m['location']['latitude'] ?? '') . ',' . ($m['location']['longitude'] ?? ''); break;
          default: $body = '[' . $type . ']';
        }
        $c = self::conversation($wa, $names[$wa] ?? null); $ts = isset($m['timestamp']) ? date('Y-m-d H:i:s', (int)$m['timestamp']) : gmdate('Y-m-d H:i:s');
        try { self::store($c->id, ['wamid' => $wamid, 'direction' => 'in', 'type' => $type, 'body' => $body, 'media_id' => $media, 'mime' => $mime, 'status' => 'received', 'ts' => $ts]); } catch (\Throwable $e) { continue; }
        DB::table('wa_conversations')->where('id', $c->id)->update(['last_message_at' => $ts, 'last_inbound_at' => $ts, 'unread' => DB::raw('unread + 1'), 'status' => 'open', 'last_text' => mb_substr($body !== '' ? $body : '[' . $type . ']', 0, 190), 'updated_at' => now()]);
        if (preg_match('/^\s*(stop|unsubscribe|বন্ধ|বন্ধ করুন)\s*$/iu', $body)) { try { DB::table('wa_optouts')->updateOrInsert(['wa_id' => $wa], ['reason' => 'Customer sent: ' . mb_substr($body, 0, 40), 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) {} }
      }
      foreach ($v['statuses'] ?? [] as $s) {
        $wamid = (string)($s['id'] ?? ''); $st = (string)($s['status'] ?? ''); if ($wamid === '' || !in_array($st, ['sent', 'delivered', 'read', 'failed'], true)) continue;
        $err = $st === 'failed' ? mb_substr((string)(($s['errors'][0]['title'] ?? '') . ' ' . ($s['errors'][0]['message'] ?? $s['errors'][0]['error_data']['details'] ?? '')), 0, 290) : null;
        $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        $cur = DB::table('wa_messages')->where('wamid', $wamid)->value('status');
        if ($cur !== null && ($rank[$cur] ?? 0) <= $rank[$st]) DB::table('wa_messages')->where('wamid', $wamid)->update(['status' => $st, 'error' => $err, 'updated_at' => now()]);
        $cr = DB::table('wa_campaign_recipients')->where('wamid', $wamid)->first();
        if ($cr && ($rank[$cr->status] ?? 0) <= $rank[$st]) DB::table('wa_campaign_recipients')->where('id', $cr->id)->update(['status' => $st, 'error' => $err, 'updated_at' => now()]);
      }
    }
  }

  public static function syncTemplates(): array {
    $w = self::cfg('waba_id'); if ($w === '') return [false, 'WhatsApp Business Account ID is not set', 0];
    $n = 0; $path = '/' . rawurlencode($w) . '/message_templates?limit=100&fields=name,language,category,status,components'; $guard = 0;
    while ($path && $guard++ < 10) {
      [$ok, $j] = self::call('GET', $path); if (!$ok) return [false, self::err($j), $n];
      foreach ($j['data'] ?? [] as $t) {
        $body = ''; foreach ($t['components'] ?? [] as $c) if (($c['type'] ?? '') === 'BODY') $body = (string)($c['text'] ?? '');
        $params = preg_match_all('/\{\{(\d+)\}\}/', $body, $mm) ? count(array_unique($mm[1])) : 0;
        DB::table('wa_templates')->updateOrInsert(['name' => $t['name'], 'language' => $t['language']], ['category' => $t['category'] ?? null, 'status' => $t['status'] ?? null, 'body' => $body, 'params' => $params, 'updated_at' => now(), 'created_at' => now()]); $n++;
      }
      $next = $j['paging']['next'] ?? null; $path = $next && str_starts_with($next, self::API) ? substr($next, strlen(self::API)) : null;
    }
    return [true, 'Synced', $n];
  }

  /** Build the recipient list for a campaign. Skips opted-out numbers and duplicates. */
  public static function audience(string $type, array $d): array {
    $out = [];
    if ($type === 'numbers') foreach (preg_split('/[\s,;]+/', (string)($d['numbers'] ?? '')) as $x) { $w = self::norm($x); if (preg_match('/^8801\d{9}$/', $w)) $out[$w] = null; }
    else {
      $min = max(0, (int)($d['min_days'] ?? 0)); $max = max($min, (int)($d['max_days'] ?? 30)); $from = now()->subDays($max)->toDateTimeString(); $to = now()->subDays($min)->toDateTimeString();
      if ($type === 'recent') $rows = DB::table('orders')->whereNotNull('phone')->where('created_at', '>=', $from)->where('created_at', '<=', $to)->groupBy('phone')->selectRaw('phone, max(customer_name) n')->limit(5000)->get();
      else $rows = DB::table('orders')->whereNotNull('phone')->groupBy('phone')->havingRaw('max(created_at) < ?', [$from])->selectRaw('phone, max(customer_name) n')->limit(5000)->get(); // lapsed: last order older than max_days
      foreach ($rows as $r) { $w = self::norm((string)$r->phone); if (preg_match('/^8801\d{9}$/', $w)) $out[$w] = $r->n; }
    }
    $opt = DB::table('wa_optouts')->pluck('wa_id')->flip()->all();
    return array_diff_key($out, $opt);
  }

  /** Worker tick: send a small batch of every running campaign, respecting the daily cap. */
  public static function tick(): void {
    if (!self::ready() || !self::enabled()) return;
    $cap = max(1, (int)Procure::set('wa_daily_cap', '250')); $today = now('Asia/Dhaka')->startOfDay()->utc()->toDateTimeString();
    $sentToday = (int)DB::table('wa_campaign_recipients')->where('sent_at', '>=', $today)->count(); if ($sentToday >= $cap) return;
    foreach (DB::table('wa_campaigns')->where('status', 'running')->orderBy('id')->get() as $c) {
      $left = $cap - $sentToday; if ($left <= 0) return;
      $batch = DB::table('wa_campaign_recipients')->where('campaign_id', $c->id)->where('status', 'pending')->orderBy('id')->limit(min(15, $left))->get();
      if ($batch->isEmpty()) { DB::table('wa_campaigns')->where('id', $c->id)->update(['status' => 'done', 'finished_at' => now(), 'updated_at' => now()]); continue; }
      $map = json_decode((string)$c->param_map, true) ?: [];
      foreach ($batch as $r) {
        if (DB::table('wa_optouts')->where('wa_id', $r->wa_id)->exists()) { DB::table('wa_campaign_recipients')->where('id', $r->id)->update(['status' => 'skipped', 'error' => 'Opted out', 'updated_at' => now()]); continue; }
        $params = array_map(fn($v) => str_replace('{name}', trim((string)($r->name ?: 'Customer')), (string)$v), $map);
        [$ok, $msg, $wamid] = self::sendTemplate($r->wa_id, $c->template_name, $c->language, $params, 'Campaign: ' . $c->name, (int)$c->id);
        DB::table('wa_campaign_recipients')->where('id', $r->id)->update(['status' => $ok ? 'sent' : 'failed', 'wamid' => $wamid, 'error' => $ok ? null : $msg, 'sent_at' => now(), 'updated_at' => now()]); $sentToday++;
      }
    }
  }
}
