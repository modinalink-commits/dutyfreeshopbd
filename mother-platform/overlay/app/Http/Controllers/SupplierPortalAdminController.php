<?php
namespace App\Http\Controllers;

use App\Support\Procure as P;
use App\Support\Sms;
use App\Support\SupplierPortal as SP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Admin side of the Supplier Partner Portal (inside the Purchase permission): logins, PO, RFQ + comparison, profile approvals, notifications. */
class SupplierPortalAdminController extends Controller
{
  private function suppliers() { return DB::table('suppliers')->where('active',true)->orderBy('name')->get(['id','name','code','phone']); }
  private function pw(): string { return substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 10); }

  public function index() {
    $logins = DB::table('suppliers as s')->leftJoin('supplier_users as u','u.supplier_id','=','s.id')->where('s.active',true)->orderBy('s.name')
      ->get(['s.id as sid','s.name','s.code','s.phone','u.id as uid','u.name as uname','u.role','u.status','u.last_login_at','u.mobile','u.email']);
    $due = P::dueMap();
    $k = [
      'suppliers' => DB::table('suppliers')->where('active',true)->count(),
      'active' => DB::table('supplier_users')->where('status','active')->distinct()->count('supplier_id'),
      'rfq' => DB::table('supplier_rfq_invites as i')->join('supplier_rfqs as r','r.id','=','i.rfq_id')->where('i.status','pending')->where('r.status','sent')->count(),
      'po' => DB::table('supplier_pos')->whereIn('status',['sent','accepted','partial','revised'])->count(),
      'awaiting' => DB::table('supplier_pos')->where('status','partial')->count(),
      'payable' => array_sum(array_filter($due, fn($v) => $v > 0)),
      'changes' => DB::table('supplier_profile_changes')->where('status','pending')->count(),
    ];
    return view('sp.admin_index', compact('logins','due','k') + ['sup'=>$this->suppliers(),'roles'=>SP::ROLES,'brand'=>SP::brand()]);
  }
  public function settingsSave(Request $r) { $b = trim((string)$r->input('brand')); if ($b !== '') P::put('sp_brand', mb_substr($b,0,60)); P::audit('sp.settings','portal',null,['brand'=>$b]); return back()->with('ok','Saved'); }

  // ---------- logins ----------
  public function userSave(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','name'=>'required|max:160','mobile'=>'nullable|max:30','email'=>'nullable|email|max:160','role'=>'required|in:owner,accounts,sales,delivery']);
    $s = DB::table('suppliers')->find((int)$r->supplier_id); if (!$s) abort(404);
    $mobile = $r->filled('mobile') ? SP::phone((string)$r->mobile) : null;
    if (!$mobile && !$r->filled('email')) return back()->with('bad','A mobile number or e-mail is needed to sign in.');
    if (!$s->code) { $s->code = 'SUP-'.str_pad($s->id,5,'0',STR_PAD_LEFT); DB::table('suppliers')->where('id',$s->id)->update(['code'=>$s->code]); }
    $pw = $this->pw();
    $id = DB::table('supplier_users')->insertGetId(['supplier_id'=>$s->id,'name'=>$r->name,'mobile'=>$mobile,'email'=>$r->email ? mb_strtolower($r->email) : null,'password'=>Hash::make($pw),'role'=>$r->role,'status'=>'active','must_change'=>true,'created_at'=>now(),'updated_at'=>now()]);
    P::audit('sp.user.add','supplier_user#'.$id,null,['supplier'=>$s->name,'role'=>$r->role]);
    $sms = false;
    if ($r->boolean('sms') && $mobile) { [$sms] = Sms::send($mobile, "DutyFreeBD Supplier Portal: ".url('/supplier-portal')." Login: {$s->code} / $mobile Password: $pw (প্রথম লগইনে পরিবর্তন করুন)"); }
    return back()->with('ok', "Login created for {$s->name}. Sign in with {$s->code} or {$mobile}. Temporary password (shown once): $pw".($sms ? ' — SMS sent.' : ''));
  }
  public function userAction(Request $r, $id, $a) {
    $u = DB::table('supplier_users')->find($id); if (!$u) abort(404); $m = '';
    if ($a === 'disable') { DB::table('supplier_users')->where('id',$id)->update(['status'=>'inactive','force_logout_at'=>now(),'updated_at'=>now()]); $m = 'Login disabled'; }
    elseif ($a === 'suspend') { DB::table('supplier_users')->where('id',$id)->update(['status'=>'suspended','force_logout_at'=>now(),'updated_at'=>now()]); $m = 'Login suspended'; }
    elseif ($a === 'enable') { DB::table('supplier_users')->where('id',$id)->update(['status'=>'active','failed_attempts'=>0,'locked_until'=>null,'updated_at'=>now()]); $m = 'Login enabled'; }
    elseif ($a === 'logout') { DB::table('supplier_users')->where('id',$id)->update(['force_logout_at'=>now(),'updated_at'=>now()]); $m = 'Forced logout done'; }
    elseif ($a === 'reset') { $pw = $this->pw(); DB::table('supplier_users')->where('id',$id)->update(['password'=>Hash::make($pw),'must_change'=>true,'failed_attempts'=>0,'locked_until'=>null,'force_logout_at'=>now(),'updated_at'=>now()]); $m = "Password reset. New temporary password (shown once): $pw"; }
    elseif ($a === 'role' && array_key_exists((string)$r->input('role'), SP::ROLES)) { DB::table('supplier_users')->where('id',$id)->update(['role'=>$r->input('role'),'updated_at'=>now()]); $m = 'Role changed'; }
    else abort(404);
    P::audit('sp.user.'.$a,'supplier_user#'.$id,['status'=>$u->status,'role'=>$u->role],null);
    return back()->with('ok',$m);
  }
  public function supplier($sid) {
    $s = DB::table('suppliers')->find($sid); if (!$s) abort(404);
    return view('sp.admin_supplier', ['s'=>$s,'users'=>DB::table('supplier_users')->where('supplier_id',$sid)->get(),'roles'=>SP::ROLES,'brand'=>SP::brand(),
      'logs'=>DB::table('supplier_login_logs')->where('supplier_id',$sid)->orderByDesc('id')->limit(30)->get(),
      'acts'=>DB::table('supplier_activity')->where('supplier_id',$sid)->orderByDesc('id')->limit(40)->get(),
      'changes'=>DB::table('supplier_profile_changes')->where('supplier_id',$sid)->orderByDesc('id')->limit(10)->get(),
      'k'=>SP::kpis((int)$sid),'pos'=>DB::table('supplier_pos')->where('supplier_id',$sid)->orderByDesc('id')->limit(10)->get()]);
  }
  public function changeAction($id, $a) {
    $c = DB::table('supplier_profile_changes')->find($id); if (!$c || $c->status !== 'pending') abort(404); $ch = json_decode($c->changes, true);
    if ($a === 'approve') {
      DB::table('suppliers')->where('id',$c->supplier_id)->update($ch['new'] + ['updated_at'=>now()]);
      SP::notify((int)$c->supplier_id,'profile','Profile change approved','Your new details are now active.','/supplier-portal/profile','info');
    } elseif ($a === 'reject') SP::notify((int)$c->supplier_id,'profile','Profile change rejected','Please contact DutyFreeBD to verify your details.','/supplier-portal/profile','action');
    else abort(404);
    DB::table('supplier_profile_changes')->where('id',$id)->update(['status'=>$a === 'approve' ? 'approved' : 'rejected','decided_by'=>auth()->user()->name,'decided_at'=>now(),'updated_at'=>now()]);
    P::audit('sp.change.'.$a,'supplier#'.$c->supplier_id,$ch['old'] ?? null,$ch['new'] ?? null);
    return back()->with('ok', $a === 'approve' ? 'Change approved and activated' : 'Change rejected');
  }
  public function notify(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','title'=>'required|max:200','body'=>'nullable|max:1000','priority'=>'required|in:info,action,urgent']);
    SP::notify((int)$r->supplier_id,'message',$r->title,$r->body,null,$r->priority); P::audit('sp.notify','supplier#'.$r->supplier_id,null,['title'=>$r->title]);
    return back()->with('ok','Notification sent');
  }

  // ---------- purchase orders ----------
  public function pos(Request $r) {
    $q = DB::table('supplier_pos as p')->join('suppliers as s','s.id','=','p.supplier_id')->orderByDesc('p.id'); if ($r->filled('s')) $q->where('p.status',$r->s);
    return view('sp.admin_pos', ['rows'=>$q->paginate(25,['p.*','s.name as sname'])->withQueryString(),'s'=>$r->s,'brand'=>SP::brand()]);
  }
  public function poForm(Request $r) {
    $pre = null; if ($r->filled('quote')) { $q = DB::table('supplier_quotes as q')->join('supplier_rfqs as f','f.id','=','q.rfq_id')->where('q.id',(int)$r->quote)->first(['q.*','f.product','f.sku','f.product_id','f.qty as rqty','f.required_date']); if ($q) $pre = $q; }
    return view('sp.admin_po_form', ['sup'=>$this->suppliers(),'pre'=>$pre,'brand'=>SP::brand()]);
  }
  public function poSave(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','po_date'=>'required|date','delivery_date'=>'nullable|date','name'=>'required|array|min:1','name.*'=>'required|max:255','qty.*'=>'required|integer|min:1','rate.*'=>'required|numeric|min:0']);
    $s = DB::table('suppliers')->find((int)$r->supplier_id); if (!$s) abort(404);
    $id = DB::transaction(function () use ($r) {
      $total = 0; $rows = [];
      foreach ($r->name as $i=>$n) { $q = (int)$r->qty[$i]; $rt = (float)$r->rate[$i]; $lt = round($q*$rt,2); $total += $lt; $rows[] = ['name'=>$n,'sku'=>$r->sku[$i] ?? null,'product_id'=>($r->pid[$i] ?? null) ?: null,'qty'=>$q,'rate'=>$rt,'line_total'=>$lt]; }
      $id = DB::table('supplier_pos')->insertGetId(['po_no'=>'TMP'.uniqid(),'supplier_id'=>(int)$r->supplier_id,'po_date'=>$r->po_date,'delivery_date'=>$r->delivery_date ?: null,'status'=>'draft','total'=>round($total,2),'note'=>$r->note,'rfq_quote_id'=>$r->quote ?: null,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
      DB::table('supplier_pos')->where('id',$id)->update(['po_no'=>'PO-'.str_pad((string)$id,5,'0',STR_PAD_LEFT)]);
      foreach ($rows as $x) DB::table('supplier_po_items')->insert($x + ['po_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    P::audit('sp.po.create','po#'.$id,null,['supplier'=>$s->name]);
    return redirect('/admin/purchase/portal/pos/'.$id)->with('ok','PO saved as draft. Send it to the supplier when ready.');
  }
  public function poShow($id) {
    $p = DB::table('supplier_pos as p')->join('suppliers as s','s.id','=','p.supplier_id')->where('p.id',$id)->first(['p.*','s.name as sname','s.code as scode']); if (!$p) abort(404);
    return view('sp.admin_po_show', ['p'=>$p,'items'=>DB::table('supplier_po_items')->where('po_id',$id)->orderBy('id')->get(),'brand'=>SP::brand()]);
  }
  public function poAction($id, $a) {
    $p = DB::table('supplier_pos')->find($id); if (!$p) abort(404); $sid = (int)$p->supplier_id; $m = '';
    if ($a === 'send' && $p->status === 'draft') {
      DB::table('supplier_pos')->where('id',$id)->update(['status'=>'sent','updated_at'=>now()]);
      SP::notify($sid,'po','New Purchase Order '.$p->po_no,'Amount '.SP::tk($p->total).($p->delivery_date ? ' · Delivery required '.$p->delivery_date : ''),'/supplier-portal/pos/'.$id,'urgent'); $m = 'PO sent to supplier';
    } elseif ($a === 'approve' && $p->status === 'partial') {
      DB::transaction(function () use ($id) { foreach (DB::table('supplier_po_items')->where('po_id',$id)->get() as $it) DB::table('supplier_po_items')->where('id',$it->id)->update(['qty'=>(int)$it->accepted_qty,'line_total'=>round((int)$it->accepted_qty*(float)$it->rate,2)]); DB::table('supplier_po_items')->where('po_id',$id)->where('qty',0)->delete(); DB::table('supplier_pos')->where('id',$id)->update(['status'=>'revised','total'=>DB::table('supplier_po_items')->where('po_id',$id)->sum('line_total'),'updated_at'=>now()]); });
      SP::notify($sid,'po','PO '.$p->po_no.' updated','The revised quantity was approved.','/supplier-portal/pos/'.$id,'action'); $m = 'Revised quantity approved';
    } elseif ($a === 'cancel' && !in_array($p->status, ['closed','cancelled'])) {
      DB::table('supplier_pos')->where('id',$id)->update(['status'=>'cancelled','updated_at'=>now()]);
      if ($p->status !== 'draft') SP::notify($sid,'po','PO '.$p->po_no.' cancelled',null,'/supplier-portal/pos/'.$id,'urgent'); $m = 'PO cancelled';
    } elseif ($a === 'close' && in_array($p->status, ['accepted','revised'])) { DB::table('supplier_pos')->where('id',$id)->update(['status'=>'closed','updated_at'=>now()]); $m = 'PO closed (goods received)'; }
    else return back()->with('bad','Action not allowed in this status');
    P::audit('sp.po.'.$a,'po#'.$id,['status'=>$p->status],null);
    return back()->with('ok',$m);
  }

  // ---------- RFQ ----------
  public function rfqs() {
    $rows = DB::table('supplier_rfqs as r')->orderByDesc('r.id')->limit(100)->get()->map(function ($x) {
      $x->invited = DB::table('supplier_rfq_invites')->where('rfq_id',$x->id)->count(); $x->quoted = DB::table('supplier_quotes')->where('rfq_id',$x->id)->count(); return $x; });
    return view('sp.admin_rfqs', ['rows'=>$rows,'brand'=>SP::brand()]);
  }
  public function rfqForm() { return view('sp.admin_rfq_form', ['sup'=>$this->suppliers(),'brand'=>SP::brand()]); }
  public function rfqSave(Request $r) {
    $r->validate(['product'=>'required|max:255','qty'=>'required|integer|min:1','required_date'=>'nullable|date','expires_at'=>'required|date|after:now','suppliers'=>'required|array|min:1']);
    $id = DB::transaction(function () use ($r) {
      $id = DB::table('supplier_rfqs')->insertGetId(['rfq_no'=>'TMP'.uniqid(),'product'=>$r->product,'product_id'=>$r->product_id ?: null,'sku'=>$r->sku,'qty'=>(int)$r->qty,'required_date'=>$r->required_date ?: null,'location'=>$r->location,'notes'=>$r->notes,'expires_at'=>$r->expires_at,'status'=>'draft','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
      DB::table('supplier_rfqs')->where('id',$id)->update(['rfq_no'=>'RFQ-'.str_pad((string)$id,5,'0',STR_PAD_LEFT)]);
      foreach (array_unique(array_map('intval',$r->suppliers)) as $sid) DB::table('supplier_rfq_invites')->insert(['rfq_id'=>$id,'supplier_id'=>$sid,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    P::audit('sp.rfq.create','rfq#'.$id,null,['product'=>$r->product]);
    return redirect('/admin/purchase/portal/rfqs/'.$id)->with('ok','RFQ saved as draft. Review the supplier list, then send.');
  }
  public function rfqShow($id) {
    $r = DB::table('supplier_rfqs')->find($id); if (!$r) abort(404);
    $inv = DB::table('supplier_rfq_invites as i')->join('suppliers as s','s.id','=','i.supplier_id')->where('i.rfq_id',$id)->get(['i.*','s.name as sname','s.code as scode']);
    $quotes = DB::table('supplier_quotes')->where('rfq_id',$id)->get()->keyBy('supplier_id');
    $rows = []; $minP = $quotes->min('unit_price'); $minL = $quotes->min('lead_days');
    foreach ($inv as $i) {
      $q = $quotes[$i->supplier_id] ?? null; $row = ['i'=>$i,'q'=>$q,'score'=>null,'prev'=>null];
      if ($q) {
        $landed = (float)$q->unit_price + ($r->qty > 0 ? (float)$q->delivery_charge / $r->qty : 0);
        $row['landed'] = $landed;
        $row['prev'] = $r->product_id ? DB::table('purchase_items as it')->join('purchases as p','p.id','=','it.purchase_id')->where('p.supplier_id',$i->supplier_id)->where('it.product_id',$r->product_id)->whereNotIn('p.status',P::BAD)->orderByDesc('p.purchase_date')->value('it.cost') : null;
        $ret = (int)DB::table('purchase_returns')->where('supplier_id',$i->supplier_id)->count(); $cnt = max(1,(int)DB::table('purchases')->where('supplier_id',$i->supplier_id)->whereNotIn('status',P::BAD)->count());
        $rel = max(0, 1 - $ret / $cnt); $row['rel'] = round($rel*100);
        $row['score'] = 0; $row['parts'] = [($minP > 0 ? $minP / max($q->unit_price,0.01) : 1), (($minL + 1) / ($q->lead_days + 1)), min(1, $q->available_qty / max(1,$r->qty)), $rel];
        $row['score'] = round(100 * ($row['parts'][0]*.45 + $row['parts'][1]*.2 + $row['parts'][2]*.2 + $row['parts'][3]*.15));
      }
      $rows[] = $row;
    }
    $best = collect($rows)->filter(fn($x) => $x['q'])->sortByDesc('score')->first();
    return view('sp.admin_rfq_show', ['r'=>$r,'rows'=>$rows,'minP'=>$minP,'minL'=>$minL,'best'=>$best['i']->supplier_id ?? null,'brand'=>SP::brand()]);
  }
  public function rfqAction(Request $req, $id, $a) {
    $r = DB::table('supplier_rfqs')->find($id); if (!$r) abort(404); $m = '';
    if ($a === 'send' && $r->status === 'draft') {
      DB::table('supplier_rfqs')->where('id',$id)->update(['status'=>'sent','updated_at'=>now()]);
      foreach (DB::table('supplier_rfq_invites')->where('rfq_id',$id)->pluck('supplier_id') as $sid)
        SP::notify((int)$sid,'rfq','New Price Request '.$r->rfq_no,$r->product.' · Qty '.$r->qty.($r->required_date ? ' · Required by '.$r->required_date : '').' · Submit before '.date('d M Y, g:i A', strtotime((string)$r->expires_at)),'/supplier-portal/rfq/'.$id,'urgent');
      $m = 'RFQ sent to '.DB::table('supplier_rfq_invites')->where('rfq_id',$id)->count().' suppliers';
    } elseif ($a === 'remind' && $r->status === 'sent') {
      foreach (DB::table('supplier_rfq_invites')->where('rfq_id',$id)->where('status','pending')->pluck('supplier_id') as $sid) SP::notify((int)$sid,'rfq','Reminder: price request '.$r->rfq_no,$r->product,'/supplier-portal/rfq/'.$id,'urgent');
      $m = 'Reminder sent to suppliers who have not quoted';
    } elseif ($a === 'award' && in_array($r->status, ['sent','closed'])) {
      $q = DB::table('supplier_quotes')->where('id',(int)$req->input('quote'))->where('rfq_id',$id)->first(); if (!$q) abort(404);
      DB::table('supplier_rfqs')->where('id',$id)->update(['status'=>'awarded','awarded_quote_id'=>$q->id,'updated_at'=>now()]);
      SP::notify((int)$q->supplier_id,'rfq','Your quote was selected: '.$r->rfq_no,'A purchase order will follow.','/supplier-portal/rfq/'.$id,'action');
      foreach (DB::table('supplier_rfq_invites')->where('rfq_id',$id)->where('supplier_id','!=',$q->supplier_id)->pluck('supplier_id') as $sid) SP::notify((int)$sid,'rfq','Price request '.$r->rfq_no.' closed','Thank you for your response.','/supplier-portal/rfq/'.$id,'info');
      return redirect('/admin/purchase/portal/pos/new?quote='.$q->id)->with('ok','Quote awarded. Create the PO below.');
    } elseif ($a === 'close' && $r->status === 'sent') { DB::table('supplier_rfqs')->where('id',$id)->update(['status'=>'closed','updated_at'=>now()]); $m = 'RFQ closed'; }
    else return back()->with('bad','Action not allowed in this status');
    P::audit('sp.rfq.'.$a,'rfq#'.$id,['status'=>$r->status],null);
    return back()->with('ok',$m);
  }
}
