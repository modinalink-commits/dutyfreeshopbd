<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owner 17 Nov 2026: one central SMS inbox inside the existing Messages inbox (channel "sms").
 * Any provider that can deliver incoming SMS posts them to its own receive address (/webhooks/sms-in/{id}/{token});
 * every connection lands in the same inbox, each conversation keeping its provider and the business number it came in on.
 * Sending-only providers are NOT shown as incoming: see capability().
 * Incoming connections are independent of the outgoing primary / fall-back choice.
 */
class SmsInbox {
  /** What each connected provider can do for incoming SMS, from its published API. */
  public static function capability(): array {
    return [
      'alpha' => ['name' => SmsGate::NAMES['alpha'], 'incoming' => false,
        'why' => 'Published API has sending, report and balance only. No incoming-message API or webhook. The approved sender 8809644999000 is a send-only number.',
        'todo' => 'Ask Alpha SMS support (09613 250 260) for a two-way number or short code with an incoming webhook. This needs their approval and has a separate cost. When they give it, add a connection below and give them its receive address.'],
      'bulk' => ['name' => SmsGate::NAMES['bulk'], 'incoming' => false,
        'why' => 'The API in use has sending and balance only. No incoming-message API or webhook was found.',
        'todo' => 'Ask BulkSMSBD whether they offer two-way SMS with a webhook. If they do, add a connection below and give them its receive address.'],
    ];
  }
  public static function conns(): array { $j = json_decode(SmsGate::set('inbound', '[]'), true); return is_array($j) ? $j : []; }
  private static function save(array $c): void { SmsGate::put('inbound', json_encode(array_values($c), JSON_UNESCAPED_UNICODE)); }
  public static function add(string $provider, string $name, string $number): array {
    $c = self::conns(); $row = ['id' => Str::lower(Str::random(8)), 'provider' => $provider, 'name' => mb_substr(trim($name), 0, 40), 'number' => preg_replace('/[^\d+]/', '', $number), 'token' => Str::random(40), 'on' => true, 'last_at' => null, 'count' => 0];
    $c[] = $row; self::save($c); return $row;
  }
  public static function toggle(string $id, ?bool $on, bool $remove = false): void {
    $c = self::conns(); foreach ($c as $i => $x) if ($x['id'] === $id) { if ($remove) unset($c[$i]); else $c[$i]['on'] = $on ?? !$x['on']; } self::save($c);
  }
  public static function find(string $id): ?array { foreach (self::conns() as $x) if ($x['id'] === $id) return $x; return null; }
  public static function url(array $c): string { return rtrim((string)config('app.url'), '/') . '/webhooks/sms-in/' . $c['id'] . '/' . $c['token']; }
  public static function ref(array $c): string { return mb_substr($c['provider'] . '|' . $c['number'], 0, 40); }
  /** "Alpha SMS · 8809644999000" for a thread's page_ref. */
  public static function label(?string $ref): ?string {
    if (!$ref) return null; [$p, $n] = array_pad(explode('|', $ref, 2), 2, '');
    return trim((SmsGate::NAMES[$p] ?? ucfirst($p)) . ($n !== '' ? ' · ' . $n : ''));
  }
  private static function pick(array $in, array $keys): string { foreach ($keys as $k) if (isset($in[$k]) && is_scalar($in[$k]) && trim((string)$in[$k]) !== '') return trim((string)$in[$k]); return ''; }
  /**
   * Store one incoming SMS. Field names differ per provider, so the usual ones are accepted.
   * @return array{0:string,1:?int} result (stored | duplicate | report | invalid | off) and the conversation id
   */
  public static function receive(array $conn, array $in): array {
    if (empty($conn['on'])) return ['off', null];
    $from = self::pick($in, ['from', 'sender', 'msisdn', 'mobile', 'number', 'source', 'src', 'phone']);
    $text = self::pick($in, ['text', 'message', 'msg', 'body', 'sms', 'content']);
    $to = self::pick($in, ['to', 'receiver', 'destination', 'dst', 'shortcode', 'recipient']);
    $mid = self::pick($in, ['message_id', 'sms_id', 'msg_id', 'id', 'uuid', 'reference']);
    // a delivery report is not a customer message: it has a status and no text
    if ($text === '' && self::pick($in, ['status', 'dlr', 'delivery_status', 'dlr_status', 'request_status']) !== '') return ['report', null];
    $num = Sms::number($from);
    if (!$num || $text === '') return ['invalid', null];
    $local = '0' . substr($num, 3);
    $ext = $local . '@' . $conn['id'];
    $stamp = self::pick($in, ['time', 'timestamp', 'received_at', 'date', 'datetime']) ?: now()->format('YmdHi');
    $key = 'sms:' . $conn['id'] . ':' . ($mid !== '' ? $mid : sha1($num . '|' . $text . '|' . $stamp)); // the same message delivered again is stored once
    $cust = DB::table('customers')->where('phone', $local)->orderBy('id')->first();
    $t = Chat::ingest('sms', $ext, $cust->name ?? $local, mb_substr($text, 0, 2000), null, mb_substr($key, 0, 190), ['phone' => $local, 'page_ref' => self::ref($conn)]);
    if (!$t) return ['duplicate', null];
    $c = self::conns(); foreach ($c as $i => $x) if ($x['id'] === $conn['id']) { $c[$i]['last_at'] = now()->toDateTimeString(); $c[$i]['count'] = (int)($x['count'] ?? 0) + 1; if ($to !== '' && empty($x['number'])) $c[$i]['number'] = preg_replace('/[^\d+]/', '', $to); } self::save($c);
    return ['stored', (int)$t->id];
  }
  /** Reply from the inbox: through the provider the message came in on, when that provider is connected for sending. @return array{bool,?string} */
  public static function reply(object $t, string $body): array {
    [$p] = array_pad(explode('|', (string)($t->page_ref ?? ''), 2), 2, '');
    $to = $t->phone ?: explode('@', (string)$t->external_id)[0];
    $ctx = ['event' => 'inbox_reply', 'type' => 'transactional'];
    $same = isset(SmsGate::NAMES[$p]) && SmsGate::ready($p);
    if ($same) $ctx['force'] = $p; // same connection the customer wrote to
    [$ok, $msg] = Sms::send((string)$to, $body, $ctx);
    return [$ok, $ok ? null : $msg];
  }
  /** Will a reply leave through the connection the customer wrote to? Shown to staff before they answer. */
  public static function replyNote(?string $ref): string {
    [$p] = array_pad(explode('|', (string)$ref, 2), 2, '');
    if (isset(SmsGate::NAMES[$p]) && SmsGate::ready($p)) { $s = SmsGate::cfg()['sender'][$p]; return 'Reply goes through ' . SmsGate::NAMES[$p] . ($s !== '' ? ' as "' . $s . '"' : ' (provider default sender)') . '.'; }
    $o = SmsGate::order()[0]; if (!$o) return 'No SMS provider is ready: a reply cannot be sent.';
    $s = SmsGate::cfg()['sender'][$o[0]];
    return 'The connection this SMS came in on cannot send. A reply will go through ' . SmsGate::NAMES[$o[0]] . ($s !== '' ? ' as "' . $s . '"' : '') . ', so the customer sees a different sender.';
  }
}
