<?php
namespace App\Http\Controllers;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SmsController extends Controller {
  public function show() {
    $counts = DB::table('sms_logs')->select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n','status');
    $set = DB::table('settings')->whereIn('key',['sms_order_confirm','owner_phone','manager_phones'])->pluck('value','key');
    return view('sms_setup', ['hasKey'=>(bool)Sms::secret('sms_api_key'), 'sender'=>Sms::secret('sms_sender_id'), 'counts'=>$counts, 'set'=>$set, 'ready'=>Sms::configured(), 'balance'=>session('balance'), 'recent'=>DB::table('sms_logs')->orderByDesc('id')->limit(15)->get()]);
  }
  public function save(Request $r) {
    $d = $r->validate(['api_key'=>'nullable|string|max:200', 'sender_id'=>'nullable|string|max:30', 'owner_phone'=>'nullable|string|max:30', 'manager_phones'=>'nullable|string|max:300']);
    if (!empty($d['api_key'])) Sms::setSecret('sms_api_key', trim($d['api_key']));
    if (!empty($d['sender_id'])) Sms::setSecret('sms_sender_id', trim($d['sender_id']));
    foreach (['owner_phone'=>$d['owner_phone'] ?? '', 'manager_phones'=>$d['manager_phones'] ?? '', 'sms_order_confirm'=>$r->boolean('order_confirm') ? '1' : '0'] as $k => $v)
      DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>$v, 'updated_at'=>now(), 'created_at'=>now()]);
    \Illuminate\Support\Facades\Cache::forget('store.settings2');
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>'sms.settings','subject'=>'SMS gateway settings saved','ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
    return back()->with('ok','SMS settings saved.');
  }
  public function test(Request $r) {
    $d = $r->validate(['to'=>'required|string|max:30']);
    if (!Sms::configured()) return back()->withErrors(['to'=>'Save the API key and Sender ID first.']);
    [$ok, $msg] = Sms::send($d['to'], 'Test SMS from DutyFreeShopBD Mother Dashboard. Time: '.now('Asia/Dhaka')->format('H:i'));
    DB::table('sms_logs')->insert(['to'=>$d['to'],'body'=>'Test SMS','event'=>'test','status'=>$ok?'sent':'failed','provider_response'=>$msg,'sent_at'=>$ok?now():null,'created_at'=>now(),'updated_at'=>now()]);
    return back()->with($ok ? 'ok' : 'bad', ($ok ? 'Test SMS sent: ' : 'Test SMS failed: ').$msg);
  }
  public function balance() { return back()->with('balance', Sms::balance()); }
  public function flush() { $n = Sms::flush(100); return back()->with('ok', "$n queued messages processed."); }
}
