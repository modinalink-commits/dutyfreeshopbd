<?php
namespace App\Http\Controllers;
use App\Models\Mother;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class AdminController extends Controller {
  public function loginForm() { return view('login'); }
  public function login(Request $r) {
    $c = $r->validate(['email'=>'required|email','password'=>'required']);
    if (Auth::attempt($c + ['active'=>true], false)) { $r->session()->regenerate(); return redirect('/admin'); }
    return back()->withErrors(['email'=>'Wrong email or password'])->onlyInput('email');
  }
  public function logout(Request $r) { Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login'); }
  public function dashboard() {
    $counts = DB::table('orders')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status');
    $today = DB::table('orders')->whereDate('created_at', today())->count();
    $sales = DB::table('orders')->whereDate('created_at', today())->whereNotIn('status',['Cancel','Returned'])->sum('total');
    return view('dashboard', compact('counts','today','sales'));
  }
  public function orders(Request $r) {
    $q = DB::table('orders')->orderByDesc('id');
    if ($r->filled('status')) $q->where('status', $r->status);
    if ($r->filled('q')) $q->where(fn($w) => $w->where('invoice','like','%'.$r->q.'%')->orWhere('phone','like','%'.$r->q.'%')->orWhere('customer_name','ilike','%'.$r->q.'%'));
    $orders = $q->paginate(50)->withQueryString();
    $counts = DB::table('orders')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status');
    return view('orders', compact('orders','counts'));
  }
  public function orderNew() { return view('order_new'); }
  public function orderStore(Request $r) {
    $d = $r->validate(['customer_name'=>'required|string|max:120','phone'=>'required|string|max:20','address'=>'nullable|string','district'=>'nullable|string|max:60','source'=>'nullable|string|max:40','total'=>'required|numeric|min:0','shipping'=>'nullable|numeric|min:0','note'=>'nullable|string','item_name'=>'required|string|max:200','qty'=>'required|integer|min:1']);
    $id = DB::transaction(function () use ($d) {
      $id = DB::table('orders')->insertGetId(['invoice'=>'TMP'.uniqid(),'store_id'=>1,'customer_name'=>$d['customer_name'],'phone'=>$d['phone'],'address'=>$d['address']??null,'district'=>$d['district']??null,'source'=>$d['source']??'Web Site','shipping'=>$d['shipping']??0,'total'=>$d['total'],'status'=>'New','note'=>$d['note']??null,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('orders')->where('id',$id)->update(['invoice'=>'INV-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
      DB::table('order_items')->insert(['order_id'=>$id,'name'=>$d['item_name'],'qty'=>$d['qty'],'price'=>$d['total'],'created_at'=>now(),'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>null,'to_status'=>'New','note'=>'Order created','created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    return redirect("/admin/orders/$id");
  }
  public function orderShow($id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    $items = DB::table('order_items')->where('order_id',$id)->get();
    $logs = DB::table('order_status_logs')->where('order_id',$id)->orderBy('id')->get();
    return view('order_show', compact('o','items','logs'));
  }
  public function orderStatus(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:'.implode(',',Mother::STAGES),'note'=>'nullable|string|max:500']);
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    if ($o->status !== $d['status']) DB::transaction(function () use ($o, $d, $id) {
      DB::table('orders')->where('id',$id)->update(['status'=>$d['status'],'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$d['status'],'note'=>$d['note']??null,'created_at'=>now(),'updated_at'=>now()]);
    });
    return back();
  }
  public function ordersCsv() {
    return response()->streamDownload(function () {
      $f = fopen('php://output','w'); fputcsv($f,['Invoice','Customer','Phone','District','Source','Total','Shipping','Status','Created']);
      foreach (DB::table('orders')->orderByDesc('id')->cursor() as $o) fputcsv($f,[$o->invoice,$o->customer_name,$o->phone,$o->district,$o->source,$o->total,$o->shipping,$o->status,$o->created_at]);
      fclose($f);
    }, 'orders.csv', ['Content-Type'=>'text/csv']);
  }
  public function staffLog() {
    $logs = DB::table('order_status_logs as l')->join('orders as o','o.id','=','l.order_id')->select('l.*','o.invoice')->orderByDesc('l.id')->paginate(100);
    return view('stafflog', compact('logs'));
  }
}
