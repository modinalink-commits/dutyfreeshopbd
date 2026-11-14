<?php
namespace App\Http\Controllers;
use App\Support\Chat;
use App\Support\CustomerIntel;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller {
  const STATUSES = ['new'=>'New', 'open'=>'Open', 'waiting'=>'Waiting for customer', 'resolved'=>'Closed'];

  // ---------------- admin: inbox ----------------
  public function inbox() { return view('chat_inbox', ['statuses'=>self::STATUSES]); }

  public function threads(Request $r) {
    \App\Support\OpcacheGuard::tick();
    $q = DB::table('chat_threads');
    $f = (string)$r->query('f', 'all');
    if (in_array($f, ['facebook', 'instagram', 'whatsapp', 'web'])) $q->where('channel', $f);
    elseif ($f === 'unread' || $f === 'late') $q->where('awaiting_reply', true);
    elseif ($f === 'upset') $q->where('awaiting_reply', true)->whereIn(DB::raw('lower(coalesce(mood, \'\'))'), ['upset', 'angry', 'urgent']);
    elseif ($f === 'assigned') $q->where('assigned_to', auth()->id());
    elseif ($f === 'human') Chat::humanScope($q);
    elseif ($f === 'resolved') $q->where('status', 'resolved');
    elseif ($f === 'report') $q->whereNotNull('reported_at')->where(fn($w) => $w->whereNull('report_solved_at')->orWhere('report_solved_at', '>=', now()->subDays(30)));
    if ($f !== 'resolved' && $f !== 'all' && $f !== 'report') $q->where('status', '!=', 'resolved');
    if (in_array($r->query('s'), array_keys(self::STATUSES))) $q->where('status', $r->query('s'));
    if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'; $q->where(fn($w) => $w->where('name', 'ilike', $like)->orWhere('phone', 'ilike', $like)->orWhere('username', 'ilike', $like)->orWhere('last_message', 'ilike', $like)->orWhereIn('phone', fn($o) => $o->from('orders')->where('invoice', 'ilike', $like)->select('phone')->limit(50))); }
    // Unread first, then waiting for staff, then read/open, then resolved; newest message first inside each group.
    $rows = $q->orderByRaw("case when status <> 'resolved' and awaiting_reply then 1 when status <> 'resolved' and (status = 'waiting' or coalesce(needs_human, false)) then 2 when status <> 'resolved' then 3 else 4 end")->orderByDesc('last_at')->limit(120)->get();
    $orq = 0; try { $orq = (int)DB::table('chat_order_requests')->where('status', 'review')->count(); } catch (\Throwable $e) {}
    $hum = 0; try { $hum = (int)Chat::humanScope(DB::table('chat_threads'))->count(); } catch (\Throwable $e) {}
    $rep = 0; try { $rep = (int)DB::table('chat_threads')->whereNotNull('reported_at')->whereNull('report_solved_at')->count(); } catch (\Throwable $e) {}
    // Inbox v2: which waiting chats need a person (the rest were answered by the AI and wait on the customer). Display only; the Awaiting rule is unchanged.
    $cards = Chat::cards($rows); $pp = [];
    try { $ids = $rows->pluck('id')->all(); if ($ids) $pp = array_flip(Chat::humanScope(DB::table('chat_threads')->whereIn('id', $ids))->pluck('id')->all()); } catch (\Throwable $e) {}
    foreach ($cards as &$c) $c['person'] = isset($pp[$c['id']]); unset($c);
    $unread = Chat::unreadTotal();
    // today's service numbers (Dhaka day), cached for a minute
    $svc = \Illuminate\Support\Facades\Cache::remember('chat.svc', 60, function () { try { $d = now('Asia/Dhaka')->startOfDay()->utc(); $r = DB::table('chat_messages')->where('created_at', '>=', $d)->selectRaw("count(*) filter (where dir = 'in') inc, count(*) filter (where dir = 'out' and status <> 'failed' and coalesce(is_ai, false)) ai, count(*) filter (where dir = 'out' and status <> 'failed' and not coalesce(is_ai, false)) nonai")->first(); $nm = DB::table('users')->pluck('name')->filter()->map(fn ($n) => trim((string)$n))->unique()->values()->all(); $st = $nm ? (int)DB::table('chat_messages')->where('created_at', '>=', $d)->where('dir', 'out')->where('status', '<>', 'failed')->whereRaw('not coalesce(is_ai, false)')->whereIn('user_name', $nm)->count() : 0; return ['in'=>(int)$r->inc, 'ai'=>(int)$r->ai, 'staff'=>$st, 'other'=>max(0, (int)$r->nonai - $st)]; } catch (\Throwable $e) { return null; } });
    // Staff reply share (14 Nov 2026): who is answering customers. Only real staff accounts that sent at least one reply appear; AI and failed sends are not counted. Share is by customers answered, so many short replies to one customer do not inflate it.
    $share = \Illuminate\Support\Facades\Cache::remember('chat.share2', 120, function () { try {
      $names = DB::table('users')->pluck('name')->filter()->map(fn ($n) => trim((string)$n))->unique()->values()->all(); if (!$names) return null;
      $get = function ($from) use ($names) { return DB::table('chat_messages')->where('created_at', '>=', $from)->where('dir', 'out')->where('status', '<>', 'failed')->whereRaw('not coalesce(is_ai, false)')->whereIn('user_name', $names)->groupBy('user_name')->selectRaw('user_name n, count(*) m, count(distinct thread_id) c')->orderByDesc('c')->orderByDesc('m')->limit(12)->get()->map(fn ($r) => ['n'=>(string)$r->n, 'm'=>(int)$r->m, 'c'=>(int)$r->c])->all(); };
      // Typical wait before a staff reply: from the customer's first unanswered message to the staff reply (median, so one very old chat does not distort it). Replies sent with no customer message waiting are left out.
      $wait = function ($from) use ($names) { try { $in = implode(',', array_fill(0, count($names), '?'));
        $rows = DB::select("select n, percentile_cont(0.5) within group (order by w) med, count(*) k from (select o.user_name n, extract(epoch from (o.created_at - (select min(i.created_at) from chat_messages i where i.thread_id = o.thread_id and i.dir = 'in' and i.created_at <= o.created_at and i.created_at > coalesce((select max(p.created_at) from chat_messages p where p.thread_id = o.thread_id and p.dir = 'out' and p.status <> 'failed' and p.created_at < o.created_at), timestamp '2000-01-01')))) w from chat_messages o where o.created_at >= ? and o.dir = 'out' and o.status <> 'failed' and not coalesce(o.is_ai, false) and o.user_name in ($in)) x where w is not null group by rollup(n)", array_merge([$from], $names));
        $o = ['all'=>null, 'by'=>[]]; foreach ($rows as $r) { if ($r->n === null) $o['all'] = ['s'=>(int)round($r->med), 'k'=>(int)$r->k]; else $o['by'][(string)$r->n] = ['s'=>(int)round($r->med), 'k'=>(int)$r->k]; } return $o; } catch (\Throwable $e) { return null; } };
      $d = now('Asia/Dhaka')->startOfDay(); return ['d1'=>$get($d->copy()->utc()), 'd7'=>$get($d->copy()->subDays(6)->utc()), 'w1'=>$wait($d->copy()->utc()), 'w7'=>$wait($d->copy()->subDays(6)->utc())];
    } catch (\Throwable $e) { return null; } });
    return response()->json(['threads'=>$cards, 'unread'=>$unread, 'order_requests'=>$orq, 'human'=>$hum, 'reports'=>$rep, 'ai_handled'=>max(0, $unread - $hum), 'svc'=>$svc, 'share'=>$share]);
  }

  /** Light poll used on every admin page: unread total plus threads with new incoming messages since the last id. */
  public function poll(Request $r) {
    $max = (int)DB::table('chat_messages')->max('id'); $out = ['last_id'=>$max, 'unread'=>Chat::unreadTotal(), 'items'=>[], 'build'=>substr(md5((string)@filemtime(resource_path('views/chat_widget.blade.php')).(string)@filemtime(resource_path('views/chat_inbox.blade.php')).(string)@filemtime(resource_path('views/layout.blade.php'))), 0, 8)];
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
      'id'=>$m->id, 'dir'=>$m->dir, 'kind'=>$m->kind, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'by'=>$m->user_name, 'is_ai'=>(bool)($m->is_ai ?? false), 'status'=>$m->status, 'error'=>$m->error, 'read'=>!empty($m->read_at), 'transcript'=>$m->transcript ?? null, 'cards'=>Chat::productCards($m->body),
      'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String()]);
    $hasMore = DB::table('chat_messages')->where('thread_id', $id)->count() > 60;
    $cust = $t->customer_id ? DB::table('customers')->find($t->customer_id) : ($t->phone ? DB::table('customers')->where('phone', $t->phone)->first() : null);
    $orders = $t->phone ? DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->limit(8)->get(['id', 'invoice', 'status', 'total', 'created_at', 'courier', 'consignment_id', 'tracking_code', 'courier_status'])->map(fn($o) => (array)$o + ['at'=>\Carbon\Carbon::parse($o->created_at)->toIso8601String(), 'date'=>\Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y')]) : collect();
    $intel = null;
    if ($t->phone && ($first = DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->first(['id', 'phone', 'total']))) $intel = CustomerIntel::forOrders([$first])[$first->id] ?? null;
    return response()->json(['thread'=>$card, 'messages'=>$msgs, 'has_more'=>$hasMore, 'customer'=>$cust ? ['id'=>$cust->id, 'name'=>$cust->name, 'phone'=>$cust->phone, 'email'=>$cust->email, 'address'=>$cust->address ?? null] : null,
      'orders'=>$orders, 'intel'=>$intel ? ['delivered'=>$intel['delivered'], 'returned'=>$intel['returned'], 'cancelled'=>$intel['cancelled'], 'rate'=>$intel['rate'], 'spent'=>$intel['spent'], 'fraud'=>$intel['fraud']] : null,
      'agents'=>DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name']), 'statuses'=>self::STATUSES,
      'me'=>(string)(auth()->user()->name ?? ''),
      'hint'=>\Illuminate\Support\Facades\Cache::get('ai.hint.'.$id), 'payhint'=>\Illuminate\Support\Facades\Cache::get('ai.pay.'.$id),
      'notes'=>self::notes((int)$id),
      'quick'=>DB::table('chat_quick_replies')->where('active', true)->orderBy('id')->get(['id', 'title', 'body'])]);
  }
  /** Staff-only internal notes of one conversation (never sent to the customer, never given to the AI). */
  private static function notes(int $tid): array {
    try { return DB::table('chat_notes')->where('thread_id', $tid)->orderByDesc('id')->limit(40)->get()->reverse()->values()->map(fn($n) => ['id'=>$n->id, 'body'=>$n->body, 'by'=>$n->user_name, 'at'=>\Carbon\Carbon::parse($n->created_at)->toIso8601String()])->all(); } catch (\Throwable $e) { return []; }
  }
  /** Save an internal note. It changes nothing else: the conversation stays Awaiting Reply until a real reply is sent. */
  public function note(Request $r, $id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    $d = $r->validate(['body'=>'required|string|max:1000']); $body = trim($d['body']); if ($body === '') return response()->json(['ok'=>false, 'error'=>'Write the note first.'], 422);
    $nid = DB::table('chat_notes')->insertGetId(['thread_id'=>(int)$id, 'user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'body'=>$body, 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'note'=>['id'=>$nid, 'body'=>$body, 'by'=>auth()->user()->name ?? '', 'at'=>now()->toIso8601String()]]);
  }
  /** Three AI reply drafts for the staff member to pick, edit and send. Nothing is sent from here and the waiting state does not change. Facts come only from the store data the AI already uses plus this customer's latest order. */
  public function draft(Request $r, $id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    $n = 'ai.draft.'.now()->format('Ymd'); \Illuminate\Support\Facades\Cache::add($n, 0, 90000); if (\Illuminate\Support\Facades\Cache::increment($n) > 500) return response()->json(['ok'=>false, 'error'=>'Daily AI draft limit reached.']);
    $hist = DB::table('chat_messages')->where('thread_id', $id)->orderByDesc('id')->limit(14)->get()->reverse()->values()->map(function ($m) { $c = trim((string)($m->transcript ?: $m->body)); if ($c === '' && $m->attachment_url) $c = '['.($m->attachment_type ?: 'file').' sent]'; return ['role'=>$m->dir === 'in' ? 'user' : 'assistant', 'content'=>$c]; })->all();
    $extra = "\n";
    try { if ($t->phone && ($o = DB::table('orders')->where('phone', $t->phone)->orderByDesc('id')->first(['invoice', 'status', 'total', 'courier', 'courier_status', 'tracking_code', 'consignment_id']))) $extra .= 'VERIFIED ORDER (this customer\'s latest order, from the store database): Invoice '.$o->invoice.' | status '.$o->status.' | total '.(int)round((float)$o->total).' BDT'.($o->courier ? ' | courier '.$o->courier : '').($o->courier_status ? ' | courier status '.$o->courier_status : '').(($o->tracking_code ?: $o->consignment_id) ? ' | tracking '.($o->tracking_code ?: $o->consignment_id) : '')."\n"; } catch (\Throwable $e) {}
    [$ok, $txt] = \App\Support\AiChat::reply($hist, $extra, $t->ai_lang ?? null, 2000); // room for the model's own working before the short answer
    if (!$ok) return response()->json(['ok'=>false, 'error'=>$txt === 'empty' ? 'The customer has not written anything after the last reply.' : ($txt === 'empty reply' ? 'The AI could not draft a reply for this message. Please write it yourself.' : 'AI draft failed: '.$txt)]);
    $drafts = [trim($txt)];
    foreach (\App\Support\AiChat::variants($drafts[0]) as $v) $drafts[] = $v; // same facts, different length and tone
    return response()->json(['ok'=>true, 'drafts'=>array_slice($drafts, 0, 3), 'note'=>count($drafts) < 3 ? \App\Support\AiChat::$vErr : null]);
  }

  public function messagesAfter(Request $r, $id) {
    $map = fn($m) => [
      'id'=>$m->id, 'dir'=>$m->dir, 'kind'=>$m->kind, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'by'=>$m->user_name, 'is_ai'=>(bool)($m->is_ai ?? false), 'status'=>$m->status, 'error'=>$m->error, 'read'=>!empty($m->read_at), 'transcript'=>$m->transcript ?? null, 'cards'=>Chat::productCards($m->body),
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
    if ($body !== '' && in_array($d['kind'] ?? 'text', ['text', 'quick'], true)) $body = mb_substr(Chat::staffGreet($t, (string)(auth()->user()->name ?? ''), (int)auth()->id()).$body, 0, 3500);
    $t0 = now(); // customer messages that arrive after this moment were not answered by this reply
    [$ok, $err] = Chat::deliver($t, $body, $att, $type ?? 'image');
    $mid = DB::table('chat_messages')->insertGetId(['thread_id'=>$id, 'dir'=>'out', 'kind'=>$d['kind'] ?? ($att ? 'file' : 'text'), 'body'=>$body, 'attachment_url'=>$att, 'attachment_type'=>$type,
      'user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'status'=>$ok ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
    // A failed send leaves the conversation waiting for a reply; only an accepted message answers the customer.
    $upd = ['assigned_to'=>$t->assigned_to ?: auth()->id(), 'ai_paused'=>true, 'updated_at'=>now()];
    if ($ok) \Illuminate\Support\Facades\Cache::forget('chat.typing.'.$t->id);
    if ($ok) $upd += ['last_message'=>mb_substr($body !== '' ? $body : '[attachment]', 0, 300), 'last_dir'=>'out', 'last_at'=>now(), 'status'=>in_array($t->status, ['new', 'open']) ? 'waiting' : $t->status];
    DB::table('chat_threads')->where('id', $id)->update($upd);
    if ($ok) { Chat::replied((int)$id, $t0); \App\Support\AiMemory::onStaff($t, $body, auth()->user()->name); }
    $res = ['ok'=>$ok, 'error'=>$err, 'id'=>$mid, 'ai'=>false];
    if ($ck) \Illuminate\Support\Facades\Cache::put($ck, $res, 600);
    return response()->json($res);
  }

  /** Turn Claude's automatic replies on or off for one conversation. */
  /** Admin diagnostic: sends the given product-link lines to a thread and returns what WhatsApp answered for each message. */
  public function photoTest(Request $r, $id) {
    abort_unless(auth()->check(), 403);
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404);
    Chat::$dlog = []; $res = Chat::deliver($t, (string)$r->input('body', ''));
    return response()->json(['ok'=>$res[0], 'err'=>$res[1], 'log'=>Chat::$dlog]);
  }
  public function polish(Request $r) {
    $d = $r->validate(['text'=>'required|string|max:3500', 'mode'=>'nullable|in:short']);
    [$ok, $t] = \App\Support\AiChat::polish($d['text'], (string)($d['mode'] ?? ''));
    return response()->json($ok ? ['ok'=>true, 'text'=>$t] : ['ok'=>false, 'error'=>$t]);
  }
  public function ai(Request $r, $id) {
    $on = $r->boolean('on'); if ($on) \Illuminate\Support\Facades\Cache::forget('ai.manual_off.'.(int)$id); else \Illuminate\Support\Facades\Cache::forever('ai.manual_off.'.(int)$id, 1);
    DB::table('chat_threads')->where('id', $id)->update(['ai_paused'=>!$on, 'updated_at'=>now()]);
    if ($on) { $tid = (int)$id; app()->terminating(fn() => Chat::aiReply($tid)); }
    return response()->json(['ok'=>true, 'ai'=>$on]);
  }
  /** Staff decision: the hand-off is finished, give this conversation back to the AI (clears the Handoff banner; the AI answers the waiting message if there is one). */
  public function handoffRelease(Request $r, $id) {
    $tid = (int)$id; $t = DB::table('chat_threads')->find($tid); abort_unless($t, 404);
    \Illuminate\Support\Facades\Cache::forget('ai.manual_off.'.$tid);
    DB::table('chat_threads')->where('id', $tid)->update(['needs_human'=>false, 'handoff_at'=>null, 'handoff_reason'=>null, 'ai_paused'=>false, 'updated_at'=>now()]);
    try { if (\Illuminate\Support\Facades\Schema::hasTable('chat_handoffs')) DB::table('chat_handoffs')->where('thread_id', $tid)->where('status', 'open')->update(['status'=>'done', 'updated_at'=>now()]); } catch (\Throwable $e) {}
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'action'=>'chat.handoff_release', 'subject'=>'Conversation #'.$tid.' given back to the AI', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    app()->terminating(fn() => Chat::aiReply($tid));
    return response()->json(['ok'=>true]);
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
    $d = $r->validate(['status'=>'required|in:new,open,waiting,resolved', 'reason'=>'nullable|string|max:300']);
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404); $reason = trim((string)($d['reason'] ?? ''));
    // Close chat is a deliberate staff decision: it ends the whole conversation (unread, Awaiting Reply, needs-a-person and hand-off are all cleared). A new customer message reopens it.
    $upd = ['status'=>$d['status'], 'updated_at'=>now()];
    if ($d['status'] === 'resolved') {
      $why = [];
      try { if (DB::table('chat_order_requests')->where('thread_id', $id)->where('status', 'review')->exists()) $why[] = 'an Order Request is still open'; } catch (\Throwable $e) {}
      // an open hand-off does not stop Close chat: closing is the staff member's decision and it ends the hand-off too (logged below)
      if (in_array((string)($t->intent ?? ''), ['complaint', 'refund'], true) && (!empty($t->awaiting_reply) || !empty($t->needs_human))) $why[] = 'the customer has an unanswered '.$t->intent.' request';
      if (!empty($t->reported_at) && empty($t->report_solved_at)) $why[] = 'the customer\'s complaint is still open';
      if ($why && $reason === '') return response()->json(['ok'=>false, 'need_reason'=>true, 'why'=>implode('; ', $why)]);
      $upd += ['unread'=>0, 'awaiting_reply'=>false, 'needs_human'=>false, 'handoff_at'=>null, 'handoff_reason'=>null, 'ai_paused'=>false];
    }
    DB::table('chat_threads')->where('id', $id)->update($upd);
    if ($d['status'] === 'resolved') { try { if (\Illuminate\Support\Facades\Schema::hasTable('chat_handoffs')) DB::table('chat_handoffs')->where('thread_id', $id)->whereIn('status', ['open', 'accepted'])->update(['status'=>'done', 'updated_at'=>now()]); } catch (\Throwable $e) {} }
    if ($d['status'] === 'resolved' || $t->status === 'resolved') { try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'action'=>$d['status'] === 'resolved' ? 'chat.close' : 'chat.reopen', 'subject'=>'Conversation #'.$id.' '.($d['status'] === 'resolved' ? 'closed' : 'reopened').($reason !== '' ? ' - '.$reason : ''), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {} }
    return response()->json(['ok'=>true, 'unread'=>Chat::unreadTotal()]);
  }
  /** Report mark: staff can file a conversation under Report by hand, or mark the customer's report as solved. */
  public function report(Request $r, $id) {
    $t = DB::table('chat_threads')->find($id); abort_unless($t, 404); $on = (bool)$r->input('on');
    DB::table('chat_threads')->where('id', $id)->update($on ? ['reported_at'=>now(), 'report_reason'=>'Marked by '.(auth()->user()->name ?? 'staff'), 'report_solved_at'=>null, 'report_solved_by'=>null, 'updated_at'=>now()] : ['report_solved_at'=>now(), 'report_solved_by'=>mb_substr((string)(auth()->user()->name ?? 'staff'), 0, 120), 'updated_at'=>now()]);
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'action'=>$on ? 'chat.report' : 'chat.report_solved', 'subject'=>'Conversation #'.$id.($on ? ' filed under Complaint' : ' complaint solved'.(!empty($t->report_reason) ? ' ('.$t->report_reason.')' : '')), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json(['ok'=>true, 'reported'=>$on, 'by'=>auth()->user()->name ?? 'staff']);
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
    $this->igHeal();
    return view('chat_settings', ['ai_tone'=>(string)\App\Support\Sms::secret('ai_tone'), 'ai_tone_custom'=>(function(){ $v = (string)\App\Support\Sms::secret('ai_tone_custom'); return $v === '' ? 'প্রতিটি উত্তরের একদম শেষে ধন্যবাদ লিখবে।' : ($v === '-' ? '' : $v); })(), 'ig_accts'=>(function(){ $j = json_decode((string)Chat::sec('chat_ig_accounts'), true); return is_array($j) ? $j : []; })(), 'verify'=>Chat::sec('chat_verify_token'), 'hook'=>url('/webhooks/meta'), 'wa_numbers'=>(function(){ try { return DB::table('chat_wa_numbers')->orderBy('id')->get(['id','phone_id','display_phone','verified_name','subscribed','note']); } catch (\Throwable $e) { return collect(); } })(), 'wa_cfg'=>(string)Chat::sec('chat_wa_config_id'), 'app_id'=>Chat::sec('chat_app_id'), 'redir'=>url('/admin/chat-settings/fb-callback'), 'pages'=>$this->pages(), 'has'=>['fb'=>$has('chat_fb_token') || $this->pages()->isNotEmpty(), 'ig'=>$has('chat_ig_token'), 'wa'=>$has('chat_wa_token'), 'secret'=>$has('chat_app_secret')],
      'hook_last'=>DB::table('settings')->where('key', 'chat_hook_last')->value('value'), 'wa_phone'=>Chat::sec('chat_wa_phone_id'), 'ai_chan'=>\App\Support\AiChat::chanOn(), 'sla_warn'=>(int)(Sms::secret('sla_warn_min') ?: 5), 'sla_red'=>(int)(Sms::secret('sla_red_min') ?: 15), 'ai_auto'=>\App\Support\AiChat::chatOn() || (\App\Support\Sms::secret('ai_chat_auto') !== '0'), 'web_on'=>(DB::table('settings')->where('key', 'chat_web_on')->value('value') ?? '1') === '1',
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
  /** A saved Instagram token without a remembered account list (connected before accounts were recorded): ask Graph which accounts it reaches, store them and subscribe. */
  private function igHeal(): void {
    try {
      $tok = (string)Chat::sec('chat_ig_token'); if (!$tok) return;
      $j = json_decode((string)Chat::sec('chat_ig_accounts'), true);
      if (is_array($j) && $j) { $ch = false; foreach ($j as $k => $a) if (empty($a['token'])) { $j[$k]['token'] = \Illuminate\Support\Facades\Crypt::encryptString($tok); $ch = true; } if ($ch) \App\Support\Sms::setSecret('chat_ig_accounts', json_encode($j)); return; }
      $G = 'https://graph.facebook.com/v21.0/'; $H = fn() => \Illuminate\Support\Facades\Http::timeout(12); $ids = [];
      foreach (['me/instagram_accounts', 'me/accounts'] as $ep) {
        $q = $H()->withToken($tok)->get($G.$ep, ['fields'=>$ep === 'me/accounts' ? 'instagram_business_account{id}' : 'id,username', 'limit'=>50])->json();
        foreach (($q['data'] ?? []) as $row) { $x = $row['instagram_business_account']['id'] ?? ($ep === 'me/instagram_accounts' ? ($row['id'] ?? null) : null); if ($x) $ids[(string)$x] = 1; }
        if ($ids) break;
      }
      $ids = array_keys($ids); if (!$ids) $ids = ['17841469408501947'];
      $acc = [];
      foreach ($ids as $igid) {
        $res = $H()->withToken($tok)->post($G.$igid.'/subscribed_apps', ['subscribed_fields'=>'messages,messaging_postbacks,message_reactions'])->json();
        $u = $H()->withToken($tok)->get($G.$igid, ['fields'=>'username,name'])->json();
        $acc[] = ['id'=>(string)$igid, 'username'=>$u['username'] ?? ($u['name'] ?? null), 'ok'=>!empty($res['success']), 'token'=>\Illuminate\Support\Facades\Crypt::encryptString($tok)];
      }
      if ($acc && array_filter($acc, fn($a) => !empty($a['username']) || !empty($a['ok']))) \App\Support\Sms::setSecret('chat_ig_accounts', json_encode($acc));
    } catch (\Throwable $e) {}
  }
  private function pages() { try { return DB::table('chat_pages')->orderBy('id')->get(['id', 'page_id', 'name', 'ig_id', 'ig_username', 'subscribed', 'note', 'updated_at']); } catch (\Throwable $e) { return collect(); } }
  /** Facebook Login: the owner signs in at Facebook and picks Pages; we never see the password. */
  public function fbConnect(Request $r) {
    $id = trim((string)Chat::sec('chat_app_id'));
    if (!$id) return redirect('/admin/chat-settings')->with('err', 'Save the Meta App ID first (Chat Settings > Meta app).');
    $ig = (bool)$r->query('ig'); $wa = (bool)$r->query('wa');
    $pl = rtrim(strtr(base64_encode(json_encode(['t'=>time(), 'i'=>$ig ? 1 : 0, 'w'=>$wa ? 1 : 0, 'n'=>bin2hex(random_bytes(6))])), '+/', '-_'), '='); $st = $pl.'.'.hash_hmac('sha256', $pl, (string)config('app.key')); // signed state: no dependence on the browser session
    session(['fb_oauth_state'=>$st, 'fb_oauth_ig'=>$ig, 'fb_oauth_wa'=>$wa]);
    if ($wa && !trim((string)Chat::sec('chat_wa_config_id'))) return redirect('/admin/chat-settings')->with('err', 'Save the WhatsApp login Configuration ID first.');
    return redirect('https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query(['client_id'=>$id, 'redirect_uri'=>url('/admin/chat-settings/fb-callback'), 'state'=>$st, 'response_type'=>'code',
      'config_id'=>$wa ? trim((string)Chat::sec('chat_wa_config_id')) : ($ig ? '989295444231024' : '1507276531459571'), 'override_default_response_type'=>'true']));
  }
  public function fbCallback(Request $r) {
    $back = redirect('/admin/chat-settings');
    if ($r->query('error')) return $back->with('err', 'Facebook login was cancelled or refused: '.mb_substr((string)($r->query('error_description') ?: $r->query('error')), 0, 220));
    $st = (string)session('fb_oauth_state'); session()->forget('fb_oauth_state');
    $qs = (string)$r->query('state'); $parts = explode('.', $qs, 2); $sd = null;
    if (count($parts) === 2 && hash_equals(hash_hmac('sha256', $parts[0], (string)config('app.key')), $parts[1])) $sd = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/')), true);
    if (!is_array($sd) || (time() - (int)($sd['t'] ?? 0)) > 1800) {
      if ($st === '' || !hash_equals($st, $qs)) return $back->with('err', 'The login session expired. Press the connect button again (once, in one window).');
      $sd = ['i'=>(int)session('fb_oauth_ig'), 'w'=>(int)session('fb_oauth_wa')];
    }
    session(['fb_oauth_ig'=>!empty($sd['i']), 'fb_oauth_wa'=>!empty($sd['w'])]);
    $id = trim((string)Chat::sec('chat_app_id')); $sec = trim((string)Chat::sec('chat_app_secret'));
    if (!$id || !$sec) return $back->with('err', 'Save both the Meta App ID and the App secret first.');
    $G = 'https://graph.facebook.com/v21.0/'; $H = fn() => \Illuminate\Support\Facades\Http::timeout(25);
    $a = $H()->get($G.'oauth/access_token', ['client_id'=>$id, 'client_secret'=>$sec, 'redirect_uri'=>url('/admin/chat-settings/fb-callback'), 'code'=>(string)$r->query('code')])->json();
    if (empty($a['access_token'])) return $back->with('err', 'Facebook did not accept the login: '.mb_substr((string)($a['error']['message'] ?? json_encode($a)), 0, 250));
    if (session()->pull('fb_oauth_wa')) { [$okw, $mw] = $this->waImport($a['access_token'], [], $r); return $back->with($okw ? 'ok' : 'err', $mw); }
    if (session()->pull('fb_oauth_ig')) { // Instagram connection (unified login): system-user token for the Instagram account(s)
      $tok = $a['access_token'];
      $dbg = $H()->get($G.'debug_token', ['input_token'=>$tok, 'access_token'=>$id.'|'.$sec])->json();
      $ids = [];
      foreach (($dbg['data']['granular_scopes'] ?? []) as $gs) if (in_array($gs['scope'] ?? '', ['instagram_basic', 'instagram_manage_messages'], true)) foreach (($gs['target_ids'] ?? []) as $t) $ids[(string)$t] = 1;
      $ids = array_keys($ids);
      if (!$ids) { // system-user tokens often carry no target list: ask the Graph API which Instagram accounts the token can reach
        foreach (['me/instagram_accounts', 'me/accounts'] as $ep) {
          $q = $H()->withToken($tok)->get($G.$ep, ['fields'=>$ep === 'me/accounts' ? 'instagram_business_account{id}' : 'id,username', 'limit'=>50])->json();
          foreach (($q['data'] ?? []) as $row) { $x = $row['instagram_business_account']['id'] ?? ($ep === 'me/instagram_accounts' ? ($row['id'] ?? null) : null); if ($x) $ids[(string)$x] = 1; }
          if ($ids) break;
        }
        $ids = array_keys($ids);
      }
      if (!$ids) $ids = ['17841469408501947']; // @dutyfreebd (Business portfolio asset id from Meta)
      if (false) return $back->with('err', 'Instagram login worked, but no Instagram account was granted. Tick the Instagram account in the login window. '.mb_substr(json_encode($dbg['data']['granular_scopes'] ?? ($dbg['error'] ?? 'no scopes')), 0, 250));
      \App\Support\Sms::setSecret('chat_ig_token', $tok);
      $msgs = [];
      foreach ($ids as $igid) {
        $res = $H()->withToken($tok)->post($G.$igid.'/subscribed_apps', ['subscribed_fields'=>'messages,messaging_postbacks,message_reactions'])->json();
        $okk = !empty($res['success']); $msgs[] = $igid.($okk ? ': receiving messages' : ': subscribe failed - '.mb_substr((string)($res['error']['message'] ?? json_encode($res)), 0, 160));
      }
      $old = json_decode((string)Chat::sec('chat_ig_accounts'), true); $keep = [];
      foreach ((is_array($old) ? $old : []) as $o) if (!in_array((string)($o['id'] ?? ''), array_map('strval', $ids), true)) $keep[] = $o;
      $acc = $keep;
      foreach ($ids as $igid) { $u = $H()->withToken($tok)->get($G.$igid, ['fields'=>'username,name'])->json(); $acc[] = ['id'=>(string)$igid, 'username'=>$u['username'] ?? ($u['name'] ?? null), 'ok'=>str_contains(implode('|', $msgs), $igid.': receiving'), 'token'=>\Illuminate\Support\Facades\Crypt::encryptString($tok)]; }
      \App\Support\Sms::setSecret('chat_ig_accounts', json_encode($acc));
      try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.ig_connect', 'subject'=>'Instagram login: '.implode(' | ', $msgs), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
      return $back->with('ok', 'Instagram connected. '.implode(' | ', $msgs));
    }
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
  /** Takes a WhatsApp login token, finds every WhatsApp Business account it can reach and stores/subscribes each of its numbers. Returns [ok, message]. */
  private function waImport(string $tok, array $hintWabas, Request $r): array {
    $id = trim((string)Chat::sec('chat_app_id')); $sec = trim((string)Chat::sec('chat_app_secret'));
    $G = 'https://graph.facebook.com/v21.0/'; $H = fn() => \Illuminate\Support\Facades\Http::timeout(25);
    $dbg = $H()->get($G.'debug_token', ['input_token'=>$tok, 'access_token'=>$id.'|'.$sec])->json();
    $wabas = [];
    foreach (($dbg['data']['granular_scopes'] ?? []) as $gs) if (in_array($gs['scope'] ?? '', ['whatsapp_business_management', 'whatsapp_business_messaging'], true)) foreach (($gs['target_ids'] ?? []) as $t) $wabas[(string)$t] = 1;
    foreach ($hintWabas as $w) if (preg_match('/^\d{6,25}$/', (string)$w)) $wabas[(string)$w] = 1;
    $wabas = array_keys($wabas);
    if (!$wabas) return [false, 'WhatsApp signup finished, but no WhatsApp Business account was granted. '.mb_substr(json_encode($dbg['data']['granular_scopes'] ?? ($dbg['error'] ?? 'no scopes')), 0, 250)];
    $added = []; $bad = [];
    foreach ($wabas as $wb) {
      $sub = $H()->withToken($tok)->post($G.$wb.'/subscribed_apps')->json(); $sok = !empty($sub['success']);
      $nums = $H()->withToken($tok)->get($G.$wb.'/phone_numbers', ['fields'=>'id,display_phone_number,verified_name', 'limit'=>25])->json();
      foreach (($nums['data'] ?? []) as $pn) {
        $pid = (string)($pn['id'] ?? ''); if ($pid === '') continue;
        DB::table('chat_wa_numbers')->updateOrInsert(['phone_id'=>$pid], ['waba_id'=>$wb, 'display_phone'=>$pn['display_phone_number'] ?? null, 'verified_name'=>$pn['verified_name'] ?? null,
          'token'=>\Illuminate\Support\Facades\Crypt::encryptString($tok), 'subscribed'=>$sok, 'note'=>$sok ? null : mb_substr((string)($sub['error']['message'] ?? 'subscribe failed'), 0, 250), 'updated_at'=>now(), 'created_at'=>now()]);
        $added[] = ($pn['display_phone_number'] ?? $pid); if (!$sok) $bad[] = $pn['display_phone_number'] ?? $pid;
      }
      if (empty($nums['data'])) $bad[] = 'WABA '.$wb.': '.mb_substr((string)($nums['error']['message'] ?? 'no numbers'), 0, 120);
    }
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.wa_connect', 'subject'=>'WhatsApp numbers connected: '.implode(', ', $added), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return [(bool)$added, count($added).' WhatsApp number(s) connected: '.implode(', ', $added).($bad ? ' Attention: '.implode(' | ', $bad) : '')];
  }
  /** Embedded Signup (Facebook JS popup) finished: exchange its code and connect the numbers. */
  public function waEmbedded(Request $r) {
    $d = $r->validate(['code'=>'required|string|max:1000', 'waba_id'=>'nullable|string|max:40']);
    $id = trim((string)Chat::sec('chat_app_id')); $sec = trim((string)Chat::sec('chat_app_secret'));
    if (!$id || !$sec) return response()->json(['ok'=>false, 'message'=>'Save both the Meta App ID and the App secret first.']);
    $a = \Illuminate\Support\Facades\Http::timeout(25)->get('https://graph.facebook.com/v21.0/oauth/access_token', ['client_id'=>$id, 'client_secret'=>$sec, 'code'=>$d['code']])->json();
    if (empty($a['access_token'])) return response()->json(['ok'=>false, 'message'=>'Meta did not accept the signup: '.mb_substr((string)($a['error']['message'] ?? json_encode($a)), 0, 250)]);
    [$ok, $m] = $this->waImport($a['access_token'], array_filter([(string)($d['waba_id'] ?? '')]), $r);
    return response()->json(['ok'=>$ok, 'message'=>$m]);
  }
  public function waDisconnect(Request $r, $id) {
    $n = DB::table('chat_wa_numbers')->find($id); if (!$n) return back();
    DB::table('chat_wa_numbers')->where('id', $id)->delete();
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.wa_disconnect', 'subject'=>'WhatsApp number disconnected: '.($n->display_phone ?: $n->phone_id), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Disconnected '.($n->display_phone ?: $n->phone_id).'. (Its old conversations stay in the inbox.)');
  }
  public function waConfigSave(Request $r) {
    $d = $r->validate(['wa_config_id'=>'required|digits_between:8,25']);
    \App\Support\Sms::setSecret('chat_wa_config_id', $d['wa_config_id']);
    return back()->with('ok', 'WhatsApp login configuration saved.');
  }
  public function igDisconnect(Request $r, $id) {
    $j = json_decode((string)Chat::sec('chat_ig_accounts'), true); $j = is_array($j) ? $j : []; $name = null;
    $j = array_values(array_filter($j, function ($a) use ($id, &$name) { if ((string)($a['id'] ?? '') === (string)$id) { $name = $a['username'] ?? $id; return false; } return true; }));
    \App\Support\Sms::setSecret('chat_ig_accounts', json_encode($j));
    if (!$j) \App\Support\Sms::setSecret('chat_ig_token', '');
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.ig_disconnect', 'subject'=>'Instagram disconnected: '.$name, 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Disconnected '.($name ? '@'.ltrim((string)$name, '@') : 'the account').'. (Its old conversations stay in the inbox.)');
  }
  public function fbDisconnect(Request $r, $id) {
    $p = DB::table('chat_pages')->find($id); if (!$p) return back();
    DB::table('chat_pages')->where('id', $id)->delete();
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.fb_disconnect', 'subject'=>'Page disconnected: '.$p->name, 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Disconnected '.$p->name.'. (Its old conversations stay in the inbox.)');
  }
  /** Preview (GET) and run (POST) of "close every never-answered WhatsApp chat with one last courtesy message". */
  public function unanswered(Request $r) {
    $rows = DB::table('chat_threads')->where('channel', 'whatsapp')->where('awaiting_reply', true)->whereNotIn('status', ['resolved'])->orderBy('last_at')->limit(400)->get();
    $ok = []; $blocked = [];
    foreach ($rows as $t) { $b = Chat::unansweredBlock($t); if ($b === null) $ok[] = $t; else $blocked[$b][] = $t; }
    $done = 0; $failed = 0;
    if ($r->isMethod('post')) {
      foreach (array_slice($ok, 0, 40) as $t) { if (Chat::closeUnanswered($t)) $done++; else $failed++; usleep(300000); }
      DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.close_unanswered', 'subject'=>"Closed $done unanswered WhatsApp chats with a courtesy message ($failed failed)", 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
      return redirect('/admin/chat-unanswered')->with('ok', "$done chats closed with the last message" . ($failed ? ", $failed could not be sent and stay open" : '') . '.');
    }
    return view('chat_unanswered', ['ok'=>$ok, 'blocked'=>$blocked]);
  }
  public function saveSettings(Request $r) {
    $d = $r->validate(['fb_token'=>'nullable|string|max:600', 'ig_token'=>'nullable|string|max:600', 'wa_token'=>'nullable|string|max:600', 'wa_phone_id'=>'nullable|string|max:40', 'app_secret'=>'nullable|string|max:100', 'app_id'=>'nullable|string|max:40']);
    foreach (['fb_token'=>'chat_fb_token', 'ig_token'=>'chat_ig_token', 'wa_token'=>'chat_wa_token', 'app_secret'=>'chat_app_secret', 'app_id'=>'chat_app_id', 'wa_phone_id'=>'chat_wa_phone_id'] as $k => $sk) if (!empty($d[$k])) Sms::setSecret($sk, trim($d[$k]));
    Sms::setSecret('ai_chat_auto', $r->boolean('ai_auto') ? '1' : '0');
    Sms::setSecret('ai_chat_chan', $r->boolean('ai_chan') ? '1' : '0');
    Sms::setSecret('ai_comment_reply', $r->boolean('ai_comment_reply') ? '1' : '0');
    if ($r->has('stale_form')) { $was = Sms::secret('chat_stale_on') !== '0'; $on = $r->boolean('chat_stale_on'); Sms::setSecret('chat_stale_on', $on ? '1' : '0'); if ($on && !$was) Sms::setSecret('chat_stale_from', now()->toDateTimeString()); // switched back on: start from now, never a mass send to old chats
      if ($r->filled('chat_stale_min')) Sms::setSecret('chat_stale_min', (string)max(5, min(240, (int)$r->input('chat_stale_min'))));
      Sms::setSecret('chat_stale_text_bn', mb_substr(trim((string)$r->input('chat_stale_text_bn', '')), 0, 500)); Sms::setSecret('chat_stale_text_en', mb_substr(trim((string)$r->input('chat_stale_text_en', '')), 0, 500)); }
    Sms::setSecret('ai_auto_close', $r->boolean('ai_auto_close') ? '1' : '0'); if ($r->filled('ai_close_after')) Sms::setSecret('ai_close_after_min', (string)max(1, min(720, (int)$r->input('ai_close_after'))));
    if ($r->filled('sla_warn')) Sms::setSecret('sla_warn_min', (string)max(1, min(600, (int)$r->input('sla_warn')))); if ($r->filled('sla_red')) Sms::setSecret('sla_red_min', (string)max(2, min(1440, (int)$r->input('sla_red'))));
    DB::table('settings')->updateOrInsert(['key'=>'chat_web_on'], ['value'=>$r->boolean('web_on') ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    \Illuminate\Support\Facades\Cache::forget('store.settings2'); \Illuminate\Support\Facades\Cache::forget('chat.web_on');
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.settings', 'subject'=>'Chat channel settings saved', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'Chat settings saved.');
  }
  public function toneSave(Request $r) {
    $k = (string)$r->input('ai_tone'); if (!in_array($k, ['', 'friendly', 'respectful', 'professional', 'short'], true)) $k = '';
    \App\Support\Sms::setSecret('ai_tone', $k); $cu = mb_substr(trim((string)$r->input('ai_tone_custom')), 0, 400); \App\Support\Sms::setSecret('ai_tone_custom', $cu === '' ? '-' : $cu);
    return back()->with('ok', 'AI reply tone saved. It applies to the next replies.');
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
    if ($th && !\App\Support\AiChat::paused($th)) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
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
  public function typing($id) { $t = DB::table('chat_threads')->find($id); if ($t && !\Illuminate\Support\Facades\Cache::add('chat.typing.sent.'.$id, 1, 4)) return response()->json(['ok'=>true]); if ($t) Chat::typing($t); return response()->json(['ok'=>true]); }
  public function webPoll(Request $r) {
    $t = $this->webThread((string)$r->query('token'));
    $m = DB::table('chat_messages')->where('thread_id', $t->id)->where('id', '>', (int)$r->query('after'))->orderBy('id')->get(['id', 'dir', 'body', 'attachment_url', 'attachment_type', 'created_at', 'read_at', 'user_name'])
      ->map(fn($m) => ['id'=>$m->id, 'dir'=>$m->dir, 'body'=>$m->body, 'att'=>$m->attachment_url, 'att_type'=>$m->attachment_type, 'at'=>\Carbon\Carbon::parse($m->created_at)->toIso8601String(), 'seen'=>(bool)$m->read_at]);
    $seen = (int)DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->whereNotNull('read_at')->max('id');
    return response()->json(['messages'=>$m, 'seen_upto'=>$seen, 'closed'=>$t->status === 'resolved', 'typing'=>\Illuminate\Support\Facades\Cache::has('chat.typing.'.$t->id)]);
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
          // public comments on our Page posts (needs the Page's "feed" subscription): stored always, answered by the AI only when switched on in Chat Settings
          if ($obj === 'page') foreach ($e['changes'] ?? [] as $chg) { if (($chg['field'] ?? '') !== 'feed') continue; $ccid = \App\Support\ChatComments::ingest((string)($e['id'] ?? ''), (array)($chg['value'] ?? [])); if ($ccid && \App\Support\ChatComments::on()) app()->terminating(fn() => \App\Support\ChatComments::reply($ccid)); }
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
            if ($att && $atype === 'image') $att = Chat::mirrorImage($att) ?: $att;
            $body = (string)($msg['text'] ?? ''); if ($body === '' && !$att) $body = '[unsupported message]';
            $th = Chat::ingest($ch, (string)$m['sender']['id'], null, $body, $att, $msg['mid'] ?? null, ['page_ref'=>(string)($e['id'] ?? ''), 'att_type'=>$atype]);
            if ($th && $att && $atype === 'audio' && ($vid = DB::table('chat_messages')->where('ext_id', $msg['mid'] ?? '-')->value('id'))) { $vid = (int)$vid; app()->terminating(fn() => Chat::transcribe($vid)); }
            if ($th && $att && $atype === 'image' && ($pid2 = DB::table('chat_messages')->where('ext_id', $msg['mid'] ?? '-')->value('id'))) { $pid2 = (int)$pid2; $ptid = (int)$th->id; app()->terminating(fn() => \App\Support\PayProof::scan($ptid, $pid2)); }
            if ($th && $att && $atype === 'image' && \App\Support\AiChat::paused($th) && ($hid = DB::table('chat_messages')->where('ext_id', $msg['mid'] ?? '-')->value('id'))) { $hid = (int)$hid; $htid = (int)$th->id; app()->terminating(fn() => Chat::imageHint($htid, $hid)); }
            if ($th && $ch === 'facebook' && !\App\Support\AiChat::paused($th) && !($att && !in_array($atype, ['image', 'audio'], true))) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
          }
        } elseif ($obj === 'whatsapp_business_account') {
          foreach ($e['changes'] ?? [] as $c) {
            $v = $c['value'] ?? []; $names = [];
            $wpid = (string)($v['metadata']['phone_number_id'] ?? ''); [$wtok, ] = Chat::waCred($wpid); $wref = ($wpid !== '' && $wpid !== (string)Chat::sec('chat_wa_phone_id') && DB::table('chat_wa_numbers')->where('phone_id', $wpid)->exists()) ? $wpid : null;
            foreach ($v['contacts'] ?? [] as $ct) $names[$ct['wa_id'] ?? ''] = $ct['profile']['name'] ?? null;
            foreach ($v['statuses'] ?? [] as $stt) \App\Support\OrderWaConfirm::status((array)$stt);
            // Old conversations the WhatsApp Business app shares when it is linked (history): stored quietly, never answered by the AI
            foreach ($v['history'] ?? [] as $hb) foreach ($hb['threads'] ?? [] as $ht) {
              $cw = preg_replace('/\D/', '', (string)($ht['id'] ?? '')); if ($cw === '') continue;
              foreach ($ht['messages'] ?? [] as $hm) {
                try {
                  $hid = (string)($hm['id'] ?? ''); if ($hid === '' || DB::table('chat_messages')->where('ext_id', $hid)->exists()) continue;
                  $hty = (string)($hm['type'] ?? 'text'); $hbd = $hty === 'text' ? (string)($hm['text']['body'] ?? '') : '['.$hty.']'; if (trim($hbd) === '') continue;
                  $hfrom = preg_replace('/\D/', '', (string)($hm['from'] ?? '')); $mine = $hfrom !== '' && $hfrom !== $cw; $hat = !empty($hm['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int)$hm['timestamp']) : now();
                  if ($mine) { \App\Support\Chat::logOut($cw, $hbd, 'WhatsApp app', $hid); }
                  else {
                    $tt = DB::table('chat_threads')->where(['channel'=>'whatsapp', 'external_id'=>$cw])->first();
                    if (!$tt) { $ph = \App\Support\Chat::phone($cw); $cu = $ph ? DB::table('customers')->where('phone', $ph)->orderBy('id')->first() : null; $nid = DB::table('chat_threads')->insertGetId(['channel'=>'whatsapp', 'external_id'=>$cw, 'customer_id'=>$cu->id ?? null, 'name'=>($cu->name ?? null) ?: ($ph ?: 'WhatsApp user'), 'phone'=>$ph, 'status'=>'resolved', 'unread'=>0, 'created_at'=>$hat, 'updated_at'=>$hat]); $tt = DB::table('chat_threads')->find($nid); }
                    DB::table('chat_messages')->insert(['thread_id'=>$tt->id, 'dir'=>'in', 'kind'=>'text', 'body'=>mb_substr($hbd, 0, 4000), 'ext_id'=>$hid, 'read_at'=>now(), 'created_at'=>$hat, 'updated_at'=>$hat]);
                  }
                } catch (\Throwable $e) { report($e); }
              }
            }
            // Messages typed by staff in the WhatsApp Business app on the phone (coexistence): shown here as sent messages, and the AI steps back from that chat
            foreach ($v['message_echoes'] ?? [] as $me) {
              $to = preg_replace('/\D/', '', (string)($me['to'] ?? '')); if ($to === '') continue;
              $mt = (string)($me['type'] ?? 'text'); if (in_array($mt, ['reaction', 'edit', 'revoke', 'unsupported'], true)) continue; // reactions/edits/deletes are not messages
              $eb = $mt === 'text' ? (string)($me['text']['body'] ?? '') : ($mt === 'button' ? (string)($me['button']['text'] ?? '') : '');
              $eatt = null; $etype = null;
              // voice notes / photos / files sent from the phone app: fetch the media so staff can play or open it here
              if (in_array($mt, ['image', 'sticker', 'document', 'audio', 'video'], true) && !empty($me[$mt]['id'])) {
                $got = Chat::waMedia((string)$me[$mt]['id'], $wtok);
                if ($got) { $eatt = $got[0]; $etype = $got[1]; $cap = trim((string)($me[$mt]['caption'] ?? '')); if ($cap !== '') $eb = $cap; }
              }
              if (trim($eb) === '' && !$eatt) $eb = $mt === 'text' ? '[message sent from the WhatsApp app]' : '['.$mt.' sent from the WhatsApp app — media could not be loaded]';
              \App\Support\Chat::logOut($to, $eb, 'WhatsApp app', (string)($me['id'] ?? '') ?: null, 'text', 'sent', null, $eatt, $etype);
              if ($tt = DB::table('chat_threads')->where(['channel'=>'whatsapp', 'external_id'=>$to])->first()) { DB::table('chat_threads')->where('id', $tt->id)->update(['ai_paused'=>true, 'updated_at'=>now()]); \App\Support\Chat::replied((int)$tt->id, now()); }
            }
            foreach ($v['messages'] ?? [] as $m) {
              $from = (string)($m['from'] ?? ''); if ($from === '') continue;
              $type = $m['type'] ?? 'text';
              if (in_array($type, ['reaction', 'edit', 'revoke', 'unsupported'], true)) continue; // no bubble, no unread, no awaiting-reply
              $body = $type === 'text' ? (string)($m['text']['body'] ?? '') : ($type === 'button' ? (string)($m['button']['text'] ?? '') : '['.$type.' received — open WhatsApp to view]');
              $att = null; $meta = [];
              if ($type === 'button' && !empty($m['button']['payload'])) \App\Support\OrderWaUpdate::onButton((string)$m['button']['payload'], $from);
              if (in_array($type, ['image', 'sticker', 'document', 'audio', 'video'], true) && !empty($m[$type]['id']) && !DB::table('chat_messages')->where('ext_id', $m['id'] ?? '-')->exists()) {
                $got = Chat::waMedia((string)$m[$type]['id'], $wtok);
                if ($got) { $att = $got[0]; $meta['att_type'] = $got[1]; $cap = trim((string)($m[$type]['caption'] ?? '')); $body = $cap !== '' ? $cap : ['image'=>'', 'sticker'=>'', 'document'=>(string)($m[$type]['filename'] ?? ''), 'audio'=>'', 'video'=>''][$type]; }
              }
              if (!$att && !empty($m['image']['caption'])) $body = $m['image']['caption'].' [image]';
              $meta['page_ref'] = $wref;
              $th = Chat::ingest('whatsapp', $from, $names[$from] ?? null, $body, $att, $m['id'] ?? null, $meta);
              if ($th && ($th->page_ref ?? null) !== $wref) { DB::table('chat_threads')->where('id', $th->id)->update(['page_ref'=>$wref]); $th->page_ref = $wref; } // replies go out from the number the customer wrote to
              if ($th && $att && ($meta['att_type'] ?? '') === 'audio' && ($vid = DB::table('chat_messages')->where('ext_id', $m['id'] ?? '-')->value('id'))) { $vid = (int)$vid; app()->terminating(fn() => Chat::transcribe($vid)); }
              if ($th && $att && ($meta['att_type'] ?? '') === 'image' && ($pid2 = DB::table('chat_messages')->where('ext_id', $m['id'] ?? '-')->value('id'))) { $pid2 = (int)$pid2; $ptid = (int)$th->id; app()->terminating(fn() => \App\Support\PayProof::scan($ptid, $pid2)); }
              if ($th && $att && ($meta['att_type'] ?? '') === 'image' && \App\Support\AiChat::paused($th) && ($hid = DB::table('chat_messages')->where('ext_id', $m['id'] ?? '-')->value('id'))) { $hid = (int)$hid; $htid = (int)$th->id; app()->terminating(fn() => Chat::imageHint($htid, $hid)); }
              if ($th && !\App\Support\AiChat::paused($th) && !($att && !in_array($meta['att_type'] ?? 'image', ['image', 'audio'], true)) && in_array($type, ['text', 'button', 'image', 'audio'], true)) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
            }
          }
        }
      }
    } catch (\Throwable $e) { \Log::error('chat webhook: '.$e->getMessage()); }
    return response('OK', 200);
  }

  /** Everything the AI has recorded in one place: customer memory, how staff answer, and the behaviour timeline. */
  public function memory(Request $r) {
    $e = fn($v) => e((string)$v);
    $cnt = ['customers'=>DB::table('ai_customer_memory')->count(), 'examples'=>DB::table('ai_staff_examples')->count(), 'events'=>DB::table('ai_behavior_events')->count()];
    $cust = DB::table('ai_customer_memory')->orderByDesc('last_seen_at')->limit(60)->get();
    $ex = DB::table('ai_staff_examples')->orderByDesc('id')->limit(40)->get();
    $ev = DB::table('ai_behavior_events')->orderByDesc('id')->limit(60)->get();
    $by = DB::table('ai_behavior_events')->selectRaw('actor, type, count(*) n')->groupBy('actor', 'type')->orderByDesc('n')->get();
    $h = '<style>.k{display:inline-block;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:8px 14px;margin:0 8px 8px 0}.k b{font-size:20px;display:block}.mm h2{margin:22px 0 8px;font-size:16px}.mm td{vertical-align:top}</style><div class="mm"><a href="/admin/chat">&larr; Messages</a><h1>AI Memory</h1><p style="color:#6b7390">What the AI has recorded from chats, and uses in its next answers. Customer memory only helps that same customer; staff examples are masked (no long numbers) and used for wording style only.</p>';
    $h .= '<div><span class="k"><b>'.$cnt['customers'].'</b>customers remembered</span><span class="k"><b>'.$cnt['examples'].'</b>staff answers learned</span><span class="k"><b>'.$cnt['events'].'</b>behaviour events</span></div>';
    $h .= '<h2>Behaviour counts</h2><table><tr><th>Who</th><th>What</th><th>Times</th></tr>'.$by->map(fn($x) => '<tr><td>'.$e($x->actor).'</td><td>'.$e($x->type).'</td><td>'.$x->n.'</td></tr>')->implode('').'</table>';
    $h .= '<h2>Customers (latest 60)</h2><table><tr><th>Customer</th><th>Channel</th><th>Msgs</th><th>Memory summary</th></tr>'.$cust->map(fn($x) => '<tr><td>'.$e($x->name).'<br><small>'.$e($x->phone).'</small></td><td>'.$e($x->channel).'</td><td>'.$x->msg_count.'</td><td>'.$e($x->summary).'</td></tr>')->implode('').'</table>';
    $h .= '<h2>How our team answers (latest 40)</h2><table><tr><th>Intent</th><th>Customer</th><th>Staff reply</th><th>Wait</th></tr>'.$ex->map(fn($x) => '<tr><td>'.$e($x->intent).'<br><small>'.$e($x->staff).'</small></td><td>'.$e($x->customer_text).'</td><td>'.$e($x->reply_text).'</td><td>'.($x->wait_sec !== null ? round($x->wait_sec / 60).' min' : '').'</td></tr>')->implode('').'</table>';
    $h .= '<h2>Timeline (latest 60)</h2><table><tr><th>Time</th><th>Who</th><th>What</th><th>Details</th></tr>'.$ev->map(fn($x) => '<tr><td>'.\Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M H:i').'</td><td>'.$e($x->actor).'</td><td>'.$e($x->type).' '.$e($x->channel).'</td><td><small>'.$e($x->meta).'</small></td></tr>')->implode('').'</table></div>';
    return view('chat_memory', ['h' => $h]);
  }
  /**
   * WhatsApp Calling status of every connected number (read from Meta: GET /<phone id>/settings) and the owner's on/off switch.
   * Switching calling ON only makes sense once calls are answered somewhere (the admin call screen or the PBX): until then customers would ring with no answer.
   */
  public function waCalls(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403); $e = fn($v) => e((string)$v);
    $nums = []; $main = (string)Chat::sec('chat_wa_phone_id'); if ($main !== '') $nums[$main] = ['label' => 'Main number', 'ref' => null];
    try { foreach (DB::table('chat_wa_numbers')->orderBy('id')->get() as $n) $nums[(string)$n->phone_id] = ['label' => trim(($n->display_phone ?: '').' '.($n->verified_name ?: '')) ?: 'Number', 'ref' => (string)$n->phone_id]; } catch (\Throwable $x) {}
    if ($r->isMethod('post')) {
      $pid = (string)$r->input('phone_id'); $on = $r->input('action') === 'enable'; abort_unless(isset($nums[$pid]), 404);
      if ($r->input('action') === 'profile') { // fill the public WhatsApp business profile from our own official details (never overwrites the photo)
        [$tok] = Chat::waCred($nums[$pid]['ref']); $ok = false; $msg = 'Token missing.';
        if ($tok) { try { $res = \Illuminate\Support\Facades\Http::timeout(15)->withToken($tok)->post(Chat::API.'/'.$pid.'/whatsapp_business_profile', ['messaging_product' => 'whatsapp',
          'about' => 'DutyFreeBD · অরিজিনাল ডিউটি-ফ্রি পণ্য', 'address' => 'Al Sabka, Deira, Dubai, United Arab Emirates', 'email' => 'info@dutyfreebd.com', 'websites' => ['https://dutyfreebd.com'], 'vertical' => 'RETAIL',
          'description' => 'অরিজিনাল ডিউটি-ফ্রি পারফিউম, স্কিন কেয়ার ও লাইফস্টাইল পণ্য। সারা বাংলাদেশে ডেলিভারি। অর্ডার, ডেলিভারি ও যেকোনো প্রশ্নে এখানে লিখুন। সেবার সময়: সকাল ১০টা – রাত ১০টা। হটলাইন: '.\App\Support\Biz::hotline()]);
          $ok = $res->ok(); $msg = $ok ? ('প্রোফাইল পূরণ হয়েছে: '.$nums[$pid]['label']) : ('Meta: '.mb_substr((string)($res->json('error.message') ?? ('HTTP '.$res->status())), 0, 220)); } catch (\Throwable $x) { $msg = 'Meta-তে পৌঁছানো যায়নি।'; } }
        try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'action'=>'whatsapp.profile', 'subject'=>'WhatsApp business profile filled '.$pid.' -> '.($ok ? 'ok' : 'failed'), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $x) {}
        return redirect('/admin/chat/wa-calls')->with($ok ? 'ok' : 'err', $msg);
      }
      [$tok] = Chat::waCred($nums[$pid]['ref']); $msg = 'Token missing.'; $ok = false;
      if ($tok) { try { $res = \Illuminate\Support\Facades\Http::timeout(15)->withToken($tok)->post(Chat::API.'/'.$pid.'/settings', ['calling' => ['status' => $on ? 'ENABLED' : 'DISABLED']]); $ok = $res->ok(); $msg = $ok ? ('Calling '.($on ? 'চালু' : 'বন্ধ').' করা হয়েছে: '.$nums[$pid]['label']) : ('Meta: '.mb_substr((string)($res->json('error.message') ?? ('HTTP '.$res->status())), 0, 220)); } catch (\Throwable $x) { $msg = 'Meta-তে পৌঁছানো যায়নি।'; } }
      try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'action'=>'whatsapp.calling', 'subject'=>'WhatsApp calling '.($on ? 'ENABLE' : 'DISABLE').' '.$pid.' -> '.($ok ? 'ok' : 'failed'), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $x) {}
      return redirect('/admin/chat/wa-calls')->with($ok ? 'ok' : 'err', $msg);
    }
    $h = '<div class="mm" style="max-width:980px"><a href="/admin/chat">&larr; Messages</a><h1>WhatsApp Calls</h1><p style="color:#6b7390">প্রতিটা WhatsApp নম্বরে কল নেওয়ার সুবিধা (WhatsApp Business Calling) এখন কী অবস্থায় আছে, Meta থেকে সরাসরি পড়া। <b>সতর্কতা:</b> কল ধরার ব্যবস্থা (অ্যাডমিনের কল স্ক্রিন বা PBX) তৈরি হওয়ার আগে চালু করলে কাস্টমার কল দেবে কিন্তু কেউ ধরবে না।</p>';
    if (session('ok')) $h .= '<div style="background:#F0FAF3;border:1px solid #BFE3CC;color:#14683A;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-weight:600">'.$e(session('ok')).'</div>';
    if (session('err')) $h .= '<div style="background:#FEF3F2;border:1px solid #F6C4C0;color:#B42318;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-weight:600">'.$e(session('err')).'</div>';
    $h .= '<table><tr><th>নম্বর</th><th>Calling</th><th>কল আইকন</th><th>SIP</th><th>ভয়েসমেইল</th><th>Meta-র বার্তা</th><th></th></tr>';
    foreach ($nums as $pid => $n) {
      [$tok] = Chat::waCred($n['ref']); $st = '—'; $ic = '—'; $sip = '—'; $vm = '—'; $note = '';
      if (!$tok) $note = 'Token নেই';
      else { try { $res = \Illuminate\Support\Facades\Http::timeout(12)->withToken($tok)->get(Chat::API.'/'.$pid.'/settings'); if ($res->ok()) { $c = (array)$res->json('calling'); $st = (string)($c['status'] ?? 'সেট করা নেই'); $ic = (string)($c['call_icon_visibility'] ?? '—'); $sip = (string)($c['sip']['status'] ?? '—'); $vm = (string)($c['voicemail']['status'] ?? '—'); if (!$c) $note = 'Calling-এর কোনো সেটিং ফেরত আসেনি (এই নম্বরে সুবিধাটা এখনো না-ও থাকতে পারে)'; } else $note = mb_substr((string)($res->json('error.message') ?? ('HTTP '.$res->status())), 0, 200); } catch (\Throwable $x) { $note = 'Meta-তে পৌঁছানো যায়নি'; } }
      $on = strtoupper($st) === 'ENABLED'; $inf = '';
      if ($tok) { try { $ri = \Illuminate\Support\Facades\Http::timeout(12)->withToken($tok)->get(Chat::API.'/'.$pid, ['fields' => 'messaging_limit_tier,platform_type,status,quality_rating,name_status,code_verification_status']); $inf = $ri->ok() ? ('সীমা: '.($ri->json('messaging_limit_tier') ?? 'Meta দেয়নি').' · প্ল্যাটফর্ম: '.($ri->json('platform_type') ?? '—').' · অবস্থা: '.($ri->json('status') ?? '—').' · মান: '.($ri->json('quality_rating') ?? '—').' · নাম: '.($ri->json('name_status') ?? '—')) : ('তথ্য: '.mb_substr((string)($ri->json('error.message') ?? ('HTTP '.$ri->status())), 0, 160)); } catch (\Throwable $x) { $inf = ''; } }
      $pf = ''; if ($tok) { try { $rp = \Illuminate\Support\Facades\Http::timeout(12)->withToken($tok)->get(Chat::API.'/'.$pid.'/whatsapp_business_profile', ['fields' => 'about,address,description,email,websites,vertical']); $pd = (array)($rp->json('data.0') ?? []); $have = array_filter(['about' => $pd['about'] ?? '', 'ঠিকানা' => $pd['address'] ?? '', 'বিবরণ' => $pd['description'] ?? '', 'ইমেইল' => $pd['email'] ?? '', 'ওয়েবসাইট' => implode(', ', (array)($pd['websites'] ?? []))], fn($v) => trim((string)$v) !== ''); $pf = $rp->ok() ? ('প্রোফাইল: '.($have ? implode(' · ', array_map(fn($k, $v) => $k.': '.mb_substr((string)$v, 0, 40), array_keys($have), $have)) : 'ফাঁকা')) : ''; } catch (\Throwable $x) {} }
      $h .= '<tr><td><b>'.$e($n['label']).'</b><br><small>'.$e($pid).'</small>'.($pf !== '' ? '<br><small style="color:#6b7390">'.$e($pf).'</small>' : '').($inf !== '' ? '<br><small style="color:#6b7390">'.$e($inf).'</small>' : '').'</td><td>'.$e($st).'</td><td>'.$e($ic).'</td><td>'.$e($sip).'</td><td>'.$e($vm).'</td><td><small>'.$e($note).'</small></td><td><form method="post">'.csrf_field().'<input type="hidden" name="phone_id" value="'.$e($pid).'"><button name="action" value="'.($on ? 'disable' : 'enable').'"'.($tok ? '' : ' disabled').'>'.($on ? 'বন্ধ করুন' : 'চালু করুন').'</button> <button name="action" value="profile"'.($tok ? '' : ' disabled').' style="margin-top:4px">প্রোফাইল পূরণ</button></form></td></tr>';
    }
    $h .= ($nums ? '' : '<tr><td colspan="7" style="color:#6b7390">কোনো WhatsApp নম্বর সংযুক্ত নেই।</td></tr>').'</table><p style="color:#6b7390;margin-top:10px">Meta-র শর্ত: নম্বরের মেসেজিং সীমা অন্তত ২,০০০ হতে হবে, নইলে চালু হবে না।</p></div>';
    return view('chat_memory', ['h' => $h]);
  }
  /** AI Chat Playbook: draft written from where the AI stopped + how the team answered; the owner activates it. */
  public function playbook(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403); $P = \App\Support\AiPlaybook::class;
    if ($r->isMethod('post')) {
      $a = (string)$r->input('action');
      if ($a === 'generate') { @set_time_limit(170); [$ok, $msg] = $P::generate(); return redirect('/admin/chat/playbook')->with($ok ? 'ok' : 'err', $msg); }
      if ($a === 'activate') { $P::activate((string)$r->input('text', ''), (string)(auth()->user()->name ?? '')); return redirect('/admin/chat/playbook')->with('ok', 'Playbook চালু হয়েছে। এখন থেকে AI প্রতিটা উত্তরে এটা মানবে।'); }
      if ($a === 'off') { $P::activate('', (string)(auth()->user()->name ?? '')); return redirect('/admin/chat/playbook')->with('ok', 'Playbook বন্ধ করা হয়েছে।'); }
      return redirect('/admin/chat/playbook');
    }
    $e = fn($v) => e((string)$v); $m = $P::meta(); $act = $P::active(); $dr = $P::draft(); $ev = $P::evidence();
    $h = '<div class="mm" style="max-width:980px"><a href="/admin/chat">&larr; Messages</a><h1>AI Chat Playbook</h1><p style="color:#6b7390">AI কোথায় থেমে মানুষের কাছে পাঠিয়েছে এবং আমাদের টিম আসলে কীভাবে উত্তর দিয়েছে, সেখান থেকে নিয়মের একটা খসড়া তৈরি হয়। আপনি পড়ে, দরকার হলে বদলে, তারপর চালু করবেন। দাম, স্টক, ডিসকাউন্ট বা প্রতিশ্রুতি এখানে থাকে না; সেগুলো সবসময় লাইভ ডেটা থেকে আসে। <a href="/admin/chat/ai-report">AI রিপোর্ট</a></p>';
    if (session('ok')) $h .= '<div style="background:#F0FAF3;border:1px solid #BFE3CC;color:#14683A;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-weight:600">'.$e(session('ok')).'</div>';
    if (session('err')) $h .= '<div style="background:#FEF3F2;border:1px solid #F6C4C0;color:#B42318;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-weight:600">'.$e(session('err')).'</div>';
    $h .= '<p><b>অবস্থা:</b> '.($act !== '' ? 'চালু ('.$e($m['active_at'] ?? '').($m['active_by'] ?? '' ? ', '.$e($m['active_by']) : '').')' : 'চালু নেই').' · খসড়ার উৎস: গত '.$ev['days'].' দিনের '.count($ev['handoffs']).'টা Handoff-কারণ, '.count($ev['examples']).'টা টিমের উত্তর'.(!empty($m['draft_at']) ? ' · শেষ খসড়া '.$e($m['draft_at']) : '').'</p>';
    $h .= '<form method="post" style="margin-bottom:10px">'.csrf_field().'<button name="action" value="generate">নতুন খসড়া তৈরি করুন (১–২ মিনিট)</button></form>';
    $h .= '<form method="post">'.csrf_field().'<textarea name="text" rows="22" style="width:100%;font-family:inherit;font-size:13px;line-height:1.5" placeholder="খসড়া এখনো নেই। উপরের বাটন চাপুন।">'.$e($dr !== '' ? $dr : $act).'</textarea><div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><button name="action" value="activate">এই লেখাটা চালু করুন</button>'.($act !== '' ? '<button name="action" value="off" style="background:#fff;color:#0B1220;border:1px solid #0B1220">Playbook বন্ধ করুন</button>' : '').'</div></form>';
    $h .= '<h2 style="font-size:15px;margin-top:18px">সবসময় চালু নিয়ম</h2><p style="color:#6b7390">চ্যাটের উত্তর খুব ছোট: এক বাক্য যথেষ্ট হলে এক বাক্য, সর্বোচ্চ দুই ছোট বাক্য (দাম/প্রোডাক্টের তালিকা বা অর্ডার নেওয়ার সময় ছাড়া)।</p></div>';
    return view('chat_memory', ['h' => $h]);
  }
  /** Public comments on our posts and what the AI answered (latest 150). */
  public function comments() {
    $e = fn($v) => e((string)$v); $ok = \App\Support\ChatComments::ready();
    $rows = $ok ? DB::table('chat_comments')->orderByDesc('id')->limit(150)->get() : collect();
    $by = $ok ? DB::table('chat_comments')->where('created_at', '>=', now()->subDays(7))->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all() : [];
    $lab = ['new' => 'অপেক্ষায়', 'replied' => 'উত্তর দেওয়া হয়েছে', 'skipped' => 'স্টাফের জন্য রাখা', 'failed' => 'পাঠানো যায়নি'];
    $h = '<style>.k{display:inline-block;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:8px 14px;margin:0 8px 8px 0}.k b{font-size:20px;display:block}.mm td{vertical-align:top}</style><div class="mm"><a href="/admin/chat">&larr; Messages</a><h1>Comment Replies</h1><p style="color:#6b7390">আমাদের পেজের পোস্টে আসা কমেন্ট ও AI-এর উত্তর। AI Comment Reply এখন: <b>'.(\App\Support\ChatComments::on() ? 'চালু' : 'বন্ধ').'</b> (Chat Settings থেকে বদলানো যায়)। সিগারেট/ভেপ নিয়ে পাবলিক কমেন্টে কখনো দাম বলা হয় না, ইনবক্সে ডাকা হয়।</p>';
    $h .= '<div>'.collect($lab)->map(fn($t, $k) => '<span class="k"><b>'.(int)($by[$k] ?? 0).'</b>'.$t.' (৭ দিন)</span>')->implode('').'</div>';
    $h .= '<table><tr><th>সময়</th><th>কে</th><th>কমেন্ট</th><th>AI উত্তর</th><th>অবস্থা</th></tr>'.$rows->map(fn($x) => '<tr><td>'.\Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M H:i').'</td><td>'.$e($x->from_name).'</td><td>'.$e(mb_substr((string)$x->message, 0, 300)).'</td><td>'.$e($x->reply_text).'</td><td>'.$e($lab[$x->status] ?? $x->status).($x->note ? '<br><small>'.$e($x->note).'</small>' : '').'</td></tr>')->implode('').($rows->count() ? '' : '<tr><td colspan="5" style="color:#6b7390">এখনো কোনো কমেন্ট আসেনি। কমেন্ট পেতে Meta অ্যাপে পেজের "feed" সাবস্ক্রিপশন চালু থাকতে হবে।</td></tr>').'</table></div>';
    return view('chat_memory', ['h' => $h]);
  }
  /** How much the AI handles alone and where it stops (read-only). */
  public function aiReport(Request $r) {
    $days = max(1, min(60, (int)$r->query('days', 7))); $from = now()->subDays($days)->toDateTimeString(); $e = fn($v) => e((string)$v);
    $q = DB::selectOne("select count(*) as threads,
        count(*) filter (where ai > 0 and staff = 0) as ai_only, count(*) filter (where ai > 0 and staff > 0) as both_, count(*) filter (where ai = 0 and staff > 0) as staff_only, count(*) filter (where ai = 0 and staff = 0) as none_
      from (select m.thread_id, count(*) filter (where m.dir = 'out' and m.is_ai is true and m.status <> 'failed') as ai, count(*) filter (where m.dir = 'out' and (m.is_ai is null or m.is_ai is false) and m.status <> 'failed') as staff,
              count(*) filter (where m.dir = 'in') as inn from chat_messages m where m.created_at >= ? group by m.thread_id) x where x.inn > 0", [$from]);
    $tot = max(1, (int)$q->threads); $pc = fn($n) => round((int)$n * 100 / $tot) . '%';
    $ch = DB::select("select t.channel, count(distinct t.id) n from chat_threads t join chat_messages m on m.thread_id = t.id and m.dir = 'in' and m.created_at >= ? group by t.channel order by n desc", [$from]);
    $ho = \Illuminate\Support\Facades\Schema::hasTable('chat_handoffs') ? DB::table('chat_handoffs')->where('created_at', '>=', $from)->selectRaw('reason, count(*) n')->groupBy('reason')->orderByDesc('n')->limit(12)->get() : collect();
    $nh = DB::table('chat_threads')->where('needs_human', true)->selectRaw("coalesce(nullif(intent,''),'(unknown)') as intent, count(*) n")->groupBy('intent')->orderByDesc('n')->limit(10)->get();
    $vo = \Illuminate\Support\Facades\Schema::hasTable('voice_chat_logs') ? DB::selectOne("select count(*) filter (where ok) ok, count(*) filter (where not ok) bad from voice_chat_logs where kind = 'stt' and created_at >= ?", [$from]) : null;
    $h = '<style>.k{display:inline-block;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:8px 14px;margin:0 8px 8px 0;min-width:150px}.k b{font-size:20px;display:block}.mm h2{margin:20px 0 8px;font-size:15px}</style><div class="mm"><a href="/admin/chat">&larr; Messages</a><h1>AI Automation Report</h1><p style="color:#6b7390">গত '.$days.' দিনে যেসব কথোপকথনে কাস্টমার লিখেছে, সেগুলো কে সামলেছে। <a href="?days=1">১ দিন</a> · <a href="?days=7">৭ দিন</a> · <a href="?days=30">৩০ দিন</a></p>';
    $h .= '<div><span class="k"><b>'.(int)$q->threads.'</b>মোট কথোপকথন</span><span class="k"><b>'.(int)$q->ai_only.' ('.$pc($q->ai_only).')</b>শুধু AI উত্তর দিয়েছে</span><span class="k"><b>'.(int)$q->both_.' ('.$pc($q->both_).')</b>AI + স্টাফ দুজনই</span><span class="k"><b>'.(int)$q->staff_only.' ('.$pc($q->staff_only).')</b>শুধু স্টাফ</span><span class="k"><b style="color:#B91C1C">'.(int)$q->none_.' ('.$pc($q->none_).')</b>কেউ উত্তর দেয়নি</span></div>';
    $h .= '<h2>চ্যানেল অনুযায়ী</h2><table><tr><th>চ্যানেল</th><th>কথোপকথন</th></tr>'.collect($ch)->map(fn($x) => '<tr><td>'.$e($x->channel).'</td><td>'.(int)$x->n.'</td></tr>')->implode('').'</table>';
    $h .= '<h2>AI কেন মানুষের কাছে পাঠিয়েছে (Handoff-এর কারণ)</h2><table><tr><th>কারণ</th><th>বার</th></tr>'.($ho->count() ? $ho->map(fn($x) => '<tr><td>'.$e($x->reason ?: '(কারণ লেখা নেই)').'</td><td>'.(int)$x->n.'</td></tr>')->implode('') : '<tr><td colspan="2" style="color:#6b7390">এই সময়ে কোনো Handoff নেই।</td></tr>').'</table>';
    $h .= '<h2>এখন মানুষের অপেক্ষায় থাকা কথোপকথন (বিষয় অনুযায়ী)</h2><table><tr><th>বিষয়</th><th>কথোপকথন</th></tr>'.($nh->count() ? $nh->map(fn($x) => '<tr><td>'.$e(str_replace('_', ' ', $x->intent)).'</td><td>'.(int)$x->n.'</td></tr>')->implode('') : '<tr><td colspan="2" style="color:#6b7390">কিছু নেই।</td></tr>').'</table>';
    if ($vo) $h .= '<h2>ভয়েস মেসেজ</h2><div><span class="k"><b>'.(int)$vo->ok.'</b>বোঝা গেছে</span><span class="k"><b style="color:#B91C1C">'.(int)$vo->bad.'</b>বোঝা যায়নি (চেষ্টা প্রতি)</span></div>';
    $h .= '</div>';
    return view('chat_memory', ['h' => $h]);
  }
  // ---- Order Requests: orders the AI collected but did not place (Auto Approve rules said staff must decide) ----
  public function orderRequests(Request $r) {
    $st = in_array($r->query('s'), ['review', 'created', 'dismissed'], true) ? $r->query('s') : 'review';
    $rows = DB::table('chat_order_requests')->where('status', $st)->orderByDesc('id')->limit(100)->get();
    return response()->json(['requests'=>$rows->map(fn($x) => ['id'=>$x->id, 'thread_id'=>$x->thread_id, 'channel'=>$x->channel, 'name'=>$x->name, 'phone'=>$x->phone, 'address'=>$x->address, 'area'=>$x->area, 'note'=>$x->note,
      'items'=>json_decode($x->items ?: '[]', true), 'total'=>(float)$x->total, 'status'=>$x->status, 'reason'=>$x->reason, 'invoice'=>$x->invoice, 'order_id'=>$x->order_id, 'at'=>\Carbon\Carbon::parse($x->created_at)->toIso8601String()])]);
  }
  public function orderRequestCreate(Request $r, $id) {
    $x = DB::table('chat_order_requests')->find($id); abort_unless($x, 404);
    if ($x->status !== 'review') return response()->json(['ok'=>false, 'error'=>'already handled'], 409);
    if (trim((string)$x->address) === '' || $x->cart === '[]' || !json_decode($x->cart, true)) return response()->json(['ok'=>false, 'error'=>'This request has no product or address yet. Open the chat, collect the details, then create the order.'], 422);
    if (!DB::table('chat_order_requests')->where('id', $id)->where('status', 'review')->update(['status'=>'creating', 'updated_at'=>now()])) return response()->json(['ok'=>false, 'error'=>'already handled'], 409);
    $p = ['name'=>$x->name, 'phone'=>$x->phone, 'address'=>$x->address, 'area'=>$x->area, 'note'=>$x->note, 'cart'=>json_decode($x->cart, true), 'total'=>$x->total];
    $res = \App\Support\AgentTools::make($p, (int)$x->thread_id, 'req-'.$x->id, null, auth()->user()->name, '[Order Request #'.$x->id.' by '.auth()->user()->name.']');
    if (empty($res['ok'])) { DB::table('chat_order_requests')->where('id', $id)->update(['status'=>'review', 'updated_at'=>now()]); return response()->json(['ok'=>false, 'error'=>$res['error']], 422); }
    DB::table('chat_order_requests')->where('id', $id)->update(['status'=>'created', 'order_id'=>$res['order_id'], 'invoice'=>$res['invoice'], 'handled_by'=>auth()->id(), 'handled_at'=>now(), 'updated_at'=>now()]);
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.order_request_create', 'subject'=>'Order Request #'.$id.' -> '.$res['invoice'], 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json(['ok'=>true, 'invoice'=>$res['invoice'], 'order_id'=>$res['order_id']]);
  }
  public function orderRequestDismiss(Request $r, $id) {
    $x = DB::table('chat_order_requests')->find($id); abort_unless($x, 404);
    DB::table('chat_order_requests')->where('id', $id)->where('status', 'review')->update(['status'=>'dismissed', 'handled_by'=>auth()->id(), 'handled_at'=>now(), 'updated_at'=>now()]);
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'chat.order_request_dismiss', 'subject'=>'Order Request #'.$id, 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json(['ok'=>true]);
  }
}
