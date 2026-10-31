<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class DealController extends Controller {
  private function sold(): array {
    return DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.status','!=','Cancel')->where('o.created_at','>=',now()->subDays(30))->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q')->pluck('q','pid')->all();
  }
  private function data(): array {
    $s = StoreController::set(); $min = (float)($s['deal_min_off'] ?? 0); $max = max(1, (int)($s['deal_max'] ?? 12));
    $cur = StoreController::dealBase($min)->limit($max)->get(); $ids = $cur->pluck('id')->all();
    $flags = DB::table('deal_flags')->pluck('mode','product_id')->all();
    $sold = $this->sold();
    $others = DB::table('products')->where('published',true)->whereColumn('compare_price','>','price')->where('compare_price','>',0)->whereNotIn('id',$ids)->orderByRaw('(compare_price - price) / compare_price desc')->limit(60)->get();
    foreach ([$cur,$others] as $list) foreach ($list as $p) { $p->off = StoreController::off($p); $p->sold30 = (int)($sold[$p->id] ?? 0); $p->flag = $flags[$p->id] ?? ''; }
    $pinned = DB::table('products')->whereIn('id', array_keys(array_filter($flags, fn($m)=>$m==='pin')))->get(['id','name']);
    return ['cur'=>$cur,'others'=>$others,'min'=>$min,'max'=>$max,'total'=>DB::table('products')->where('published',true)->whereColumn('compare_price','>','price')->where('compare_price','>',0)->count(),'pinned'=>$pinned];
  }
  public function index() { return view('deals', $this->data()); }
  public function csv() {
    $d = $this->data();
    return response()->streamDownload(function () use ($d) {
      $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['Product','Price','Old price','Discount %','Stock','Sold 30d','Flag','On Today\'s Deal']);
      foreach ([[$d['cur'],'yes'],[$d['others'],'no']] as [$list,$on]) foreach ($list as $p) fputcsv($o, [$p->name,$p->price,$p->compare_price,$p->off,$p->stock ?? '',$p->sold30,$p->flag,$on]);
      fclose($o);
    }, 'todays-deal-'.now()->format('Y-m-d').'.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
  }
  private function bust() { foreach (['store.home4','store.home6','store.home5','store.settings2'] as $k) Cache::forget($k); }
  public function flag(Request $r, $id, $mode) {
    abort_unless(in_array($mode, ['pin','exclude','clear'], true), 404);
    $p = DB::table('products')->find($id); abort_unless($p, 404);
    if ($mode === 'clear') DB::table('deal_flags')->where('product_id',$id)->delete();
    else DB::table('deal_flags')->updateOrInsert(['product_id'=>$id], ['mode'=>$mode,'updated_at'=>now(),'created_at'=>now()]);
    $this->audit($r, "deal.$mode", $p->name); $this->bust();
    return back()->with('ok', ($mode==='clear' ? 'Back to automatic: ' : ($mode==='pin' ? 'Pinned to the top: ' : 'Hidden from Today\'s Deal: ')).$p->name);
  }
  public function settings(Request $r) {
    $min = max(0, min(90, (float)$r->input('deal_min_off', 0))); $max = max(1, min(24, (int)$r->input('deal_max', 12)));
    foreach (['deal_min_off'=>$min,'deal_max'=>$max] as $k=>$v) DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>(string)$v,'updated_at'=>now(),'created_at'=>now()]);
    $this->audit($r, 'deal.settings', "min $min%, max $max"); $this->bust();
    return back()->with('ok', 'Today\'s Deal rules saved');
  }
  private function audit(Request $r, $action, $subject) {
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$action,'subject'=>mb_substr((string)$subject,0,190),'meta'=>json_encode([]),'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
  }
}
