<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
/** Unified customer chat: Facebook Messenger, Instagram DM, WhatsApp Business and website live chat. */
class Chat {
  const CH = ['facebook'=>'Facebook Messenger', 'instagram'=>'Instagram', 'whatsapp'=>'WhatsApp', 'web'=>'Website Chat', 'sms'=>'SMS'];
  const API = 'https://graph.facebook.com/v19.0';
  public static function phone(?string $p): ?string {
    $d = preg_replace('/\D/', '', (string)$p); if ($d === '') return null;
    if (str_starts_with($d, '880') && strlen($d) === 13) return '0'.substr($d, 3);
    if (strlen($d) === 10 && $d[0] === '1') return '0'.$d;
    return $d;
  }
  public static function sec(string $k): ?string { return Sms::secret($k); }
  /** WhatsApp credentials for a conversation: an extra connected number (thread page_ref = its phone id) or the original single number. Returns [token, phoneId]. */
  public static function waCred(?string $ref): array {
    try {
      if ($ref && $ref !== self::sec('chat_wa_phone_id') && ($n = DB::table('chat_wa_numbers')->where('phone_id', $ref)->first()) && $n->token) return [\Illuminate\Support\Facades\Crypt::decryptString($n->token), (string)$n->phone_id];
    } catch (\Throwable $e) {}
    return [self::sec('chat_wa_token'), self::sec('chat_wa_phone_id')];
  }
  /** Token for the Page / Instagram account a conversation belongs to (Facebook Login connections), else the single saved token. */
  public static function pageTok(string $ch, ?string $ref): ?string {
    try {
      $p = $ref ? DB::table('chat_pages')->where('page_id', $ref)->orWhere('ig_id', $ref)->first() : null;
      if (!$p && !$ref && DB::table('chat_pages')->count() === 1) $p = DB::table('chat_pages')->first();
      if ($p && $p->token) return \Illuminate\Support\Facades\Crypt::decryptString($p->token);
      if ($ch === 'instagram' && $ref) { $j = json_decode((string)self::sec('chat_ig_accounts'), true); foreach ((is_array($j) ? $j : []) as $a) if ((string)($a['id'] ?? '') === (string)$ref && !empty($a['token'])) return \Illuminate\Support\Facades\Crypt::decryptString($a['token']); }
    } catch (\Throwable $e) {}
    return self::sec($ch === 'instagram' && self::sec('chat_ig_token') ? 'chat_ig_token' : 'chat_fb_token');
  }
  /** Product cards (photo, name, price, link) for every store product link in a message, so staff see what the customer sees. */
  public static function productCards(?string $body): array {
    try {
      if (!$body || stripos($body, 'dutyfreebd.com/') === false) return [];
      if (!preg_match_all('#https?://(?:www\.)?dutyfreebd\.com/[^\s]+/([a-z0-9\-]+)/?(?=\s|$)#iu', $body, $m, PREG_SET_ORDER)) return [];
      $urls = []; foreach ($m as $x) { $urls[strtolower($x[1])] = rtrim($x[0], '/'); } $urls = array_slice($urls, 0, 6, true);
      $rows = DB::table('products')->where(fn($q) => $q->whereIn('slug', array_keys($urls))->orWhereIn('path_slug', array_keys($urls)))->get(['name', 'price', 'offer_price', 'image', 'slug', 'path_slug']);
      $out = [];
      foreach ($urls as $slug => $u) {
        $p = $rows->first(fn($r) => strtolower((string)$r->slug) === $slug || strtolower((string)$r->path_slug) === $slug); if (!$p) continue;
        $img = strtok((string)$p->image, '?'); if ($img !== '' && str_starts_with($img, '/')) $img = 'https://dutyfreebd.com'.$img;
        $out[] = ['name'=>$p->name, 'price'=>number_format((float)($p->offer_price ?: $p->price)), 'image'=>$img ?: null, 'url'=>$u];
      }
      return $out;
    } catch (\Throwable $e) { return []; }
  }
  /** Store one incoming message and (re)open its thread. Returns the thread, or null for a duplicate. */
  public static function ingest(string $ch, string $ext, ?string $name, string $body, ?string $att = null, ?string $mid = null, array $meta = []): ?object {
    if ($mid && DB::table('chat_messages')->where('ext_id', $mid)->exists()) return null;
    $t = DB::table('chat_threads')->where(['channel'=>$ch, 'external_id'=>$ext])->first();
    $phone = $ch === 'whatsapp' ? self::phone($ext) : self::phone($meta['phone'] ?? null);
    if (!$t) {
      $prof = in_array($ch, ['facebook', 'instagram']) ? self::profile($ch, $ext, $meta['page_ref'] ?? null) : [];
      $cust = $phone ? DB::table('customers')->where('phone', $phone)->orderBy('id')->first() : null;
      // two events for a brand-new customer at the same moment: the second insert hits the unique (channel, external_id) key and simply uses the conversation the first one created
      try {
        $id = DB::table('chat_threads')->insertGetId(['channel'=>$ch, 'external_id'=>$ext, 'customer_id'=>$cust->id ?? null,
          'name'=>$name ?: ($prof['name'] ?? null) ?: ($cust->name ?? null) ?: self::CH[$ch].' user', 'phone'=>$phone, 'username'=>$meta['username'] ?? ($prof['username'] ?? null),
          'avatar'=>$prof['avatar'] ?? null, 'page_ref'=>($meta['page_ref'] ?? null) ?: null, 'status'=>'new', 'unread'=>0, 'created_at'=>now(), 'updated_at'=>now()]);
      } catch (\Illuminate\Database\UniqueConstraintViolationException $e) { $id = (int)DB::table('chat_threads')->where(['channel'=>$ch, 'external_id'=>$ext])->value('id'); }
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
    // ext_id is unique: when the platform delivers the same event twice at the same moment, only the first insert succeeds and the second is dropped here (no second answer).
    $ins = DB::table('chat_messages')->insertOrIgnore(['thread_id'=>$t->id, 'dir'=>'in', 'kind'=>$att ? 'file' : 'text', 'body'=>mb_substr($body, 0, 4000), 'attachment_url'=>$att,
      'attachment_type'=>$att ? ($meta['att_type'] ?? 'image') : null, 'ext_id'=>$mid, 'created_at'=>now(), 'updated_at'=>now()]);
    if ($mid && !$ins) return null;
    $st = $t->status === 'resolved' ? 'new' : ($t->status === 'waiting' ? 'open' : $t->status);
    // Every new customer message puts the conversation back into "waiting for a reply".
    $prev = $body !== '' ? $body : ($att ? (($meta['att_type'] ?? 'image') === 'audio' ? '🎤 Voice message' : '[attachment]') : '');
    DB::table('chat_threads')->where('id', $t->id)->update(['unread'=>DB::raw('unread + 1'), 'awaiting_reply'=>true, 'last_customer_message_at'=>now(), 'last_message'=>mb_substr($prev, 0, 300),
      'last_dir'=>'in', 'last_at'=>now(), 'status'=>$st, 'updated_at'=>now()] + self::fuClear() // the customer came back: the follow-up is over, the normal Awaiting rule takes it from here
      + (ChatOwner::ready() ? ['awaiting_since'=>!empty($t->awaiting_reply) && !empty($t->awaiting_since) ? $t->awaiting_since : now()] : []));
    self::reportCheck((int)$t->id, (string)$body);
    $fresh = DB::table('chat_threads')->find($t->id);
    if (ChatOwner::ready()) { ChatOwner::onCustomerMessage($t, $fresh); $fresh = DB::table('chat_threads')->find($t->id); } // reopened chat starts without an owner; a chat the AI will not answer goes to a representative
    try { ChatRules::onCustomer($fresh, (string)$body); $fresh = DB::table('chat_threads')->find($t->id); } catch (\Throwable $e) { report($e); } // hand-off words (agent / refund / cancel / payment / wrong product)
    AiMemory::onCustomer($fresh, $body, $att);
    return $fresh;
  }
  /** Record a WhatsApp message sent by the system (order confirmation, template, campaign) in the customer's chat, so staff see exactly what the customer was told. */
  /** Sent by the system itself (not typed by a person, not the chat AI): order confirmation / order update / campaign messages. */
  public static function isAuto(?string $by): bool { $by = (string)$by; return str_contains($by, '(auto)') || str_starts_with($by, 'Campaign:'); }
  public static function logOut(string $wa, string $body, string $by, ?string $mid = null, string $kind = 'text', string $status = 'sent', ?string $err = null, ?string $att = null, ?string $attType = null): void {
    try {
      $wa = preg_replace('/\D/', '', $wa); if ($wa === '' || (trim($body) === '' && !$att)) return;
      if ($mid && DB::table('chat_messages')->where('ext_id', $mid)->exists()) return;
      $t = DB::table('chat_threads')->where(['channel'=>'whatsapp', 'external_id'=>$wa])->first();
      $phone = self::phone($wa); $auto = self::isAuto($by);
      if (!$t) {
        $cust = $phone ? DB::table('customers')->where('phone', $phone)->orderBy('id')->first() : null;
        $id = DB::table('chat_threads')->insertGetId(['channel'=>'whatsapp', 'external_id'=>$wa, 'customer_id'=>$cust->id ?? null, 'name'=>($cust->name ?? null) ?: ($phone ?: 'WhatsApp user'), 'phone'=>$phone,
          'status'=>$auto ? 'resolved' : 'open', 'unread'=>0, 'created_at'=>now(), 'updated_at'=>now()]); // an automatic message alone never opens a conversation in the working list
        $t = DB::table('chat_threads')->find($id);
      }
      DB::table('chat_messages')->insert(['thread_id'=>$t->id, 'dir'=>'out', 'kind'=>$att ? 'file' : $kind, 'body'=>mb_substr($body, 0, 4000), 'attachment_url'=>$att, 'attachment_type'=>$att ? $attType : null, 'ext_id'=>$mid, 'user_name'=>$by, 'status'=>$status, 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
      // Automatic messages (order confirmation / update, campaigns) stay in the thread's history and on the Automatic Messages page, but they do not move or change the conversation in the list.
      if (!$auto) DB::table('chat_threads')->where('id', $t->id)->update(['last_message'=>mb_substr(trim($body) !== '' ? $body : ($attType === 'audio' ? '🎤 Voice message' : '[attachment]'), 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
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
  /** Facebook / Instagram photos arrive as short-lived Meta links. Keep our own copy so the photo stays visible in the inbox and the AI can actually look at it.
   *  Only Meta's own media hosts are fetched, and only real images (checked by content) are stored. */
  public static function mirrorImage(string $url): ?string {
    try {
      $h = strtolower((string)parse_url($url, PHP_URL_HOST)); if (!str_starts_with($url, 'https://') || !preg_match('/(^|\.)(fbcdn\.net|fbsbx\.com|cdninstagram\.com|facebook\.com)$/', $h)) return null;
      $r = Http::timeout(20)->get($url); if (!$r->ok()) return null;
      $b = $r->body(); if ($b === '' || strlen($b) > 12 * 1048576) return null;
      $ext = str_starts_with($b, "\xFF\xD8\xFF") ? 'jpg' : (str_starts_with($b, "\x89PNG") ? 'png' : (str_starts_with($b, 'GIF8') ? 'gif' : (substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP' ? 'webp' : null))); if (!$ext) return null;
      $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $name = 'in-'.date('ymd').'-'.bin2hex(random_bytes(6)).'.'.$ext; file_put_contents($dir.'/'.$name, $b);
      return url('/uploads/chat/'.$name);
    } catch (\Throwable $e) { return null; }
  }
  /** A stored chat photo prepared for the AI: [mime, base64] or null. Large phone photos are scaled down (longest side 1568px, JPEG) instead of being skipped, so the AI always gets to see them. */
  public static function visionData(string $path): ?array {
    try {
      if (!is_file($path)) return null; $size = (int)filesize($path); if ($size < 1 || $size > 16 * 1048576) return null;
      $mt = (string)@mime_content_type($path); if (!in_array($mt, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return null;
      $dim = @getimagesize($path); $big = $dim ? max((int)$dim[0], (int)$dim[1]) : 0;
      if ($size <= 1500000 && $big > 0 && $big <= 1568) return [$mt, base64_encode((string)file_get_contents($path))];
      if (!function_exists('imagecreatefromstring') || !$dim || $dim[0] * $dim[1] > 60000000) return $size <= 3500000 ? [$mt, base64_encode((string)file_get_contents($path))] : null;
      $im = @imagecreatefromstring((string)file_get_contents($path)); if (!$im) return $size <= 3500000 ? [$mt, base64_encode((string)file_get_contents($path))] : null;
      $k = min(1, 1568 / max(1, $big)); $w = max(1, (int)round($dim[0] * $k)); $h = max(1, (int)round($dim[1] * $k));
      $out = imagecreatetruecolor($w, $h); imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $dim[0], $dim[1]);
      ob_start(); imagejpeg($out, null, 85); $jpg = (string)ob_get_clean(); imagedestroy($im); imagedestroy($out);
      return $jpg !== '' ? ['image/jpeg', base64_encode($jpg)] : null;
    } catch (\Throwable $e) { return null; }
  }
  /** Speech-to-text for one stored incoming voice message. Best effort, runs after the webhook response; the result is shown under the player as an AI transcript. */
  public static function transcribe(int $mid): void {
    try {
      $m = DB::table('chat_messages')->find($mid);
      if (!$m || $m->dir !== 'in' || $m->attachment_type !== 'audio' || !empty($m->transcript) || (!VoiceChat::key() && !VoiceChat::scribeKey())) return;
      $d = 'inbox.stt.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($d, 0, 90000); if (\Illuminate\Support\Facades\Cache::increment($d) > 800) return;
      $path = public_path('uploads/chat/'.basename((string)parse_url((string)$m->attachment_url, PHP_URL_PATH)));
      if (!is_file($path) || filesize($path) > 20 * 1048576) return;
      $raw = (string)file_get_contents($path); $fmt = self::audioExt($raw); $t = null;
      // 1) ElevenLabs Scribe when its key is saved (owner's chosen engine): the file as it arrived, any format or length.
      if (VoiceChat::scribeKey()) [$t] = VoiceChat::scribe($raw, (int)$m->thread_id, (string)($fmt ?: pathinfo($path, PATHINFO_EXTENSION)));
      // 2) Google's short-audio API: Ogg/WebM Opus up to about a minute. Both common sample rates are tried, also when the first one hears nothing.
      $b = ''; if ($t === null && VoiceChat::key()) { $b = $raw; if (!in_array($fmt, ['ogg', 'webm'], true)) $b = (string)self::toOpus($path); }
      if ($t === null && $b !== '' && strlen($b) <= 950000) {
        [$t] = VoiceChat::transcribe($b, (int)$m->thread_id, 16000);
        if ($t === null) [$t] = VoiceChat::transcribe($b, (int)$m->thread_id, 48000);
      }
      // Second engine for everything Google could not understand or take (long clips, MP4/AAC without a converter): the AI then answers instead of stopping for staff.
      if ($t === null) [$t] = VoiceChat::transcribeAlt($raw, (int)$m->thread_id, (string)($fmt ?: pathinfo($path, PATHINFO_EXTENSION)));
      if ($t === null) return;
      $t = mb_substr(trim(strip_tags($t)), 0, 1000); if ($t === '') return;
      DB::table('chat_messages')->where('id', $mid)->update(['transcript'=>$t, 'input_type'=>'voice', 'updated_at'=>now()]);
      self::reportCheck((int)$m->thread_id, (string)$t);
      try { if ($th = DB::table('chat_threads')->find($m->thread_id)) AiMemory::onCustomer($th, $t, null); } catch (\Throwable $e) {}
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
  public static function waMedia(string $mediaId, ?string $useTok = null): ?array {
    try {
      $tok = $useTok ?: self::sec('chat_wa_token'); if (!$tok || $mediaId === '') return null;
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
  /** Staff-only help: the customer sent a product photo while a human owns the chat. The AI identifies it, finds matching in-stock products and stores a ready reply the agent can send with one click. Never sent to the customer. */
  public static function imageHint(int $tid, int $mid): void {
    try {
      if (!AiChat::chatOn()) return;
      $m = DB::table('chat_messages')->where('id', $mid)->first(); if (!$m || $m->dir !== 'in' || $m->attachment_type !== 'image' || !$m->attachment_url) return;
      $ip = public_path('uploads/chat/'.basename((string)parse_url((string)$m->attachment_url, PHP_URL_PATH)));
      $vd = self::visionData($ip); if (!$vd) return; [$mt, $b64] = $vd;
      $key = Sms::secret('ai_key'); if (!$key) return;
      $n = 'ai.hint.n.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($n, 0, 90000); if (\Illuminate\Support\Facades\Cache::increment($n) > 400) return;
      $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(25)->post('https://api.anthropic.com/v1/messages', ['model'=>AiChat::model(), 'max_tokens'=>200,
        'system'=>'You look at a photo a customer sent to a Bangladeshi duty-free / cosmetics / perfume / cigarette store. Reply with ONE line only: the brand and product name exactly as printed on the pack, then 3-6 short English search keywords separated by commas. Format: "Brand Product | keyword, keyword, keyword". If it is not a product photo, reply: NONE',
        'messages'=>[['role'=>'user', 'content'=>[['type'=>'image', 'source'=>['type'=>'base64', 'media_type'=>$mt, 'data'=>$b64]], ['type'=>'text', 'text'=>trim((string)$m->body) ?: 'Which product is this?']]]]]);
      if (!$res->ok()) return;
      $line = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode(' '));
      if ($line === '' || stripos($line, 'NONE') === 0) { \Illuminate\Support\Facades\Cache::put('ai.hint.'.$tid, ['mid'=>$mid, 'found'=>'Not a clear product photo', 'items'=>[], 'reply'=>''], 43200); return; }
      [$nm, $kw] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
      $words = array_values(array_unique(array_filter(preg_split('/[\s,]+/u', mb_strtolower($nm.' '.$kw)), fn($w) => mb_strlen($w) >= 3)));
      $items = [];
      if ($words) {
        $rows = StockGuard::listable(DB::table('products')->whereNull('parent_product_id')->where('published', true))->where(function ($w) use ($words) { foreach (array_slice($words, 0, 8) as $t) { $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $t).'%'; $w->orWhere('name', 'ilike', $like); } })->limit(60)->get(['id', 'name', 'price', 'offer_price', 'stock', 'category_id', 'path_slug', 'sub', 'sub_sub']);
        $nmw = array_values(array_filter(preg_split('/[\s,]+/u', mb_strtolower($nm)), fn($w) => mb_strlen($w) >= 3));
        $scored = $rows->map(function ($p) use ($nmw, $words) { $l = mb_strtolower($p->name); $sc = 0; foreach ($nmw as $w) if (str_contains($l, $w)) $sc += 3; foreach ($words as $w) if (str_contains($l, $w)) $sc += 1; return [$sc, $p]; })->sortByDesc(fn($x) => $x[0])->take(3);
        foreach ($scored as [$sc, $p]) { if ($sc <= 0) continue; $pr = (float)($p->offer_price ?: $p->price); $items[] = ['name'=>$p->name, 'price'=>number_format($pr), 'link'=>url(\App\Http\Controllers\StoreController::url($p))]; }
      }
      $reply = $items ? implode("\n", array_map(fn($i) => $i['name'].', '.$i['price'].' TK: '.$i['link'], $items)) : '';
      \Illuminate\Support\Facades\Cache::put('ai.hint.'.$tid, ['mid'=>$mid, 'found'=>$nm, 'items'=>$items, 'reply'=>$reply], 43200);
    } catch (\Throwable $e) { report($e); }
  }

  /** A short, clear goodbye / thank-you from the customer (Bangla, English, Banglish). */
  public static function isFarewell(string $b, bool $asked = false): bool {
    $b = trim($b); if ($b === '' || mb_strlen($b) > 80) return false;
    if ($asked && preg_match('/^(না|নাই|নেই|no|nope|nah|na|nai|না,? ?(ধন্যবাদ|থ্যাংকস|thanks?))[\s.!]*$/iu', $b)) return true;  // a plain "no" only answers our "any other question?" line
    return (bool)preg_match('/^(ok|okay|okk?|thanks?|thank you|thank u|thx|tnx|ty|bye|good ?night|allah hafez|khoda hafez|dhonnobad|dhonnyobad|ধন্যবাদ|থ্যাংকস|থ্যাঙ্ক ইউ|শুভ রাত্রি|আল্লাহ হাফেজ|খোদা হাফেজ|ঠিক আছে|আচ্ছা|জি|জী|ওকে|👍|🙏|❤️?)([\s.!,]*(thanks?|thank you|thx|ধন্যবাদ|ok|okay|ওকে|bye|বাই|ভালো থাকবেন|আর কিছু (লাগবে|জানার) না|আর কিছু না|nothing else|that.s all|all good|🙏|👍))*[\s.!]*$/iu', $b)
      || (bool)preg_match('/(আর কিছু (লাগবে|জানার|দরকার) না|আর কিছু না|কিছু লাগবে না|nothing else|that.?s all|no more (question|help))/iu', $b)
      // more ways our customers really end a chat (taken from the inbox, 14 Nov 2026): thanks with "bhai", "will let you know later", religious thanks, "take care". A message with a question mark or a digit is never a goodbye.
      || (!preg_match('/[?？\d০-৯]/u', $b) && (bool)preg_match('/^(ok(ay)?|ওকে|ঠিক আছে|thik ?(ache|ase|achhe)|tik ?(ache|ase)|accha|acha|achha|আচ্ছা|hmm+|হুম+|ji|jee|জি|জী)?[\s,.!]*(vai|bhai|bro|ভাই|ভাইয়া|sir|স্যার|apu|আপু)?[\s,.!]*(ami |আমি )?(thik ?(ache|ase|achhe)|tik ?(ache|ase)|accha|acha|achha|thanks?( a lot| so much)?|thank ?(you|u)( so much| very much)?|many thanks|tnx|thx|ধন্যবাদ|অনেক ধন্যবাদ|থ্যাংকস|থ্যাংক ইউ|dhonnobad|donnobad|jazakallah( khair(an)?)?|জাযাকাল্লাহ( খাইরান)?|জাজাকাল্লাহ( খায়ের)?|shukriya|শুকরিয়া|alhamdulillah|আলহামদুলিল্লাহ|welcome|ওয়েলকাম|(pore|kalke|kal|ektu pore) ?(janabo|janacchi|bolbo|nibo|order korbo)|janabo|jnabo|(পরে|কালকে|কাল|একটু পরে) ?(জানাবো|জানাব|জানাচ্ছি|বলবো|নিবো|নেব|অর্ডার করবো)|জানাবো|জানাব|dekhi|দেখি|dekhtesi|lagbe na|লাগবে না|dorkar nai|দরকার নাই|দরকার নেই|valo thak(ben|o)|bhalo thak(ben|o)|ভালো থাকবেন|ভাল থাকবেন|allah hafez|আল্লাহ হাফেজ|khoda hafez|bye+|বাই|tata|আসি|আসসালামু আলাইকুম ভালো থাকবেন)[\s,.!]*(vai|bhai|bro|ভাই|ভাইয়া|sir|স্যার|apu|আপু)?[\s.!🙏👍❤️😊🙂]*$/iu', $b));
  }
  /** Worker tick: a chat where we asked "any other question?" and the customer stayed silent for the set time (Chat Settings, default 30 min) gets the short closing line and is resolved. */
  public static function closeIdleTick(): void {
    if (Sms::secret('ai_auto_close') === '0' || !AiChat::chatOn()) return;
    $min = max(1, min(720, (int)(Sms::secret('ai_close_after_min') ?: 30)));
    $rows = self::fuKeep(DB::table('chat_threads'))->where('last_dir', 'out')->whereIn('status', ['new', 'open', 'waiting'])->where('awaiting_reply', false)
      ->where('last_at', '<=', now()->subMinutes($min))->where('last_at', '>=', now()->subHours(20))->orderBy('last_at')->limit(15)->get();
    foreach ($rows as $t) {
      $k = 'ai.idleclose.'.$t->id.'.'.substr((string)$t->last_at, 0, 16);
      if (!\Illuminate\Support\Facades\Cache::add($k, 1, 86400)) continue; // once per "any other question?" line, even if sending failed
      self::autoClose($t, true);
    }
  }
  /** Night / AI-hours auto-resolve: after the customer's clear goodbye and when nothing is pending, send one short courtesy line, then resolve the chat (no unread / Awaiting Reply left). A failed send changes nothing. */
  public static function autoClose(object $t, bool $idle = false): bool {
    try {
      if (Sms::secret('ai_auto_close') === '0') return false;
      $tid = (int)$t->id; $t = DB::table('chat_threads')->find($tid); if (!$t || $t->status === 'resolved') return false;
      // never close while anything is open: hand-off / manager review, needs-human, complaint, refund, cancel, order change, payment, human request, order request
      if (!empty($t->needs_human) || !empty($t->handoff_at) || (!empty($t->reported_at) && empty($t->report_solved_at)) || AiChat::paused($t)) return false;
      if (in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'human_request', 'payment', 'order_request'], true)) return false;
      if (DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists()) return false;
      // only after we actually helped: at least one reply of ours exists and the last message is the customer's goodbye
      if (!DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->where('status', '!=', 'failed')->whereRaw("coalesce(user_name, '') not like '%(auto)%'")->exists()) return false; // an automatic notice is not a reply of ours
      $li = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['id', 'dir', 'body', 'created_at']);
      if (!$li) return false;
      if ($idle) { // the customer went quiet after our "any other question?" line
        if ($li->dir !== 'out' || empty($li->is_ai) && !preg_match('/(আর কোনো প্রশ্ন|any other question|anything else)/iu', (string)$li->body)) return false;
        if (!preg_match('/(আর কোনো প্রশ্ন|any other question|anything else)/iu', (string)$li->body)) return false;
        $cb = (string)DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->orderByDesc('id')->value('body');
        $bn = AiChat::banglaOnly() ? true : (bool)preg_match('/[\x{0980}-\x{09FF}]/u', $cb !== '' ? $cb : (string)$li->body);
      } else {
        if ($li->dir !== 'in') return false;
        $bn = AiChat::banglaOnly() ? true : (bool)preg_match('/[\x{0980}-\x{09FF}]/u', (string)$li->body);
      }
      $txt = $bn ? 'স্যার, যদি কোনো কিছুর প্রয়োজন হয় আমাকে জানাবেন। ধন্যবাদ। 🙏' : 'Sir, please let me know if you need anything. Thank you. 🙏';
      [$sent, $err] = self::deliver($t, $txt);
      DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>$txt, 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
      if (!$sent) return false; // sending failed: stays unread / Awaiting Reply
      self::replied($tid, $idle ? now() : $li->created_at);
      DB::table('chat_threads')->where('id', $tid)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
      return true;
    } catch (\Throwable $e) { report($e); return false; }
  }
  /** Messages > Human Answer: chats that need a person - an unanswered voice message, a hand-off / needs-human flag, or a critical open request (complaint, refund, cancel, order change, payment, human request, order request). Owner rule 11 Nov 2026. */
  public static function humanScope($q) {
    return $q->where('awaiting_reply', true)->where('status', '!=', 'resolved')->where(function ($w) {
      $w->where('needs_human', true)->orWhereNotNull('handoff_at')
        ->orWhereIn('intent', ['complaint', 'refund', 'cancel', 'change_order', 'human_request', 'payment', 'order_request'])
        ->orWhere('last_message', 'like', '🎤%')
        ->orWhereRaw("exists (select 1 from chat_messages m where m.thread_id = chat_threads.id and m.dir = 'in' and m.attachment_type = 'audio' and m.id > coalesce((select max(o.id) from chat_messages o where o.thread_id = chat_threads.id and o.dir = 'out' and o.status <> 'failed' and coalesce(o.user_name, '') not like '%(auto)%'), 0))");
    });
  }
  /** Set only by staleTick around one aiReply call: lets the AI answer inside staff hours after the wait limit. */
  public static bool $force = false;
  /** Worker tick (owner rule 14 Nov 2026): no customer is left without an answer. When a customer has waited longer than the limit (Chat Settings, default 30 min) with no reply:
   *  - an ordinary question is answered by the AI, even inside staff hours;
   *  - a chat that needs a person (hand-off, complaint, refund, cancel, payment, order request, AI switched off for it) gets ONE short holding message - the AI decides nothing there;
   *  - the chat moves from an agent who has gone quiet to one who is replying right now, and a staff-only note records what happened;
   *  - the owner's Issues Center gets one alert listing who is waiting.
   * A holding message never clears Awaiting Reply and never stops the waiting timer: only a person's reply does. Each customer message triggers this once. Chats that were already waiting when the rule was switched on are left to staff (no mass send). */
  public static function staleTick(): void {
    if (Sms::secret('chat_stale_on') === '0') { try { DeployHealth::resolve('chat.stale', 'System (rule switched off)'); } catch (\Throwable $e) {} return; }
    if (!\Illuminate\Support\Facades\Cache::add('chat.stale.lock', 1, 50)) return;
    $min = max(5, min(240, (int)(Sms::secret('chat_stale_min') ?: 30)));
    $from = Sms::secret('chat_stale_from'); if (!$from) { Sms::setSecret('chat_stale_from', now()->toDateTimeString()); return; }
    $cut = now()->subMinutes($min); $start = \Carbon\Carbon::parse($from)->max(now()->subHours(22));
    $cand = DB::table('chat_threads')->whereIn('channel', ['web', 'whatsapp', 'facebook', 'instagram'])->where('awaiting_reply', true)->where('status', '!=', 'resolved')->where('last_at', '>=', $start)->orderBy('last_at')->limit(60)->get();
    if ($cand->isEmpty()) { try { DeployHealth::resolve('chat.stale', 'System (nobody waiting)'); } catch (\Throwable $e) {} return; }
    $ids = implode(',', $cand->pluck('id')->map(fn($i) => (int)$i)->all());
    // waiting since the first customer message after our last delivered message of any kind (decides when to act) ...
    $any = collect(DB::select("select m.thread_id, min(m.created_at) since, max(m.id) mid from chat_messages m where m.thread_id in ($ids) and m.dir = 'in' and m.id > coalesce((select max(o.id) from chat_messages o where o.thread_id = m.thread_id and o.dir = 'out' and o.status <> 'failed' and coalesce(o.user_name, '') not like '%(auto)%' and coalesce(o.user_name, '') not like 'Campaign:%'), 0) group by m.thread_id"))->keyBy('thread_id'); // an automatic order / campaign message is not an answer: it must not restart the customer's waiting time
    // ... and since the last reply by a person (what the alert reports)
    $hum = collect(DB::select("select m.thread_id, min(m.created_at) since from chat_messages m where m.thread_id in ($ids) and m.dir = 'in' and m.id > coalesce((select max(o.id) from chat_messages o where o.thread_id = m.thread_id and o.dir = 'out' and coalesce(o.is_ai, false) = false and o.status <> 'failed'), 0) group by m.thread_id"))->pluck('since', 'thread_id');
    // A plain thank-you / goodbye after our reply asks for nothing: no holding message and no alert for it (first live case, 14 Nov 2026: "ধন্যবাদ ভাই").
    $bye = [];
    try { $mids = $any->pluck('mid')->map(fn($i) => (int)$i)->filter()->all();
      if ($mids) foreach (DB::table('chat_messages')->whereIn('id', $mids)->get(['thread_id', 'body', 'attachment_type', 'attachment_url']) as $m) if (empty($m->attachment_url) && self::isFarewell((string)$m->body)) $bye[(int)$m->thread_id] = true;
    } catch (\Throwable $e) {}
    $done = 0;
    foreach ($cand as $t) {
      if ($done >= 4) break; $a = $any[$t->id] ?? null; if (!$a || ($t->last_dir ?? '') !== 'in' || isset($bye[(int)$t->id])) continue;
      $since = \Carbon\Carbon::parse($a->since); if ($since->gt($cut) || $since->lt($start)) continue;
      if (!\Illuminate\Support\Facades\Cache::add('chat.stale.'.$t->id.'.'.(int)$a->mid, 1, 172800)) continue;
      $done++;
      try { self::staleOne($t, $min); } catch (\Throwable $e) { report($e); }
    }
    // one alert for the owner: everybody who has had no reply from a person for longer than the limit
    try {
      $late = $cand->filter(fn($t) => !isset($bye[(int)$t->id]) && isset($hum[$t->id]) && \Carbon\Carbon::parse($hum[$t->id])->lte($cut) && \Carbon\Carbon::parse($hum[$t->id])->gte($start))->values();
      if ($late->isEmpty()) DeployHealth::resolve('chat.stale', 'System (everyone answered)');
      else { $ag = DB::table('users')->whereIn('id', $late->pluck('assigned_to')->filter()->unique())->pluck('name', 'id');
        $list = $late->take(6)->map(fn($t) => trim((string)($t->name ?: $t->phone ?: 'Customer')).' ('.(int)\Carbon\Carbon::parse($hum[$t->id])->diffInMinutes(now()).' min'.(!empty($t->assigned_to) && isset($ag[$t->assigned_to]) ? ', '.$ag[$t->assigned_to] : ', nobody assigned').')')->implode('; ');
        DeployHealth::raise('chat.stale', 'high', 'Messages', $late->count().' customer'.($late->count() === 1 ? '' : 's').' waiting over '.$min.' minutes for a staff reply', $list.($late->count() > 6 ? ' and '.($late->count() - 6).' more' : '').'.', 'No staff member replied within '.$min.' minutes. The AI answered what it safely could or sent a short holding message; these chats still need a person.', 'Open Messages > Late replies and answer them.'); }
    } catch (\Throwable $e) {}
  }
  /** One customer past the wait limit: reassign, let the AI answer or send the holding message, leave a staff-only note. */
  public static function staleOne(object $t, int $min): void {
    $tid = (int)$t->id; $what = []; $blame = !empty($t->assigned_to) ? (int)$t->assigned_to : 0;
    $handed = !empty($t->handoff_at); try { $handed = $handed || DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists(); } catch (\Throwable $e) {}
    $human = $handed || in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'human_request', 'payment'], true) || \Illuminate\Support\Facades\Cache::has('ai.manual_off.'.$tid);
    $aiWill = !$human && AiChat::chatOn() && in_array($t->channel, ['web', 'whatsapp', 'facebook'], true); // the AI will answer below: then the chat is released to the AI, not moved to another agent
    // 1) move the chat away from an agent who has not replied to anyone for the whole wait
    try {
      if (!empty($t->assigned_to) && !$aiWill) {
        $cur = DB::table('users')->find($t->assigned_to);
        $busy = $cur ? DB::table('chat_messages')->where('dir', 'out')->where('status', '<>', 'failed')->whereRaw('not coalesce(is_ai, false)')->where('user_name', $cur->name)->where('created_at', '>=', now()->subMinutes($min))->exists() : false;
        if (!$busy) {
          $act = DB::table('chat_messages')->where('dir', 'out')->where('status', '<>', 'failed')->whereRaw('not coalesce(is_ai, false)')->where('created_at', '>=', now()->subMinutes($min))->whereNotNull('user_name')->groupBy('user_name')->selectRaw('user_name, max(created_at) la')->orderByDesc('la')->pluck('user_name')->all();
          $to = $act ? DB::table('users')->whereIn('name', $act)->where('id', '!=', $t->assigned_to)->where('active', true)->get(['id', 'name'])->sortBy(fn($u) => array_search($u->name, $act))->first() : null;
          if (ChatOwner::ready()) { if (ChatOwner::system($tid, null, 'release', ($cur->name ?? 'The owner').' did not reply for '.$min.' minutes: back to the open list.')) $what[] = 'Taken from '.($cur->name ?? 'an agent').' (no reply for '.$min.' minutes) and opened to everyone: the first person who replies owns it.'; }
          elseif ($to && (bool)DB::table('chat_threads')->where('id', $tid)->update(['assigned_to'=>$to->id, 'updated_at'=>now()])) { $what[] = 'Moved from '.($cur->name ?? 'an agent').' to '.$to->name.', who is replying now.'; }
          else $what[] = ($cur->name ?? 'The assigned agent').' has not replied to anyone for '.$min.' minutes and no other agent is replying right now.';
        }
      }
    } catch (\Throwable $e) {}
    // 2) an ordinary question: the AI answers it now, even inside staff hours
    // a customer who wants to order is not left waiting: the AI takes the details and files an Order Request (a person creates the order)
    // Owner (14 Nov 2026): "needs a person" left behind by an earlier holding message must not keep the AI out for ever (live case: a customer waited 130 minutes because a holding message from the morning had marked the chat). Only a real hand-over, a sensitive subject or the manual OFF switch keeps the chat with staff.
    if (!$human && (!empty($t->needs_human) || !empty($t->ai_paused))) { try { DB::table('chat_threads')->where('id', $tid)->update(['needs_human'=>false, 'ai_paused'=>false, 'updated_at'=>now()]); } catch (\Throwable $e) {} }
    $answered = false;
    if (!$human && AiChat::chatOn() && in_array($t->channel, ['web', 'whatsapp', 'facebook'], true)) {
      if (!empty($t->assigned_to) && ChatOwner::ready() && ChatOwner::system($tid, null, 'release', 'No reply from the owner for '.$min.' minutes: the AI answers this customer.')) $what[] = 'Taken from '.ChatOwner::name((int)$t->assigned_to).' (no reply for '.$min.' minutes) and given to the AI.';
      self::$force = true; try { self::aiReply($tid); } catch (\Throwable $e) { report($e); } finally { self::$force = false; }
      $n = DB::table('chat_threads')->find($tid); $answered = $n && ($n->last_dir ?? '') === 'out';
      if ($answered) { \Illuminate\Support\Facades\Cache::put('ai.takeover.'.$tid, now()->timestamp, 43200); $what[] = 'No staff reply for '.$min.' minutes, so the AI answered the customer and keeps answering this chat until it is resolved or a staff member replies.'; }
    }
    // 3) otherwise one short holding message; the chat stays with a person
    if (!$answered) {
      $n = DB::table('chat_threads')->find($tid); if (!$n || $n->status === 'resolved' || ($n->last_dir ?? '') !== 'in') return;
      $lc = $n->last_customer_message_at ?? null; $open = $n->channel === 'web' || ($lc && \Carbon\Carbon::parse($lc)->gte(now()->subHours(23)));
      if ($open) {
        $cb = (string)DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->orderByDesc('id')->value('body');
        $bn = AiChat::banglaOnly() ? true : (bool)preg_match('/[\x{0980}-\x{09FF}]/u', $cb);
        $txt = trim((string)Sms::secret($bn ? 'chat_stale_text_bn' : 'chat_stale_text_en')) ?: ($bn ? 'স্যার, আপনার বার্তাটি আমরা পেয়েছি। আমাদের একজন প্রতিনিধি বিষয়টি দেখছেন এবং খুব শিগগিরই আপনাকে জানাবেন। একটু অপেক্ষা করার জন্য ধন্যবাদ। 🙏' : 'Sir, we have received your message. One of our team is looking into it and will reply to you very soon. Thank you for waiting. 🙏');
        [$sent, $err] = self::deliver($n, $txt);
        DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>$txt, 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
        // still Awaiting Reply and still waiting for a person: only last_* changes so the list shows what was sent
        $upd = ['needs_human'=>true, 'updated_at'=>now()]; if ($sent) $upd += ['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out'];
        DB::table('chat_threads')->where('id', $tid)->update($upd);
        $what[] = $sent ? 'No staff reply for '.$min.' minutes: a holding message was sent. This chat still needs a person.' : 'No staff reply for '.$min.' minutes. The holding message could not be sent ('.mb_substr((string)$err, 0, 80).').';
      } else { DB::table('chat_threads')->where('id', $tid)->update(['needs_human'=>true, 'updated_at'=>now()]); $what[] = 'No staff reply for '.$min.' minutes. Nothing could be sent: the 24-hour channel window is closed.'; }
    }
    // tell the staff too (popup on their screen + their My Day page): the agent the chat was with; nobody assigned = everyone who answers chats
    try {
      $cust = trim((string)($t->name ?: $t->phone ?: 'A customer'));
      // owner rule (14 Nov 2026): the warning goes to every staff member and manager, not only to the agent it was with; only that agent's row counts as their own late reply
      $to = DB::table('users')->where('active', true)->whereNotIn('role', ['super', 'admin'])->get(['id', 'name']);
      foreach ($to as $usr) DB::table('chat_wait_notices')->insert(['thread_id'=>$tid, 'user_id'=>$usr->id, 'user_name'=>$usr->name, 'customer'=>mb_substr($cust, 0, 160), 'wait_min'=>$min, 'assigned'=>$blame && (int)$usr->id === $blame, 'created_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) {}
    try { if ($what) DB::table('chat_notes')->insert(['thread_id'=>$tid, 'user_id'=>null, 'user_name'=>'Automation', 'body'=>implode(' ', $what), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    try { AiMemory::event($tid, null, 'ai', 'stale_wait_handled', null, ['min'=>$min, 'ai_answered'=>$answered]); } catch (\Throwable $e) {}
  }
  /** Worker tick (owner request 11 Nov 2026): the backlog of unanswered chats. The AI reads each one (voice is transcribed first); if it understands and answers, the chat is handled and closes by the normal rules; if it cannot (hand-off, complaint, unreadable voice, any reason) the chat is set aside for a person (Messages > Human Answer). */
  public static function backlogTick(): void {
    if (!AiChat::chatOn() || !AiSchedule::activeNow()) return;
    // voice messages that could not be heard earlier (speech-to-text was failing): hear them once more, then let the AI answer
    try {
      $vm = DB::table('chat_messages')->where('dir', 'in')->where('attachment_type', 'audio')->whereNull('transcript')->where('created_at', '>=', now()->subHours(22))->orderByDesc('id')->limit(3)->get(['id', 'thread_id']);
      foreach ($vm as $v) {
        if (!\Illuminate\Support\Facades\Cache::add('ai.stt.retry.'.$v->id, 1, 172800)) continue;
        self::transcribe((int)$v->id);
        $th = DB::table('chat_threads')->find($v->thread_id);
        if ($th && $th->last_dir === 'in' && $th->status !== 'resolved' && empty($th->handoff_at) && !empty(DB::table('chat_messages')->where('id', $v->id)->value('transcript'))) {
          DB::table('chat_threads')->where('id', $th->id)->update(['needs_human'=>false, 'updated_at'=>now()]);
          \Illuminate\Support\Facades\Cache::forget('ai.backlog.'.$th->id.'.'.(int)DB::table('chat_messages')->where('thread_id', $th->id)->where('dir', 'in')->max('id'));
        }
      }
    } catch (\Throwable $e) { report($e); }
    $rows = DB::table('chat_threads')->whereIn('channel', ['web', 'whatsapp', 'facebook', 'instagram'])->where('awaiting_reply', true)->where('last_dir', 'in')->where('status', '!=', 'resolved')
      ->where(fn($w) => $w->whereNull('needs_human')->orWhere('needs_human', false))->whereNull('handoff_at')
      ->where('last_at', '>=', now()->subHours(22))->orderByDesc('last_at')->limit(4)->get();
    foreach ($rows as $t) {
      $mid = (int)DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->max('id');
      $k = 'ai.backlog.'.$t->id.'.'.$mid; if (!\Illuminate\Support\Facades\Cache::add($k, 1, 172800)) continue;
      try {
        if (in_array($t->channel, ['web', 'whatsapp', 'facebook'], true) && !AiChat::paused($t)) self::aiReply((int)$t->id);
        $n = DB::table('chat_threads')->find($t->id);
        // still waiting after the AI looked at it: the AI could not answer, so a person takes it
        if ($n && $n->last_dir === 'in' && $n->awaiting_reply && empty($n->handoff_at)) DB::table('chat_threads')->where('id', $t->id)->update(['needs_human'=>true, 'updated_at'=>now()]);
      } catch (\Throwable $e) { report($e); }
    }
  }
  /** Worker tick (owner rule 11 Nov 2026): our message is the last one in the chat and the customer has not answered it for the set time (Chat Settings, default 30 min) - there is nothing to wait for, so the chat is closed as "no reply needed": a short last courtesy line (only while the 24-hour channel window is open) and Resolved, no Waiting / Awaiting Reply left. Open hand-offs, complaints, refunds, order requests etc. are never touched. */
  public static function closeSilentTick(): void {
    if (Sms::secret('ai_auto_close') === '0') return;
    try { self::closeCompletedTick(); } catch (\Throwable $e) { report($e); }
    $min = max(1, min(720, (int)(Sms::secret('ai_close_after_min') ?: 30)));
    try { self::closeGreetingTick($min); } catch (\Throwable $e) { report($e); }
    $rows = self::fuKeep(DB::table('chat_threads'))->where('last_dir', 'out')->whereIn('status', ['new', 'open', 'waiting'])->where(fn($w) => $w->where('awaiting_reply', true)->orWhere('unread', '>', 0)->orWhereNull('last_customer_message_at')) // owner rule 14 Nov 2026: a chat where the customer never wrote and we only sent messages closes by itself too
      ->where('last_at', '<=', now()->subMinutes($min))->orderBy('last_at')->limit(15)->get();
    foreach ($rows as $t) {
      $k = 'ai.silentclose.'.$t->id.'.'.substr((string)$t->last_at, 0, 16);
      if (!\Illuminate\Support\Facades\Cache::add($k, 1, 86400)) continue;
      self::closeSilent($t);
    }
  }
  /** Owner rule 14 Nov 2026: a customer who only said hello (every incoming message is a greeting), was answered, and then stayed silent for the close-after time goes to Closed. */
  public static function closeGreetingTick(int $min): void {
    $rows = self::fuKeep(DB::table('chat_threads'))->where('last_dir', 'out')->whereIn('status', ['new', 'open', 'waiting'])->whereNotNull('last_customer_message_at')
      ->where('last_at', '<=', now()->subMinutes($min))->orderBy('last_at')->limit(30)->get();
    foreach ($rows as $t) {
      try {
        $in = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->limit(6)->get(['body', 'attachment_type']);
        if ($in->isEmpty() || $in->count() > 4) continue;
        $ok = true; foreach ($in as $m) { $b = trim((string)$m->body); if ($m->attachment_type || $b === '' || mb_strlen($b) > 30 || !preg_match('/^(hi+|hello+|hey+|helo|hlw|hallo|hell?o there|assalamu? ?alaikum|assalamualaikum|salam|সালাম|আসসালামু? ?আলাইকুম|আসসালামুয়ালাইকুম|হ্যালো|হ্যাল্লো|হাই|হেলো|হ্যালো স্যার|hello sir|hi sir)[\s!.?🙏👋]*$/iu', $b)) { $ok = false; break; } }
        if (!$ok) continue;
        $k = 'ai.greetclose.'.$t->id.'.'.substr((string)$t->last_at, 0, 16); if (!\Illuminate\Support\Facades\Cache::add($k, 1, 86400)) continue;
        self::closeSilent($t);
      } catch (\Throwable $e) { report($e); }
    }
  }
  /** Owner rule 14 Nov 2026: a customer whose latest order is Delivered and whose last message is only a courtesy (thanks / ok / emoji) has nothing left to ask: the chat is closed silently (nothing is sent), leaves the unread list and goes to All History. Complaints, refund / return / cancel / payment chats, open Order Requests and open reports are never closed here. A new message from the customer reopens it. */
  public static function closeCompletedTick(): void {
    $rows = DB::table('chat_threads')->where('last_dir', 'in')->whereIn('status', ['new', 'open', 'waiting'])->where(fn($w) => $w->where('awaiting_reply', true)->orWhere('unread', '>', 0))
      ->whereNotNull('phone')->where('phone', '<>', '')->where('last_at', '<=', now()->subMinutes(5))->orderBy('last_at')->limit(30)->get();
    foreach ($rows as $t) {
      try {
        if (in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'payment', 'order_request', 'human_request'], true)) continue;
        if (!empty($t->reported_at) && empty($t->report_solved_at)) continue;
        $m = DB::table('chat_messages')->where('thread_id', $t->id)->orderByDesc('id')->first(['dir', 'body', 'kind', 'attachment_type']); if (!$m || $m->dir !== 'in') continue;
        $q = trim((string)$m->body); if ($m->attachment_type || $q === '' || mb_strlen($q) > 60) continue;
        if (!preg_match('/^(ok|okay|thanks?( you)?( so much)?|thx|ty|thank u|ধন্যবাদ|অনেক ধন্যবাদ|থ্যাংকস|থ্যাঙ্ক ইউ|ঠিক আছে|আচ্ছা|জি|জ্বি|হ্যাঁ ঠিক আছে|আলহামদুলিল্লাহ|ভালো|good|great|nice|received|পেয়েছি|পেয়ে গেছি|👍|🙏|❤️?|😊|🥰)([\s.!,।🙏👍❤️😊]|ধন্যবাদ|স্যার|ভাই|sir|bhai)*$/iu', $q)) continue;
        $o = DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->first(['id', 'status']); if (!$o || $o->status !== 'Delivered') continue;
        try { if (DB::table('chat_order_requests')->where('thread_id', $t->id)->where('status', 'review')->exists()) continue; } catch (\Throwable $e) {}
        DB::table('chat_threads')->where('id', $t->id)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'needs_human'=>false, 'updated_at'=>now()]);
        try { DB::table('chat_handoffs')->where('thread_id', $t->id)->whereIn('status', ['open', 'accepted'])->update(['status'=>'done', 'updated_at'=>now()]); } catch (\Throwable $e) {}
        try { AiMemory::event((int)$t->id, null, 'ai', 'auto_resolved_completed', null, ['why'=>'order delivered, customer only said thanks']); } catch (\Throwable $e) {}
      } catch (\Throwable $e) { report($e); }
    }
  }
  public static function closeSilent(object $t): bool {
    try {
      $tid = (int)$t->id; $t = DB::table('chat_threads')->find($tid); if (!$t || $t->status === 'resolved' || ($t->last_dir ?? '') !== 'out') return false;
      if (!empty($t->needs_human) || !empty($t->handoff_at) || (!empty($t->reported_at) && empty($t->report_solved_at))) return false;
      if (in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'human_request', 'payment', 'order_request'], true)) return false;
      if (DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists()) return false;
      try { if (DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', 'review')->exists()) return false; } catch (\Throwable $e) {}
      $li = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['id', 'dir', 'body', 'status', 'created_at']);
      if (!$li || $li->dir !== 'out' || $li->status === 'failed') return false; // the customer never got our last message: a person must look
      $lc = $t->last_customer_message_at ?? null;
      $open = $t->channel === 'web' || ($lc && \Carbon\Carbon::parse($lc)->gte(now()->subHours(23)));
      $cb = (string)DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->orderByDesc('id')->value('body');
      $bn = AiChat::banglaOnly() ? true : (bool)preg_match('/[\x{0980}-\x{09FF}]/u', $cb);
      $txt = $bn ? 'স্যার, যদি কোনো কিছুর প্রয়োজন হয় আমাকে জানাবেন। ধন্যবাদ। 🙏' : 'Sir, please let me know if you need anything. Thank you. 🙏';
      $upd = ['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'updated_at'=>now()];
      if ($open && !preg_match('/(যদি কোনো কিছুর প্রয়োজন|let me know if you need)/iu', (string)$li->body)) {
        [$sent, $err] = self::deliver($t, $txt);
        DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>$txt, 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
        if ($sent) $upd += ['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now()];
      }
      DB::table('chat_threads')->where('id', $tid)->update($upd);
      try { AiMemory::event($tid, null, 'ai', 'auto_resolved_no_reply', null, ['why'=>'customer silent after our last message']); } catch (\Throwable $e) {}
      return true;
    } catch (\Throwable $e) { report($e); return false; }
  }
  /** Owner 27 Nov 2026: move every chat we have answered, where the customer stayed silent for $hours or more, to Closed. Nothing is sent to the customer. Same safety blocks as closeSilent(); follow-ups, hand-offs, open complaints / refunds / orders and failed last messages stay open. $dry only counts. Returns [closed, kept]. */
  /** Conversations that hold only our automatic notices (order confirmation / update, campaigns): no customer message, no staff message, no owner, nothing pending. They belong in Closed. Anything with a customer message or staff work is left alone. */
  public static function closeNotifyOnly(bool $dry = false, ?string $by = null): array {
    $n = 0; $kept = 0;
    $q = DB::table('chat_threads as t')->where('t.status', '!=', 'resolved')->where(fn($w) => $w->whereNull('t.awaiting_reply')->orWhere('t.awaiting_reply', false))->where(fn($w) => $w->whereNull('t.needs_human')->orWhere('t.needs_human', false))
      ->whereNull('t.handoff_at')->whereNull('t.assigned_to')
      ->whereExists(fn($x) => $x->selectRaw('1')->from('chat_messages as m')->whereColumn('m.thread_id', 't.id')->where('m.dir', 'out'))
      ->whereNotExists(fn($x) => $x->selectRaw('1')->from('chat_messages as m')->whereColumn('m.thread_id', 't.id')->where('m.dir', 'in'))
      ->whereNotExists(fn($x) => $x->selectRaw('1')->from('chat_messages as m')->whereColumn('m.thread_id', 't.id')->where('m.dir', 'out')->where(fn($y) => $y->whereNull('m.user_name')->orWhere(fn($z) => $z->where('m.user_name', 'not like', '%(auto)%')->where('m.user_name', 'not like', 'Campaign:%'))))
      ->orderBy('t.id')->select('t.*');
    foreach ($q->limit(2000)->get() as $t) {
      try {
        $tid = (int)$t->id;
        if (DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists()) { $kept++; continue; }
        try { if (DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', 'review')->exists()) { $kept++; continue; } } catch (\Throwable $e) {}
        $n++;
        if ($dry) continue;
        DB::table('chat_threads')->where('id', $tid)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'updated_at'=>now()]);
        try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>$by ?: '', 'action'=>'chat.close', 'subject'=>'Conversation #'.$tid.' closed: automatic notices only (bulk)', 'ip'=>request()->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
      } catch (\Throwable $e) { report($e); }
    }
    return [$n, $kept];
  }
  public static function closeAnswered(int $hours = 3, bool $dry = false, ?string $by = null): array {
    $n = 0; $kept = 0;
    $q = DB::table('chat_threads')->whereIn('channel', ['web', 'whatsapp', 'facebook', 'instagram'])->where('status', '!=', 'resolved')->where('last_dir', 'out')->where('last_at', '<=', now()->subHours(max(1, $hours)))->orderBy('id');
    $q = self::fuKeep($q);
    foreach ($q->limit(2000)->get() as $t) {
      try {
        $tid = (int)$t->id;
        if (!empty($t->awaiting_reply) || !empty($t->needs_human) || (!empty($t->reported_at) && empty($t->report_solved_at))) { $kept++; continue; }
        if (in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'payment'], true)) { $kept++; continue; }
        // Owner rule 27 Nov 2026 (20:44): a hand-off or a "wants a person" chat closes only after a PERSON answered it (after the hand-off and after the customer's last message) and the customer then stayed silent for the set hours, counted from that reply. The AI's own "the team will reply" line never counts.
        // Chats that only the new rule can close (hand-off answered by a person, or an old "order request" label with no open request) and whose last reply is older than the day the rule started stay open: the owner asked that old chats are not closed in one go. Setting chat_close_relaxed_from (UTC date-time; '0' = no limit).
        $from = (string)(Sms::secret('chat_close_relaxed_from') ?: '2026-11-27 15:00:00');
        $relaxed = !empty($t->handoff_at) || in_array((string)($t->intent ?? ''), ['human_request', 'order_request'], true);
        if ($relaxed && $from !== '0' && (string)$t->last_at < $from) { $kept++; continue; }
        $ho = !empty($t->handoff_at) || (string)($t->intent ?? '') === 'human_request' || DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists();
        if ($ho) {
          $hu = self::lastHumanOut([$tid])[$tid] ?? null; $cf = self::lastConfirmOut([$tid])[$tid] ?? null; $hu = self::answeredAt($t, $hu !== null ? (string)$hu : null, $cf !== null ? (string)$cf : null);
          if ($hu === null || (!empty($t->handoff_at) && $hu < (string)$t->handoff_at) || (!empty($t->last_customer_message_at) && $hu < (string)$t->last_customer_message_at) || $hu > (string)now()->subHours(max(1, $hours))) { $kept++; continue; }
        }
        try { if (DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', 'review')->exists()) { $kept++; continue; } } catch (\Throwable $e) {}
        $li = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['dir', 'status']);
        if (!$li || $li->dir !== 'out' || $li->status === 'failed') { $kept++; continue; }
        $n++;
        if ($dry) continue;
        DB::table('chat_threads')->where('id', $tid)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'updated_at'=>now()] + ($ho ? ['handoff_at'=>null, 'handoff_reason'=>null, 'ai_paused'=>false] : []));
        if ($ho) { try { DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->update(['status'=>'done', 'updated_at'=>now()]); } catch (\Throwable $e) {} }
        try { ChatOwner::system($tid, null, 'release', 'Closed: answered, customer silent ('.($by ?: 'staff').').'); } catch (\Throwable $e) {}
        try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>$by ?: '', 'action'=>'chat.close', 'subject'=>'Conversation #'.$tid.' closed: answered, customer silent for '.$hours.'h+ (bulk)', 'ip'=>request()->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
      } catch (\Throwable $e) { report($e); }
    }
    return [$n, $kept];
  }
  /** Owner 27 Nov 2026 (option A): a chat we answered where the customer stays silent closes by itself after the set hours (setting ai_close_answered_hours; empty or 0 = off). Uses closeAnswered(), so complaints, refunds, order requests, hand-offs, follow-ups, "needs a person" and chats still Awaiting Reply are never closed. Nothing is sent to the customer; a new customer message reopens the chat. */
  /** Customer's last message is only a confirmation / thanks (no question, nothing else asked). */
  public static function isConfirmOnly(string $b): bool {
    $b = trim($b); if ($b === '' || mb_strlen($b) > 60 || preg_match('/[?？]|কি\b|কী|কবে|কখন|কত|কেন|koto|kobe|kokhon|keno|\bki\b|when|how|why|what|where/iu', $b)) return false;
    return (bool)preg_match('/^(ji|jee|জি|জ্বি|হ্যাঁ|ha|haa|yes|ok(ay|k)?|ওকে|ঠিক আছে|thik ?(ache|ase)|আচ্ছা|accha|acha)?[\s,.!।]*((order|অর্ডার)\s*)?(confirm(ed)?|conform|confrom|কনফার্ম|কনফার্মড|কনফার্ম করছি|কনফার্ম করলাম|confirm kor(chi|lam|ci|en)|done|ok(ay|k)?|ওকে|ঠিক আছে|thik ?(ache|ase)|আচ্ছা|accha|acha|thanks?( you)?|thank u|ধন্যবাদ|জি|জ্বি|ji|👍|🙏)([\s,.!।👍🙏]|করছি|করলাম|korchi|korlam|koren|করেন|ভাই|vai|bhai|sir|স্যার|ধন্যবাদ|thanks?|ok|ওকে)*$/iu', $b);
  }
  /**
   * Owner 27 Nov 2026 (21:33): a chat is NOT closed just because its order became Approved. It closes when all three are true: the order details went out
   * (the automatic "Order confirmation" notice was sent), the customer's last message was only a confirmation / thanks, and nothing else is open
   * (no complaint, refund, cancel, order change, payment check, open Order Request, follow-up, "needs a person", or a hand-off other than the AI's own "the team will follow up").
   * Anything else waits for the normal 3-hour rule. Only notices of the last 30 minutes are looked at, so old chats are never swept. A new customer message reopens the chat.
   */
  public static function closeConfirmedTick(): void {
    if (Sms::secret('ai_auto_close') === '0' || Sms::secret('chat_close_confirmed') === '0') return;
    if (!\Illuminate\Support\Facades\Cache::add('chat.closeconfirmed.tick', 1, 55)) return;
    $ids = DB::table('chat_messages')->where('dir', 'out')->where('status', '!=', 'failed')->where('user_name', 'like', 'Order confirmation (auto)%')->where('created_at', '>=', now()->subMinutes(30))->distinct()->limit(60)->pluck('thread_id')->all();
    if (!$ids) return;
    foreach (self::fuKeep(DB::table('chat_threads')->whereIn('id', $ids)->where('status', '!=', 'resolved')->where('last_dir', 'out')->where('awaiting_reply', false))->get() as $t) {
      try {
        $tid = (int)$t->id;
        if (!empty($t->needs_human) || (!empty($t->reported_at) && empty($t->report_solved_at)) || !empty($t->assigned_to)) continue; // a chat a person owns is closed by that person or by the 3-hour rule
        if (in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'payment', 'human_request'], true)) continue;
        if (!empty($t->handoff_at) && !str_starts_with((string)($t->handoff_reason ?? ''), 'Auto: the AI told')) continue;
        try { if (DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', 'review')->exists()) continue; } catch (\Throwable $e) {}
        $cin = DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->orderByDesc('id')->first(['id', 'body', 'attachment_type']); if (!$cin || $cin->attachment_type || !self::isConfirmOnly((string)$cin->body)) continue;
        if (!DB::table('chat_messages')->where('thread_id', $tid)->where('id', '>', $cin->id)->where('dir', 'out')->where('status', '!=', 'failed')->where('user_name', 'like', 'Order confirmation (auto)%')->exists()) continue; // the order details must have gone out AFTER the confirmation
        $li = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['dir', 'status']); if (!$li || $li->dir !== 'out' || $li->status === 'failed') continue;
        if (!DB::table('chat_threads')->where('id', $tid)->where('status', '!=', 'resolved')->where('awaiting_reply', false)->update(['status'=>'resolved', 'unread'=>0, 'needs_human'=>false, 'handoff_at'=>null, 'handoff_reason'=>null, 'ai_paused'=>false, 'updated_at'=>now()])) continue;
        try { DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->update(['status'=>'done', 'updated_at'=>now()]); } catch (\Throwable $e) {}
        try { ChatOwner::system($tid, null, 'release', 'Closed: order details sent, customer confirmed, nothing else open.'); } catch (\Throwable $e) {}
        try { DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System (rules)', 'action'=>'chat.close', 'subject'=>'Conversation #'.$tid.' closed: order details sent, customer confirmed, nothing else open', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
      } catch (\Throwable $e) { report($e); }
    }
  }
  public static function closeAnsweredTick(): void {
    try { self::closeConfirmedTick(); } catch (\Throwable $e) { report($e); }
    $h = (int)(Sms::secret('ai_close_answered_hours') ?: 0);
    if ($h < 1 || Sms::secret('ai_auto_close') === '0') return;
    if (!\Illuminate\Support\Facades\Cache::add('chat.closeanswered.tick', 1, 600)) return; // at most every 10 minutes
    [$n] = self::closeAnswered(min(72, $h), false, 'Automatic');
    if ($n > 0) { try { \Illuminate\Support\Facades\Log::info('closeAnsweredTick closed '.$n.' chats'); } catch (\Throwable $e) {} }
  }
  /** Why a never-answered WhatsApp chat can NOT be closed with a last courtesy message (null = it can). */
  /** Words that mean the customer is reporting a problem (complaint, bad / fake / damaged product, wrong item, refund or return wish). Display only: it files the chat under Report, nothing is promised or sent. */
  const REPORT_RX = '/অভিযোগ|কমপ্লেইন|কমপ্লেন|রিপোর্ট|নকল|ভেজাল|নষ্ট|ড্যাম|ড্যামেজ|ভাঙা|ভাঙ্গা|ছেঁড়া|মেয়াদ|বাজে|খারাপ|গন্ধ|গলা জ্ব|ভুল প্রোডাক্ট|ভুল পণ্য|ভুল জিনিস|ফেরত|রিফান্ড|রিটার্ন|ঠক|প্রতারণা|complain|complaint|report|fake|nokol|vejal|damage|damp|broken|expired|kharap|baje|gondho|wrong product|wrong item|vul product|refund|return korbo|ferot|ferat|cheat|protarona|scam/iu';
  /** Customer text that reads like a problem report files the conversation under Report (once; staff can clear it). */
  public static function reportCheck(int $tid, string $text): void {
    try { if ($tid <= 0 || trim($text) === '' || !preg_match(self::REPORT_RX, $text, $m)) return; self::markReported($tid, 'Customer wrote: "'.mb_substr(trim($text), 0, 120).'"'); } catch (\Throwable $e) {}
  }
  public static function markReported(int $tid, string $why): void {
    // files a new complaint, or opens it again when the customer complains after an earlier one was solved
    try { if ($tid > 0) DB::table('chat_threads')->where('id', $tid)->where(fn($w) => $w->whereNull('reported_at')->orWhereNotNull('report_solved_at'))->update(['reported_at'=>now(), 'report_reason'=>mb_substr($why, 0, 200), 'report_solved_at'=>null, 'report_solved_by'=>null, 'updated_at'=>now()]); } catch (\Throwable $e) {}
  }
  public static function unansweredBlock(object $t): ?string {
    if (!empty($t->needs_human) || !empty($t->handoff_at)) return 'hand-off / needs a person';
    if (in_array((string)($t->intent ?? ''), ['complaint', 'refund', 'cancel', 'change_order', 'human_request', 'payment', 'order_request'], true)) return 'open '.($t->intent).' request';
    if (DB::table('chat_handoffs')->where('thread_id', $t->id)->whereIn('status', ['open', 'accepted'])->exists()) return 'open hand-off';
    $lc = $t->last_customer_message_at ?? $t->last_at;
    if (!$lc || \Carbon\Carbon::parse($lc)->lt(now()->subHours(23))) return 'outside the 24-hour WhatsApp window';
    // a real question, order or price request must be answered by a person, never closed with a courtesy line
    $lb = trim((string)DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->orderByDesc('id')->value('body'));
    if ($lb === '' || !preg_match('/^(hello|hi|hey|helo|hlw|ok|okay|thanks?|thank you|thx|salam|assalamu\S*|ধন্যবাদ|থ্যাংক\S*|হ্যালো|হাই|হ্যাই|জি|আচ্ছা|ওকে|আসসালামু\S*|সালাম|can i get more info on this\??|হ্যালো! আমি কি এই ব্যাপারে আরও তথ্য জানতে পারি\??)[\s!.?🙏👍❤️]*$/iu', $lb) && !preg_match('/more info on this|আরও তথ্য জানতে পারি/iu', $lb)) return 'real question or order — needs a person';
    return null;
  }
  /** Owner action: a WhatsApp chat whose customer message was never answered gets one short last courtesy message and is resolved. A failed send changes nothing (stays Awaiting Reply). */
  public static function closeUnanswered(object $t): bool {
    try {
      $tid = (int)$t->id; $t = DB::table('chat_threads')->find($tid);
      if (!$t || $t->channel !== 'whatsapp' || $t->status === 'resolved' || !$t->awaiting_reply || self::unansweredBlock($t) !== null) return false;
      $li = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['id', 'dir', 'body', 'created_at']);
      if (!$li || $li->dir !== 'in') return false;
      $bn = AiChat::banglaOnly() ? true : ((bool)preg_match('/[\x{0980}-\x{09FF}]/u', (string)$li->body) || !preg_match('/[a-z]{3,}/i', (string)$li->body));
      $txt = $bn ? 'স্যার, উত্তর দিতে দেরি হওয়ার জন্য আন্তরিকভাবে দুঃখিত। যদি কোনো কিছুর প্রয়োজন হয় আমাকে জানাবেন। ধন্যবাদ। 🙏' : 'Sir, sorry for the late reply. Please let me know if you need anything. Thank you. 🙏';
      [$sent, $err] = self::deliver($t, $txt);
      DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>$txt, 'user_name'=>auth()->user()->name ?? 'Owner', 'is_ai'=>false, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
      if (!$sent) return false;
      self::replied($tid, now());
      DB::table('chat_threads')->where('id', $tid)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
      return true;
    } catch (\Throwable $e) { report($e); return false; }
  }
  /** True while the AI has taken a chat over (no staff reply within the wait limit). Ends when a staff member replies, the chat is resolved or handed to a person, or after 12 hours. */
  public static function aiTakeover(int $tid): bool {
    try {
      $ts = (int)\Illuminate\Support\Facades\Cache::get('ai.takeover.'.$tid, 0); if (!$ts) return false;
      $t = DB::table('chat_threads')->find($tid);
      $end = !$t || $t->status === 'resolved' || !empty($t->handoff_at)
        || DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->whereRaw('not coalesce(is_ai, false)')->where('status', '<>', 'failed')->where('created_at', '>=', \Carbon\Carbon::createFromTimestamp($ts))->exists();
      if ($end) { \Illuminate\Support\Facades\Cache::forget('ai.takeover.'.$tid); return false; }
      return true;
    } catch (\Throwable $e) { return false; }
  }
  public static function aiReply(int $tid, int $depth = 0): void {
    $lock = null;
    try {
      if (!AiChat::chatOn()) return;
      if (DB::table('chat_threads')->where('id', $tid)->value('channel') === 'sms') return; // SMS inbox: people answer; no automatic replies (each SMS costs money)
      if (!self::$force && !AiSchedule::activeNow() && !self::aiTakeover($tid)) return; // outside the owner's AI hours staff answer, unless the AI took this chat over after the staff wait limit (staleTick)
      $t = DB::table('chat_threads')->find($tid);
      if ($t && in_array($t->channel, ['web', 'whatsapp', 'facebook'], true) && ChatRules::ackDue($t)) { self::handoffAck($t); return; } // the rules just sent this chat to the agent queue: one short line to the customer, then nothing more from the AI
      if (!$t || AiChat::paused($t) || !in_array($t->channel, ['web', 'whatsapp', 'facebook'], true)) return;
      if (ChatOwner::ready() && $t->assigned_to !== null) return; // a person owns this conversation: the AI does not answer it
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
        // a customer voice message: turn it into words first (synchronously), then answer exactly what was said; if it cannot be understood, it goes to Messages > Human Answer for staff
        if ($li->attachment_type === 'audio' && $li->attachment_url) {
          if (empty($li->transcript)) { self::transcribe((int)$li->id); $li = DB::table('chat_messages')->find($li->id); }
          if (!empty($li->transcript)) { $lb = trim((string)$li->transcript); }
          else { DB::table('chat_threads')->where('id', $tid)->update(['needs_human'=>true, 'updated_at'=>now()]); return; }
        }
        // the customer's clear goodbye (or "no" to our "any other question?" line): courtesy reply + resolve, when nothing is pending
        $askedClose = (bool)preg_match('/(আর কোনো প্রশ্ন|any other question|anything else)/iu', (string)DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->orderByDesc('id')->value('body'));
        if (self::isFarewell($lb, $askedClose) && self::autoClose($t)) { \Illuminate\Support\Facades\Cache::forget($lock); return; }
        // staff handle these: voice/video/files, unsupported or system placeholders, and plain thank-you / ok messages
        if (($li->attachment_url && !in_array($li->attachment_type, ['image', 'audio'], true)) || preg_match('/^\[(unsupported|[a-z]+ received)/iu', $lb) || preg_match('/^(ok|okay|thanks?|thank you|thx|ধন্যবাদ|থ্যাংকস|👍|🙏|❤️?)[\s.!]*$/iu', $lb)) return;
      }
      // a short "yes / হ্যাঁ / confirm" right after our order-confirmation message answers THAT order: staff handle it, the AI must never start a new order from it
      if ($ext && isset($lb) && mb_strlen($lb) <= 40 && preg_match('/^(yes|yeah|y|ji|jii|han|ha|haa|hya|hyan|confirm(ed)?|ok(ay)?|ঠিক আছে|হ্যাঁ|হ্যা|হা|জি|জী|কনফার্ম)[\s.!]*$/iu', $lb)
        && ($lo = DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->orderByDesc('id')->first(['kind', 'created_at'])) && $lo->kind === 'invoice' && $lo->created_at >= now()->subHours(48)->toDateTimeString()) return;
      $k = 'ai.t.'.$tid.'.'.now()->format('YmdH'); \Illuminate\Support\Facades\Cache::add($k, 0, 4000);
      if (\Illuminate\Support\Facades\Cache::increment($k) > 25) return;
      $d = 'ai.n.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($d, 0, 90000);
      if (\Illuminate\Support\Facades\Cache::increment($d) > 1500) return;
      $rows = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->limit(10)->get()->reverse()->values();
      if ($rows->isEmpty() || $rows->last()->dir !== 'in') return;
      // one AI answer per customer message, kept in the database: a second run for the same message (duplicate event, two workers) stops here
      $claim = ChatOwner::ready() ? (int)$rows->last()->id : 0;
      if ($claim && !DB::table('chat_messages')->where('id', $claim)->where(fn($w) => $w->whereNull('ai_claim_at')->orWhere('ai_claim_at', '<', now()->subMinutes(3)))->update(['ai_claim_at'=>now()])) return;
      $hist = $rows->map(fn($m) => ['role'=>$m->dir === 'in' ? 'user' : 'assistant', 'content'=>$m->body !== '' ? $m->body : (!empty($m->transcript) ? $m->transcript : ($m->attachment_type === 'audio' ? '[ভয়েস মেসেজ বোঝা যায়নি]' : '[ছবি]'))])->all();
      // the customer's most recent photo (this message or one of the last few) goes to the model as an image, so a follow-up like "price?" still refers to what the photo shows
      try {
        for ($ix = $rows->count() - 1; $ix >= max(0, $rows->count() - 8); $ix--) {
          $im = $rows[$ix]; // 27 Nov 2026: was $li, which overwrote the customer's LAST message and made replied() clear only up to an older photo, leaving the chat "Awaiting" after a successful AI answer
          if ($im->dir === 'in' && $im->attachment_type === 'image' && $im->attachment_url) {
            $ip = public_path('uploads/chat/'.basename((string)parse_url((string)$im->attachment_url, PHP_URL_PATH)));
            if ($vd = self::visionData($ip)) $hist[$ix]['img'] = $vd;
            break;
          }
        }
      } catch (\Throwable $e) {}
      if (AiAgent::on()) {
        $lastIn = $rows->last(); $txt = AiAgent::replyThread($tid, $hist, (int)$lastIn->id, (string)($lastIn->body !== '' ? $lastIn->body : ($lastIn->transcript ?? '')));
        if ($txt === null) goto legacy;
        // a hand-off pauses the AI on purpose; its own closing message must still reach the customer
        if (AiChat::paused(DB::table('chat_threads')->find($tid)) && !DB::table('chat_handoffs')->where('thread_id', $tid)->where('created_at', '>', now()->subMinutes(2))->exists()) return;
        // checked again just before sending: a person may have taken the conversation while the AI was writing (the guard inside aiDeliver repeats this at the moment of sending)
        $ho = !empty(ChatOwner::$handoffRun[$tid]);
        if (!$ho && ChatOwner::ownerId($tid) !== null) return;
        if ($ext) $txt = mb_substr($txt, 0, 1900);
        [$sent, $err] = self::aiDeliver($t, $txt, $ho);
        if (!$sent && ChatOwner::$refused) return; // refused by the ownership guard: nothing was sent and nothing is shown to the customer
        if (!$sent && $claim) DB::table('chat_messages')->where('id', $claim)->update(['ai_claim_at'=>null]); // a failed send may be tried again
        DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($txt, 0, 3500), 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
        if ($sent) { DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]); if ($ext) self::replied((int)$tid, $li->created_at); } // a successfully SENT AI reply answers the customer message (a failed send stays unread)
        if ($lock) \Illuminate\Support\Facades\Cache::forget($lock);
        AiMemory::onAi((int)$tid, ['sent'=>(bool)$sent]);
        // an order the AI just placed: send the secure invoice link as its own message (only after the backend confirmed the order exists)
        if ($sent && ($invNo = \Illuminate\Support\Facades\Cache::pull('ai.invoice.'.$tid)) && DB::table('orders')->where('invoice', $invNo)->exists()) {
          $itx = "আপনার ইনভয়েস (".\App\Support\Inv::show($invNo)."): ".self::invoiceUrl($invNo); $ie = null; $is = true;
          [$is, $ie] = self::aiDeliver($t, $itx, $ho);
          if (!$is && ChatOwner::$refused) return;
          DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'invoice', 'body'=>$itx, 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$is ? 'sent' : 'failed', 'error'=>$ie, 'created_at'=>now(), 'updated_at'=>now()]);
          if ($is) DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($itx, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
        }
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
      // website chat: the answer is written under the same guard, so it never lands in a conversation a person has just taken
      ChatOwner::guard($t, ['type'=>'ai'], function () use ($tid, $txt) {
        DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($txt, 0, 3500), 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>'sent', 'created_at'=>now(), 'updated_at'=>now()]);
        DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
        return [true, null];
      });
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
  /** Public URL of the main photo of the product a "name, price: link" line points to (null when it cannot be resolved or the file is missing). */
  public static function productImage(string $c): ?string {
    try {
      if (!preg_match('#https?://(?:www\.)?dutyfreebd\.com/[^\s]+/([a-z0-9\-]+)/?(?=\s|$)#iu', $c, $mm)) return null;
      $pr = DB::table('products')->whereNull('parent_product_id')->where('published', true)->where(fn($q) => $q->where('slug', $mm[1])->orWhere('path_slug', $mm[1]))->first(['image']);
      $ip = $pr ? strtok((string)$pr->image, '?') : '';
      return ($ip !== '' && str_starts_with($ip, '/uploads/') && is_file(public_path(ltrim($ip, '/')))) ? 'https://dutyfreebd.com'.$ip : null;
    } catch (\Throwable $e) { return null; }
  }
  /** Product photo caption: name on line 1, price (and stock) on line 2, order link alone on the last line. */
  private static function productCaption(string $c): string {
    $c = trim($c);
    $c = preg_replace('#\s*[:：\-–]?\s*(https?://\S+)\s*$#u', "\n$1", $c);
    $c = preg_replace('#,\s*(?=(?:দাম:?\s*)?(?:৳\s*)?[\d,০-৯]+\s*(?:TK|Tk|tk|টাকা|৳))#u', "\nদাম: ", $c, 1);
    $c = preg_replace('#দাম:\s*দাম:?\s*#u', 'দাম: ', $c);
    return mb_substr($c, 0, 900);
  }

  public static array $dlog = [];
  /** Owner rule: an agent's first message to a customer opens with a salam carrying the customer's name and the agent's own name; the agent's text follows.
   *  "First" = this agent has not written in this conversation in the last 12 hours. Returns the prefix ('' when none is due). Switch: setting chat_staff_greet. */
  public static function staffGreet(object $t, string $agent, int $uid): string {
    try {
      if (Procure::set('chat_staff_greet', '1') !== '1') return '';
      if (DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'out')->where('user_id', $uid)->where('created_at', '>', now()->subHours(12)->toDateTimeString())->exists()) return '';
      $n = trim(preg_replace('/\s+/u', ' ', (string)($t->name ?? '')));
      if ($n === '' || preg_match('/\d{5,}|whatsapp user|guest|visitor|unknown|facebook user|instagram user/iu', $n) || !preg_match('/\p{L}{2,}/u', $n)) $n = '';
      else $n = mb_substr(implode(' ', array_slice(explode(' ', $n), 0, 3)), 0, 40);
      $a = trim((string)explode(' ', trim($agent))[0]);
      return 'আসসালামু আলাইকুম'.($n !== '' ? ' '.$n : '').', DutyFreeBD থেকে '.($a !== '' ? $a.' ' : '').'বলছি।'."\n\n";
    } catch (\Throwable $e) { return ''; }
  }
  /** Who is sending right now: ['type'=>'human','id'=>..,'name'=>..], ['type'=>'ai'] or null (= automation). Set by the caller around deliver(). */
  public static ?array $sender = null;
  /** Every outgoing message passes the ownership guard (ChatOwner): a person must own the conversation, the AI may send only while nobody owns it, and two sends never overlap. */
  public static function deliver(object $t, string $body, ?string $att = null, string $attType = 'image'): array {
    return ChatOwner::guard($t, self::$sender ?? ['type'=>'system'], fn() => self::deliverRaw($t, $body, $att, $attType));
  }
  /** The single fixed line after ChatRules sent the chat to the agent queue. It does not clear Awaiting Reply: only a person's reply does. */
  public static function handoffAck(object $t): void {
    try {
      $tid = (int)$t->id; if (ChatOwner::ready() && $t->assigned_to !== null) return;
      if ($t->channel !== 'web' && !AiChat::chanOn()) return;
      $txt = ChatRules::ACK; [$sent, $err] = self::aiDeliver($t, $txt, true);
      if (!$sent && ChatOwner::$refused) return;
      DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>$txt, 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
      if ($sent) DB::table('chat_threads')->where('id', $tid)->update(['last_message'=>mb_substr($txt, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  /** The AI's own send. $handoff: the closing line of the run that handed the chat to a person (the only AI line allowed after a person was assigned). */
  private static function aiDeliver(object $t, string $body, bool $handoff = false): array {
    $p = self::$sender; self::$sender = ['type'=>$handoff ? 'system' : 'ai'];
    try { return self::deliver($t, $body); } finally { self::$sender = $p; }
  }
  private static function deliverRaw(object $t, string $body, ?string $att = null, string $attType = 'image'): array {
    try {
      if ($t->channel === 'web') return [true, null];
      if ($t->channel === 'sms') { // SMS inbox: only a person's own reply is sent; automation notices and closing lines are not sent as SMS
        if ((self::$sender['type'] ?? 'system') !== 'human') return [false, 'Automatic messages are not sent by SMS.'];
        if ($att) return [false, 'SMS cannot carry an attachment. Send text only.'];
        return SmsInbox::reply($t, $body);
      }
      if ($t->channel === 'whatsapp') {
        [$tok, $pid] = self::waCred($t->page_ref ?? null);
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
              if (preg_match('#https?://\S+#u', $ln)) {
                $own = trim($ln); $last = '';
                if (trim(preg_replace('#https?://\S+#u', '', $ln), " \t:-–：") === '') { // bare link: the line right above it is its product name / price
                  while ($buf && trim((string)end($buf)) === '') array_pop($buf);
                  if ($buf) { $last = trim((string)array_pop($buf)); if ($last !== '') $own = $last."\n".$own; }
                }
                if ($buf) { $pre = trim(implode("\n", $buf)); if ($pre !== '') $chunks[] = $pre; $buf = []; }
                $chunks[] = $own;
              }
              else $buf[] = $ln;
            }
            if ($buf) { $pre = trim(implode("\n", $buf)); if ($pre !== '') $chunks[] = $pre; }
          } else $chunks[] = $body;
          foreach ($chunks as $c) {
            // a product line (name, price, link) goes as the product PHOTO with that line as its caption: WhatsApp link cards often show only the link, a real photo always shows
            $img = null;
            if (preg_match('#https?://(?:www\.)?dutyfreebd\.com/[^\s]+/([a-z0-9\-]+)/?(?=\s|$)#iu', $c, $mm)) {
              try {
                $pr = DB::table('products')->whereNull('parent_product_id')->where('published', true)->where(fn($q) => $q->where('slug', $mm[1])->orWhere('path_slug', $mm[1]))->first(['image']);
                $ip = $pr ? strtok((string)$pr->image, '?') : '';
                if ($ip !== '' && str_starts_with($ip, '/uploads/') && is_file(public_path(ltrim($ip, '/')))) $img = 'https://dutyfreebd.com'.$ip;
              } catch (\Throwable $e) { $img = null; }
            }
            $out[] = $img ? ['type'=>'image', 'image'=>['link'=>$img, 'caption'=>self::productCaption($c)]] : ['type'=>'text', 'text'=>['body'=>$c, 'preview_url'=>true]];
          }
        }
        foreach ($out as $k => $m) {
          if ($k > 0) usleep(400000);
          $r = Http::timeout(15)->withToken($tok)->post(self::API.'/'.$pid.'/messages', ['messaging_product'=>'whatsapp', 'to'=>$to] + $m);
          self::$dlog[] = [$m['type'], $r->status(), mb_substr($r->body(), 0, 220), $m['image']['link'] ?? null];
          if (!$r->ok()) return [false, self::err($r)];
        }
        return [true, null];
      }
      $tok = self::pageTok($t->channel, $t->page_ref ?? null);
      if (!$tok) return [false, ucfirst($t->channel).' is not connected (Chat Settings).'];
      $msgs = [];
      if ($att) $msgs[] = ['attachment'=>['type'=>in_array($attType, ['image', 'audio'], true) ? $attType : 'file', 'payload'=>['url'=>$att, 'is_reusable'=>true]]];
      if ($body !== '' && !$att && preg_match_all('#https?://(?:www\.)?dutyfreebd\.com/\S+#iu', $body) >= 1) {
        // product lines go as the product photo followed by "name / price / link"; other lines stay plain text
        $plain = [];
        foreach (preg_split('/\R/u', $body) as $ln) {
          if (trim($ln) === '') continue;
          $img = preg_match('#https?://(?:www\.)?dutyfreebd\.com/\S+#iu', $ln) ? self::productImage($ln) : null;
          if ($img) { if ($plain) { $msgs[] = ['text'=>implode("\n", $plain)]; $plain = []; } $msgs[] = ['attachment'=>['type'=>'image', 'payload'=>['url'=>$img, 'is_reusable'=>true]]]; $msgs[] = ['text'=>self::productCaption($ln)]; }
          else $plain[] = $ln;
        }
        if ($plain) $msgs[] = ['text'=>implode("\n", $plain)];
      } elseif ($body !== '') $msgs[] = ['text'=>$body];
      foreach ($msgs as $m) {
        $r = Http::timeout(15)->post(self::API.'/me/messages?access_token='.urlencode($tok), ['recipient'=>['id'=>$t->external_id], 'messaging_type'=>'RESPONSE', 'message'=>$m]);
        if (!$r->ok()) return [false, self::err($r)];
      }
      return [true, null];
    } catch (\Throwable $e) { return [false, 'Could not reach the platform: '.mb_substr($e->getMessage(), 0, 120)]; }
  }
  /** Customer-side "typing…" signal while a staff member writes: web widget dots, Messenger/Instagram typing_on, WhatsApp typing indicator (WhatsApp also shows the customer's last message as read, by design of that API). */
  public static function typing(object $t): void {
    try {
      if ($t->channel === 'web') { \Illuminate\Support\Facades\Cache::put('chat.typing.'.$t->id, 1, 7); return; }
      if ($t->channel === 'whatsapp') {
        [$tok, $pid] = self::waCred($t->page_ref ?? null); if (!$tok || !$pid) return;
        $mid = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->whereNotNull('ext_id')->where('created_at', '>', now()->subHours(23))->orderByDesc('id')->value('ext_id'); if (!$mid) return;
        Http::timeout(6)->withToken($tok)->post(self::API.'/'.$pid.'/messages', ['messaging_product'=>'whatsapp', 'status'=>'read', 'message_id'=>$mid, 'typing_indicator'=>['type'=>'text']]);
        return;
      }
      $tok = self::pageTok($t->channel, $t->page_ref ?? null); if (!$tok) return;
      Http::timeout(6)->post(self::API.'/me/messages?access_token='.urlencode($tok), ['recipient'=>['id'=>$t->external_id], 'sender_action'=>'typing_on']);
    } catch (\Throwable $e) {}
  }
  private static function err($r): string { return mb_substr((string)($r->json('error.message') ?? $r->body()), 0, 220); }

  /** Card data for lists and popups: customer category, order count and risk. */
  /** Follow-up columns exist (migration 2026_11_27_090000). Checked once per request; before the migration every follow-up feature stays off. */
  public static function fuReady(): bool { static $r = false, $at = 0; if (!$r && time() - $at >= 60) { $at = time(); try { $r = \Illuminate\Support\Facades\Schema::hasColumn('chat_threads', 'follow_up_at'); } catch (\Throwable $e) { $r = false; } } return $r; } // a long-running worker started before the migration notices the new columns within a minute
  /** Columns that end a follow-up, for a conversation update (a new customer message, a sent staff reply, Close chat). Empty before the migration. */
  public static function fuClear(): array { return self::fuReady() ? ['follow_up_at'=>null, 'follow_up_note'=>null, 'follow_up_by'=>null, 'follow_up_set_at'=>null] : []; }
  /** Automatic closing never touches a conversation that has a follow-up waiting (the representative promised to come back to it). No effect before the migration. */
  public static function fuKeep($q) { return self::fuReady() ? $q->whereNull('follow_up_at') : $q; }
  /** Time of the last reply a PERSON sent (not the AI, not an automatic notice, not a failed send), per conversation id. */
  public static function lastHumanOut(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return [];
    try {
      return DB::table('chat_messages')->whereIn('thread_id', $ids)->where('dir', 'out')->where('status', '!=', 'failed')->whereRaw('coalesce(is_ai, false) = false')
        ->whereRaw("coalesce(user_name, '') not like '%(auto)%'")->whereRaw("coalesce(user_name, '') not in ('Claude AI', 'Automation', 'System')")
        ->groupBy('thread_id')->selectRaw('thread_id, max(created_at) as at')->pluck('at', 'thread_id')->all();
    } catch (\Throwable $e) { report($e); return []; }
  }
  /** Time of the last automatic "order confirmed" notice per conversation: the order was approved after the customer's confirmation. */
  public static function lastConfirmOut(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return [];
    try { return DB::table('chat_messages')->whereIn('thread_id', $ids)->where('dir', 'out')->where('status', '!=', 'failed')->where('user_name', 'like', 'Order confirmation (auto)%')->groupBy('thread_id')->selectRaw('thread_id, max(created_at) as at')->pluck('at', 'thread_id')->all(); } catch (\Throwable $e) { report($e); return []; }
  }
  /** Owner 27 Nov 2026 (20:55): the AI's automatic "the team will follow up" hand-off after a customer's order confirmation is finished once the order is approved (the confirmation notice went out) and the customer wrote nothing after it. Any other hand-off still needs a person's reply. */
  public static function answeredAt(object $t, ?string $humanAt, ?string $confAt): ?string {
    if ($confAt !== null && str_starts_with((string)($t->handoff_reason ?? ''), 'Auto: the AI told') && ($humanAt === null || $confAt > $humanAt)) return $confAt;
    return $humanAt;
  }
  /** A hand-off the team has not answered yet: no person replied after the hand-off (and after the customer's last message). The customer is waiting for US, not the other way round. */
  public static function teamDue(object $t, ?string $humanAt): bool {
    if (empty($t->handoff_at) || ($t->status ?? '') === 'resolved') return false;
    if ($humanAt === null) return true;
    if ($humanAt < (string)$t->handoff_at) return true;
    $lc = (string)($t->last_customer_message_at ?? ''); return $lc !== '' && $humanAt < $lc;
  }
  public static function cards($rows): array {
    $rows = collect($rows); if ($rows->isEmpty()) return [];
    $hoIds = $rows->filter(fn($t) => !empty($t->handoff_at) && $t->status !== 'resolved')->pluck('id')->all(); $hum = self::lastHumanOut($hoIds); $cnf = self::lastConfirmOut($hoIds);
    $objs = $rows->map(fn($t) => (object)['id'=>$t->id, 'phone'=>$t->phone ?: '-none-'.$t->id, 'total'=>0]);
    $intel = CustomerIntel::forOrders($objs);
    $phones = $rows->pluck('phone')->filter()->unique()->values();
    $oc = $phones->isEmpty() ? collect() : DB::table('orders')->whereIn('phone', $phones)->select('phone', DB::raw('count(*) n'))->groupBy('phone')->pluck('n', 'phone');
    $agents = DB::table('users')->whereIn('id', $rows->pluck('assigned_to')->filter()->unique())->pluck('name', 'id');
    // SLA: waiting since the first customer message that no human reply has followed (opening the chat never stops it).
    $ws = collect(); $slaW = 5; $slaR = 15;
    try {
      $slaW = max(1, (int)(Sms::secret('sla_warn_min') ?: 5)); $slaR = max($slaW + 1, (int)(Sms::secret('sla_red_min') ?: 15));
      $aw = $rows->filter(fn($t) => !empty($t->awaiting_reply) && $t->status !== 'resolved')->pluck('id')->all();
      if ($aw) $ws = collect(DB::select("select m.thread_id, min(m.created_at) as since from chat_messages m where m.thread_id in (".implode(',', array_map('intval', $aw)).") and m.dir = 'in' and m.id > coalesce((select max(o.id) from chat_messages o where o.thread_id = m.thread_id and o.dir = 'out' and coalesce(o.is_ai, false) = false and o.status <> 'failed'), 0) group by m.thread_id"))->pluck('since', 'thread_id');
    } catch (\Throwable $e) { report($e); }
    $srcWa = collect(); $srcPg = collect(); $srcIg = collect();
    try { $srcWa = DB::table('chat_wa_numbers')->get(['phone_id','display_phone','verified_name'])->keyBy('phone_id'); } catch (\Throwable $e) {}
    try { foreach (DB::table('chat_pages')->get(['page_id','name','ig_id','ig_username']) as $pg) { $srcPg[$pg->page_id] = $pg; if ($pg->ig_id) $srcIg[$pg->ig_id] = $pg; } } catch (\Throwable $e) {}
    $srcAcc = []; try { $j = json_decode((string)self::sec('chat_ig_accounts'), true); if (is_array($j)) $srcAcc = $j; } catch (\Throwable $e) {}
    $srcOne = $srcPg->count() === 1 ? $srcPg->first() : null;
    $mcl = \App\Support\WaCalls::missedByPhone();
    $lcl = []; try { $lcl = \App\Support\WaCalls::lastByKeys($rows->map(fn($t) => substr(preg_replace('/\D+/', '', (string)($t->phone ?: ($t->channel === 'whatsapp' ? $t->external_id : ''))), -10))->all()); } catch (\Throwable $e) {}
    return $rows->map(function ($t) use ($lcl, $intel, $oc, $agents, $ws, $slaW, $slaR, $srcWa, $srcPg, $srcIg, $srcOne, $srcAcc, $mcl, $hum, $cnf) {
      $mk = $mcl ? substr(preg_replace('/\D+/', '', (string)($t->phone ?: ($t->channel === 'whatsapp' ? $t->external_id : ''))), -10) : ''; $mc = $mk !== '' ? ($mcl[$mk] ?? null) : null;
      $lk = substr(preg_replace('/\D+/', '', (string)($t->phone ?: ($t->channel === 'whatsapp' ? $t->external_id : ''))), -10); $lc = $lk !== '' ? ($lcl[$lk] ?? null) : null;
      $i = $intel[$t->id] ?? null; $ref = $t->page_ref ?? null; $src = null;
      if ($t->channel === 'whatsapp') { $w = $ref ? ($srcWa[$ref] ?? null) : null; if (!$w && !$ref) $w = $srcWa[(string)self::sec('chat_wa_phone_id')] ?? null; $src = $w ? trim(($w->display_phone ?: $w->phone_id).($w->verified_name ? ' · '.$w->verified_name : '')) : ($ref ? $ref : 'Main number'); }
      elseif ($t->channel === 'facebook') { $g = $ref ? ($srcPg[$ref] ?? null) : $srcOne; $src = $g ? $g->name : null; }
      elseif ($t->channel === 'sms') { $src = SmsInbox::label($ref); $rn = SmsInbox::replyNote($ref); if (!str_starts_with($rn, 'Reply goes through')) $src .= ' — ' . $rn; } // staff see before answering when the reply would leave from a different sender
      elseif ($t->channel === 'instagram') { $g = $ref ? ($srcIg[$ref] ?? $srcPg[$ref] ?? null) : $srcOne; $src = $g ? ($g->ig_username ? '@'.$g->ig_username : $g->name) : null; if (!$src) { foreach ($srcAcc as $ac) if (!$ref || ($ac['id'] ?? '') === (string)$ref) { $src = !empty($ac['username']) ? '@'.$ac['username'] : null; break; } } } $n = (int)($oc[$t->phone] ?? 0) + (int)($i['legacy'] ?? 0);
      return ['team_due'=>self::teamDue($t, self::answeredAt($t, isset($hum[$t->id]) ? (string)$hum[$t->id] : null, isset($cnf[$t->id]) ? (string)$cnf[$t->id] : null)), 'id'=>$t->id, 'channel'=>$t->channel, 'channel_name'=>self::CH[$t->channel] ?? $t->channel, 'source'=>$src, 'name'=>$t->name, 'phone'=>$t->phone, 'username'=>$t->username, 'avatar'=>$t->avatar,
        'status'=>$t->status, 'awaiting'=>(bool)($t->awaiting_reply ?? false) && $t->status !== 'resolved', 'unread'=>!empty($t->awaiting_reply) ? (int)$t->unread : 0,
        'replied'=>empty($t->awaiting_reply) && !empty($t->last_successful_agent_reply_at) && !empty($t->last_customer_message_at) && $t->last_successful_agent_reply_at >= $t->last_customer_message_at,
        'stale'=>empty($t->awaiting_reply) && ($t->last_dir ?? null) === 'in' && $t->status !== 'resolved' && !empty($t->last_at) && \Carbon\Carbon::parse($t->last_at)->lt(now()->subDays(7)), 'last'=>$t->last_message, 'last_dir'=>$t->last_dir, 'last_at'=>$t->last_at ? \Carbon\Carbon::parse($t->last_at)->toIso8601String() : null,
        'ai'=>!($t->ai_paused ?? false), 'ai_lang'=>$t->ai_lang ?? null, 'ai_detected'=>$t->ai_detected ?? null, 'assigned_to'=>$t->assigned_to, 'assigned_name'=>$agents[$t->assigned_to] ?? null, 'customer_id'=>$t->customer_id,
        'cat'=>$n > 0 && $i ? $i['cat'] : 'New', 'orders'=>$n, 'risk'=>$n > 0 && $i ? $i['risk'] : 'Low',
        'intent'=>$t->intent ?? null, 'mood'=>$t->mood ?? null, 'priority'=>$t->priority ?? 'normal', 'needs_human'=>(bool)($t->needs_human ?? false), 'reported'=>!empty($t->reported_at) && empty($t->report_solved_at), 'report_solved'=>!empty($t->reported_at) && !empty($t->report_solved_at), 'report_solved_by'=>$t->report_solved_by ?? null, 'report_reason'=>$t->report_reason ?? null,
        'waiting_since'=>isset($ws[$t->id]) ? \Carbon\Carbon::parse($ws[$t->id])->toIso8601String() : null, 'sla_w'=>$slaW, 'sla_r'=>$slaR, 'handoff_at'=>!empty($t->handoff_at) ? \Carbon\Carbon::parse($t->handoff_at)->toIso8601String() : null, 'handoff_reason'=>$t->handoff_reason ?? null, 'ai_summary'=>$t->ai_summary ?? null,
        'follow_at'=>!empty($t->follow_up_at) && $t->status !== 'resolved' ? \Carbon\Carbon::parse($t->follow_up_at)->toIso8601String() : null, 'last_call'=>$lc, 'missed_call'=>$mc ? (int)$mc['n'] : 0, 'missed_call_id'=>$mc ? (int)$mc['id'] : null, 'missed_call_at'=>$mc ? $mc['at'] : null, 'follow_note'=>$t->follow_up_note ?? null, 'follow_due'=>!empty($t->follow_up_at) && $t->status !== 'resolved' && \Carbon\Carbon::parse($t->follow_up_at)->lte(now())];
    })->all();
  }
  /** Number of customers currently waiting for a staff reply (not a message count, and never reduced by opening a chat). */
  /** Owner rule 14 Nov 2026: a chat that is an order (open Order Request) lives in the Order Requests tab, not in Unread. */
  public static function notOrder($q) {
    return $q->where(fn($w) => $w->whereNull('intent')->orWhere('intent', '<>', 'order_request'))->whereRaw("not exists (select 1 from chat_order_requests r where r.thread_id = chat_threads.id and r.status = 'review')");
  }
  public static function unreadTotal(): int { try { return (int)self::notOrder(DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved'))->count(); } catch (\Throwable $e) { return 0; } }
  /** A staff member's reply was accepted by the platform: customer messages that arrived up to $t0 are answered; anything newer stays waiting. */
  public static function replied(int $tid, $t0, bool $echo = false): void {
    $left = (int)DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->where('created_at', '>', $t0)->count();
    DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->whereNull('read_at')->where('created_at', '<=', $t0)->update(['read_at'=>now()]);
    $upd = ['unread'=>$left, 'awaiting_reply'=>$left > 0, 'last_successful_agent_reply_at'=>now(), 'updated_at'=>now()];
    if ($left === 0) $upd['needs_human'] = false; // a person has answered: the "needs a person" mark is done (a hand-off stays until it is released or the chat is closed)
    DB::table('chat_threads')->where('id', $tid)->update($upd);
  }
  public static function productUrl(object $p): string { return url(\App\Http\Controllers\StoreController::url($p)); }
  public static function invoiceUrl(string $invoice): string { return URL::signedRoute('order.invoice', ['invoice'=>$invoice]); }
}
