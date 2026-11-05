<?php
namespace App\Http\Controllers;

use App\Support\Procure as P;
use App\Support\SupplierInsight as SI;
use App\Support\SupplierPortal as SP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Supplier portal phase 2: deliveries, returns/damage, documents, disputes, messages, payment requests, performance, price history. All scoped to SP::sid(). */
class SupplierPortal2Controller extends Controller
{
  private function need(string $a): void { if (!SP::can($a)) abort(403, 'Your portal role does not include this page.'); }
  private function v(string $view, array $d = []) { return view('sp.'.$view, $d + ['u'=>SP::user(), 'brand'=>SP::brand()]); }

  // ---- files (private; only the owner supplier) ----
  public function file($id) {
    $f = DB::table('supplier_files')->where('id',$id)->where('supplier_id',SP::sid())->first(); if (!$f || !Storage::disk('local')->exists($f->path)) abort(404);
    return response()->file(Storage::disk('local')->path($f->path), ['Content-Type'=>$f->mime ?: 'application/octet-stream','Content-Disposition'=>'inline; filename="'.addslashes($f->name).'"']);
  }

  // ---- deliveries ----
  public function deliveries() {
    $this->need('delivery'); $sid = SP::sid();
    $ready = DB::table('supplier_pos as p')->where('p.supplier_id',$sid)->whereIn('p.status',['accepted','revised'])->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('supplier_deliveries as d')->whereColumn('d.po_id','p.id')->where('d.status','!=','cancelled'))->orderBy('p.delivery_date')->get(['p.id','p.po_no','p.delivery_date','p.total']);
    $rows = DB::table('supplier_deliveries as d')->join('supplier_pos as p','p.id','=','d.po_id')->where('d.supplier_id',$sid)->orderByDesc('d.id')->limit(100)->get(['d.*','p.po_no']);
    return $this->v('deliveries', compact('ready','rows'));
  }
  public function deliveryStart(Request $r, $poId) {
    $this->need('delivery'); $sid = SP::sid(); $p = DB::table('supplier_pos')->where('id',$poId)->where('supplier_id',$sid)->whereIn('status',['accepted','revised'])->first(); if (!$p) abort(404);
    if (DB::table('supplier_deliveries')->where('po_id',$p->id)->exists()) return back()->with('bad','A delivery already exists for this PO.');
    $id = DB::transaction(function () use ($p, $sid) {
      $id = DB::table('supplier_deliveries')->insertGetId(['po_id'=>$p->id,'supplier_id'=>$sid,'status'=>'ready','expected_arrival'=>$p->delivery_date,'created_at'=>now(),'updated_at'=>now()]);
      foreach (DB::table('supplier_po_items')->where('po_id',$p->id)->get() as $i) DB::table('supplier_delivery_items')->insert(['delivery_id'=>$id,'po_item_id'=>$i->id,'expected_qty'=>(int)($i->accepted_qty ?? $i->qty),'created_at'=>now(),'updated_at'=>now()]);
      return $id; });
    SP::act('Delivery created', $p->po_no); return redirect('/supplier-portal/deliveries/'.$id);
  }
  private function delivery($id) { $d = DB::table('supplier_deliveries as d')->join('supplier_pos as p','p.id','=','d.po_id')->where('d.id',$id)->where('d.supplier_id',SP::sid())->first(['d.*','p.po_no']); if (!$d) abort(404); return $d; }
  public function deliveryShow($id) {
    $this->need('delivery'); $d = $this->delivery($id);
    $items = DB::table('supplier_delivery_items as di')->join('supplier_po_items as i','i.id','=','di.po_item_id')->where('di.delivery_id',$d->id)->get(['di.*','i.name','i.sku']);
    $files = DB::table('supplier_files')->where('supplier_id',SP::sid())->where('ref_type','delivery')->where('ref_id',$d->id)->get();
    return $this->v('delivery_show', compact('d','items','files'));
  }
  public function deliverySave(Request $r, $id) {
    $this->need('delivery'); $d = $this->delivery($id); if ($d->status === 'delivered') return back()->with('bad','Already delivered.');
    $r->validate(['status'=>'required|in:ready,dispatched,delivered','dispatch_date'=>'nullable|date','vehicle'=>'nullable|max:160','challan_no'=>'nullable|max:80','expected_arrival'=>'nullable|date','notes'=>'nullable|max:500','kind'=>'nullable|in:Delivery Challan,Invoice,Packing List,Supporting Document']);
    if ($r->status !== 'ready' && !$r->filled('dispatch_date') && !$d->dispatch_date) return back()->with('bad','Dispatch date is required.');
    DB::table('supplier_deliveries')->where('id',$d->id)->update(['status'=>$r->status,'dispatch_date'=>$r->dispatch_date ?: $d->dispatch_date,'vehicle'=>$r->vehicle,'challan_no'=>$r->challan_no,'expected_arrival'=>$r->expected_arrival ?: $d->expected_arrival,'notes'=>$r->notes,'updated_at'=>now()]);
    if ($r->hasFile('file')) SP::store($r->file('file'), SP::sid(), 'delivery', $d->id, $r->input('kind') ?: 'Supporting Document');
    SP::act('Delivery updated', $d->po_no, ['status'=>$r->status]);
    return redirect('/supplier-portal/deliveries/'.$d->id)->with('ok','Delivery updated. DutyFreeBD will confirm after the goods are received.');
  }

  // ---- returns / damage ----
  public function returns() {
    $this->need('returns'); $sid = SP::sid();
    $ret = DB::table('purchase_returns')->where('supplier_id',$sid)->orderByDesc('id')->limit(100)->get()->map(fn($x) => (object)['t'=>'return','id'=>$x->id,'code'=>$x->code,'product'=>$x->name,'qty'=>$x->qty,'reason'=>$x->reason,'dtype'=>'Return','amount'=>$x->value,'date'=>$x->return_date]);
    $dm = DB::table('damaged_products')->where('supplier_id',$sid)->where('responsibility','supplier')->orderByDesc('id')->limit(100)->get()->map(fn($x) => (object)['t'=>'damage','id'=>$x->id,'code'=>$x->code ?: 'DMG-'.str_pad($x->id,6,'0',STR_PAD_LEFT),'product'=>$x->name ?? '','qty'=>$x->qty,'reason'=>$x->reason_type,'dtype'=>'Damage','amount'=>$x->value,'date'=>$x->dmg_date ?: substr((string)$x->created_at,0,10)]);
    $rows = $ret->concat($dm)->sortByDesc('date')->values();
    return $this->v('returns', compact('rows'));
  }
  private function caseOk(string $t, int $id) { $row = $t === 'return' ? DB::table('purchase_returns')->where('id',$id)->where('supplier_id',SP::sid())->first() : DB::table('damaged_products')->where('id',$id)->where('supplier_id',SP::sid())->where('responsibility','supplier')->first(); if (!$row) abort(404); return $row; }
  public function caseShow($t, $id) {
    $this->need('returns'); abort_unless(in_array($t,['return','damage']), 404); $row = $this->caseOk($t,(int)$id);
    $notes = DB::table('supplier_case_notes')->where('supplier_id',SP::sid())->where('case_type',$t)->where('case_id',$id)->orderBy('id')->get();
    $files = DB::table('supplier_files')->where('supplier_id',SP::sid())->where('ref_type','case-'.$t)->where('ref_id',$id)->get();
    return $this->v('case_show', compact('t','row','notes','files'));
  }
  public function caseNote(Request $r, $t, $id) {
    $this->need('returns'); abort_unless(in_array($t,['return','damage']), 404); $this->caseOk($t,(int)$id);
    $r->validate(['body'=>'nullable|max:1000']); if (!$r->filled('body') && !$r->hasFile('file')) return back();
    if ($r->filled('body')) DB::table('supplier_case_notes')->insert(['supplier_id'=>SP::sid(),'case_type'=>$t,'case_id'=>(int)$id,'by_side'=>'supplier','body'=>$r->body,'created_at'=>now(),'updated_at'=>now()]);
    if ($r->hasFile('file')) SP::store($r->file('file'), SP::sid(), 'case-'.$t, (int)$id, 'Evidence');
    SP::act('Case note added', $t.'#'.$id); return back()->with('ok','Added to the case history.');
  }

  // ---- documents ----
  public function documents() {
    $this->need('docs'); $sid = SP::sid(); $s = DB::table('suppliers')->find($sid); $out = [];
    if (SP::can('po')) foreach (DB::table('supplier_pos')->where('supplier_id',$sid)->where('status','!=','draft')->orderByDesc('id')->limit(50)->get(['id','po_no','po_date']) as $x) $out[] = ['Purchase Order',$x->po_no,$x->po_date,'/supplier-portal/pos/'.$x->id.'/print'];
    if (SP::can('bills')) foreach (DB::table('purchases')->where('supplier_id',$sid)->whereNotIn('status',P::BAD)->orderByDesc('id')->limit(50)->get() as $b) { $out[] = ['Bill / Invoice',$b->invoice,$b->purchase_date,P::invoiceLink($b)]; }
    if (SP::can('ledger')) $out[] = ['Ledger Statement','Current month',now('Asia/Dhaka')->toDateString(),'/supplier-portal/ledger?export=pdf'];
    if (SP::can('payments')) foreach (DB::table('supplier_payments')->where('supplier_id',$sid)->where('status','paid')->orderByDesc('id')->limit(50)->get(['id','code','pay_date']) as $x) $out[] = ['Payment Advice',$x->code,$x->pay_date,'/supplier-portal/payments/'.$x->id.'/advice'];
    if (SP::can('returns')) foreach (DB::table('purchase_returns')->where('supplier_id',$sid)->orderByDesc('id')->limit(30)->get(['id','code','return_date']) as $x) $out[] = ['Return Note',$x->code,$x->return_date,'/supplier-portal/returns/return/'.$x->id];
    foreach (DB::table('supplier_files')->where('supplier_id',$sid)->where('ref_type','delivery')->orderByDesc('id')->limit(50)->get() as $f) $out[] = [$f->kind ?: 'Delivery file',$f->name,substr((string)$f->created_at,0,10),'/supplier-portal/files/'.$f->id];
    foreach (DB::table('supplier_documents as d')->join('supplier_files as f','f.id','=','d.file_id')->where('d.supplier_id',$sid)->orderByDesc('d.id')->get(['d.kind','d.title','d.created_at','f.id as fid']) as $x) $out[] = [$x->kind,$x->title,substr((string)$x->created_at,0,10),'/supplier-portal/files/'.$x->fid];
    return $this->v('documents', compact('out','s'));
  }
  public function poPrint($id) {
    $this->need('po'); $p = DB::table('supplier_pos')->where('id',$id)->where('supplier_id',SP::sid())->where('status','!=','draft')->first(); if (!$p) abort(404);
    return view('sp.print_doc', ['title'=>'Purchase Order '.$p->po_no,'s'=>DB::table('suppliers')->find($p->supplier_id),'meta'=>['PO date'=>$p->po_date,'Delivery required'=>$p->delivery_date ?: '—','Status'=>$p->status],'head'=>['Product','SKU','Qty','Rate','Amount'],'rows'=>DB::table('supplier_po_items')->where('po_id',$p->id)->get()->map(fn($i) => [$i->name,$i->sku,$i->qty,number_format($i->rate,2),number_format($i->line_total,2)])->all(),'foot'=>['Total','','','',number_format($p->total,2)],'note'=>$p->note]);
  }
  public function advice($id) {
    $this->need('payments'); $p = DB::table('supplier_payments')->where('id',$id)->where('supplier_id',SP::sid())->where('status','paid')->first(); if (!$p) abort(404);
    $bills = DB::table('payment_allocations as a')->join('purchases as pu','pu.id','=','a.purchase_id')->where('a.payment_id',$p->id)->get(['pu.invoice','a.amount']);
    SP::act('Payment advice downloaded', $p->code);
    return view('sp.print_doc', ['title'=>'Payment Advice '.$p->code,'s'=>DB::table('suppliers')->find($p->supplier_id),'meta'=>['Payment date'=>$p->pay_date,'Method'=>$p->method,'Reference'=>$p->reference ?: ($p->txn_id ?: '—'),'Amount'=>'৳'.number_format($p->amount,2)],'head'=>['Bill','Amount settled'],'rows'=>$bills->map(fn($b) => [$b->invoice,number_format($b->amount,2)])->all() ?: [['On account',number_format($p->amount,2)]],'foot'=>['Total paid',number_format($p->amount,2)],'note'=>$p->note]);
  }

  // ---- disputes ----
  const DTYPES = ['Bill','Ledger','Payment','Return','Purchase Order','Quantity','Rate'];
  public function disputes() { $this->need('support'); return $this->v('disputes', ['rows'=>DB::table('supplier_disputes')->where('supplier_id',SP::sid())->orderByDesc('id')->get(),'types'=>self::DTYPES]); }
  public function disputeSave(Request $r) {
    $this->need('support'); $r->validate(['dtype'=>'required|in:'.implode(',',self::DTYPES),'reference'=>'nullable|max:120','description'=>'required|max:2000']);
    $id = DB::table('supplier_disputes')->insertGetId(['code'=>'TMP'.uniqid(),'supplier_id'=>SP::sid(),'dtype'=>$r->dtype,'reference'=>$r->reference,'description'=>$r->description,'status'=>'open','raised_by'=>SP::user()->id,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('supplier_disputes')->where('id',$id)->update(['code'=>'DSP-'.str_pad((string)$id,5,'0',STR_PAD_LEFT)]);
    if ($r->hasFile('file')) SP::store($r->file('file'), SP::sid(), 'dispute', $id, 'Dispute attachment');
    SP::act('Dispute raised', 'DSP-'.str_pad((string)$id,5,'0',STR_PAD_LEFT), ['type'=>$r->dtype]);
    return redirect('/supplier-portal/disputes')->with('ok','Dispute raised. DutyFreeBD will review it.');
  }

  // ---- messages ----
  public function messages() {
    $this->need('support'); $sid = SP::sid();
    DB::table('supplier_messages')->where('supplier_id',$sid)->where('from_side','admin')->whereNull('read_at')->update(['read_at'=>now()]);
    $rows = DB::table('supplier_messages as m')->leftJoin('supplier_files as f','f.id','=','m.file_id')->where('m.supplier_id',$sid)->orderBy('m.id')->limit(300)->get(['m.*','f.name as fname']);
    return $this->v('messages', compact('rows'));
  }
  public function messageSave(Request $r) {
    $this->need('support'); $r->validate(['body'=>'required|max:2000','ref_type'=>'nullable|in:PO,Bill,Payment','ref_no'=>'nullable|max:60']);
    $fid = $r->hasFile('file') ? SP::store($r->file('file'), SP::sid(), 'message', null, 'Message attachment') : null;
    DB::table('supplier_messages')->insert(['supplier_id'=>SP::sid(),'from_side'=>'supplier','ref_type'=>$r->ref_type,'ref_no'=>$r->ref_no,'body'=>$r->body,'file_id'=>$fid,'sender'=>SP::user()->name,'created_at'=>now(),'updated_at'=>now()]);
    SP::act('Message sent', $r->ref_no); return back()->with('ok','Message sent.');
  }

  // ---- payment request ----
  public function payRequestSave(Request $r) {
    $this->need('payments'); $r->validate(['amount'=>'required|numeric|min:1|max:99999999','bills'=>'nullable|max:255','reason'=>'nullable|max:200','note'=>'nullable|max:500']);
    if ((float)$r->amount > max(0, P::due(SP::sid())) + 0.01) return back()->withInput()->with('bad','Requested amount is more than your current outstanding balance.');
    $id = DB::table('supplier_payment_requests')->insertGetId(['code'=>'TMP'.uniqid(),'supplier_id'=>SP::sid(),'amount'=>$r->amount,'bills'=>$r->bills,'reason'=>$r->reason,'note'=>$r->note,'status'=>'requested','created_at'=>now(),'updated_at'=>now()]);
    DB::table('supplier_payment_requests')->where('id',$id)->update(['code'=>'REQ-'.str_pad((string)$id,5,'0',STR_PAD_LEFT)]);
    SP::act('Payment request', 'REQ-'.str_pad((string)$id,5,'0',STR_PAD_LEFT), ['amount'=>$r->amount]); return back()->with('ok','Payment request sent for approval.');
  }

  // ---- performance & own price history ----
  public function performance() {
    $this->need('perf'); if (!P::on('sp_show_perf')) abort(404);
    return $this->v('performance', ['o'=>SI::own(SP::sid())]);
  }
  public function prices() {
    $this->need('rfq'); $sid = SP::sid();
    $rows = DB::table('supplier_quotes as q')->join('supplier_rfqs as r','r.id','=','q.rfq_id')->where('q.supplier_id',$sid)->orderBy('r.product')->orderBy('q.created_at')->get(['r.product','r.rfq_no','q.unit_price','q.created_at']);
    $by = []; foreach ($rows as $x) $by[$x->product][] = $x;
    return $this->v('prices', ['by'=>$by]);
  }
}
