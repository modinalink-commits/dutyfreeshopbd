<?php
namespace App\Http\Controllers;
use App\Support\AiChat;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class AiChatController extends Controller {
  public function show() {
    return view('ai_chat', ['hasKey'=>(bool)Sms::secret('ai_key'), 'on'=>Sms::secret('ai_enabled') === '1', 'model'=>AiChat::model(), 'today'=>(int)Cache::get('ai.n.'.now()->format('Ymd'), 0)]);
  }
  public function save(Request $r) {
    $d = $r->validate(['api_key'=>'nullable|string|max:300', 'model'=>'required|in:claude-haiku-5-5,claude-sonnet-5-5', 'enabled'=>'nullable|boolean']);
    if (!empty($d['api_key'])) Sms::setSecret('ai_key', trim($d['api_key']));
    Sms::setSecret('ai_model', $d['model']);
    Sms::setSecret('ai_enabled', $r->boolean('enabled') ? '1' : '0');
    Cache::forget('ai.on');
    $this->audit($r, 'ai.settings', 'AI chat settings saved');
    return back()->with('ok', 'AI chat settings saved.');
  }
  public function test(Request $r) {
    [$ok, $m] = AiChat::reply([['role'=>'user', 'content'=>'Say hello in one short Bengali sentence.']]);
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'Claude replied: '.$m : 'Claude API error: '.$m);
  }
  public function ask(Request $r) {
    if (!AiChat::on()) return response()->json(['reply'=>'Chat is not available right now.'], 503);
    $k = 'ai.n.'.now()->format('Ymd'); Cache::add($k, 0, 90000);
    if (Cache::increment($k) > 1500) return response()->json(['reply'=>'আজকের চ্যাট সীমা শেষ। অনুগ্রহ করে কল করুন: 09613828081']);
    $h = $r->input('messages'); if (!is_array($h)) return response()->json(['reply'=>''], 422);
    [$ok, $m] = AiChat::reply($h);
    return response()->json(['reply'=>$ok ? $m : 'দুঃখিত, এখন উত্তর দিতে পারছি না। কল/WhatsApp করুন: 09613828081']);
  }
}
