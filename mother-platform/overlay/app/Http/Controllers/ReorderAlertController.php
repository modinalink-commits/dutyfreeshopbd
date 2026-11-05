<?php
namespace App\Http\Controllers;

use App\Support\Procure as P;
use App\Support\Reorder as R;
use App\Support\SupplierInsight as SI;
use App\Support\SupplierPortal as SP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin side of Auto Reorder Alert: dashboard, settings, requests, per-product override. */
class ReorderAlertController extends Controller
{
  public function index(Request $r) {
    if (!R::ready()) return view('sp.admin_reorder', ['off'=>true,'brand'=>SP::brand()]);
    $c = R::cfg(); $ctrl = R::ctrl();
    $below = R::below(); $open = DB::table('reorder_requests')->whereNotIn('status', R::END)->pluck('status','product_id');
    $below = $below->map(function ($p) use ($c, $ctrl, $open) {
      $p->thr = R::threshold($p, $c); $p->mode = R::mode($p, $ctrl, $c); $p->urg = R::urgency((int)$p->stock, $c); $p->open = $open[$p->id] ?? null; $p->nsup = count(SI::suppliersFor((int)$p->id)); return $p; });
    $st = DB::table('reorder_requests')->whereNotIn('status', R::END)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n','status');
    $card = ['below'=>$below->where('mode','auto')->count(),'critical'=>$below->where('mode','auto')->where('urg','Critical')->count(),'pending'=>(int)($st['Sent to Supplier'] ?? 0),
      'quotes'=>(int)(($st['Quote Received'] ?? 0)+($st['Under Review'] ?? 0)),'po'=>(int)(($st['Supplier Selected'] ?? 0)+($st['PO Created'] ?? 0)+($st['Ordered'] ?? 0)),'nosup'=>(int)($st['No Supplier'] ?? 0)];
    $q = DB::table('reorder_requests as q')->join('products as p','p.id','=','q.product_id')->leftJoin('supplier_rfqs as f','f.id','=','q.rfq_id');
    if ($r->filled('s')) $q->where('q.status', $r->query('s')); else if (!$r->boolean('all')) $q->whereNotIn('q.status', R::END);
    if ($r->filled('u')) $q->where('q.urgency', $r->query('u'));
    if ($r->filled('q')) { $t = '%'.mb_strtolower(trim((string)$r->query('q'))).'%'; $q->where(fn($w) => $w->whereRaw('lower(p.name) like ?', [$t])->orWhereRaw('lower(coalesce(p.sku,\'\')) like ?', [$t])->orWhereRaw('lower(q.req_no) like ?', [$t])); }
    $rows = $q->orderByRaw("case q.urgency when 'Critical' then 0 when 'High' then 1 when 'Medium' then 2 else 3 end")->orderByDesc('q.id')->limit(300)->get(['q.*','p.name','p.sku','p.stock','p.image','f.rfq_no','f.status as rfq_status']);
    $rows = $rows->map(function ($x) { $x->quotes = $x->rfq_id ? DB::table('supplier_quotes')->where('rfq_id',$x->rfq_id)->count() : 0; $x->invited = $x->rfq_id ? DB::table('supplier_rfq_invites')->where('rfq_id',$x->rfq_id)->count() : 0; return $x; });
    $uq = DB::table('products as p')->where('p.published', false)->whereNotExists(fn($e) => $e->select(DB::raw(1))->from('reorder_requests as o')->whereColumn('o.product_id','p.id')->whereNotIn('o.status', R::END));
    if ($r->filled('pq')) { $t = '%'.mb_strtolower(trim((string)$r->query('pq'))).'%'; $uq->where(fn($w) => $w->whereRaw('lower(p.name) like ?', [$t])->orWhereRaw('lower(coalesce(p.sku,\'\')) like ?', [$t])); }
    $unpubTotal = (clone $uq)->count();
    $unpub = $uq->orderByDesc('p.id')->limit(40)->get(['p.id','p.name','p.sku','p.image','p.stock'])->map(function ($x) { $x->nsup = count(SI::suppliersFor((int)$x->id)); return $x; });
    return view('sp.admin_reorder', ['unpub'=>$unpub,'unpubTotal'=>$unpubTotal,'pq'=>(string)$r->query('pq'),'off'=>false,'c'=>$c,'rows'=>$rows,'below'=>$below,'card'=>$card,'f'=>$r->only('s','u','q','all'),'brand'=>SP::brand()]);
  }
  public function settings(Request $r) {
    $d = $r->validate(['threshold'=>'required|integer|min:1|max:100000','target'=>'required|integer|min:1|max:1000000','days'=>'required|integer|min:1|max:90','hours'=>'required|integer|min:1|max:720','high'=>'required|integer|min:1|max:1000','med'=>'required|integer|min:1|max:1000','send_at'=>['nullable','regex:/^([01]?\d|2[0-3]):[0-5]\d$/']]);
    foreach (['threshold','target'] as $k) P::put('reorder_'.$k, (string)$d[$k]); P::put('reorder_days', (string)$d['days']); P::put('reorder_rfq_hours', (string)$d['hours']);
    P::put('reorder_u_high', (string)$d['high']); P::put('reorder_u_med', (string)max($d['high'], $d['med']));
    P::put('reorder_send_at', trim((string)($d['send_at'] ?? '')));
    foreach (['enabled','auto_send','sourcing','sms'] as $k) P::put('reorder_'.$k, $r->boolean($k) ? '1' : '0');
    P::audit('reorder.settings', 'reorder', null, $d + $r->only('enabled','auto_send','sourcing','sms'));
    return back()->with('ok','Reorder settings saved.');
  }
  public function run() {
    if (!P::on('reorder_enabled')) return back()->with('bad','Turn on Auto Reorder first (Settings → Auto reorder enabled).');
    [$made,$nos,$skip] = R::scan(null, auth()->id()); R::sync();
    return back()->with('ok', $made.' request(s) created'.($nos ? ', '.$nos.' with no supplier mapped (admin alert)' : '').'. '.$skip.' product(s) skipped (auto reorder off for them).');
  }
  public function preorder(Request $r) {
    $ids = array_map('intval', (array)$r->input('ids')); if (!$ids) return back()->with('bad','Select at least one product.'); $qty = (array)$r->input('qty'); $ok = 0; $nos = 0; $ex = 0;
    foreach (DB::table('products')->whereIn('id', $ids)->where('published', false)->get(['id','name','sku','stock']) as $p) {
      $res = R::createPreorder($p, max(1, (int)($qty[$p->id] ?? 100)), auth()->id()); if ($res === 'ok') $ok++; elseif ($res === 'No Supplier') $nos++; else $ex++;
    }
    return back()->with('ok', $ok.' pre-order request(s) created'.($nos ? ', '.$nos.' with no supplier mapped (admin alert)' : '').($ex ? ', '.$ex.' already had an open request' : '').'. They go to suppliers at the daily send time (or press Send).');
  }
  /** Every unpublished product that has a mapped supplier and no open request -> Pre-Order request (runs after the response; they go out at the daily send time). */
  public function preorderAll(Request $r) {
    $qty = max(1, (int)$r->input('qty', 100)); $by = auth()->id();
    $total = DB::table('products as p')->where('p.published', false)->whereNotExists(fn($e) => $e->select(DB::raw(1))->from('reorder_requests as o')->whereColumn('o.product_id','p.id')->whereNotIn('o.status', R::END))->count();
    dispatch(function () use ($qty, $by) {
      @set_time_limit(0); $ok = 0; $nos = 0;
      DB::table('products as p')->where('p.published', false)->whereNotExists(fn($e) => $e->select(DB::raw(1))->from('reorder_requests as o')->whereColumn('o.product_id','p.id')->whereNotIn('o.status', R::END))
        ->orderBy('p.id')->select(['p.id','p.name','p.sku','p.stock'])->chunkById(100, function ($rows) use (&$ok, &$nos, $qty, $by) {
          foreach ($rows as $p) { if (!SI::suppliersFor((int)$p->id)) { $nos++; continue; } if (R::createPreorder($p, $qty, $by) === 'ok') $ok++; }
        }, 'p.id', 'id');
      P::audit('reorder.preorder_all', 'reorder', null, ['created'=>$ok,'no_supplier_skipped'=>$nos]);
      P::put('reorder_preorder_all_last', now('Asia/Dhaka')->format('d M, g:i A').' — '.$ok.' created, '.$nos.' skipped (no supplier mapped)');
    })->afterResponse();
    return back()->with('ok','Creating pre-order requests for '.$total.' unpublished product(s) in the background (products with no mapped supplier are skipped). Refresh in a minute. They go to suppliers at the daily send time.');
  }
  public function override(Request $r) {
    $d = $r->validate(['sku'=>'required|max:120','threshold'=>'nullable|integer|min:0|max:100000','target'=>'nullable|integer|min:0|max:1000000','mode'=>'nullable|in:default,auto,off']);
    $p = DB::table('products')->where('sku',trim($d['sku']))->first(); if (!$p) return back()->with('bad','No product with SKU '.$d['sku']);
    $n = fn($v) => ($v === null || $v === '' || (int)$v <= 0) ? null : (int)$v;
    DB::table('products')->where('id',$p->id)->update(['low_stock_alert'=>$n($d['threshold'] ?? null),'reorder_target'=>$n($d['target'] ?? null),'reorder_mode'=>in_array($d['mode'] ?? 'default',['auto','off'],true) ? $d['mode'] : null]);
    P::audit('reorder.override','product#'.$p->id,null,$d);
    return back()->with('ok','Saved for '.$p->name.'. Leave a field blank to use the default.');
  }
  public function act(Request $r, $id, $a) {
    $q = DB::table('reorder_requests')->find($id); if (!$q) abort(404);
    if ($a === 'send') return R::send((int)$id) ? back()->with('ok','Sent to '.DB::table('supplier_rfq_invites')->where('rfq_id',$q->rfq_id)->count().' supplier(s).') : back()->with('bad','Nothing to send.');
    if ($a === 'cancel') { R::cancel((int)$id); return back()->with('ok','Request cancelled.'); }
    if ($a === 'review' && $q->status === 'Quote Received') { DB::table('reorder_requests')->where('id',$id)->update(['status'=>'Under Review','updated_at'=>now()]); return back()->with('ok','Marked under review.'); }
    return back()->with('bad','Action not allowed.');
  }
}
