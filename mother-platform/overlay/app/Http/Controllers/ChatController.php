<?php
namespace App\Http\Controllers;
use App\Support\Chat;
use App\Support\CustomerIntel;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller {
  const STATUSES = ['new'=>'New', 'open'=>'Open', 'waiting'=>'Waiting', 'resolved'=>'Resolved'];

  // ---------------- admin: inbox ----------------
  public function inbox() { return view('chat_inbox', ['statuses'=>self::STATUSES]); }

  public function threads(Request $r) {
    $q = DB::table('chat_threads');
    $f = (string)$r->query('f', 'all');
    if (in_array($f, ['facebook', 'instagram', 'whatsapp', 'web'])) $q->where('channel', $f);
    elseif ($f === 'unread') $q->where('awaiting_reply', true);
    elseif ($f === 'assigned') $q->where('assigned_to', auth()->id());
    elseif ($f === 'resolved') $q->where('status', 'resolved');
    if ($f !== 'resolved' && $f !== 'all') $q->where('status', '!=', 'resolved');
    if (in_array($r->query('s'), array_keys(self::STATUSES))) $q->where('status', $r->query('s'));
    if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'; $q->where(fn($w) => $w->where('name', 'ilike', $like)->orWhere('phone', 'ilike', $like)->orWhere('username', 'ilike', $like)->orWhere('last_message', 'ilike', $like)->orWhereIn('phone', fn($o) => $o->from('orders')->where('invoice', 'ilike', $like)->select('phone')->limit(50))); }
    // Unread first, then waiting for staff, then read/open, then resolved; newest message first inside each group.
    $rows = $q->orderByRaw("case when status <> 'resolved' and awaiting_reply then 1 when status <> 'resolved' and (status = 'waiting' or coalesce(needs_human, false)) then 2 when status <> 'resolved' then 3 else 4 end")->orderByDesc('last_at')->limit(120)->get();
    return response()->json(['threads'=>Chat::cards($rows), 'unread'=>Chat::unreadTotal()]);
  }

  /** Light poll used on every admin page: unread total plus threads with new incoming messages since the last id. */
  public function poll(Request $r) {
    $max = (int)DB::table('chat_messages')->max('id'); $out = ['last_id'=>$max, 'unread'=>Chat::unreadTotal(), 'items'=>[]];
    if ($r->has('since')) {
      $since = (int)$r->query('since');
      $ids = DB::table('chat_messages')->where('id', '>', $since)->where('dir', 'in')->orderBy('id')->get(['thread_id', 'body', 'attachment_url', 'attachment_type', 'created_at'])->groupBy('thread_id');
      if ($ids->isNotEmpty()) {
        $cards = collect(Chat::cards(DB::table('chat_threads')->whereIn('id', $ids->keys())->get()))->keyBy('id');
        foreach ($ids as $tid => $ms) if (isset($cards[$tid])) $out['items'][] = $cards[$tid] + ['new_count'=>$ms->count(), 'preview'=>($ms->last()->body ?: ($ms->last()->attachment_type === 'audio' ? '🎤 Voice message' : '[attachment]')), 'at'=>\Carbon\Carbon::parse($ms->last()->created_at)->toIso8601String()];
      }
    }
    return response()->json($out);
  }

  public function thread($id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    $card = Chat::cards([$t])[0];
    $msgs = DB::table('chat_messages')->where('thread_id', $id)->orderByDesc('id')->limit(61)->get()->take(60)->reverse()->values()->map(fn($m) => [
      'id'=>$m->id, 'dir'=>$m->dir, 'kind'=>$m->kind, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'by'=>$m->user_name, 'is_ai'=>(bool)($m->is_ai ?? false), 'status'=>$m->status, 'error'=>$m->error, 'read'=>!empty($m->read_at), 'transcript'=>$m->transcript ?? null,
      'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String()]);
    $hasMore = DB::table('chat_messages')->where('thread_id', $id)->count() > 60;
    $cust = $t->customer_id ? DB::table('customers')->find($t->customer_id) : ($t->phone ? DB::table('customers')->where('phone', $t->phone)->first() : null);
    $orders = $t->phone ? DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->limit(8)->get(['id', 'invoice', 'status', 'total', 'created_at', 'courier', 'consignment_id', 'tracking_code', 'courier_status'])->map(fn($o) => (array)$o + ['date'=>\Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y')]) : collect();
    $intel = null;
    if ($t->phone && ($first = DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->first(['id', 'phone', 'total']))) $intel = CustomerIntel::forOrders([$first])[$first->id] ?? null;
    return response()->json(['thread'=>$card, 'messages'=>$msgs, 'has_more'=>$hasMore, 'customer'=>$cust ? ['id'=>$cust->id, 'name'=>$cust->name, 'phone'=>$cust->phone, 'email'=>$cust->email, 'address'=>$cust->address ?? null] : null,
      'orders'=>$orders, 'intel'=>$intel ? ['delivered'=>$intel['delivered'], 'returned'=>$intel['returned'], 'cancelled'=>$intel['cancelled'], 'rate'=>$intel['rate'], 'spent'=>$intel['spent'], 'fraud'=>$intel['fraud']] : null,
      'agents'=>DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name']), 'statuses'=>self::STATUSES,
      'quick'=>DB::table('chat_quick_replies')->where('active', true)->orderBy('id')->get(['id', 'title', 'body'])]);
  }

  public function messagesAfter(Request $r, $id) {
    $map = fn($m) => [
      'id'=>$m->id, 'dir'=>$m->dir, 'kind'=>$m->kind, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'by'=>$m->user_name, 'is_ai'=>(bool)($m->is_ai ?? false), 'status'=>$m->status, 'error'=>$m->error, 'read'=>!empty($m->read_at), 'transcript'=>$m->transcript ?? null,
      'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String()];
    if ($r->query('before')) { // older history, newest-first page of 40 (lazy "load older")
      $rows = DB::table('chat_messages')->where('thread_id', $id)->where('id', '<', (int)$r->query('before'))->orderByDesc('id')->limit(41)->get();
      return response()->json(['messages'=>$rows->take(40)->reverse()->values()->map($map), 'has_more'=>$rows->count() > 40]);
    }
    $after = (int)$r->query('after');
    $m = DB::table('chat_messages')->where('thread_id', $id)->where('id', '>', $after)->orderBy('id')->get()->map($map);
    $t = DB::table('chat_threads')->find($id);
    return response()->json(['messages'=>$m, 'status'=>$t->status ?? null, 'unread'=>$t && $t->awaiting_reply ? (int)$t->unread : 0, 'awaiting'=>$t ? (bool)$t->awaiting_reply : false, 'replied'=>$t ? (!$t->awaiting_reply && !empty($t->last_successful_agent_reply_at)) : false,
      'transcripts'=>DB::table('chat_messages')->where('thread_id', $id)->where('dir', 'in')->where('attachment_type', 'audio')->whereNotNull('transcript')->orderByDesc('id')->limit(5)->get(['id', 'transcript'])]);
  }

  /** Records that a staff member opened the chat (activity data only). It NEVER clears unread: only a successful staff reply or an explicit Resolve does. */
  public function read($id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    DB::table('chat_threads')->where('id', $id)->update(['last_opened_at'=>now(), 'status'=>$t->status === 'new' ? 'open' : $t->status]);
    return response()->json(['ok'=>true, 'unread'=>Chat::unreadTotal()]);
  }

  public function reply(Request $r, $id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    // Idempotency: the same client token (double click, retry after a slow response) never sends twice.
    $tok = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$r->input('client_token', ''));
    $ck = $tok !== '' ? 'chat.reply.'.auth()->id().'.'.$id.'.'.substr($tok, 0, 40) : null;
    if ($ck && ($prev = \Illuminate\Support\Facades\Cache::get($ck))) return response()->json($prev);
    if ($ck && !\Illuminate\Support\Facades\Cache::add($ck.'.lock', 1, 20)) return response()->json(['ok'=>true, 'dup'=>true, 'id'=>0, 'error'=>null, 'ai'=>false]);
    $d = $r->validate(['body'=>'nullable|string|max:3500', 'kind'=>'nullable|in:text,product,invoice,tracking,quick', 'file'=>'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf,mp3,mpga,mpeg,m4a,ogg,oga,wav|max:8192', 'image_url'=>'nullable|string|max:400']);
    $body = trim((string)($d['body'] ?? '')); $att = null; $type = null;
    if ($f = $r->file('file')) {
      $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $ext = strtolower($f->getClientOriginalExtension() ?: 'jpg'); $mime = (string)$f->getMimeType();
      $isAudio = str_starts_with($mime, 'audio/') || in_array($ext, ['mp3', 'm4a', 'ogg', 'oga', 'wav', 'mpga'], true);
      if ($isAudio) { // a voice reply: verify the real content, then keep a safe extension
        $head = (string)file_get_contents($f->getRealPath(), false, null, 0, 16); $real = Chat::audioExt($head);
        if (!$real) return response()->json(['ok'=>false, 'error'=>'This audio file is not supported. Record again or use an MP3.'], 422);
        $ext = $real;
      }
      $name = date('ymd').'-'.bin2hex(random_bytes(6)).'.'.$ext; $f->move($dir, $name);
      $att = url('/uploads/chat/'.$name); $type = $isAudio ? 'audio' : (str_starts_with($mime, 'image/') ? 'image' : 'file');
    } elseif (!empty($d['image_url']) && str_starts_with($d['image_url'], '/')) { $att = url($d['image_url']); $type = 'image'; }
    if ($body === '' && !$att) return response()->json(['ok'=>false, 'error'=>'Write a message first.'], 422);
    $t0 = now(); // customer messages that arrive after this moment were not answered by this reply
    [$ok, $err] = Chat::deliver($t, $body, $att, $type ?? 'image');
    $mid = DB::table('chat_messages')->insertGetId(['thread_id'=>$id, 'dir'=>'out', 'kind'=>$d['kind'] ?? ($att ? 'file' : 'text'), 'body'=>$body, 'attachment_url'=>$att, 'attachment_type'=>$type,
      'user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'status'=>$ok ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
    // A failed send leaves the conversation waiting for a reply; only an accepted message answers the customer.
    $upd = ['assigned_to'=>$t->assigned_to ?: auth()->id(), 'ai_paused'=>true, 'updated_at'=>now()];
    if ($ok) $upd += ['last_message'=>mb_substr($body !== '' ? $body : '[attachment]', 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'status'=>in_array($t->status, ['new', 'open']) ? 'waiting' : $t->status];
    DB::table('chat_threads')->where('id', $id)->update($upd);
    if ($ok) Chat::replied((int)$id, $t0);
    $res = ['ok'=>$ok, 'error'=>$err, 'id'=>$mid, 'ai'=>false];
    if ($ck) \Illuminate\Support\Facades\Cache::put($ck, $res, 600);
    return response()->json($res);
  }

  /** Turn Claude's automatic replies on or off for one conversation. */
  public function ai(Request $r, $id) {
    $on = $r->boolean('on');
    DB::table('chat_threads')->where('id', $id)->update(['ai_paused'=>!$on, 'updated_at'=>now()]);
    if ($on) { $tid = (int)$id; app()->terminating(fn() => Chat::aiReply($tid)); }
    return response()->json(['ok'=>true, 'ai'=>$on]);
  }
  /** Fix the AI reply language for one conversation (auto = follow the customer). Only wording changes; facts stay the same. */
  public function lang(Request $r, $id) {
    $v = (string)$r->input('lang', 'auto'); if (!in_array($v, ['auto', 'bangla', 'english', 'banglish'], true)) return response()->json(['ok'=>false], 422);
    DB::table('chat_threads')->where('id', $id)->update(['ai_lang'=>$v === 'auto' ? null : $v, 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'lang'=>$v]);
  }
  public function assign(Request $r, $id) {
    $d = $r->validate(['user_id'=>'nullable|integer|exists:users,id']);
    DB::table('chat_threads')->where('id', $id)->update(['assigned_to'=>$d['user_id'] ?? null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function status(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:new,open,waiting,resolved']);
    // Resolve is a deliberate staff decision ("no reply needed"), so it is the only other action that clears the waiting state.
    $upd = ['status'=>$d['status'], 'updated_at'=>now()];
    if ($d['status'] === 'resolved') $upd += ['unread'=>0, 'awaiting_reply'=>false];
    DB::table('chat_threads')->where('id', $id)->update($upd);
    return response()->json(['ok'=>true, 'unread'=>Chat::unreadTotal()]);
  }
  /** Link a social conversation to a customer by phone number. */
  public function phone(Request $r, $id) {
    $d = $r->validate(['phone'=>'required|string|max:30']); $p = Chat::phone($d['phone']);
    if (!$p || strlen($p) < 10) return response()->json(['ok'=>false, 'error'=>'Enter a valid mobile number.'], 422);
    $c = DB::table('customers')->where('phone', $p)->orderBy('id')->first();
    DB::table('chat_threads')->where('id', $id)->update(['phone'=>$p, 'customer_id'=>$c->id ?? null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }

  public function products(Request $r) {
    $s = trim((string)$r->query('q')); if (mb_strlen($s) < 2) return response()->json([]);
    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%';
    $rows = DB::table('products')->where('published', true)->where(fn($w) => $w->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like))->orderBy('name')->limit(8)->get();
    return response()->json($rows->map(fn($p) => ['id'=>$p->id, 'name'=>$p->name, 'price'=>(float)$p->price, 'image'=>$p->image, 'url'=>Chat::productUrl($p)]));
  }
  /** Ready-to-send texts for an order: invoice summary or tracking link. */
  public function orderText(Request $r, $oid) {
    $o = DB::table('orders')->find($oid); abort_unless($o, 404);
    $kind = $r->query('kind') === 'tracking' ? 'tracking' : 'invoice';
    if ($kind === 'tracking') {
      $txt = "আপনার অর্ডার {$o->invoice} এর বর্তমান অবস্থা: {$o->status}".($o->courier_status ? " ({$o->courier_status})" : '').($o->tracking_code ? "\nTracking: {$o->tracking_code}" : '')."\nঅর্ডার ট্র্যাক করুন: ".url('/track?invoice='.$o->invoice);
    } else {
      $items = DB::table('order_items')->where('order_id', $o->id)->get(['name', 'qty', 'price']);
      $lines = $items->map(fn($i) => '• '.$i->name.' × '.$i->qty.' = ৳'.number_format($i->qty * $i->price))->implode("\n");
      $txt = "Invoice {$o->invoice}\n{$lines}\nডেলিভারি: ৳".number_format($o->shipping)."\nমোট: ৳".number_format($o->total)."\nইনভয়েস দেখুন: ".Chat::invoiceUrl($o->invoice);
    }
    return response()->json(['text'=>$txt, 'kind'=>$kind]);
  }

  // ---------------- admin: settings ----------------
  public function settings() {
    $has = fn($k) => (bool)Chat::sec($k);
    if (!Chat::sec('chat_verify_token')) Sms::setSecret('chat_verify_token', bin2hex(random_bytes(12)));
    return view('chat_settings', ['verify'=>Chat::sec('chat_verify_token'), 'hook'=>url('/webhooks/meta'), 'app_id'=>Chat::sec('chat_app_id'), 'redir'=>url('/admin/chat-settings/fb-callback'), 'pages'=>$this->pages(), 'has'=>['fb'=>$has('chat_fb_token') || $this->pages()->isNotEmpty(), 'ig'=>$has('chat_ig_token'), 'wa'=>$has('chat_wa_token'), 'secret'=>$has('chat_app_secret')],
      'hook_last'=>DB::table('settings')->where('key', 'chat_hook_last')->value('value'), 'wa_phone'=>Chat::sec('chat_wa_phone_id'), 'ai_chan'=>\App\Support\AiChat::chanOn(), 'ai_auto'=>\App\Support\AiChat::chatOn() || (\App\Support\Sms::secret('ai_chat_auto') !== '0'), 'web_on'=>(DB::table('settings')->where('key', 'chat_web_on')->value('value') ?? '1') === '1',
      'quick'=>DB::table('chat_quick_replies')->orderBy('id')->get(), 'counts'=>DB::table('chat_threads')->select('channel', DB::raw('count(*) n'))->groupBy('channel')->pluck('n', 'channel')]);
  }
  public function waSubscribe(Request $r) {
    $tok = Chat::sec('chat_wa_token'); $ph = Chat::sec('chat_wa_phone_id');
    if (!$tok || !$ph) return back()->with('err', 'Save the WhatsApp Cloud API token and phone number ID first.');
    $g = fn($m, $u, $q = []) => \Illuminate\Support\Facades\Http::timeout(20)->withToken($tok)->{$m}('https://graph.facebook.com/v21.0/'.$u, $q)->json();
    $waba = trim((string)$r->input('waba_id'));
    if (!$waba) {
      $d = \Illuminate\Support\Facades\Http::timeout(20)->get('https://graph.facebook.com/v21.0/debug_token', ['input_token'=>$tok, 'access_token'=>$tok])->json();
      foreach (($d['data']['granular_scopes'] ?? []) as $gs) if (($gs['scope'] ?? '') === 'whatsapp_business_management') foreach (($gs['target_ids'] ?? []) as $id) { $c = $g('get', $id.'/phone_numbers', ['fields'=>'id']); foreach (($c['data'] ?? []) as $pn) if (($pn['id'] ?? '') === $ph) $waba = $id; }
      if (!$waba) return back()->with('err', 'Could not find the WhatsApp account for this token/phone ID: '.json_encode($d['error'] ?? ($d['data']['granular_scopes'] ?? 'no scopes')).' Enter the WABA ID manually.');
    }
    $cb = url('/webhooks/meta'); $vt = Chat::sec('chat_verify_token');
    $a = $g('post', $waba.'/subscribed_apps', ['override_callback_uri'=>$cb, 'verify_token'=>$vt]);
    $p = $g('post', $ph, ['webhook_configuration'=>['override_callback_uri'=>$cb, 'verify_token'=>$vt]]);
    $b = $g('get', $waba.'/subscribed_apps');
    $pc = $g('get', $ph, ['fields'=>'webhook_configuration,display_phone_number,status']);
    $names = collect($b['data'] ?? [])->map(fn($x) => $x['whatsapp_business_api_data']['name'] ?? ($x['name'] ?? '?'))->implode(', ');
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.wa_subscribe', 'subject'=>'WhatsApp app subscribe (WABA '.$waba.')', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    // keep flash messages short: a long one overflows the response header and the proxy answers 502
    $cut = fn($v) => mb_substr((string)(is_string($v) ? $v : json_encode($v)), 0, 300);
    if (!empty($a['success'])) return back()->with('ok', 'App subscribed to WhatsApp account '.$waba.'. Subscribed apps now: '.($names ?: 'none listed').'. Phone override: '.$cut($p).'. Phone webhook config: '.$cut($pc['webhook_configuration'] ?? $pc));
    return back()->with('err', 'Subscribe failed for '.$waba.': '.$cut($a['error'] ?? $a).' Subscribed apps: '.($names ?: 'none'));
  }
  public function waRegister(Request $r) {
    $d = $r->validate(['reg_pin'=>['nullable', 'regex:/^\d{6}$/']]);
    if (empty($d['reg_pin'])) $d['reg_pin'] = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $tok = Chat::sec('chat_wa_token'); $ph = Chat::sec('chat_wa_phone_id');
    if (!$tok || !$ph) return back()->with('err', 'Save the WhatsApp Cloud API token and phone number ID first.');
    $h = fn($m, $u, $q = []) => \Illuminate\Support\Facades\Http::timeout(25)->withToken($tok)->{$m}('https://graph.facebook.com/v21.0/'.$u, $q)->json();
    $res = $h('post', $ph.'/register', ['messaging_product'=>'whatsapp', 'pin'=>$d['reg_pin']]);
    // number was used by another tool (e.g. LazyChat) that set its own two-step PIN: set ours, then register again
    if (empty($res['success']) && (int)($res['error']['code'] ?? 0) === 133005) {
      $h('post', $ph, ['pin'=>$d['reg_pin']]);
      $res = $h('post', $ph.'/register', ['messaging_product'=>'whatsapp', 'pin'=>$d['reg_pin']]);
    }
    $st = $h('get', $ph, ['fields'=>'status,code_verification_status,platform_type,throughput,display_phone_number']);
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.wa_register', 'subject'=>'WhatsApp number register attempt', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    if (!empty($res['success'])) return back()->with('ok', 'WhatsApp number registered. Status now: '.json_encode($st));
    return back()->with('err', 'Register failed: '.json_encode($res['error'] ?? $res).' Status: '.json_encode($st));
  }
  public function waCode(Request $r) {
    $tok = Chat::sec('chat_wa_token'); $ph = Chat::sec('chat_wa_phone_id');
    if (!$tok || !$ph) return back()->with('err', 'Save the WhatsApp Cloud API token and phone number ID first.');
    $h = fn($m, $u, $q = []) => \Illuminate\Support\Facades\Http::timeout(25)->withToken($tok)->{$m}('https://graph.facebook.com/v21.0/'.$u, $q)->json();
    $code = preg_replace('/\D/', '', (string)$r->input('wa_code'));
    if ($code !== '') {
      $res = $h('post', $ph.'/verify_code', ['code'=>$code]);
      return !empty($res['success']) ? back()->with('ok', 'Code verified. Now enter a 6-digit PIN and press Register WhatsApp number.') : back()->with('err', 'Verify failed: '.json_encode($res['error'] ?? $res));
    }
    $method = $r->input('code_method') === 'VOICE' ? 'VOICE' : 'SMS';
    $res = $h('post', $ph.'/request_code', ['code_method'=>$method, 'language'=>'en_US']);
    return !empty($res['success']) ? back()->with('ok', 'Verification code requested by '.$method.' to the number. Enter the 6-digit code below and press Verify.') : back()->with('err', 'Request failed: '.json_encode($res['error'] ?? $res));
  }
  public function fbSubscribe(Request $r) {
    $tok = Chat::sec('chat_fb_token');
    if (!$tok) return back()->with('err', 'Save the Facebook Page access token first.');
    $h = fn($m, $u, $q = []) => \Illuminate\Support\Facades\Http::timeout(25)->withToken($tok)->{$m}('https://graph.facebook.com/v21.0/'.$u, $q)->json();
    $pages = $h('get', 'me/accounts', ['fields'=>'id,name,access_token', 'limit'=>25]);
    $out = [];
    foreach (($pages['data'] ?? []) as $pg) {
      $pt = $pg['access_token'] ?? $tok;
      $res = \Illuminate\Support\Facades\Http::timeout(25)->withToken($pt)->post('https://graph.facebook.com/v21.0/'.$pg['id'].'/subscribed_apps', ['subscribed_fields'=>'messages,messaging_postbacks,message_reads']);
      $j = $res->json();
      $out[] = ($pg['name'] ?? $pg['id']).' ('.$pg['id'].'): '.(!empty($j['success']) ? 'subscribed' : json_encode($j['error'] ?? $j));
    }
    if (!$out) {
      $pid = trim((string)$r->input('page_id'));
      if ($pid) { $j = \Illuminate\Support\Facades\Http::timeout(25)->withToken($tok)->post('https://graph.facebook.com/v21.0/'.$pid.'/subscribed_apps', ['subscribed_fields'=>'messages,messaging_postbacks,message_reads'])->json(); $out[] = $pid.': '.(!empty($j['success']) ? 'subscribed' : json_encode($j['error'] ?? $j)); }
      else return back()->with('err', 'No pages found for this token: '.json_encode($pages['error'] ?? $pages).' Enter the Page ID below and try again.');
    }
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.fb_subscribe', 'subject'=>'Facebook page subscribe', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Facebook: '.implode(' | ', $out));
  }
  private function pages() { try { return DB::table('chat_pages')->orderBy('id')->get(['id', 'page_id', 'name', 'ig_id', 'ig_username', 'subscribed', 'note', 'updated_at']); } catch (\Throwable $e) { return collect(); } }
  /** Facebook Login: the owner signs in at Facebook and picks Pages; we never see the password. */
  public function fbConnect(Request $r) {
    $id = trim((string)Chat::sec('chat_app_id'));
    if (!$id) return redirect('/admin/chat-settings')->with('err', 'Save the Meta App ID first (Chat Settings > Meta app).');
    $st = bin2hex(random_bytes(16)); session(['fb_oauth_state'=>$st]);
    return redirect('https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query(['client_id'=>$id, 'redirect_uri'=>url('/admin/chat-settings/fb-callback'), 'state'=>$st, 'response_type'=>'code',
      'config_id'=>'1507276531459571', 'override_default_response_type'=>'true']));
  }
  public function fbCallback(Request $r) {
    $back = redirect('/admin/chat-settings');
    if ($r->query('error')) return $back->with('err', 'Facebook login was cancelled or refused: '.mb_substr((string)($r->query('error_description') ?: $r->query('error')), 0, 220));
    $st = (string)session('fb_oauth_state'); session()->forget('fb_oauth_state');
    if ($st === '' || !hash_equals($st, (string)$r->query('state'))) return $back->with('err', 'The login session expired. Press "Connect with Facebook" again.');
    $id = trim((string)Chat::sec('chat_app_id')); $sec = trim((string)Chat::sec('chat_app_secret'));
    if (!$id || !$sec) return $back->with('err', 'Save both the Meta App ID and the App secret first.');
    $G = 'https://graph.facebook.com/v21.0/'; $H = fn() => \Illuminate\Support\Facades\Http::timeout(25);
    $a = $H()->get($G.'oauth/access_token', ['client_id'=>$id, 'client_secret'=>$sec, 'redirect_uri'=>url('/admin/chat-settings/fb-callback'), 'code'=>(string)$r->query('code')])->json();
    if (empty($a['access_token'])) return $back->with('err', 'Facebook did not accept the login: '.mb_substr((string)($a['error']['message'] ?? json_encode($a)), 0, 250));
    $l = $H()->get($G.'oauth/access_token', ['grant_type'=>'fb_exchange_token', 'client_id'=>$id, 'client_secret'=>$sec, 'fb_exchange_token'=>$a['access_token']])->json();
    $ut = $l['access_token'] ?? $a['access_token'];
    [$ok, $msg] = $this->importPages($ut, $r);
    return $back->with($ok ? 'ok' : 'err', $msg);
  }
  /** Takes a user or Page token, finds every Page it manages, stores each Page token (encrypted) and subscribes the Page. Returns [ok, message]. */
  private function importPages(string $ut, Request $r): array {
    $G = 'https://graph.facebook.com/v21.0/'; $H = fn() => \Illuminate\Support\Facades\Http::timeout(25);
    $pg = $H()->withToken($ut)->get($G.'me/accounts', ['fields'=>'id,name,access_token,instagram_business_account{id,username}', 'limit'=>50])->json();
    $list = $pg['data'] ?? [];
    if (!$list) return [false, 'No Facebook Pages were returned. The token must belong to someone who manages the Pages (pages_show_list). '.mb_substr((string)($pg['error']['message'] ?? ''), 0, 200)];
    $n = 0; $bad = [];
    foreach ($list as $p) {
      if (empty($p['access_token'])) continue;
      $res = $H()->withToken($p['access_token'])->post($G.$p['id'].'/subscribed_apps', ['subscribed_fields'=>'messages,messaging_postbacks,message_reads'])->json();
      $ok = !empty($res['success']); if (!$ok) $bad[] = ($p['name'] ?? $p['id']).': '.mb_substr((string)($res['error']['message'] ?? 'subscribe failed'), 0, 120);
      DB::table('chat_pages')->updateOrInsert(['page_id'=>(string)$p['id']], ['name'=>$p['name'] ?? null, 'token'=>\Illuminate\Support\Facades\Crypt::encryptString($p['access_token']),
        'ig_id'=>$p['instagram_business_account']['id'] ?? null, 'ig_username'=>$p['instagram_business_account']['username'] ?? null, 'subscribed'=>$ok, 'note'=>$ok ? null : mb_substr(end($bad), 0, 290), 'updated_at'=>now(), 'created_at'=>now()]);
      $n++;
    }
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.fb_connect', 'subject'=>'Facebook Login: '.$n.' page(s) connected', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return [true, $n.' Facebook Page(s) connected.'.($bad ? ' Attention: '.implode(' | ', $bad) : ' Messages from these Pages now arrive in the inbox.')];
  }
  public function fbImport(Request $r) {
    $t = trim((string)$r->input('user_token'));
    if ($t === '') return back()->with('err', 'Paste the token first.');
    [$ok, $msg] = $this->importPages($t, $r);
    return back()->with($ok ? 'ok' : 'err', $msg);
  }
  public function fbDisconnect(Request $r, $id) {
    $p = DB::table('chat_pages')->find($id); if (!$p) return back();
    DB::table('chat_pages')->where('id', $id)->delete();
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.fb_disconnect', 'subject'=>'Page disconnected: '.$p->name, 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Disconnected '.$p->name.'. (Its old conversations stay in the inbox.)');
  }
  public function saveSettings(Request $r) {
    $d = $r->validate(['fb_token'=>'nullable|string|max:600', 'ig_token'=>'nullable|string|max:600', 'wa_token'=>'nullable|string|max:600', 'wa_phone_id'=>'nullable|string|max:40', 'app_secret'=>'nullable|string|max:100', 'app_id'=>'nullable|string|max:40']);
    foreach (['fb_token'=>'chat_fb_token', 'ig_token'=>'chat_ig_token', 'wa_token'=>'chat_wa_token', 'app_secret'=>'chat_app_secret', 'app_id'=>'chat_app_id', 'wa_phone_id'=>'chat_wa_phone_id'] as $k => $sk) if (!empty($d[$k])) Sms::setSecret($sk, trim($d[$k]));
    Sms::setSecret('ai_chat_auto', $r->boolean('ai_auto') ? '1' : '0');
    Sms::setSecret('ai_chat_chan', $r->boolean('ai_chan') ? '1' : '0');
    DB::table('settings')->updateOrInsert(['key'=>'chat_web_on'], ['value'=>$r->boolean('web_on') ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    \Illuminate\Support\Facades\Cache::forget('store.settings2'); \Illuminate\Support\Facades\Cache::forget('chat.web_on');
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.settings', 'subject'=>'Chat channel settings saved', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Chat settings saved.');
  }
  public function quickSave(Request $r) {
    $d = $r->validate(['id'=>'nullable|integer', 'title'=>'required|string|max:80', 'body'=>'required|string|max:1500']);
    if (!empty($d['id'])) DB::table('chat_quick_replies')->where('id', $d['id'])->update(['title'=>$d['title'], 'body'=>$d['body'], 'updated_at'=>now()]);
    else DB::table('chat_quick_replies')->insert(['title'=>$d['title'], 'body'=>$d['body'], 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Quick reply saved.');
  }
  public function quickDelete($id) { DB::table('chat_quick_replies')->where('id', $id)->delete(); return back()->with('ok', 'Quick reply removed.'); }

  // ---------------- public: website live chat ----------------
  private function webOn(): bool { return (DB::table('settings')->where('key', 'chat_web_on')->value('value') ?? '1') === '1'; }
  public function webStart(Request $r) {
    abort_unless($this->webOn(), 404);
    \App\Support\PhoneRule::trim($r);
    $d = $r->validate(['name'=>'nullable|string|max:60', 'phone'=>['required','string',new \App\Support\PhoneRule]]);
    $ph = Chat::phone($d['phone']);
    if (!$ph || !preg_match('/^01\d{9}$/', $ph)) return response()->json(['error'=>'Enter a valid mobile number.'], 422);
    // a saved customer is recognised by number; the visitor is never shown what we hold, only the admin sees it
    $cu = DB::table('customers')->where('phone', $ph)->orderBy('id')->first();
    $name = trim(strip_tags((string)($d['name'] ?? ''))) ?: ($cu->name ?? null) ?: DB::table('orders')->where('phone', $ph)->orderByDesc('id')->value('customer_name') ?: 'Visitor '.substr($ph, -4);
    $tok = bin2hex(random_bytes(16));
    DB::table('chat_threads')->insert(['channel'=>'web', 'external_id'=>$tok, 'name'=>mb_substr($name, 0, 60), 'phone'=>$ph, 'customer_id'=>$cu->id ?? null, 'status'=>'new', 'unread'=>0, 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['token'=>$tok]);
  }
  private function webThread(string $tok): object { $t = DB::table('chat_threads')->where(['channel'=>'web', 'external_id'=>$tok])->first(); abort_unless($t, 404); return $t; }
  public function webSend(Request $r) {
    abort_unless($this->webOn(), 404);
    $d = $r->validate(['token'=>'required|string|size:32', 'body'=>'required|string|max:1000']);
    $t = $this->webThread($d['token']);
    $th = Chat::ingest('web', $d['token'], null, strip_tags($d['body']));
    if ($th && !$th->ai_paused) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
    $m = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->orderByDesc('id')->first(['id', 'created_at']);
    return response()->json(['ok'=>true, 'id'=>$m->id ?? null, 'at'=>$m ? \Carbon\Carbon::parse($m->created_at)->toIso8601String() : null]);
  }
  public function webUpload(Request $r) {
    abort_unless($this->webOn(), 404);
    $r->validate(['token'=>'required|string|size:32', 'file'=>'required|file|mimes:jpg,jpeg,png,webp,gif|max:5120']);
    $t = $this->webThread($r->input('token')); $f = $r->file('file');
    $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'c-'.date('ymd').'-'.bin2hex(random_bytes(6)).'.'.strtolower($f->getClientOriginalExtension() ?: 'jpg'); $f->move($dir, $name);
    $url = url('/uploads/chat/'.$name);
    Chat::ingest('web', $t->external_id, null, '', $url);
    $m = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->orderByDesc('id')->first(['id', 'created_at']);
    return response()->json(['ok'=>true, 'id'=>$m->id ?? null, 'url'=>$url, 'at'=>$m ? \Carbon\Carbon::parse($m->created_at)->toIso8601String() : null]);
  }
  public function webPoll(Request $r) {
    $t = $this->webThread((string)$r->query('token'));
    $m = DB::table('chat_messages')->where('thread_id', $t->id)->where('id', '>', (int)$r->query('after'))->orderBy('id')->get(['id', 'dir', 'body', 'attachment_url', 'attachment_type', 'created_at', 'read_at', 'user_name'])
      ->map(fn($m) => ['id'=>$m->id, 'dir'=>$m->dir, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String(), 'seen'=>(bool)$m->read_at]);
    $seen = (int)DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->whereNotNull('read_at')->max('id');
    return response()->json(['messages'=>$m, 'seen_upto'=>$seen, 'closed'=>$t->status === 'resolved']);
  }
  /** Status of the visitor's own order: released only when invoice and mobile number both match. */
  public function webOrder(Request $r) {
    $inv = \App\Support\Inv::candidates((string)$r->query('invoice'));
    $ph = Chat::phone($r->query('phone'));
    $o = $ph && $inv ? DB::table('orders')->whereIn('invoice', $inv)->first(['invoice', 'status', 'total', 'phone', 'courier_status', 'tracking_code', 'created_at']) : null;
    if (!$o || Chat::phone($o->phone) !== $ph) return response()->json(['found'=>false]);
    return response()->json(['found'=>true, 'invoice'=>$o->invoice, 'status'=>$o->status, 'total'=>(float)$o->total, 'courier_status'=>$o->courier_status, 'url'=>\App\Support\Inv::trackUrl($o->invoice)]);
  }

  // ---------------- public: Meta webhook (Messenger, Instagram, WhatsApp Cloud API) ----------------
  public function metaVerify(Request $r) {
    $tok = Chat::sec('chat_verify_token');
    if ($tok && $r->query('hub_mode') === 'subscribe' && hash_equals($tok, (string)$r->query('hub_verify_token'))) return response((string)$r->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    return response('Forbidden', 403);
  }
  public function metaHook(Request $r) {
    $secret = Chat::sec('chat_app_secret'); $raw = $r->getContent();
    $note = function ($t) { try { DB::table('settings')->updateOrInsert(['key'=>'chat_hook_last'], ['value'=>now()->timezone('Asia/Dhaka')->format('d M H:i:s').' — '.$t]); } catch (\Throwable $e) {} };
    if (!$secret) { $note('Webhook received, but the App secret is not saved in Chat Settings, so it was rejected.'); return response('Bad signature', 403); }
    if (!hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), (string)$r->header('X-Hub-Signature-256'))) { $note('Webhook received, but the signature did not match. The App secret saved here is different from the one in the Meta app (App settings > Basic).'); return response('Bad signature', 403); }
    $j = json_decode($raw, true) ?: []; $obj = $j['object'] ?? '';
    $note('Webhook received and accepted (object: '.($obj ?: 'none').', '.count($j['entry'] ?? []).' entries).');
    try {
      foreach ($j['entry'] ?? [] as $e) {
        if ($obj === 'page' || $obj === 'instagram') {
          $ch = $obj === 'page' ? 'facebook' : 'instagram';
          foreach ($e['messaging'] ?? [] as $m) {
            if (!empty($m['message']['is_echo'])) { // a staff member answered from the Page inbox app: that counts as a human reply (our own API sends carry our app id and are ignored)
              $aid = (string)($m['message']['app_id'] ?? '');
              if (($aid === '' || $aid === '263902037430900') && !empty($m['recipient']['id']) && ($tt = DB::table('chat_threads')->where(['channel'=>$ch, 'external_id'=>(string)$m['recipient']['id']])->first())
                && !DB::table('chat_messages')->where('ext_id', $m['message']['mid'] ?? '-')->exists()) {
                $eb = (string)($m['message']['text'] ?? ''); if ($eb === '') $eb = '[attachment]';
                DB::table('chat_messages')->insert(['thread_id'=>$tt->id, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($eb, 0, 4000), 'ext_id'=>$m['message']['mid'] ?? null, 'user_name'=>'Page inbox', 'status'=>'sent', 'created_at'=>now(), 'updated_at'=>now()]);
                DB::table('chat_threads')->where('id', $tt->id)->update(['last_message'=>mb_substr($eb, 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'ai_paused'=>true]); // a person answered from the Page inbox: the AI steps back
                Chat::replied((int)$tt->id, now());
              }
              continue;
            }
            if (empty($m['message']) || empty($m['sender']['id'])) continue;
            $msg = $m['message']; $att = null; $atype = 'image';
            foreach ($msg['attachments'] ?? [] as $a) if (!empty($a['payload']['url'])) { $att = $a['payload']['url']; $atype = in_array($a['type'] ?? '', ['audio', 'video', 'image', 'file'], true) ? $a['type'] : 'image'; break; }
            if ($att && $atype === 'audio') $att = Chat::mirrorAudio($att) ?: $att;
            $body = (string)($msg['text'] ?? ''); if ($body === '' && !$att) $body = '[unsupported message]';
            $th = Chat::ingest($ch, (string)$m['sender']['id'], null, $body, $att, $msg['mid'] ?? null, ['page_ref'=>(string)($e['id'] ?? ''), 'att_type'=>$atype]);
            if ($th && $att && $atype === 'audio' && ($vid = DB::table('chat_messages')->where('ext_id', $msg['mid'] ?? '-')->value('id'))) { $vid = (int)$vid; app()->terminating(fn() => Chat::transcribe($vid)); }
            if ($th && $ch === 'facebook' && !$th->ai_paused && !($att && $atype !== 'image')) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
          }
        } elseif ($obj === 'whatsapp_business_account') {
          foreach ($e['changes'] ?? [] as $c) {
            $v = $c['value'] ?? []; $names = [];
            foreach ($v['contacts'] ?? [] as $ct) $names[$ct['wa_id'] ?? ''] = $ct['profile']['name'] ?? null;
            foreach ($v['statuses'] ?? [] as $stt) \App\Support\OrderWaConfirm::status((array)$stt);
            foreach ($v['messages'] ?? [] as $m) {
              $from = (string)($m['from'] ?? ''); if ($from === '') continue;
              $type = $m['type'] ?? 'text';
              $body = $type === 'text' ? (string)($m['text']['body'] ?? '') : ($type === 'button' ? (string)($m['button']['text'] ?? '') : '['.$type.' received — open WhatsApp to view]');
              $att = null; $meta = [];
              if (in_array($type, ['image', 'sticker', 'document', 'audio', 'video'], true) && !empty($m[$type]['id']) && !DB::table('chat_messages')->where('ext_id', $m['id'] ?? '-')->exists()) {
                $got = Chat::waMedia((string)$m[$type]['id']);
                if ($got) { $att = $got[0]; $meta['att_type'] = $got[1]; $cap = trim((string)($m[$type]['caption'] ?? '')); $body = $cap !== '' ? $cap : ['image'=>'', 'sticker'=>'', 'document'=>(string)($m[$type]['filename'] ?? ''), 'audio'=>'', 'video'=>''][$type]; }
              }
              if (!$att && !empty($m['image']['caption'])) $body = $m['image']['caption'].' [image]';
              $th = Chat::ingest('whatsapp', $from, $names[$from] ?? null, $body, $att, $m['id'] ?? null, $meta);
              if ($th && $att && ($meta['att_type'] ?? '') === 'audio' && ($vid = DB::table('chat_messages')->where('ext_id', $m['id'] ?? '-')->value('id'))) { $vid = (int)$vid; app()->terminating(fn() => Chat::transcribe($vid)); }
              if ($th && !$th->ai_paused && !($att && ($meta['att_type'] ?? 'image') !== 'image') && in_array($type, ['text', 'button', 'image'], true)) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
            }
          }
        }
      }
    } catch (\Throwable $e) { \Log::error('chat webhook: '.$e->getMessage()); }
    return response('OK', 200);
  }
}
