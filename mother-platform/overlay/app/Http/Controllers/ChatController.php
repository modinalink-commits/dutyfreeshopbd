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
    elseif ($f === 'unread') $q->where('unread', '>', 0);
    elseif ($f === 'assigned') $q->where('assigned_to', auth()->id());
    elseif ($f === 'resolved') $q->where('status', 'resolved');
    if ($f !== 'resolved' && $f !== 'all') $q->where('status', '!=', 'resolved');
    if (in_array($r->query('s'), array_keys(self::STATUSES))) $q->where('status', $r->query('s'));
    if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'; $q->where(fn($w) => $w->where('name', 'ilike', $like)->orWhere('phone', 'ilike', $like)->orWhere('username', 'ilike', $like)->orWhere('last_message', 'ilike', $like)); }
    $rows = $q->orderByDesc('last_at')->limit(120)->get();
    return response()->json(['threads'=>Chat::cards($rows), 'unread'=>Chat::unreadTotal()]);
  }

  /** Light poll used on every admin page: unread total plus threads with new incoming messages since the last id. */
  public function poll(Request $r) {
    $max = (int)DB::table('chat_messages')->max('id'); $out = ['last_id'=>$max, 'unread'=>Chat::unreadTotal(), 'items'=>[]];
    if ($r->has('since')) {
      $since = (int)$r->query('since');
      $ids = DB::table('chat_messages')->where('id', '>', $since)->where('dir', 'in')->orderBy('id')->get(['thread_id', 'body', 'attachment_url', 'created_at'])->groupBy('thread_id');
      if ($ids->isNotEmpty()) {
        $cards = collect(Chat::cards(DB::table('chat_threads')->whereIn('id', $ids->keys())->get()))->keyBy('id');
        foreach ($ids as $tid => $ms) if (isset($cards[$tid])) $out['items'][] = $cards[$tid] + ['new_count'=>$ms->count(), 'preview'=>($ms->last()->body ?: '[attachment]'), 'at'=>\Carbon\Carbon::parse($ms->last()->created_at)->toIso8601String()];
      }
    }
    return response()->json($out);
  }

  public function thread($id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    $card = Chat::cards([$t])[0];
    $msgs = DB::table('chat_messages')->where('thread_id', $id)->orderByDesc('id')->limit(250)->get()->reverse()->values()->map(fn($m) => [
      'id'=>$m->id, 'dir'=>$m->dir, 'kind'=>$m->kind, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'by'=>$m->user_name, 'is_ai'=>(bool)($m->is_ai ?? false), 'status'=>$m->status, 'error'=>$m->error,
      'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String()]);
    $cust = $t->customer_id ? DB::table('customers')->find($t->customer_id) : ($t->phone ? DB::table('customers')->where('phone', $t->phone)->first() : null);
    $orders = $t->phone ? DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->limit(8)->get(['id', 'invoice', 'status', 'total', 'created_at', 'courier', 'consignment_id', 'tracking_code', 'courier_status'])->map(fn($o) => (array)$o + ['date'=>\Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y')]) : collect();
    $intel = null;
    if ($t->phone && ($first = DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->first(['id', 'phone', 'total']))) $intel = CustomerIntel::forOrders([$first])[$first->id] ?? null;
    return response()->json(['thread'=>$card, 'messages'=>$msgs, 'customer'=>$cust ? ['id'=>$cust->id, 'name'=>$cust->name, 'phone'=>$cust->phone, 'email'=>$cust->email, 'address'=>$cust->address ?? null] : null,
      'orders'=>$orders, 'intel'=>$intel ? ['delivered'=>$intel['delivered'], 'returned'=>$intel['returned'], 'cancelled'=>$intel['cancelled'], 'rate'=>$intel['rate'], 'spent'=>$intel['spent'], 'fraud'=>$intel['fraud']] : null,
      'agents'=>DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name']), 'statuses'=>self::STATUSES,
      'quick'=>DB::table('chat_quick_replies')->where('active', true)->orderBy('id')->get(['id', 'title', 'body'])]);
  }

  public function messagesAfter(Request $r, $id) {
    $after = (int)$r->query('after');
    $m = DB::table('chat_messages')->where('thread_id', $id)->where('id', '>', $after)->orderBy('id')->get()->map(fn($m) => [
      'id'=>$m->id, 'dir'=>$m->dir, 'kind'=>$m->kind, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'by'=>$m->user_name, 'status'=>$m->status, 'error'=>$m->error,
      'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String()]);
    $t = DB::table('chat_threads')->find($id);
    return response()->json(['messages'=>$m, 'status'=>$t->status ?? null]);
  }

  public function read($id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    DB::table('chat_messages')->where('thread_id', $id)->where('dir', 'in')->whereNull('read_at')->update(['read_at'=>now()]);
    DB::table('chat_threads')->where('id', $id)->update(['unread'=>0, 'status'=>$t->status === 'new' ? 'open' : $t->status, 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'unread'=>Chat::unreadTotal()]);
  }

  public function reply(Request $r, $id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    $d = $r->validate(['body'=>'nullable|string|max:3500', 'kind'=>'nullable|in:text,product,invoice,tracking,quick', 'file'=>'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf|max:8192', 'image_url'=>'nullable|string|max:400']);
    $body = trim((string)($d['body'] ?? '')); $att = null; $type = null;
    if ($f = $r->file('file')) {
      $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $ext = strtolower($f->getClientOriginalExtension() ?: 'jpg'); $mime = $f->getMimeType();
      $name = date('ymd').'-'.bin2hex(random_bytes(6)).'.'.$ext; $f->move($dir, $name);
      $att = url('/uploads/chat/'.$name); $type = str_starts_with((string)$mime, 'image/') ? 'image' : 'file';
    } elseif (!empty($d['image_url']) && str_starts_with($d['image_url'], '/')) { $att = url($d['image_url']); $type = 'image'; }
    if ($body === '' && !$att) return response()->json(['ok'=>false, 'error'=>'Write a message first.'], 422);
    [$ok, $err] = Chat::deliver($t, $body, $att, $type ?? 'image');
    $mid = DB::table('chat_messages')->insertGetId(['thread_id'=>$id, 'dir'=>'out', 'kind'=>$d['kind'] ?? ($att ? 'file' : 'text'), 'body'=>$body, 'attachment_url'=>$att, 'attachment_type'=>$type,
      'user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'status'=>$ok ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
    DB::table('chat_threads')->where('id', $id)->update(['last_message'=>mb_substr($body !== '' ? $body : '[attachment]', 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'unread'=>0,
      'status'=>in_array($t->status, ['new', 'open']) ? 'waiting' : $t->status, 'assigned_to'=>$t->assigned_to ?: auth()->id(), 'ai_paused'=>true, 'updated_at'=>now()]);
    return response()->json(['ok'=>$ok, 'error'=>$err, 'id'=>$mid, 'ai'=>false]);
  }

  /** Turn Claude's automatic replies on or off for one conversation. */
  public function ai(Request $r, $id) {
    $on = $r->boolean('on');
    DB::table('chat_threads')->where('id', $id)->update(['ai_paused'=>!$on, 'updated_at'=>now()]);
    if ($on) { $tid = (int)$id; app()->terminating(fn() => Chat::aiReply($tid)); }
    return response()->json(['ok'=>true, 'ai'=>$on]);
  }
  public function assign(Request $r, $id) {
    $d = $r->validate(['user_id'=>'nullable|integer|exists:users,id']);
    DB::table('chat_threads')->where('id', $id)->update(['assigned_to'=>$d['user_id'] ?? null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function status(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:new,open,waiting,resolved']);
    DB::table('chat_threads')->where('id', $id)->update(['status'=>$d['status'], 'unread'=>$d['status'] === 'resolved' ? 0 : DB::raw('unread'), 'updated_at'=>now()]);
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
    return view('chat_settings', ['verify'=>Chat::sec('chat_verify_token'), 'hook'=>url('/webhooks/meta'), 'has'=>['fb'=>$has('chat_fb_token'), 'ig'=>$has('chat_ig_token'), 'wa'=>$has('chat_wa_token'), 'secret'=>$has('chat_app_secret')],
      'wa_phone'=>Chat::sec('chat_wa_phone_id'), 'ai_auto'=>\App\Support\AiChat::chatOn() || (\App\Support\Sms::secret('ai_chat_auto') !== '0'), 'web_on'=>(DB::table('settings')->where('key', 'chat_web_on')->value('value') ?? '1') === '1',
      'quick'=>DB::table('chat_quick_replies')->orderBy('id')->get(), 'counts'=>DB::table('chat_threads')->select('channel', DB::raw('count(*) n'))->groupBy('channel')->pluck('n', 'channel')]);
  }
  public function saveSettings(Request $r) {
    $d = $r->validate(['fb_token'=>'nullable|string|max:600', 'ig_token'=>'nullable|string|max:600', 'wa_token'=>'nullable|string|max:600', 'wa_phone_id'=>'nullable|string|max:40', 'app_secret'=>'nullable|string|max:100']);
    foreach (['fb_token'=>'chat_fb_token', 'ig_token'=>'chat_ig_token', 'wa_token'=>'chat_wa_token', 'app_secret'=>'chat_app_secret', 'wa_phone_id'=>'chat_wa_phone_id'] as $k => $sk) if (!empty($d[$k])) Sms::setSecret($sk, trim($d[$k]));
    Sms::setSecret('ai_chat_auto', $r->boolean('ai_auto') ? '1' : '0');
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
    $d = $r->validate(['name'=>'nullable|string|max:60', 'phone'=>'required|string|max:20']);
    $ph = Chat::phone($d['phone']);
    if (!$ph || !preg_match('/^01[3-9]\d{8}$/', $ph)) return response()->json(['error'=>'Enter a valid mobile number.'], 422);
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
    $inv = strtoupper(trim((string)$r->query('invoice'))); if (ctype_digit($inv)) $inv = 'INV-'.str_pad($inv, 6, '0', STR_PAD_LEFT);
    $ph = Chat::phone($r->query('phone'));
    $o = $ph && $inv ? DB::table('orders')->where('invoice', $inv)->first(['invoice', 'status', 'total', 'phone', 'courier_status', 'tracking_code', 'created_at']) : null;
    if (!$o || Chat::phone($o->phone) !== $ph) return response()->json(['found'=>false]);
    return response()->json(['found'=>true, 'invoice'=>$o->invoice, 'status'=>$o->status, 'total'=>(float)$o->total, 'courier_status'=>$o->courier_status, 'url'=>url('/track?invoice='.$o->invoice)]);
  }

  // ---------------- public: Meta webhook (Messenger, Instagram, WhatsApp Cloud API) ----------------
  public function metaVerify(Request $r) {
    $tok = Chat::sec('chat_verify_token');
    if ($tok && $r->query('hub_mode') === 'subscribe' && hash_equals($tok, (string)$r->query('hub_verify_token'))) return response((string)$r->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    return response('Forbidden', 403);
  }
  public function metaHook(Request $r) {
    $secret = Chat::sec('chat_app_secret'); $raw = $r->getContent();
    if (!$secret || !hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), (string)$r->header('X-Hub-Signature-256'))) return response('Bad signature', 403);
    $j = json_decode($raw, true) ?: []; $obj = $j['object'] ?? '';
    try {
      foreach ($j['entry'] ?? [] as $e) {
        if ($obj === 'page' || $obj === 'instagram') {
          $ch = $obj === 'page' ? 'facebook' : 'instagram';
          foreach ($e['messaging'] ?? [] as $m) {
            if (empty($m['message']) || !empty($m['message']['is_echo']) || empty($m['sender']['id'])) continue;
            $msg = $m['message']; $att = null;
            foreach ($msg['attachments'] ?? [] as $a) if (!empty($a['payload']['url'])) { $att = $a['payload']['url']; break; }
            $body = (string)($msg['text'] ?? ''); if ($body === '' && !$att) $body = '[unsupported message]';
            Chat::ingest($ch, (string)$m['sender']['id'], null, $body, $att, $msg['mid'] ?? null);
          }
        } elseif ($obj === 'whatsapp_business_account') {
          foreach ($e['changes'] ?? [] as $c) {
            $v = $c['value'] ?? []; $names = [];
            foreach ($v['contacts'] ?? [] as $ct) $names[$ct['wa_id'] ?? ''] = $ct['profile']['name'] ?? null;
            foreach ($v['messages'] ?? [] as $m) {
              $from = (string)($m['from'] ?? ''); if ($from === '') continue;
              $type = $m['type'] ?? 'text';
              $body = $type === 'text' ? (string)($m['text']['body'] ?? '') : ($type === 'button' ? (string)($m['button']['text'] ?? '') : '['.$type.' received — open WhatsApp to view]');
              if (!empty($m['image']['caption'])) $body = $m['image']['caption'].' [image]';
              Chat::ingest('whatsapp', $from, $names[$from] ?? null, $body, null, $m['id'] ?? null);
            }
          }
        }
      }
    } catch (\Throwable $e) { \Log::error('chat webhook: '.$e->getMessage()); }
    return response('OK', 200);
  }
}
