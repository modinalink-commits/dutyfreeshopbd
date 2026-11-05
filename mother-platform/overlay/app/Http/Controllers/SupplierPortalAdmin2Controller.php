<?php
namespace App\Http\Controllers;

use App\Support\Procure as P;
use App\Support\SupplierInsight as SI;
use App\Support\SupplierPortal as SP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin side of portal phases 2-3: goods receiving, requests inbox, scores, forecast/reorder, price trend, product mapping, documents. */
class SupplierPortalAdmin2Controller extends Controller
{
  private function v(string $v, array $d = []) { return view('sp.'.$v, $d + ['brand'=>SP::brand()]); }

  // ---- deliveries / goods receiving ----
  public function deliveries() {
    $rows = DB::table('supplier_deliveries as d')->join('supplier_pos as p','p.id','=','d.po_id')->join('suppliers as s','s.id','=','d.supplier_id')->orderByRaw("case d.status when 'dispatched' then 0 when 'ready' then 1 else 2 end")->orderByDesc('d.id')->limit(150)->get(['d.*','p.po_no','s.name as sname']);
    return $this->v('admin_deliveries', compact('rows'));
  }
  public function deliveryShow($id) {
    $d = DB::table('supplier_deliveries as d')->join('supplier_pos as p','p.id','=','d.po_id')->join('suppliers as s','s.id','=','d.supplier_id')->where('d.id',$id)->first(['d.*','p.po_no','s.name as sname']); if (!$d) abort(404);
    $items = DB::table('supplier_delivery_items as di')->join('supplier_po_items as i','i.id','=','di.po_item_id')->where('di.delivery_id',$id)->get(['di.*','i.name','i.sku','i.rate']);
    return $this->v('admin_delivery_show', ['d'=>$d,'items'=>$items,'files'=>DB::table('supplier_files')->where('ref_type','delivery')->where('ref_id',$id)->get()]);
  }
  /** Receive goods: record expected/received/damaged/rejected/accepted, then create a DRAFT purchase. Stock changes only when that purchase is saved/approved in Purchase. */
  public function receive(Request $r, $id) {
    $d = DB::table('supplier_deliveries')->find($id); if (!$d || $d->received_at) return back()->with('bad','Already received or not found.');
    $po = DB::table('supplier_pos')->find($d->po_id); $items = DB::table('supplier_delivery_items as di')->join('supplier_po_items as i','i.id','=','di.po_item_id')->where('di.delivery_id',$id)->get(['di.*','i.name','i.sku','i.rate','i.product_id']);
    $rec = (array)$r->input('rec'); $dam = (array)$r->input('dam'); $rej = (array)$r->input('rej'); $lines = []; $any = false;
    $pid = DB::transaction(function () use ($d, $po, $items, $rec, $dam, $rej, &$lines, &$any) {
      foreach ($items as $it) {
        $rc = max(0,(int)($rec[$it->id] ?? 0)); $dm = max(0,min($rc,(int)($dam[$it->id] ?? 0))); $rj = max(0,min($rc-$dm,(int)($rej[$it->id] ?? 0))); $ac = $rc - $dm - $rj;
        DB::table('supplier_delivery_items')->where('id',$it->id)->update(['received_qty'=>$rc,'damaged_qty'=>$dm,'rejected_qty'=>$rj,'accepted_qty'=>$ac,'updated_at'=>now()]);
        if ($rc - $rj > 0) { $any = true; $lines[] = ['product_id'=>$it->product_id,'name'=>$it->name,'sku'=>$it->sku,'qty'=>$rc-$rj,'cost'=>(float)$it->rate,'damage_qty'=>$dm,'line_total'=>round(($rc-$rj)*(float)$it->rate,2)]; }
      }
      $purchase = null;
      if ($any) {
        $total = round(array_sum(array_column($lines,'line_total')),2); $s = DB::table('suppliers')->find($d->supplier_id);
        $purchase = DB::table('purchases')->insertGetId(['supplier_id'=>$d->supplier_id,'purchase_date'=>now('Asia/Dhaka')->toDateString(),'supplier_invoice'=>$d->challan_no,'ptype'=>'Local','pay_type'=>'Credit','reference'=>$po->po_no,'note'=>'From delivery '.$po->po_no.' — review, then save to add stock','subtotal'=>$total,'total'=>$total,'status'=>'draft','po_id'=>$po->id,'due_date'=>$s->payment_terms ? now('Asia/Dhaka')->addDays((int)$s->payment_terms)->toDateString() : null,'invoice'=>'TMP'.uniqid(),'token'=>bin2hex(random_bytes(10)),'user_id'=>auth()->id(),'paid'=>0,'prev_due'=>0,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('purchases')->where('id',$purchase)->update(['invoice'=>'PUR-'.str_pad((string)$purchase,6,'0',STR_PAD_LEFT)]);
        foreach ($lines as $l) DB::table('purchase_items')->insert($l + ['purchase_id'=>$purchase,'created_at'=>now(),'updated_at'=>now()]);
      }
      DB::table('supplier_deliveries')->where('id',$d->id)->update(['status'=>'delivered','received_at'=>now(),'purchase_id'=>$purchase,'updated_at'=>now()]);
      return $purchase; });
    SP::notify((int)$d->supplier_id,'delivery','Delivery received: '.$po->po_no,'DutyFreeBD confirmed receipt of your goods.','/supplier-portal/deliveries/'.$d->id,'action');
    P::audit('sp.delivery.receive','delivery#'.$id,null,['po'=>$po->po_no,'purchase'=>$pid]);
    return $pid ? redirect('/admin/purchase/edit/'.$pid)->with('ok','Goods recorded. Draft purchase created — check it and press Save to add stock and update the ledger.') : back()->with('ok','Recorded. Nothing accepted, so no purchase was created.');
  }

  // ---- requests inbox: messages, disputes, payment requests ----
  public function inbox() {
    $msgs = DB::table('supplier_messages as m')->join('suppliers as s','s.id','=','m.supplier_id')->orderByDesc('m.id')->limit(60)->get(['m.*','s.name as sname']);
    return $this->v('admin_inbox', ['msgs'=>$msgs,
      'disputes'=>DB::table('supplier_disputes as d')->join('suppliers as s','s.id','=','d.supplier_id')->orderByRaw("case d.status when 'open' then 0 when 'review' then 1 else 2 end")->orderByDesc('d.id')->limit(60)->get(['d.*','s.name as sname']),
      'reqs'=>DB::table('supplier_payment_requests as r')->join('suppliers as s','s.id','=','r.supplier_id')->orderByRaw("case r.status when 'requested' then 0 when 'approved' then 1 when 'processing' then 2 else 3 end")->orderByDesc('r.id')->limit(60)->get(['r.*','s.name as sname'])]);
  }
  public function reply(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','body'=>'required|max:2000']);
    DB::table('supplier_messages')->insert(['supplier_id'=>(int)$r->supplier_id,'from_side'=>'admin','body'=>$r->body,'sender'=>auth()->user()->name,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('supplier_messages')->where('supplier_id',(int)$r->supplier_id)->where('from_side','supplier')->whereNull('read_at')->update(['read_at'=>now()]);
    SP::notify((int)$r->supplier_id,'message','New message from DutyFreeBD',mb_substr($r->body,0,120),'/supplier-portal/messages','action'); return back()->with('ok','Reply sent');
  }
  public function disputeAction(Request $r, $id) {
    $d = DB::table('supplier_disputes')->find($id); if (!$d) abort(404); $r->validate(['status'=>'required|in:open,review,resolved,rejected','resolution'=>'nullable|max:1000']);
    $done = in_array($r->status,['resolved','rejected']);
    DB::table('supplier_disputes')->where('id',$id)->update(['status'=>$r->status,'resolution'=>$r->resolution ?: $d->resolution,'resolved_by'=>$done ? auth()->user()->name : null,'resolved_at'=>$done ? now() : null,'updated_at'=>now()]);
    SP::notify((int)$d->supplier_id,'dispute','Dispute '.$d->code.' '.$r->status,$r->resolution,'/supplier-portal/disputes','action'); P::audit('sp.dispute','dispute#'.$id,['status'=>$d->status],['status'=>$r->status]);
    return back()->with('ok','Dispute updated');
  }
  public function payReqAction(Request $r, $id) {
    $q = DB::table('supplier_payment_requests')->find($id); if (!$q) abort(404); $r->validate(['status'=>'required|in:approved,processing,paid,failed,rejected','note'=>'nullable|max:500']);
    DB::table('supplier_payment_requests')->where('id',$id)->update(['status'=>$r->status,'decided_by'=>auth()->user()->name,'decision_note'=>$r->note,'updated_at'=>now()]);
    SP::notify((int)$q->supplier_id,'payment','Payment request '.$q->code.' '.$r->status,'Amount '.SP::tk($q->amount),'/supplier-portal/payments','action'); P::audit('sp.payreq','req#'.$id,['status'=>$q->status],['status'=>$r->status]);
    return back()->with('ok','Updated');
  }
  public function caseNote(Request $r, $t, $id) {
    abort_unless(in_array($t,['return','damage']),404);
    $row = $t === 'return' ? DB::table('purchase_returns')->find($id) : DB::table('damaged_products')->find($id); if (!$row || !$row->supplier_id) abort(404); $r->validate(['body'=>'required|max:1000']);
    DB::table('supplier_case_notes')->insert(['supplier_id'=>$row->supplier_id,'case_type'=>$t,'case_id'=>(int)$id,'by_side'=>'admin','body'=>$r->body,'created_at'=>now(),'updated_at'=>now()]);
    SP::notify((int)$row->supplier_id,'return','Update on your '.$t.' case',mb_substr($r->body,0,120),'/supplier-portal/returns/'.$t.'/'.$id,'info'); return back()->with('ok','Note added');
  }

  // ---- scores / performance ----
  public function scores() {
    $rows = []; foreach (DB::table('suppliers')->where('active',true)->orderBy('name')->get(['id','name','code']) as $s) { $x = SI::score($s->id); $x['s'] = $s; $x['purchase'] = (float)DB::table('purchases')->where('supplier_id',$s->id)->whereNotIn('status',P::BAD)->sum('total'); $rows[] = $x; }
    usort($rows, fn($a,$b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1));
    return $this->v('admin_scores', ['rows'=>$rows,'show'=>P::on('sp_show_perf')]);
  }
  public function perfToggle(Request $r) { P::put('sp_show_perf', $r->boolean('on') ? '1' : '0'); P::audit('sp.perf-visible','portal',null,['on'=>$r->boolean('on')]); return back()->with('ok','Saved'); }

  // ---- forecast / reorder / price trend ----
  public function forecast() {
    $rows = SI::forecast(); foreach ($rows as &$x) $x['sup'] = DB::table('suppliers')->whereIn('id', SI::suppliersFor($x['p']->id) ?: [0])->pluck('name')->all(); unset($x);
    return $this->v('admin_forecast', compact('rows'));
  }
  /** Reorder: one DRAFT RFQ per selected product, invited = suppliers mapped / who sold it before. Human reviews and sends. */
  public function reorder(Request $r) {
    $ids = array_map('intval',(array)$r->input('p')); $made = 0; $skipped = [];
    foreach (DB::table('products')->whereIn('id',$ids ?: [0])->get(['id','name','sku']) as $p) {
      $sup = SI::suppliersFor($p->id); if (!$sup) { $skipped[] = $p->name; continue; }
      $need = max(1,(int)($r->input('need')[$p->id] ?? 1));
      $id = DB::table('supplier_rfqs')->insertGetId(['rfq_no'=>'TMP'.uniqid(),'product_id'=>$p->id,'product'=>$p->name,'sku'=>$p->sku,'qty'=>$need,'required_date'=>now('Asia/Dhaka')->addDays(7)->toDateString(),'notes'=>'Auto-drafted from stock forecast','expires_at'=>now()->addDays(2),'status'=>'draft','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
      DB::table('supplier_rfqs')->where('id',$id)->update(['rfq_no'=>'RFQ-'.str_pad((string)$id,5,'0',STR_PAD_LEFT)]);
      foreach ($sup as $sid) DB::table('supplier_rfq_invites')->insert(['rfq_id'=>$id,'supplier_id'=>$sid,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]); $made++;
    }
    P::audit('sp.reorder','rfq',null,['drafts'=>$made]);
    return redirect('/admin/purchase/portal/rfqs')->with('ok', $made.' draft RFQ(s) created. Review the supplier list and deadline, then send.'.($skipped ? ' No supplier known for: '.implode(', ', array_slice($skipped,0,5)) : ''));
  }
  public function prices(Request $r) {
    $p = $r->filled('product') ? DB::table('products')->find((int)$r->product) : null; $hist = $p ? SI::priceHistory($p->id) : [];
    $alerts = DB::table('supplier_price_alerts as a')->join('suppliers as s','s.id','=','a.supplier_id')->orderByDesc('a.id')->limit(40)->get(['a.*','s.name as sname']);
    return $this->v('admin_prices', compact('p','hist','alerts'));
  }
  public function alertSeen($id) { DB::table('supplier_price_alerts')->where('id',$id)->update(['seen'=>true]); return back(); }

  // ---- product mapping + documents ----
  public function products($sid) {
    $s = DB::table('suppliers')->find($sid); if (!$s) abort(404);
    $rows = DB::table('supplier_products as sp')->join('products as p','p.id','=','sp.product_id')->where('sp.supplier_id',$sid)->orderBy('p.name')->get(['sp.*','p.name','p.sku'])->map(function ($x) use ($sid) {
      $l = DB::table('purchase_items as i')->join('purchases as pu','pu.id','=','i.purchase_id')->where('pu.supplier_id',$sid)->where('i.product_id',$x->product_id)->whereNotIn('pu.status',P::BAD)->orderByDesc('pu.purchase_date')->first(['i.cost','pu.purchase_date']); $x->last_cost = $l->cost ?? null; $x->last_date = $l->purchase_date ?? null; return $x; });
    $docs = DB::table('supplier_documents as d')->join('supplier_files as f','f.id','=','d.file_id')->where('d.supplier_id',$sid)->get(['d.*','f.id as fid']);
    return $this->v('admin_products', compact('s','rows','docs'));
  }
  public function productSave(Request $r, $sid) {
    $r->validate(['product_id'=>'required|integer','supplier_sku'=>'nullable|max:120','latest_price'=>'nullable|numeric|min:0','moq'=>'nullable|integer|min:0','lead_days'=>'nullable|integer|min:0']); if (!DB::table('products')->where('id',$r->product_id)->exists()) abort(404);
    DB::table('supplier_products')->updateOrInsert(['supplier_id'=>$sid,'product_id'=>$r->product_id],['supplier_sku'=>$r->supplier_sku,'latest_price'=>$r->latest_price ?: null,'moq'=>(int)$r->moq,'lead_days'=>(int)$r->lead_days,'updated_at'=>now(),'created_at'=>now()]); return back()->with('ok','Saved');
  }
  public function productDelete($sid, $id) { DB::table('supplier_products')->where('supplier_id',$sid)->where('id',$id)->delete(); return back()->with('ok','Removed'); }
  public function docSave(Request $r, $sid) {
    $r->validate(['kind'=>'required|in:Agreement,Tax Documents,Other','title'=>'required|max:200','file'=>'required|file']);
    $fid = SP::store($r->file('file'), (int)$sid, 'document', null, $r->kind, 'admin'); if (!$fid) return back()->with('bad','File type not allowed or larger than 6 MB.');
    DB::table('supplier_documents')->insert(['supplier_id'=>$sid,'kind'=>$r->kind,'title'=>$r->title,'file_id'=>$fid,'created_at'=>now(),'updated_at'=>now()]);
    SP::notify((int)$sid,'document','New document: '.$r->title,null,'/supplier-portal/documents','info'); return back()->with('ok','Document added for the supplier');
  }
  public function file($id) { $f = DB::table('supplier_files')->find($id); if (!$f || !\Illuminate\Support\Facades\Storage::disk('local')->exists($f->path)) abort(404); return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($f->path), ['Content-Type'=>$f->mime ?: 'application/octet-stream','Content-Disposition'=>'inline; filename="'.addslashes($f->name).'"']); }
}
