<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
/** Unified customer chat: Facebook Messenger, Instagram DM, WhatsApp Business and website live chat. */
class Chat {
  const CH = ['facebook'=>'Facebook Messenger', 'instagram'=>'Instagram', 'whatsapp'=>'WhatsApp', 'web'=>'Website Chat'];
  const API = 'https://graph.facebook.com/v19.0';
  public static function phone(?string $p): ?string {
    $d = preg_replace('/\D/', '', (string)$p); if ($d === '') return null;
    if (str_starts_with($d, '880') && strlen($d) === 13) return '0'.substr($d, 3);
    if (strlen($d) === 10 && $d[0] === '1') return '0'.$d;
    return $d;
  }
  public static function sec(string $k): ?string { return Sms::secret($k); }
  /** Store one incoming message and (re)open its thread. Returns the thread, or null for a duplicate. */
  public static function ingest(string $ch, string $ext, ?string $name, string $body, ?string $att = null, ?string $mid = null, array $meta = []): ?object {
    if ($mid && DB::table('chat_messages')->where('ext_id', $mid)->exists()) return null;
    $t = DB::table('chat_threads')->where(['channel'=>$ch, 'external_id'=>$ext])->first();
    $phone = $ch === 'whatsapp' ? self::phone($ext) : self::phone($meta['phone'] ?? null);
    if (!$t) {
      $prof = in_array($ch, ['facebook', 'instagram']) ? self::profile($ch, $ext) : [];
      $cust = $phone ? DB::table('customers')->where('phone', $phone)->orderBy('id')->first() : null;
      $id = DB::table('chat_threads')->insertGetId(['channel'=>$ch, 'external_id'=>$ext, 'customer_id'=>$cust->id ?? null,
        'name'=>$name ?: ($prof['name'] ?? null) ?: ($cust->name ?? null) ?: self::CH[$ch].' user', 'phone'=>$phone, 'username'=>$meta['username'] ?? ($prof['username'] ?? null),
        'avatar'=>$prof['avatar'] ?? null, 'status'=>'new', 'unread'=>0, 'created_at'=>now(), 'updated_at'=>now()]);
      $t = DB::table('chat_threads')->find($id);
    } elseif ($name && (str_ends_with((string)$t->name, ' user') || !$t->name)) {
      DB::table('chat_threads')->where('id', $t->id)->update(['name'=>$name]);
    }
    DB::table('chat_messages')->insert(['thread_id'=>$t->id, 'dir'=>'in', 'kind'=>$att ? 'file' : 'text', 'body'=>mb_substr($body, 0, 4000), 'attachment_url'=>$att,
      'attachment_type'=>$att ? 'image' : null, 'ext_id'=>$mid, 'created_at'=>now(), 'updated_at'=>now()]);
    $st = $t->status === 'resolved' ? 'new' : ($t->status === 'waiting' ? 'open' : $t->status);
    DB::table('chat_threads')->where('id', $t->id)->update(['unread'=>DB::raw('unread + 1'), 'last_message'=>mb_substr($body !== '' ? $body : '[attachment]', 0, 300),
      'last_dir'=>'in', 'last_at'=>now(), 'status'=>$st, 'updated_at'=>now()]);
    return DB::table('chat_threads')->find($t->id);
  }
  /** Claude answers a website chat message, unless a staff member has taken the thread over. Runs after the response is sent. */
  public static function aiReply(int $tid): void {
    try {
      if (!AiChat::chatOn()) return;
      $t = DB::table('chat_threads')->find($tid);
      if (!$t || $t->channel !== 'web' || $t->ai_paused) return;
      $k = 'ai.t.'.$tid.'.'.now()->format('YmdH'); \Illuminate\Support\Facades\Cache::add($k, 0, 4000);
      if (\Illuminate\Support\Facades\Cache::increment($k) > 25) return;
      $d = 'ai.n.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($d, 0, 90000);
      if (\Illuminate\Support\Facades\Cache::increment($d) > 1500) return;
      $rows = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->limit(10)->get()->reverse()->values();
      if ($rows->isEmpty() || $rows->last()->dir !== 'in') return;
      $hist = $rows->map(fn($m) => ['role'=>$m->dir === 'in' ? 'user' : 'assistant', 'content'=>$m->body !== '' ? $m->body : '[ছবি]'])->all();
      [$ok, $txt] = AiChat::reply($hist, "You are answering inside the live chat. If the customer wants a person, has a complaint, wants to change/cancel an order, or you are not sure, say a team member will reply shortly and give the phone/WhatsApp number. Do not claim to be human.\n");
      if (!$ok) return;
      // a staff member may have replied while Claude was thinking
      if (DB::table('chat_threads')->where('id', $tid)->value('ai_paused')) return;
      DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($txt, 0, 3500), 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>'sent', 'created_at'=>now(), 'updated_at'=>now()]);
      DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  /** Name / photo from the Graph API (best effort). */
  public static function profile(string $ch, string $id): array {
    $tok = self::sec($ch === 'instagram' && self::sec('chat_ig_token') ? 'chat_ig_token' : 'chat_fb_token'); if (!$tok) return [];
    try {
      $r = Http::timeout(4)->get(self::API.'/'.$id, ['fields'=>$ch === 'instagram' ? 'name,username,profile_pic' : 'first_name,last_name,profile_pic', 'access_token'=>$tok]);
      if (!$r->ok()) return [];
      $j = $r->json();
      $n = $j['name'] ?? trim(($j['first_name'] ?? '').' '.($j['last_name'] ?? ''));
      return ['name'=>$n ?: null, 'username'=>$j['username'] ?? null, 'avatar'=>$j['profile_pic'] ?? null];
    } catch (\Throwable $e) { return []; }
  }
  /** Send an admin reply. Returns [ok, error]. Website chat needs no API: the visitor's widget fetches it. */
  public static function deliver(object $t, string $body, ?string $att = null, string $attType = 'image'): array {
    try {
      if ($t->channel === 'web') return [true, null];
      if ($t->channel === 'whatsapp') {
        $tok = self::sec('chat_wa_token'); $pid = self::sec('chat_wa_phone_id');
        if (!$tok || !$pid) return [false, 'WhatsApp is not connected (Chat Settings).'];
        $to = preg_replace('/\D/', '', $t->external_id);
        $out = [];
        if ($att) $out[] = $attType === 'image' ? ['type'=>'image', 'image'=>['link'=>$att, 'caption'=>mb_substr($body, 0, 900)]] : ['type'=>'document', 'document'=>['link'=>$att, 'caption'=>mb_substr($body, 0, 900)]];
        else $out[] = ['type'=>'text', 'text'=>['body'=>$body, 'preview_url'=>true]];
        foreach ($out as $m) {
          $r = Http::timeout(15)->withToken($tok)->post(self::API.'/'.$pid.'/messages', ['messaging_product'=>'whatsapp', 'to'=>$to] + $m);
          if (!$r->ok()) return [false, self::err($r)];
        }
        return [true, null];
      }
      $tok = self::sec($t->channel === 'instagram' && self::sec('chat_ig_token') ? 'chat_ig_token' : 'chat_fb_token');
      if (!$tok) return [false, ucfirst($t->channel).' is not connected (Chat Settings).'];
      $msgs = [];
      if ($att) $msgs[] = ['attachment'=>['type'=>$attType === 'image' ? 'image' : 'file', 'payload'=>['url'=>$att, 'is_reusable'=>true]]];
      if ($body !== '') $msgs[] = ['text'=>$body];
      foreach ($msgs as $m) {
        $r = Http::timeout(15)->post(self::API.'/me/messages?access_token='.urlencode($tok), ['recipient'=>['id'=>$t->external_id], 'messaging_type'=>'RESPONSE', 'message'=>$m]);
        if (!$r->ok()) return [false, self::err($r)];
      }
      return [true, null];
    } catch (\Throwable $e) { return [false, 'Could not reach the platform: '.mb_substr($e->getMessage(), 0, 120)]; }
  }
  private static function err($r): string { return mb_substr((string)($r->json('error.message') ?? $r->body()), 0, 220); }

