<?php
namespace App\Http\Controllers;
use App\Support\AiChat;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class AiChatController extends Controller {
  /** Read-only: words/phrases customers wrote (incoming messages) at least N times, with a flag whether the knowledge base already covers them. */
  public function keywords(Request $r) {
    $min = max(3, min(200, (int)($r->input('min') ?: 10)));
    $days = max(7, min(720, (int)($r->input('days') ?: 180)));
    $stop = array_flip(['আমি','আমার','আপনি','আপনার','আপনাকে','এই','এটা','এটি','ওই','সেই','কি','কী','কে','কেন','কোন','কোনো','না','হ্যাঁ','হা','জি','ভাই','আপু','স্যার','সার','করে','করা','করুন','করব','করবো','দিন','দিবেন','দেন','দেবেন','আছে','নাই','নেই','হবে','হয়','হলো','থেকে','জন্য','এবং','আর','তো','টা','টি','খুব','একটা','একটি','আপনাদের','আমাদের','আসসালামু','আলাইকুম','ধন্যবাদ','ok','okay','the','and','you','for','are','how','what','can','that','this','with','have','please','sir','bhai','kore','den','ache','nai','ami','apni','apnar','amar','koto','hobe','hoy','jonno','the','is','to','of','in','it','my','me','i','a']);
    $uni = []; $bi = []; $thr = []; $total = 0;
    foreach (\Illuminate\Support\Facades\DB::table('chat_messages')->where('dir', 'in')->where('kind', 'text')->where('created_at', '>=', now()->subDays($days))->whereNotNull('body')->orderBy('id')->select('id', 'thread_id', 'body')->cursor() as $m) {
      $total++;
      preg_match_all('/[\p{L}\p{M}\p{N}]+/u', mb_strtolower(mb_substr((string)$m->body, 0, 600)), $mm);
      $w = array_values(array_filter($mm[0], fn($x) => mb_strlen($x) >= 2 && !isset($stop[$x]) && !ctype_digit($x)));
      $seen = [];
      foreach ($w as $i => $x) { $uni[$x] = ($uni[$x] ?? 0) + 1; $seen[$x] = 1; if (isset($w[$i + 1])) { $b = $x.' '.$w[$i + 1]; $bi[$b] = ($bi[$b] ?? 0) + 1; $seen[$b] = 1; } }
      foreach ($seen as $k => $_) { $thr[$k][$m->thread_id] = 1; }
    }
    $kb = mb_strtolower(AiChat::kb());
    $mk = function (array $arr) use ($min, $thr, $kb) { $o = []; foreach ($arr as $k => $n) if ($n >= $min) $o[] = ['w'=>$k, 'n'=>$n, 'c'=>count($thr[$k] ?? []), 'kb'=>mb_substr_count($kb, (string)$k)]; usort($o, fn($a, $b) => $b['n'] <=> $a['n']); return array_slice($o, 0, 400); };
    return view('ai_keywords', ['min'=>$min, 'days'=>$days, 'total'=>$total, 'uni'=>$mk($uni), 'bi'=>$mk($bi)]);
  }
  public function show() {
    $lr = [];
    try { // last 7 days: conversations by the style the AI detected, how many a person had to take over (AI paused) and how many were staff-fixed to a language
      foreach (\Illuminate\Support\Facades\DB::table('chat_threads')->where('channel', 'web')->where('updated_at', '>=', now()->subDays(7))->whereNotNull('ai_detected')
        ->selectRaw("ai_detected as l, count(*) as n, sum(case when ai_paused then 1 else 0 end) as taken, sum(case when ai_lang is not null then 1 else 0 end) as fixed")->groupBy('ai_detected')->get() as $x) $lr[$x->l] = ['n'=>(int)$x->n, 'taken'=>(int)$x->taken, 'fixed'=>(int)$x->fixed];
    } catch (\Throwable $e) {}
    return view('ai_chat', ['langRows'=>$lr, 'hasKey'=>(bool)Sms::secret('ai_key'), 'on'=>Sms::secret('ai_enabled') === '1', 'model'=>AiChat::model(), 'today'=>(int)Cache::get('ai.n.'.now()->format('Ymd'), 0), 'agentTools'=>Sms::secret('ai_agent_tools') === '1', 'agentOrders'=>Sms::secret('ai_agent_orders') === '1', 'kb'=>AiChat::kb(), 'kbCustom'=>trim((string)Sms::secret('ai_kb')) !== '', 'sched'=>\App\Support\AiSchedule::get(), 'schedNow'=>\App\Support\AiSchedule::activeNow()]);
  }
  private function audit(Request $r, string $a, string $sub): void { try { \Illuminate\Support\Facades\DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$sub,'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {} }
  /** Rewrites the knowledge base text in a warmer, better-designed layout without changing any fact or rule. Returns a draft only; nothing is saved until the owner presses Save. */
  public function beautifyKb(Request $r) {
    $kb = trim((string)$r->input('kb')); if ($kb === '') return response()->json(['ok'=>false, 'error'=>'Knowledge base is empty.']);
    $key = Sms::secret('ai_key'); if (!$key) return response()->json(['ok'=>false, 'error'=>'Claude API key is not saved yet.']);
    $sys = "You are an editor for the DutyFreeBD (Bangladeshi online store) AI-assistant knowledge base, written mainly in Bangla. Rewrite the text line by line so it reads beautifully, warmly and clearly: natural, graceful, kind Bangla with a gentle, caring shop-keeper feel, clean short sentences, consistent punctuation. Design it well: keep each section as a heading line starting with '== ' and ending with ' ==' and put ONE fitting emoji at the start of the heading text (for example 🏢 company, 🚚 delivery, 💳 payment, 🎁 offers, 🛍️ products, 📝 taking orders, 🔁 returns, 🚫 never say, 🤝 hand-over, 📷 photos, 💬 language and manner). Inside a section use short lines, each starting with '• ' (one idea per line), keep step lists as '১) ২) ৩)'. Put a blank line between sections.\nSTRICT RULES: (1) Do NOT add, remove, weaken or change ANY fact, number, price, phone number, bank/bKash/Nagad detail, URL, time, percentage, condition, prohibition or instruction; every rule must keep exactly the same meaning and strength (words like never/always/must/only keep their force). (2) Keep every example phrase the AI is told to say, the forbidden-word list, and every tool name (such as delivery_info, current_offers, handoff) exactly as written. (3) Do not invent anything new. (4) The result is a prompt for another AI, so it must stay complete: do not shorten it into a summary; the result should be about the same length or slightly longer. (5) Output ONLY the rewritten knowledge base text, no explanation, no code fences.";
    try {
      $res = \Illuminate\Support\Facades\Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(170)->post('https://api.anthropic.com/v1/messages', ['model'=>'claude-sonnet-5-5', 'max_tokens'=>16000, 'system'=>$sys, 'messages'=>[['role'=>'user', 'content'=>$kb]]]);
      if (!$res->ok()) return response()->json(['ok'=>false, 'error'=>'AI error '.$res->status().': '.mb_substr((string)($res->json('error.message') ?? ''), 0, 160)]);
      $t = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
      if (mb_strlen($t) < mb_strlen($kb) * 0.7) return response()->json(['ok'=>false, 'error'=>'The rewrite came back too short, so it was discarded to protect your rules. Try again.']);
      return response()->json(['ok'=>true, 'text'=>$t]);
    } catch (\Throwable $e) { return response()->json(['ok'=>false, 'error'=>'The AI did not answer in time. Try again.']); }
  }
  public function saveKb(Request $r) {
    $d = $r->validate(['kb'=>'nullable|string|max:60000']);
    $v = trim((string)($d['kb'] ?? ''));
    Sms::setSecret('ai_kb', $r->boolean('reset') ? null : ($v === '' ? null : $v));
    Cache::forget('ai.facts3'); Cache::forget('ai.facts2');
    $this->audit($r, 'ai.kb', 'AI knowledge base saved');
    return back()->with('ok', 'Knowledge base saved. The AI uses it within a minute.');
  }
  /** One-click ON/OFF switch from the top panel; changes exactly one setting and logs it. */
  public function toggle(Request $r) {
    $d = $r->validate(['key'=>'required|in:enabled,agent_tools,agent_orders', 'on'=>'required|boolean']);
    $map = ['enabled'=>'ai_enabled', 'agent_tools'=>'ai_agent_tools', 'agent_orders'=>'ai_agent_orders'];
    $v = $r->boolean('on');
    Sms::setSecret($map[$d['key']], $v ? '1' : '0');
    Cache::forget('ai.on');
    $this->audit($r, 'ai.switch', $d['key'].' '.($v ? 'ON' : 'OFF'));
    return response()->json(['ok'=>true, 'key'=>$d['key'], 'on'=>$v]);
  }
  public function schedule(Request $r) {
    $d = $r->validate(['on'=>'nullable|boolean', 'a1'=>['required', 'regex:/^\d{1,2}:\d{2}$/'], 'b1'=>['required', 'regex:/^\d{1,2}:\d{2}$/'], 'a2'=>['nullable', 'regex:/^\d{1,2}:\d{2}$/'], 'b2'=>['nullable', 'regex:/^\d{1,2}:\d{2}$/']]);
    $w = [[$d['a1'], $d['b1']]]; if (!empty($d['a2']) && !empty($d['b2'])) $w[] = [$d['a2'], $d['b2']];
    Sms::setSecret('ai_schedule', json_encode(['on'=>$r->boolean('on'), 'w'=>$w]));
    $this->audit($r, 'ai.schedule', ($r->boolean('on') ? 'ON ' : 'OFF ').json_encode($w));
    return back()->with('ok', 'AI working hours saved.');
  }
  public function save(Request $r) {
    $d = $r->validate(['api_key'=>'nullable|string|max:300', 'model'=>'required|in:claude-haiku-5-5,claude-sonnet-5-5', 'enabled'=>'nullable|boolean', 'agent_tools'=>'nullable|boolean', 'agent_orders'=>'nullable|boolean']);
    if (!empty($d['api_key'])) Sms::setSecret('ai_key', trim($d['api_key']));
    Sms::setSecret('ai_model', $d['model']);
    Cache::forget('ai.on');
    $this->audit($r, 'ai.settings', 'AI chat settings saved');
    return back()->with('ok', 'AI chat settings saved.');
  }
  /** Admin simulation: runs the real agent loop on a typed conversation. No order is ever created and nothing is sent to a customer. */
  public function simulate(Request $r) {
    $d = $r->validate(['messages'=>'required|array|max:20', 'messages.*.role'=>'required|in:user,assistant', 'messages.*.content'=>'required|string|max:800', 'state'=>'nullable|string|max:4000', 'orders'=>'nullable|boolean']);
    if (!AiChat::on()) return response()->json(['error'=>'AI chat is off or no API key is saved'], 422);
    $st = !empty($d['state']) ? (json_decode($d['state'], true) ?: []) : [];
    $last = collect($d['messages'])->last(); $n = count($d['messages']);
    [$ok, $text, $trace] = \App\Support\AiAgent::converse($d['messages'], $st, ['sim'=>true, 'thread_id'=>null, 'orders_on'=>$r->boolean('orders'), 'last_msg_id'=>$n, 'last_msg_text'=>$last['role'] === 'user' ? $last['content'] : '']);
    return response()->json(['ok'=>$ok, 'reply'=>$ok ? $text : null, 'error'=>$ok ? null : $text, 'tools'=>$trace, 'state'=>json_encode($st, JSON_UNESCAPED_UNICODE)]);
  }
  public function test(Request $r) {
    [$ok, $m] = AiChat::reply([['role'=>'user', 'content'=>'Say hello in one short Bengali sentence.']]);
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'Claude replied: '.$m : 'Claude API error: '.$m);
  }
  public function ask(Request $r) {
    if (!AiChat::on()) return response()->json(['reply'=>'দুঃখিত, এই মুহূর্তে চ্যাট চালু নেই। অনুগ্রহ করে কল করুন: '.\App\Support\Biz::hotline()], 503);
    $k = 'ai.n.'.now()->format('Ymd'); Cache::add($k, 0, 90000);
    if (Cache::increment($k) > 1500) return response()->json(['reply'=>'আজকের চ্যাট সীমা শেষ। অনুগ্রহ করে কল করুন: '.\App\Support\Biz::hotline()]);
    $h = $r->input('messages'); if (!is_array($h)) return response()->json(['reply'=>''], 422);
    [$ok, $m] = AiChat::reply($h);
    return response()->json(['reply'=>$ok ? $m : 'দুঃখিত, এখন উত্তর দিতে পারছি না। কল/WhatsApp করুন: '.\App\Support\Biz::hotline()]);
  }
}
