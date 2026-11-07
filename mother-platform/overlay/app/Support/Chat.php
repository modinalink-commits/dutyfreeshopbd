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
  /** Token for the Page / Instagram account a conversation belongs to (Facebook Login connections), else the single saved token. */
  public static function pageTok(string $ch, ?string $ref): ?string {
    try {
      $p = $ref ? DB::table('chat_pages')->where('page_id', $ref)->orWhere('ig_id', $ref)->first() : null;
      if (!$p && !$ref && DB::table('chat_pages')->count() === 1) $p = DB::table('chat_pages')->first();
      if ($p && $p->token) return \Illuminate\Support\Facades\Crypt::decryptString($p->token);
    } catch (\Throwable $e) {}
    return self::sec($ch === 'instagram' && self::sec('chat_ig_token') ? 'chat_ig_token' : 'chat_fb_token');
  }
  /** Store one incoming message and (re)open its thread. Returns the thread, or null for a duplicate. */
  public static function ingest(string $ch, string $ext, ?string $name, string $body, ?string $att = null, ?string $mid = null, array $meta = []): ?object {
    if ($mid && DB::table('chat_messages')->where('ext_id', $mid)->exists()) return null;
    $t = DB::table('chat_threads')->where(['channel'=>$ch, 'external_id'=>$ext])->first();
    $phone = $ch === 'whatsapp' ? self::phone($ext) : self::phone($meta['phone'] ?? null);
    if (!$t) {
      $prof = in_array($ch, ['facebook', 'instagram']) ? self::profile($ch, $ext, $meta['page_ref'] ?? null) : [];
      $cust = $phone ? DB::table('customers')->where('phone', $phone)->orderBy('id')->first() : null;
      $id = DB::table('chat_threads')->insertGetId(['channel'=>$ch, 'external_id'=>$ext, 'customer_id'=>$cust->id ?? null,
        'name'=>$name ?: ($prof['name'] ?? null) ?: ($cust->name ?? null) ?: self::CH[$ch].' user', 'phone'=>$phone, 'username'=>$meta['username'] ?? ($prof['username'] ?? null),
        'avatar'=>$prof['avatar'] ?? null, 'page_ref'=>($meta['page_ref'] ?? null) ?: null, 'status'=>'new', 'unread'=>0, 'created_at'=>now(), 'updated_at'=>now()]);
      $t = DB::table('chat_threads')->find($id);
    } elseif ($name && (str_ends_with((string)$t->name, ' user') || !$t->name)) {
      DB::table('chat_threads')->where('id', $t->id)->update(['name'=>$name]);
    }
    // A customer typed their mobile number in the message: link this conversation to the customer profile automatically.
    if (!$t->phone && $body !== '' && preg_match('/(?<!\d)(?:\+?88)?(01[3-9]\d{8})(?!\d)/', strtr($body, ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9']), $pm)) {
      $c2 = DB::table('customers')->where('phone', $pm[1])->orderBy('id')->first();
      DB::table('chat_threads')->where('id', $t->id)->update(['phone'=>$pm[1], 'customer_id'=>$c2->id ?? $t->customer_id, 'updated_at'=>now()]);
      $t->phone = $pm[1];
    }
    DB::table('chat_messages')->insert(['thread_id'=>$t->id, 'dir'=>'in', 'kind'=>$att ? 'file' : 'text', 'body'=>mb_substr($body, 0, 4000), 'attachment_url'=>$att,
      'attachment_type'=>$att ? ($meta['att_type'] ?? 'image') : null, 'ext_id'=>$mid, 'created_at'=>now(), 'updated_at'=>now()]);
    $st = $t->status === 'resolved' ? 'new' : ($t->status === 'waiting' ? 'open' : $t->status);
    // Every new customer message puts the conversation back into "waiting for a reply".
    $prev = $body !== '' ? $body : ($att ? (($meta['att_type'] ?? 'image') === 'audio' ? '🎤 Voice message' : '[attachment]') : '');
    DB::table('chat_threads')->where('id', $t->id)->update(['unread'=>DB::raw('unread + 1'), 'awaiting_reply'=>true, 'last_customer_message_at'=>now(), 'last_message'=>mb_substr($prev, 0, 300),
      'last_dir'=>'in', 'last_at'=>now(), 'status'=>$st, 'updated_at'=>now()]);
    return DB::table('chat_threads')->find($t->id);
  }
  /** Record a WhatsApp message sent by the system (order confirmation, template, campaign) in the customer's chat, so staff see exactly what the customer was told. */
  public static function logOut(string $wa, string $body, string $by, ?string $mid = null, string $kind = 'text'): void {
    try {
      $wa = preg_replace('/\D/', '', $wa); if ($wa === '' || trim($body) === '') return;
      if ($mid && DB::table('chat_messages')->where('ext_id', $mid)->exists()) return;
      $t = DB::table('chat_threads')->where(['channel'=>'whatsapp', 'external_id'=>$wa])->first();
      $phone = self::phone($wa);
      if (!$t) {
        $cust = $phone ? DB::table('customers')->where('phone', $phone)->orderBy('id')->first() : null;
        $id = DB::table('chat_threads')->insertGetId(['channel'=>'whatsapp', 'external_id'=>$wa, 'customer_id'=>$cust->id ?? null, 'name'=>($cust->name ?? null) ?: ($phone ?: 'WhatsApp user'), 'phone'=>$phone,
          'status'=>'open', 'unread'=>0, 'created_at'=>now(), 'updated_at'=>now()]);
        $t = DB::table('chat_threads')->find($id);
      }
      DB::table('chat_messages')->insert(['thread_id'=>$t->id, 'dir'=>'out', 'kind'=>$kind, 'body'=>mb_substr($body, 0, 4000), 'ext_id'=>$mid, 'user_name'=>$by, 'status'=>'sent', 'created_at'=>now(), 'updated_at'=>now()]);
      DB::table('chat_threads')->where('id', $t->id)->update(['last_message'=>mb_substr($body, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  /** What kind of audio file these bytes are (by content, never by the name the sender gave it). */
  public static function audioExt(string $b): ?string {
    if (substr($b, 0, 4) === 'OggS') return 'ogg';
    if (substr($b, 0, 4) === "\x1A\x45\xDF\xA3") return 'webm';
    if (substr($b, 4, 4) === 'ftyp') return 'm4a';
    if (substr($b, 0, 3) === 'ID3' || (strlen($b) > 1 && ord($b[0]) === 0xFF && (ord($b[1]) & 0xE0) === 0xE0)) return 'mp3';
    if (substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WAVE') return 'wav';
    if (substr($b, 0, 6) === "#!AMR\n") return 'amr';
    return null;
  }
  /** Keep our own copy of a Messenger / Instagram voice clip so it still plays after the platform link expires. Returns the local URL, or null. */
  public static function mirrorAudio(string $url): ?string {
    try {
      $r = Http::timeout(20)->get($url); if (!$r->ok()) return null;
      $b = $r->body(); if ($b === '' || strlen($b) > 12 * 1048576) return null;
      $ext = self::audioExt($b); if (!$ext) return null;
      $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $name = 'in-'.date('ymd').'-'.bin2hex(random_bytes(6)).'.'.$ext; file_put_contents($dir.'/'.$name, $b);
      return url('/uploads/chat/'.$name);
    } catch (\Throwable $e) { return null; }
  }
  /** Speech-to-text for one stored incoming voice message. Best effort, runs after the webhook response; the result is shown under the player as an AI transcript. */
  public static function transcribe(int $mid): void {
    try {
      $m = DB::table('chat_messages')->find($mid);
      if (!$m || $m->dir !== 'in' || $m->attachment_type !== 'audio' || !empty($m->transcript) || !VoiceChat::key()) return;
      $d = 'inbox.stt.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($d, 0, 90000); if (\Illuminate\Support\Facades\Cache::increment($d) > 800) return;
      $path = public_path('uploads/chat/'.basename((string)parse_url((string)$m->attachment_url, PHP_URL_PATH)));
      if (!is_file($path) || filesize($path) > 950000) return; // longer than a minute or so: skip rather than pay for a failing request
      $b = (string)file_get_contents($path); $fmt = self::audioExt($b);
      if (!in_array($fmt, ['ogg', 'webm'], true)) { $b = (string)self::toOpus($path); if ($b === '') return; }
      [$t] = VoiceChat::transcribe($b, (int)$m->thread_id, 16000);
      if ($t === null && VoiceChat::$lastStatus === 400) [$t] = VoiceChat::transcribe($b, (int)$m->thread_id, 48000);
      if ($t === null) return;
      $t = mb_substr(trim(strip_tags($t)), 0, 1000); if ($t === '') return;
      DB::table('chat_messages')->where('id', $mid)->update(['transcript'=>$t, 'input_type'=>'voice', 'updated_at'=>now()]);
      // keep the list preview useful: the words instead of "Voice message", while this is still the latest message
      if ((int)DB::table('chat_messages')->where('thread_id', $m->thread_id)->max('id') === $mid) DB::table('chat_threads')->where('id', $m->thread_id)->update(['last_message'=>'🎤 '.mb_substr($t, 0, 280)]);
    } catch (\Throwable $e) { report($e); }
  }
  /** Converts other audio types to Ogg Opus for speech recognition, only when ffmpeg exists on the server. */
  private static function toOpus(string $path): ?string {
    try {
      if (!function_exists('exec') || !is_file($path)) return null;
      $out = tempnam(sys_get_temp_dir(), 'stt').'.ogg'; $o = []; $rc = 1;
      @exec('ffmpeg -y -loglevel error -i '.escapeshellarg($path).' -t 58 -ac 1 -ar 16000 -c:a libopus '.escapeshellarg($out).' 2>&1', $o, $rc);
      $b = ($rc === 0 && is_file($out)) ? (string)file_get_contents($out) : ''; @unlink($out);
      return $b !== '' ? $b : null;
    } catch (\Throwable $e) { return null; }
  }
  /** Download an incoming WhatsApp media file (image, sticker, document, audio, video) and keep a local copy. Returns [url, type] or null. */
  public static function waMedia(string $mediaId): ?array {
    try {
      $tok = self::sec('chat_wa_token'); if (!$tok || $mediaId === '') return null;
      $m = Http::timeout(15)->withToken($tok)->get(self::API.'/'.rawurlencode($mediaId)); if (!$m->ok()) return null;
      $src = (string)$m->json('url'); $mime = strtolower((string)$m->json('mime_type')); if ($src === '' || (int)$m->json('file_size') > 16 * 1048576) return null;
      $f = Http::timeout(30)->withToken($tok)->get($src); if (!$f->ok() || $f->body() === '' || strlen($f->body()) > 16 * 1048576) return null;
      $ext = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif', 'application/pdf'=>'pdf', 'audio/ogg'=>'ogg', 'audio/mpeg'=>'mp3', 'audio/mp4'=>'m4a', 'audio/aac'=>'aac', 'audio/amr'=>'amr', 'video/mp4'=>'mp4', 'video/3gpp'=>'3gp'][strtok($mime, ';')] ?? null;
      if (!$ext) return null; // unknown type: never store an arbitrary file under the public folder
      $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $name = 'in-'.date('ymd').'-'.bin2hex(random_bytes(6)).'.'.$ext; file_put_contents($dir.'/'.$name, $f->body());
      $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'audio/') ? 'audio' : (str_starts_with($mime, 'video/') ? 'video' : 'file'));
      return [url('/uploads/chat/'.$name), $type];
    } catch (\Throwable $e) { return null; }
  }
  /** Claude answers a website chat message, unless a staff member has taken the thread over. Runs after the response is sent. */
  public static function aiReply(int $tid, int $depth = 0): void {
    $lock = null;
    try {
      if (!AiChat::chatOn() || !AiChat::hoursOk()) return;
      $t = DB::table('chat_threads')->find($tid);
      if (!$t || AiChat::paused($t) || !in_array($t->channel, ['web', 'whatsapp', 'facebook'], true)) return;
      $ext = $t->channel !== 'web'; // WhatsApp / Facebook Messenger: the reply is sent to the customer through the platform
      if ($ext) {
        if (!AiChat::chanOn()) return;
        // one answer at a time per conversation; a second quick message waits briefly, then is picked up by the follow-up run below
        $lk = 'ai.lock.'.$tid;
        if (!\Illuminate\Support\Facades\Cache::add($lk, 1, 90)) { sleep(6); if (!\Illuminate\Support\Facades\Cache::add($lk, 1, 90)) return; }
        $lock = $lk;
        $li = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first();
        if (!$li || $li->dir !== 'in') return;
        $lb = trim((string)$li->body);
        // staff handle these: voice/video/files, unsupported or system placeholders, and plain thank-you / ok messages
        if (($li->attachment_url && $li->attachment_type !== 'image') || preg_match('/^\[(unsupported|[a-z]+ received)/iu', $lb) || preg_match('/^(ok|okay|thanks?|thank you|thx|ধন্যবাদ|থ্যাংকস|👍|🙏|❤️?)[\s.!]*$/iu', $lb)) return;
      }
      $k = 'ai.t.'.$tid.'.'.now()->format('YmdH'); \Illuminate\Support\Facades\Cache::add($k, 0, 4000);
      if (\Illuminate\Support\Facades\Cache::increment($k) > 25) return;
      $d = 'ai.n.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($d, 0, 90000);
      if (\Illuminate\Support\Facades\Cache::increment($d) > 1500) return;
      $rows = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->limit(10)->get()->reverse()->values();
      if ($rows->isEmpty() || $rows->last()->dir !== 'in') return;
      $hist = $rows->map(fn($m) => ['role'=>$m->dir === 'in' ? 'user' : 'assistant', 'content'=>$m->body !== '' ? $m->body : '[ছবি]'])->all();
      // the customer's latest photo goes to the model as an image (jpeg/png/webp/gif up to 3.5 MB); anything else stays the text placeholder
      try {
        $li = $rows->last(); $ix = $rows->count() - 1;
        if ($li && $li->attachment_type === 'image' && $li->attachment_url) {
          $ip = public_path('uploads/chat/'.basename((string)parse_url((string)$li->attachment_url, PHP_URL_PATH)));
          if (is_file($ip) && filesize($ip) <= 3500000) { $mt = (string)@mime_content_type($ip); if (in_array($mt, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) $hist[$ix]['img'] = [$mt, base64_encode((string)file_get_contents($ip))]; }
        }
      } catch (\Throwable $e) {}
      if (AiAgent::on()) {
        $lastIn = $rows->last(); $txt = AiAgent::replyThread($tid, $hist, (int)$lastIn->id, (string)$lastIn->body);
        if ($txt === null) goto legacy;
        // a hand-off pauses the AI on purpose; its own closing message must still reach the customer
        if (AiChat::paused(DB::table('chat_threads')->find($tid)) && !DB::table('chat_handoffs')->where('thread_id', $tid)->where('created_at', '>', now()->subMinutes(2))->exists()) return;
        $sent = true; $err = null;
        if ($ext) { $txt = mb_substr($txt, 0, 1900); [$sent, $err] = self::deliver($t, $txt); }
        DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($txt, 0, 3500), 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
        if ($sent) DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
        if ($lock) \Illuminate\Support\Facades\Cache::forget($lock);
        // a customer message that arrived while the AI was answering gets its own answer (once)
        if ($ext && $sent && $depth < 1 && DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->value('dir') === 'in') self::aiReply($tid, $depth + 1);
        return;
      }
      legacy:
      if ($ext) return; // WhatsApp / Messenger are answered by the agent only (the legacy path cannot send through the platform)
      [$ok, $txt] = AiChat::reply($hist, "You are answering inside the live chat. If the customer wants a person, has a complaint, wants to change/cancel an order, or you are not sure, say a team member will reply shortly and give the phone/WhatsApp number. Do not claim to be human.\n", (string)($t->ai_lang ?? ''));
      if (!$ok) return;
      // a staff member may have replied while Claude was thinking
      if (AiChat::paused(DB::table('chat_threads')->find($tid))) return;
      DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($txt, 0, 3500), 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>'sent', 'created_at'=>now(), 'updated_at'=>now()]);
      DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
    finally { if ($lock) \Illuminate\Support\Facades\Cache::forget($lock); }
  }
  /** Name / photo from the Graph API (best effort). */
  public static function profile(string $ch, string $id, ?string $ref = null): array {
    $tok = self::pageTok($ch, $ref); if (!$tok) return [];
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
        if ($att && $attType === 'audio') { $out[] = ['type'=>'audio', 'audio'=>['link'=>$att]]; if ($body !== '') $out[] = ['type'=>'text', 'text'=>['body'=>$body, 'preview_url'=>true]]; } // WhatsApp audio cannot carry a caption
        elseif ($att) $out[] = $attType === 'image' ? ['type'=>'image', 'image'=>['link'=>$att, 'caption'=>mb_substr($body, 0, 900)]] : ['type'=>'document', 'document'=>['link'=>$att, 'caption'=>mb_substr($body, 0, 900)]];
        else {
          // WhatsApp shows a picture card for only ONE link per message: when the text holds several links, send each product line (name, price, link) as its own message so every link shows its photo preview.
          $lines = preg_split('/\R/u', $body); $chunks = []; $buf = []; $nl = 0;
          foreach ($lines as $ln) { if (preg_match('#https?://\S+#u', $ln)) $nl++; }
          if ($nl >= 2 && $nl <= 6) {
            foreach ($lines as $ln) {
              if (preg_match('#https?://\S+#u', $ln)) { if ($buf) { $pre = trim(implode("\n", $buf)); if ($pre !== '') $chunks[] = $pre; $buf = []; } $chunks[] = trim($ln); }
              else $buf[] = $ln;
            }
            if ($buf) { $pre = trim(implode("\n", $buf)); if ($pre !== '') $chunks[] = $pre; }
          } else $chunks[] = $body;
          foreach ($chunks as $c) $out[] = ['type'=>'text', 'text'=>['body'=>$c, 'preview_url'=>true]];
        }
        foreach ($out as $k => $m) {
          if ($k > 0) usleep(400000);
          $r = Http::timeout(15)->withToken($tok)->post(self::API.'/'.$pid.'/messages', ['messaging_product'=>'whatsapp', 'to'=>$to] + $m);
          if (!$r->ok()) return [false, self::err($r)];
        }
        return [true, null];
      }
      $tok = self::pageTok($t->channel, $t->page_ref ?? null);
      if (!$tok) return [false, ucfirst($t->channel).' is not connected (Chat Settings).'];
      $msgs = [];
      if ($att) $msgs[] = ['attachment'=>['type'=>in_array($attType, ['image', 'audio'], true) ? $attType : 'file', 'payload'=>['url'=>$att, 'is_reusable'=>true]]];
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
        'status'=>$t->status, 'awaiting'=>(bool)($t->awaiting_reply ?? false) && $t->status !== 'resolved', 'unread'=>!empty($t->awaiting_reply) ? (int)$t->unread : 0,
        'replied'=>empty($t->awaiting_reply) && !empty($t->last_successful_agent_reply_at) && !empty($t->last_customer_message_at) && $t->last_successful_agent_reply_at >= $t->last_customer_message_at,
        'stale'=>empty($t->awaiting_reply) && ($t->last_dir ?? null) === 'in' && $t->status !== 'resolved' && !empty($t->last_at) && \Carbon\Carbon::parse($t->last_at)->lt(now()->subDays(7)), 'last'=>$t->last_message, 'last_dir'=>$t->last_dir, 'last_at'=>$t->last_at ? \Carbon\Carbon::parse($t->last_at)->toIso8601String() : null,
        'ai'=>!($t->ai_paused ?? false), 'ai_lang'=>$t->ai_lang ?? null, 'ai_detected'=>$t->ai_detected ?? null, 'assigned_to'=>$t->assigned_to, 'assigned_name'=>$agents[$t->assigned_to] ?? null, 'customer_id'=>$t->customer_id,
        'cat'=>$n > 0 && $i ? $i['cat'] : 'New', 'orders'=>$n, 'risk'=>$n > 0 && $i ? $i['risk'] : 'Low',
        'intent'=>$t->intent ?? null, 'mood'=>$t->mood ?? null, 'priority'=>$t->priority ?? 'normal', 'needs_human'=>(bool)($t->needs_human ?? false),
        'handoff_at'=>!empty($t->handoff_at) ? \Carbon\Carbon::parse($t->handoff_at)->toIso8601String() : null, 'handoff_reason'=>$t->handoff_reason ?? null, 'ai_summary'=>$t->ai_summary ?? null];
    })->all();
  }
  /** Number of customers currently waiting for a staff reply (not a message count, and never reduced by opening a chat). */
  public static function unreadTotal(): int { try { return (int)DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->count(); } catch (\Throwable $e) { return 0; } }
  /** A staff member's reply was accepted by the platform: customer messages that arrived up to $t0 are answered; anything newer stays waiting. */
  public static function replied(int $tid, $t0, bool $echo = false): void {
    $left = (int)DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->where('created_at', '>', $t0)->count();
    DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->whereNull('read_at')->where('created_at', '<=', $t0)->update(['read_at'=>now()]);
    DB::table('chat_threads')->where('id', $tid)->update(['unread'=>$left, 'awaiting_reply'=>$left > 0, 'last_successful_agent_reply_at'=>now(), 'updated_at'=>now()]);
  }
  public static function productUrl(object $p): string { return url(\App\Http\Controllers\StoreController::url($p)); }
  public static function invoiceUrl(string $invoice): string { return URL::signedRoute('order.invoice', ['invoice'=>$invoice]); }
}