  /** Card data for lists and popups: customer category, order count and risk. */
  public static function cards($rows): array {
    $rows = collect($rows); if ($rows->isEmpty()) return [];
    $objs = $rows->map(fn($t) => (object)['id'=>$t->id, 'phone'=>$t->phone ?: '-none-'.$t->id, 'total'=>0]);
    $intel = CustomerIntel::forOrders($objs);
    $phones = $rows->pluck('phone')->filter()->unique()->values();
    $oc = $phones->isEmpty() ? collect() : DB::table('orders')->whereIn('phone', $phones)->select('phone', DB::raw('count(*) n'))->groupBy('phone')->pluck('n', 'phone');
    $agents = DB::table('users')->whereIn('id', $rows->pluck('assigned_to')->filter()->unique())->pluck('name', 'id');
    return $rows->map(function ($t) use ($intel, $oc, $agents) {
      $i = $intel[$t->id] ?? null; $n = (int)($oc[$t->phone] ?? 0) + (int)($i['legacy'] ?? 0);
      return ['id'=>$t->id, 'channel'=>$t->channel, 'channel_name'=>self::CH[$t->channel] ?? $t->channel, 'name'=>$t->name, 'phone'=>$t->phone, 'username'=>$t->username, 'avatar'=>$t->avatar,
        'status'=>$t->status, 'unread'=>(int)$t->unread, 'last'=>$t->last_message, 'last_dir'=>$t->last_dir, 'last_at'=>$t->last_at ? \Carbon\Carbon::parse($t->last_at)->toIso8601String() : null,
        'ai'=>!($t->ai_paused ?? false), 'assigned_to'=>$t->assigned_to, 'assigned_name'=>$agents[$t->assigned_to] ?? null, 'customer_id'=>$t->customer_id,
        'cat'=>$n > 0 && $i ? $i['cat'] : 'New', 'orders'=>$n, 'risk'=>$n > 0 && $i ? $i['risk'] : 'Low'];
    })->all();
  }
  public static function unreadTotal(): int { return (int)DB::table('chat_threads')->where('unread', '>', 0)->sum('unread'); }
  public static function productUrl(object $p): string { return url(\App\Http\Controllers\StoreController::url($p)); }
  public static function invoiceUrl(string $invoice): string { return URL::signedRoute('order.invoice', ['invoice'=>$invoice]); }
}
