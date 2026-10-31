<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class PaymentController extends Controller {
  const KINDS = ['bank'=>'Bank Transfer','bkash'=>'bKash Personal','nagad'=>'Nagad Personal'];
  public static function waNumber(): string {
    $n = preg_replace('/\D/','',(string)(DB::table('settings')->where('key','pay_wa')->value('value') ?: '01871600600'));
    return str_starts_with($n,'880') ? $n : '88'.ltrim($n,'0') ;
  }
  public static function active() { try { return DB::table('payment_methods')->where('active',true)->orderBy('position')->get(); } catch (\Throwable $e) { return collect(); } }
  public function index() { return view('payment_methods', ['rows'=>DB::table('payment_methods')->orderBy('position')->get(), 'wa'=>DB::table('settings')->where('key','pay_wa')->value('value') ?: '01871600600', 'pending'=>DB::table('orders')->where('pay_status','Verification Pending')->count()]); }
  public function save(Request $r, $id = null) {
    $d = $r->validate(['kind'=>'required|in:bank,bkash,nagad','title'=>'required|max:80','account_name'=>'nullable|max:120','account_no'=>'nullable|max:60','branch'=>'nullable|max:120','routing'=>'nullable|max:30','swift'=>'nullable|max:30','instruction'=>'nullable|max:500','position'=>'nullable|integer']);
    $d['active'] = $r->boolean('active'); $d['updated_at'] = now(); $d['position'] = (int)($d['position'] ?? 0);
    if ($id) DB::table('payment_methods')->where('id',$id)->update($d); else { $d['created_at'] = now(); $d['logo'] = $d['kind']==='bank' ? null : $d['kind']; $id = DB::table('payment_methods')->insertGetId($d); }
    $this->audit($r, 'payment.method', $d['title']); Cache::forget('store.settings2');
    return redirect('/admin/payment-methods')->with('ok','Saved: '.$d['title']);
  }
  public function wa(Request $r) {
    $n = preg_replace('/\D/','',(string)$r->input('pay_wa')); abort_unless(strlen($n) >= 10, 422);
    DB::table('settings')->updateOrInsert(['key'=>'pay_wa'], ['value'=>$n,'updated_at'=>now(),'created_at'=>now()]);
    $this->audit($r, 'payment.whatsapp', $n); return back()->with('ok','WhatsApp approval number saved');
  }
  public function proof($id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->pay_proof && Storage::disk('local')->exists($o->pay_proof), 404);
    return response()->file(Storage::disk('local')->path($o->pay_proof));
  }
  public function decide(Request $r, $id, $mode) {
    abort_unless(in_array($mode, ['verify','reject'], true), 404);
    $o = DB::table('orders')->find($id); abort_unless($o && $o->pay_status, 404);
    $new = $mode === 'verify' ? 'Verified' : 'Rejected';
    DB::table('orders')->where('id',$id)->update(['pay_status'=>$new,'pay_by'=>Auth::user()->name,'pay_at'=>now(),'updated_at'=>now()]);
    DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$o->status,'note'=>"Payment $new (".$o->payment_method.', ref '.$o->pay_txn.')','created_at'=>now(),'updated_at'=>now()]);
    $this->audit($r, 'payment.'.$mode, $o->invoice);
    return back()->with('ok', "Payment $new for ".$o->invoice);
  }
  private function audit(Request $r, $action, $subject) {
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$action,'subject'=>mb_substr((string)$subject,0,190),'meta'=>json_encode([]),'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
  }
}
