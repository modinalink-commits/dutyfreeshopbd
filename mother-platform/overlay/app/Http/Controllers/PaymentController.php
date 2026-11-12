<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class PaymentController extends Controller {
  const KINDS = ['bank'=>\App\Support\Brand::BANK_LABEL,'bkash'=>'bKash Personal','nagad'=>'Nagad Personal'];
  public static function waNumber(): string {
    return \App\Support\Biz::waIntl();
  }
  public static function active() { try { return DB::table('payment_methods')->where('active',true)->orderBy('position')->get(); } catch (\Throwable $e) { return collect(); } }
  public function index() { return view('payment_methods', ['rows'=>DB::table('payment_methods')->orderBy('position')->get(), 'wa'=>\App\Support\Biz::whatsapp(), 'pending'=>DB::table('orders')->where('pay_status','Verification Pending')->count()]); }
  public function save(Request $r, $id = null) {
    $d = $r->validate(['kind'=>'required|in:bank,bkash,nagad,cod,shurjopay','title'=>'required|max:80','description'=>'nullable|max:300','logo_file'=>'nullable|image|max:1024','remove_logo'=>'nullable','account_name'=>'nullable|max:120','account_no'=>'nullable|max:60','branch'=>'nullable|max:120','routing'=>'nullable|max:30','swift'=>'nullable|max:30','instruction'=>'nullable|max:500','position'=>'nullable|integer']);
    $d['active'] = $r->boolean('active'); $d['updated_at'] = now(); $d['position'] = (int)($d['position'] ?? 0);
    $file = $r->file('logo_file'); unset($d['logo_file'], $d['remove_logo']);
    if ($id) DB::table('payment_methods')->where('id',$id)->update($d); else { $d['created_at'] = now(); $d['logo'] = $d['kind']==='bank' ? null : $d['kind']; $d['code'] = $d['kind']; $id = DB::table('payment_methods')->insertGetId($d); }
    if ($file) { $dir = public_path('uploads'); if (!is_dir($dir)) @mkdir($dir, 0775, true); $ext = strtolower($file->getClientOriginalExtension() ?: 'png'); $nm = 'pay-'.$id.'-'.time().'.'.$ext; $file->move($dir, $nm); DB::table('payment_methods')->where('id',$id)->update(['logo_url'=>'/uploads/'.$nm]); }
    elseif ($r->boolean('remove_logo')) DB::table('payment_methods')->where('id',$id)->update(['logo_url'=>null,'logo'=>null]);
    $this->audit($r, 'payment.method', $d['title']); Cache::forget('store.settings2'); Cache::forget('pay.methods');
    return redirect('/admin/payment-methods')->with('ok','Saved: '.$d['title']);
  }
  public function wa(Request $r) { return back()->with('ok','The WhatsApp number is now managed in one place: Site settings > Business contact.'); }
  public function proof($id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->pay_proof && Storage::disk('local')->exists($o->pay_proof), 404);
    return response()->file(Storage::disk('local')->path($o->pay_proof));
  }
  public function decide(Request $r, $id, $mode) {
    abort_unless(in_array($mode, ['verify','reject'], true), 404);
    abort_unless(ManualPaymentController::canVerify(), 403, 'Only Mohammad Faruk can verify or reject manual payments.');
    $o = DB::table('orders')->find($id); abort_unless($o && $o->pay_status, 404);
    $new = $mode === 'verify' ? 'Verified' : 'Rejected';
    if ($o->pay_status === $new) return back()->with('ok', "Payment already $new for ".$o->invoice);
    if ($new === 'Rejected' && $o->pay_status === 'Verified' && in_array($o->status, ['Shipment','Delivered','Partial Delivered','Returned','Return Pending'], true)) return back()->with('bad', 'This payment was already verified and the order is '.$o->status.'; it cannot be rejected now.');
    $upd = DB::table('orders')->where('id',$id)->where('pay_status',$o->pay_status)->update(['pay_status'=>$new,'pay_by'=>Auth::user()->name,'pay_at'=>now(),'updated_at'=>now()]);
    if (!$upd) return back()->with('bad', 'Payment status was just changed by someone else; reload and check.');
    DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$o->status,'note'=>"Payment $new (".$o->payment_method.', ref '.$o->pay_txn.')','created_at'=>now(),'updated_at'=>now()]);
    $this->audit($r, 'payment.'.$mode, $o->invoice);
    $ap = $mode === 'verify' ? \App\Support\PaymentSettle::apply((int)$id, 'Payment verification') : null;
    return back()->with('ok', "Payment $new for ".$o->invoice.($ap ? ' · order moved to Approved' : ''));
  }
  private function audit(Request $r, $action, $subject) {
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$action,'subject'=>mb_substr((string)$subject,0,190),'meta'=>json_encode([]),'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
  }
}
