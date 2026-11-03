<?php
namespace App\Http\Controllers;
use App\Support\AiChat;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class AiChatController extends Controller {
  public function show() {
    return view('ai_chat', ['hasKey'=>(bool)Sms::secret('ai_key'), 'on'=>Sms::secret('ai_enabled') === '1', 'model'=>AiChat::model(), 'today'=>(int)Cache::get('ai.n.'.now()->format('Ymd'), 0), 'agentTools'=>Sms::secret('ai_agent_tools') === '1', 'agentOrders'=>Sms::secret('ai_agent_orders') === '1']);
  }
  public function save(Request $r) {
    $d = $r->validate(['api_key'=>'nullable|string|max:300', 'model'=>'required|in:claude-haiku-5-5,claude-sonnet-5-5', 'enabled'=>'nullable|boolean', 'agent_tools'=>'nullable|boolean', 'agent_orders'=>'nullable|boolean']);
    if (!empty($d['api_key'])) Sms::setSecret('ai_key', trim($d['api_key']));
    Sms::setSecret('ai_model', $d['model']);
    Sms::setSecret('ai_enabled', $r->boolean('enabled') ? '1' : '0');
    Sms::setSecret('ai_agent_tools', $r->boolean('agent_tools') ? '1' : '0');
    Sms::setSecret('ai_agent_orders', $r->boolean('agent_orders') ? '1' : '0');
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
