<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DiscountController extends Controller {
  public function index() {
    return view('discounts', ['rules'=>DB::table('discount_rules')->orderBy('position')->get(), 'coupons'=>DB::table('coupons')->orderByDesc('id')->get()]);
  }
  public function ruleSave(Request $r, $id) {
    $rule = DB::table('discount_rules')->find($id); abort_unless($rule, 404);
    $cfg = json_decode($rule->config, true);
    $in = (array) $r->input('c', []);
    foreach ($cfg as $k => $v) if (array_key_exists($k, $in) && is_numeric($in[$k]) && $in[$k] >= 0) $cfg[$k] = $in[$k] + 0;
    DB::table('discount_rules')->where('id',$id)->update(['enabled'=>$r->boolean('enabled'),'config'=>json_encode($cfg),'updated_at'=>now()]);
    \Illuminate\Support\Facades\Cache::forget('store.rules');
    return back();
  }
  public function couponSave(Request $r) {
    $d = $r->validate(['code'=>'required|string|max:40|alpha_dash|unique:coupons,code','type'=>'required|in:percent,fixed,free_delivery','value'=>'required|numeric|min:0','min_order'=>'nullable|numeric|min:0','max_discount'=>'nullable|numeric|min:0','usage_limit'=>'nullable|integer|min:1','per_customer'=>'nullable|integer|min:1','valid_from'=>'nullable|date','valid_to'=>'nullable|date']);
    $d['code'] = strtoupper($d['code']); $d['min_order'] = $d['min_order'] ?? 0; $d['per_customer'] = $d['per_customer'] ?? 1;
    if ($d['type']==='percent' && $d['value']>100) return back()->withErrors(['value'=>'Percent cannot be over 100'])->withInput();
    DB::table('coupons')->insert($d + ['created_at'=>now(),'updated_at'=>now()]); return back();
  }
  public function couponToggle($id) {
    $c = DB::table('coupons')->find($id); abort_unless($c, 404);
    DB::table('coupons')->where('id',$id)->update(['active'=>!$c->active,'updated_at'=>now()]); return back();
  }
  public function couponsCsv() {
    return response()->streamDownload(function () {
      $f = fopen('php://output','w'); fputcsv($f,['Code','Type','Value','Min order','Used','Limit','From','To','Active']);
      foreach (DB::table('coupons')->orderBy('id')->cursor() as $c) fputcsv($f,[$c->code,$c->type,$c->value,$c->min_order,$c->used_count,$c->usage_limit,$c->valid_from,$c->valid_to,$c->active?'yes':'no']);
      fclose($f);
    }, 'coupons.csv', ['Content-Type'=>'text/csv']);
  }
}
