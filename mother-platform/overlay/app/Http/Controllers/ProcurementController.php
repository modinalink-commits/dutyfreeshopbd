<?php
namespace App\Http\Controllers;
use App\Support\Procure as P;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProcurementController extends Controller {
  private const PER = 40;
  private function today(): string { return now('Asia/Dhaka')->toDateString(); }
  private function suppliers() { return DB::table('suppliers')->orderBy('name')->get(['id','name','code','phone']); }
  private function stores() { return DB::table('stores')->where('active',true)->orderBy('id')->get(['id','name']); }
  private function e($v): string { return e((string)$v); }
  private function badge(string $k): string {
    $L = ['paid'=>'Paid','partial'=>'Partial','due'=>'Due','overdue'=>'Overdue','draft'=>'Draft','pending'=>'Pending Approval','approved'=>'Approved','received'=>'Received','cancelled'=>'Cancelled','company'=>'Company loss','supplier'=>'Supplier claim','debit'=>'Debit note','credit'=>'Credit note'];
    return '<span class="pb pb-'.$k.'">'.($L[$k] ?? $k).'</span>';
  }
  private function post(string $url, string $label, array $f = [], string $cls = 'g sm', string $ask = ''): string {
    $h = ''; foreach ($f as $k=>$v) $h .= '<input type="hidden" name="'.$k.'" value="'.e($v).'">';
    return '<form method="post" action="'.$url.'" style="display:inline"'.($ask ? ' onsubmit="return confirm(\''.e($ask).'\')"' : '').'>'.csrf_field().$h.'<button class="'.$cls.'">'.$label.'</button></form>';
  }
  private function ic(string $href, string $t, string $svg, ?array $post = null): string {
    $i = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$svg.'</svg>';
    if ($post !== null) return '<form method="post" action="'.$href.'" style="display:inline" onsubmit="return confirm(\''.e($t).'?\')">'.csrf_field().'<button class="ib" title="'.e($t).'">'.$i.'</button></form>';
    return '<a class="ib" href="'.$href.'" title="'.e($t).'">'.$i.'</a>';
  }
  private function supActions(int $id): string {
    return $this->ic('/admin/purchase/new?supplier='.$id,'New purchase','<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2.5l2.2 11h10.6l2-8H6.2"/>')
      .$this->ic('/admin/purchase/payments/new?supplier='.$id,'Make payment','<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/>')
      .$this->ic('/admin/purchase/ledger?supplier='.$id,'Ledger','<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M9 9h6M9 13h6"/>')
      .$this->ic('/admin/purchase/suppliers/'.$id.'/remind','Send due SMS','<path d="M4 5h16v11H9l-5 4z"/>',[])
      .$this->ic('/admin/purchase/suppliers/'.$id,'View profile / more','<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>');
  }
  private function paidMap() { return DB::table('payment_allocations as a')->join('supplier_payments as p','p.id','=','a.payment_id')->where('p.status','paid')->groupBy('a.purchase_id')->selectRaw('a.purchase_id, sum(a.amount) s')->pluck('s','purchase_id'); }

  // ---------------- generic list + export ----------------
  private function listPage(Request $r, string $kind, array $d) {
    $rows = $d['rows']; $page = max(1, (int)$r->input('page', 1)); $n = count($rows);
    $d['tabs'] = match ($kind) { 'dues','payments' => [['Due list','/admin/purchase/dues',$kind==='dues'],['Payment history','/admin/purchase/payments',$kind==='payments']], 'damages','returns' => [['Damage','/admin/purchase/damages',$kind==='damages'],['Supplier Return','/admin/purchase/returns',$kind==='returns']], default => [] };
    $d['rows'] = array_slice($rows, ($page-1)*self::PER, self::PER); $d += ['page'=>$page,'pages'=>max(1,(int)ceil($n/self::PER)),'n'=>$n,'kind'=>$kind,'sup'=>$this->suppliers(),'stores'=>$this->stores()];
    return view('proc.list', $d);
  }
  public function list($kind, Request $r) { $d = $this->data($kind, $r); if (!$d) abort(404); return $this->listPage($r, $kind, $d); }
  public function export($kind, $fmt, Request $r) {
    $d = $this->data($kind, $r); if (!$d) abort(404);
    $rows = $d['rows']; if ($r->filled('ids')) { $ids = array_map('intval', explode(',', (string)$r->input('ids'))); $rows = array_values(array_filter($rows, fn($x) => in_array($x['id'], $ids))); }
    $name = $kind.'-'.date('Ymd-His'); $title = $d['title']; P::audit('proc.export', $kind.'.'.$fmt, null, ['rows'=>count($rows)]);
    if ($fmt === 'csv') return response()->streamDownload(function () use ($d, $rows) { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $d['head']); foreach ($rows as $x) fputcsv($o, $x['text']); fclose($o); }, "$name.csv", ['Content-Type'=>'text/csv; charset=utf-8']);
    $table = function () use ($d, $rows) { echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>'; foreach ($d['head'] as $h) echo '<th style="background:#FF5A00;color:#fff">'.e($h).'</th>'; echo '</tr></thead><tbody>'; foreach ($rows as $x) { echo '<tr>'; foreach ($x['text'] as $v) echo '<td>'.e($v).'</td>'; echo '</tr>'; } echo '</tbody></table>'; };
    $pre = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{font-family:system-ui,Arial,sans-serif}table{border-collapse:collapse;font-size:12px}h2{color:#0B1220}</style></head><body><h2>'.e($title).' — DutyFreeBD</h2><p>'.e(now('Asia/Dhaka')->format('d M Y H:i')).'</p>';
    if ($fmt === 'xls') return response()->streamDownload(function () use ($pre, $table) { echo $pre; $table(); echo '</body></html>'; }, "$name.xls", ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8']);
    if ($fmt === 'doc') return response()->streamDownload(function () use ($pre, $table) { echo $pre; $table(); echo '</body></html>'; }, "$name.doc", ['Content-Type'=>'application/msword; charset=utf-8']);
    if ($fmt === 'pdf') return response()->stream(function () use ($pre, $table) { echo $pre; $table(); echo '<script>window.onload=function(){window.print()}</script></body></html>'; }, 200, ['Content-Type'=>'text/html; charset=utf-8']);
    abort(404);
  }
  private function data(string $kind, Request $r): ?array {
    return match ($kind) { 'invoices'=>$this->dInvoices($r), 'suppliers'=>$this->dSuppliers($r), 'payments'=>$this->dPayments($r), 'dues'=>$this->dDues($r), 'damages'=>$this->dDamages($r), 'returns'=>$this->dReturns($r), 'notes'=>$this->dNotes($r), 'ledger'=>$this->dLedger($r), 'reports'=>$this->dReport($r), default=>null };
  }
  private function dateF() { return [['from','From','date'],['to','To','date']]; }

  private function dInvoices(Request $r): array {
    $q = DB::table('purchases as p')->join('suppliers as s','s.id','=','p.supplier_id')->select('p.*','s.name as sname','s.code as scode');
    if ($r->filled('from')) $q->where('p.purchase_date','>=',$r->from); if ($r->filled('to')) $q->where('p.purchase_date','<=',$r->to);
    if ($r->filled('supplier')) $q->where('p.supplier_id',(int)$r->supplier); if ($r->filled('store')) $q->where('p.store_id',(int)$r->store); if ($r->filled('status')) $q->where('p.status',$r->status);
    if ($r->filled('q')) { $t = '%'.$r->q.'%'; $q->where(fn($w) => $w->where('p.invoice','ilike',$t)->orWhere('p.supplier_invoice','ilike',$t)->orWhere('s.name','ilike',$t)->orWhere('p.reference','ilike',$t)); }
    if ($r->filled('product')) { $t = '%'.$r->product.'%'; $q->whereExists(fn($w) => $w->select(DB::raw(1))->from('purchase_items as i')->whereColumn('i.purchase_id','p.id')->where(fn($x) => $x->where('i.name','ilike',$t)->orWhere('i.sku','ilike',$t)->orWhere('i.barcode','ilike',$t))); }
    $paid = $this->paidMap(); $stores = DB::table('stores')->pluck('name','id'); $rows = []; $T = ['t'=>0,'p'=>0,'d'=>0];
    foreach ($q->orderByDesc('p.purchase_date')->orderByDesc('p.id')->limit(5000)->get() as $p) {
      $pd = (float)($paid[$p->id] ?? 0); $ps = P::payStatus($p, $pd); if ($r->filled('pay') && $r->pay !== $ps) continue;
      if ($r->filled('due') && ($r->due === 'open' ? ($p->total-$pd) <= 0.009 : ($r->due === 'overdue' && $ps !== 'overdue'))) continue;
      $due = in_array($p->status, P::BAD) ? 0 : $p->total - $pd; $T['t'] += $p->total; $T['p'] += $pd; $T['d'] += $due;
      $rows[] = ['id'=>$p->id,'cells'=>['<a href="/admin/purchase/invoices/'.$p->id.'"><b>'.e($p->invoice).'</b></a>',$p->purchase_date,'<a href="/admin/purchase/suppliers/'.$p->supplier_id.'">'.e($p->sname).'</a>',e($p->supplier_invoice),e($stores[$p->store_id] ?? '—'),$this->badge($p->status),$this->badge($ps),number_format($p->total,2),number_format($pd,2),number_format($due,2),$p->due_date ?: '—'],
        'text'=>[$p->invoice,$p->purchase_date,$p->sname,$p->supplier_invoice,$stores[$p->store_id] ?? '',$p->status,$ps,$p->total,$pd,$due,$p->due_date]];
    }
    return ['title'=>'Purchase','sub'=>'Every purchase, its payment status and what is still owed.','head'=>['Invoice','Date','Supplier','Supplier invoice','Store','Status','Payment','Total','Paid','Due','Due date'],'rows'=>$rows,'num'=>[7,8,9],
      'foot'=>['Total',7,[number_format($T['t'],2),number_format($T['p'],2),number_format($T['d'],2)]],'new'=>['New Purchase','/admin/purchase/new'],
      'filters'=>array_merge($this->dateF(),[['supplier','Supplier','supplier'],['q','Invoice / reference','text'],['product','Product / SKU / barcode','text'],['store','Store','store'],['status','Purchase status','select',['draft'=>'Draft','pending'=>'Pending Approval','approved'=>'Approved','received'=>'Received','cancelled'=>'Cancelled']],['pay','Payment status','select',['paid'=>'Paid','partial'=>'Partial','due'=>'Due','overdue'=>'Overdue']],['due','Due status','select',['open'=>'Has due','overdue'=>'Overdue only']]])];
  }
  private function dSuppliers(Request $r): array {
    $dm = P::dueMap(); $stm = []; foreach ($this->dues($r) as $x) $stm[$x['s']->id] = $x['st']; $q = DB::table('suppliers'); if ($r->filled('q')) { $t = '%'.$r->q.'%'; $q->where(fn($w) => $w->where('name','ilike',$t)->orWhere('phone','ilike',$t)->orWhere('code','ilike',$t)->orWhere('business_name','ilike',$t)); }
    if ($r->filled('status')) $q->where('active', $r->status === 'active');
    $tp = DB::table('purchases')->whereNotIn('status',P::BAD)->groupBy('supplier_id')->selectRaw('supplier_id, sum(total) s, max(purchase_date) last')->get()->keyBy('supplier_id');
    $pd = DB::table('supplier_payments')->where('status','paid')->groupBy('supplier_id')->selectRaw('supplier_id, sum(amount) s')->pluck('s','supplier_id'); $rows = [];
    foreach ($q->orderBy('name')->get() as $s) { $due = $dm[$s->id] ?? 0; $o = $tp[$s->id] ?? null; if ($r->filled('due') && ($r->due === 'open' ? $due <= 0.009 : $due > 0.009)) continue; $st = $stm[$s->id] ?? 'paid';
      if ($r->filled('pay') && $r->pay !== $st) continue; if (!$o && $due <= 0.009 && !$r->filled('q')) $st = 'none';
      $rows[] = ['id'=>$s->id,'cells'=>['<a href="/admin/purchase/suppliers/'.$s->id.'"><b>'.e($s->name).'</b></a>'.($s->active ? '' : ' <span class="pb pb-cancelled">INACTIVE</span>'),e($s->phone),number_format($o->s ?? 0),number_format($pd[$s->id] ?? 0),'<b style="color:'.($due > 0.009 ? '#C2410C' : '#166534').'">'.number_format($due).'</b>',$o->last ?? '—',$st === 'none' ? '<span class="mut">—</span>' : $this->badge($st),$this->supActions($s->id)],
        'text'=>[$s->name,$s->phone,$o->s ?? 0,$pd[$s->id] ?? 0,$due,$o->last ?? '',$st]]; }
    return ['title'=>'Suppliers','sub'=>'Manage purchases, supplier payments and dues','head'=>['Supplier','Mobile','Total Purchase','Paid','Due','Last Purchase','Status','Action'],'rows'=>$rows,'noExportCol'=>7,'num'=>[2,3,4],'new'=>['Supplier','/admin/purchase/suppliers/new'],
      'filters'=>[['q','Name / mobile','text'],['pay','Status','select',['paid'=>'Paid','partial'=>'Partial','due'=>'Due','overdue'=>'Overdue']],['due','Balance','select',['open'=>'Has due','clear'=>'No due']]]];
  }
  private function dPayments(Request $r): array {
    $q = DB::table('supplier_payments as p')->join('suppliers as s','s.id','=','p.supplier_id')->leftJoin('purchases as u','u.id','=','p.purchase_id')->select('p.*','s.name as sname','u.invoice as inv');
    if ($r->filled('from')) $q->where('p.pay_date','>=',$r->from); if ($r->filled('to')) $q->where('p.pay_date','<=',$r->to); if ($r->filled('supplier')) $q->where('p.supplier_id',(int)$r->supplier);
    if ($r->filled('method')) $q->where('p.method',$r->method); if ($r->filled('status')) $q->where('p.status',$r->status);
    if ($r->filled('q')) { $t = '%'.$r->q.'%'; $q->where(fn($w) => $w->where('p.code','ilike',$t)->orWhere('p.txn_id','ilike',$t)->orWhere('p.reference','ilike',$t)->orWhere('s.name','ilike',$t)->orWhere('u.invoice','ilike',$t)); }
    $rows = []; $tot = 0;
    foreach ($q->orderByDesc('p.pay_date')->orderByDesc('p.id')->limit(5000)->get() as $p) { if ($p->status === 'paid') $tot += $p->amount;
      $act = ''; if ($p->status === 'pending' && P::canApprove()) $act .= $this->post('/admin/purchase/payments/'.$p->id.'/approve','Approve',[],'sm');
      if ($p->status !== 'paid' || P::canApprove()) $act .= ' '.$this->post('/admin/purchase/payments/'.$p->id.'/delete','Delete',[],'g sm','Delete this payment?');
      $rows[] = ['id'=>$p->id,'cells'=>[e($p->code),$p->pay_date,'<a href="/admin/purchase/suppliers/'.$p->supplier_id.'">'.e($p->sname).'</a>',$p->inv ? '<a href="/admin/purchase/invoices/'.$p->purchase_id.'">'.e($p->inv).'</a>' : 'On account',e($p->method.($p->account ? ' · '.$p->account : '')),e($p->txn_id),number_format($p->amount,2),$p->proof ? '<a href="'.e($p->proof).'" target="_blank">View</a>' : '—',$this->badge($p->status === 'paid' ? 'paid' : 'pending'),$act],
        'text'=>[$p->code,$p->pay_date,$p->sname,$p->inv ?: 'On account',$p->method.' '.$p->account,$p->txn_id,$p->amount,$p->proof ? 'yes' : '',$p->status]]; }
    return ['title'=>'Payment History','sub'=>'Every payment made to suppliers, with proof and invoice allocation.','head'=>['Payment','Date','Supplier','Invoice','Method / account','Transaction ID','Amount','Proof','Status','Actions'],'rows'=>$rows,'num'=>[6],'noExportCol'=>9,'foot'=>['Total paid',6,[number_format($tot,2)]],'new'=>['Make Payment','/admin/purchase/payments/new'],
      'filters'=>array_merge($this->dateF(),[['supplier','Supplier','supplier'],['q','Payment / txn / invoice','text'],['method','Method','select',array_combine(P::METHODS,P::METHODS)],['status','Status','select',['paid'=>'Paid','pending'=>'Pending approval']]])];
  }
  private function dues(Request $r): array {
    $dm = P::dueMap(); $today = $this->today(); $paid = $this->paidMap(); $info = [];
    foreach (DB::table('purchases')->whereNotIn('status',P::BAD)->get() as $p) { $d = $p->total - ($paid[$p->id] ?? 0); if ($d <= 0.009) continue; $i = &$info[$p->supplier_id]; $i['open'] = ($i['open'] ?? 0) + $d;
      if ($p->due_date && $p->due_date < $today) $i['over'] = ($i['over'] ?? 0) + $d; if ($p->due_date && (empty($i['next']) || $p->due_date < $i['next'])) $i['next'] = $p->due_date; unset($i); }
    $tp = DB::table('purchases')->whereNotIn('status',P::BAD)->groupBy('supplier_id')->selectRaw('supplier_id, sum(total) s')->pluck('s','supplier_id');
    $tpd = DB::table('supplier_payments')->where('status','paid')->groupBy('supplier_id')->selectRaw('supplier_id, sum(amount) s, max(pay_date) last')->get()->keyBy('supplier_id'); $out = [];
    foreach (DB::table('suppliers')->orderBy('name')->get() as $s) { $due = $dm[$s->id] ?? 0; $i = $info[$s->id] ?? []; $over = $i['over'] ?? 0; $pd = (float)($tpd[$s->id]->s ?? 0);
      $st = $due <= 0.009 ? 'paid' : ($over > 0.009 ? 'overdue' : ($pd > 0.009 ? 'partial' : 'due'));
      $out[] = ['s'=>$s,'due'=>$due,'over'=>$over,'paid'=>$pd,'tp'=>(float)($tp[$s->id] ?? 0),'last'=>$tpd[$s->id]->last ?? null,'next'=>$i['next'] ?? null,'st'=>$st]; }
    return $out;
  }
  private function dDues(Request $r): array {
    $rows = []; $T = ['p'=>0,'pd'=>0,'d'=>0,'o'=>0];
    foreach ($this->dues($r) as $x) { $s = $x['s']; if ($r->filled('supplier') && $s->id != $r->supplier) continue; if ($r->filled('status') ? $r->status !== $x['st'] : abs($x['due']) < 0.01) continue;
      if ($r->filled('q') && stripos($s->name.' '.$s->phone.' '.$s->code, $r->q) === false) continue;
      $T['p'] += $x['tp']; $T['pd'] += $x['paid']; $T['d'] += $x['due']; $T['o'] += $x['over'];
      $rows[] = ['id'=>$s->id,'cells'=>['<a href="/admin/purchase/suppliers/'.$s->id.'"><b>'.e($s->name).'</b></a>',number_format($x['tp'],2),number_format($x['paid'],2),'<b>'.number_format($x['due'],2).'</b>',number_format($x['over'],2),$x['last'] ?: '—',$x['next'] ?: '—',$this->badge($x['st']),
        '<a class="btn sm" href="/admin/purchase/payments/new?supplier='.$s->id.'">Pay</a> <a class="btn g sm" href="/admin/purchase/ledger?supplier='.$s->id.'">Ledger</a> '.$this->post('/admin/purchase/suppliers/'.$s->id.'/remind','SMS reminder',[],'g sm','Send due reminder SMS to '.$s->name.'?')],
        'text'=>[$s->name,$x['tp'],$x['paid'],$x['due'],$x['over'],$x['last'],$x['next'],$x['st']]]; }
    return ['new'=>['Make Payment','/admin/purchase/payments/new'],'title'=>'Payments & Due','sub'=>'What we owe each supplier. Overdue = an invoice past its due date and still unpaid.','head'=>['Supplier','Total purchase','Total paid','Current due','Overdue','Last payment','Next due date','Status','Actions'],'rows'=>$rows,'num'=>[1,2,3,4],'noExportCol'=>8,'foot'=>['Total',1,[number_format($T['p'],2),number_format($T['pd'],2),number_format($T['d'],2),number_format($T['o'],2)]],
      'filters'=>[['supplier','Supplier','supplier'],['q','Name / mobile','text'],['status','Status','select',['paid'=>'Paid','partial'=>'Partial','due'=>'Due','overdue'=>'Overdue']]]];
  }
  private function dDamages(Request $r): array {
    $q = DB::table('damaged_products as d')->leftJoin('suppliers as s','s.id','=','d.supplier_id')->leftJoin('products as pr','pr.id','=','d.product_id')->leftJoin('purchases as u','u.id','=','d.purchase_id')->select('d.*','s.name as sname','pr.name as pname','u.invoice as inv');
    if ($r->filled('from')) $q->where('d.dmg_date','>=',$r->from); if ($r->filled('to')) $q->where('d.dmg_date','<=',$r->to); if ($r->filled('supplier')) $q->where('d.supplier_id',(int)$r->supplier);
    if ($r->filled('reason')) $q->where('d.reason_type',$r->reason); if ($r->filled('resp')) $q->where('d.responsibility',$r->resp); if ($r->filled('store')) $q->where('d.store_id',(int)$r->store);
    if ($r->filled('q')) { $t = '%'.$r->q.'%'; $q->where(fn($w) => $w->where('pr.name','ilike',$t)->orWhere('u.invoice','ilike',$t)->orWhere('d.batch','ilike',$t)); }
    $rows = []; $T = 0; $qt = 0;
    foreach ($q->orderByDesc('d.dmg_date')->orderByDesc('d.id')->limit(5000)->get() as $d) { $T += $d->value; $qt += $d->qty; $code = $d->code ?: 'DMG-'.str_pad($d->id,6,'0',STR_PAD_LEFT);
      $rows[] = ['id'=>$d->id,'cells'=>[e($code),$d->dmg_date,e($d->pname),e($d->sname ?: '—'),$d->inv ? '<a href="/admin/purchase/invoices/'.$d->purchase_id.'">'.e($d->inv).'</a>' : '—',e($d->batch),(int)$d->qty,number_format($d->value,2),e($d->reason_type),$this->badge($d->responsibility === 'supplier' ? 'supplier' : 'company'),$d->photo ? '<a href="'.e($d->photo).'" target="_blank">Photo</a>' : '—',$this->post('/admin/purchase/damages/'.$d->id.'/delete','Delete',[],'g sm','Delete this damage record and restore the stock?')],
        'text'=>[$code,$d->dmg_date,$d->pname,$d->sname,$d->inv,$d->batch,$d->qty,$d->value,$d->reason_type,$d->responsibility === 'supplier' ? 'Supplier claim' : 'Company loss',$d->note]]; }
    return ['title'=>'Damage / Return','sub'=>'Damaged stock leaves saleable stock and is tracked separately. Supplier claims reduce the supplier balance automatically.','head'=>['Damage','Date','Product','Supplier','Invoice','Batch','Qty','Value','Reason','Treatment','Photo','Actions'],'rows'=>$rows,'num'=>[6,7],'noExportCol'=>11,'foot'=>['Total',6,[$qt,number_format($T,2)]],'new'=>['Add Damage / Return','/admin/purchase/damages/new'],
      'filters'=>array_merge($this->dateF(),[['supplier','Supplier','supplier'],['q','Product / invoice / batch','text'],['reason','Reason','select',array_combine(P::REASONS,P::REASONS)],['resp','Treatment','select',['company'=>'Company loss','supplier'=>'Supplier claim']],['store','Store','store']])];
  }
  private function dReturns(Request $r): array {
    $q = DB::table('purchase_returns as t')->join('suppliers as s','s.id','=','t.supplier_id')->leftJoin('purchases as u','u.id','=','t.purchase_id')->select('t.*','s.name as sname','u.invoice as inv');
    if ($r->filled('from')) $q->where('t.return_date','>=',$r->from); if ($r->filled('to')) $q->where('t.return_date','<=',$r->to); if ($r->filled('supplier')) $q->where('t.supplier_id',(int)$r->supplier);
    if ($r->filled('q')) { $t = '%'.$r->q.'%'; $q->where(fn($w) => $w->where('t.name','ilike',$t)->orWhere('u.invoice','ilike',$t)->orWhere('t.code','ilike',$t)); }
    $rows = []; $T = 0;
    foreach ($q->orderByDesc('t.return_date')->orderByDesc('t.id')->limit(5000)->get() as $t) { $T += $t->value;
      $rows[] = ['id'=>$t->id,'cells'=>[e($t->code),$t->return_date,e($t->sname),$t->inv ? '<a href="/admin/purchase/invoices/'.$t->purchase_id.'">'.e($t->inv).'</a>' : '—',e($t->name),(int)$t->qty,number_format($t->rate,2),number_format($t->value,2),e($t->reason),$this->post('/admin/purchase/returns/'.$t->id.'/delete','Delete',[],'g sm','Delete this return and put the stock back?')],
        'text'=>[$t->code,$t->return_date,$t->sname,$t->inv,$t->name,$t->qty,$t->rate,$t->value,$t->reason]]; }
    return ['title'=>'Damage / Return','sub'=>'Goods sent back to suppliers. Stock goes down and the supplier balance is reduced.','head'=>['Return','Date','Supplier','Invoice','Product','Qty','Rate','Value','Reason','Actions'],'rows'=>$rows,'num'=>[5,6,7],'noExportCol'=>9,'foot'=>['Total',7,[number_format($T,2)]],'new'=>['Add Damage / Return','/admin/purchase/damages/new'],
      'filters'=>array_merge($this->dateF(),[['supplier','Supplier','supplier'],['q','Product / invoice','text']])];
  }
  private function dNotes(Request $r): array {
    $q = DB::table('supplier_notes as n')->join('suppliers as s','s.id','=','n.supplier_id')->leftJoin('purchases as u','u.id','=','n.purchase_id')->select('n.*','s.name as sname','u.invoice as inv');
    if ($r->filled('from')) $q->where('n.note_date','>=',$r->from); if ($r->filled('to')) $q->where('n.note_date','<=',$r->to); if ($r->filled('supplier')) $q->where('n.supplier_id',(int)$r->supplier); if ($r->filled('kind')) $q->where('n.kind',$r->kind);
    $rows = [];
    foreach ($q->orderByDesc('n.note_date')->orderByDesc('n.id')->limit(5000)->get() as $n)
      $rows[] = ['id'=>$n->id,'cells'=>[e($n->code),$n->note_date,$this->badge($n->kind),e($n->sname),$n->inv ? '<a href="/admin/purchase/invoices/'.$n->purchase_id.'">'.e($n->inv).'</a>' : '—',e($n->reason),number_format($n->amount,2),e($n->note),$this->post('/admin/purchase/notes/'.$n->id.'/delete','Delete',[],'g sm','Delete this note?')],'text'=>[$n->code,$n->note_date,$n->kind,$n->sname,$n->inv,$n->reason,$n->amount,$n->note]];
    return ['title'=>'Debit / Credit Notes','sub'=>'Debit note = reduces what we owe (claim, price difference). Credit note = adds to what we owe (extra charge).','head'=>['Note','Date','Type','Supplier','Invoice','Reason','Amount','Note','Actions'],'rows'=>$rows,'num'=>[6],'noExportCol'=>8,'new'=>['New Note','/admin/purchase/notes/new'],
      'filters'=>array_merge($this->dateF(),[['supplier','Supplier','supplier'],['kind','Type','select',['debit'=>'Debit note','credit'=>'Credit note']]])];
  }
  private function dLedger(Request $r): array {
    $sid = (int)$r->input('supplier'); $head = ['Date','Type','Reference','Purchase','Payment','Return','Damage adjustment','Debit','Credit','Running balance']; $rows = []; $foot = null;
    if ($sid) { $L = P::events($sid, $r->input('from') ?: null, $r->input('to') ?: null); $f = fn($v) => $v ? number_format($v,2) : '';
      $rows[] = ['id'=>0,'cells'=>['','<i>Opening balance</i>','','','','','','','',number_format($L['opening'],2)],'text'=>['','Opening balance','','','','','','','',$L['opening']]];
      foreach ($L['rows'] as $i=>$e) $rows[] = ['id'=>$i+1,'cells'=>[$e['d'],e($e['t']),e($e['ref']),$f($e['purchase']),$f($e['payment']),$f($e['return']),$f($e['damage']),$f($e['debit']),$f($e['credit']),'<b>'.number_format($e['bal'],2).'</b>'],'text'=>[$e['d'],$e['t'],$e['ref'],$e['purchase'],$e['payment'],$e['return'],$e['damage'],$e['debit'],$e['credit'],$e['bal']]];
      $t = $L['tot']; $foot = ['Totals / closing',3,[number_format($t['purchase'],2),number_format($t['payment'],2),number_format($t['return'],2),number_format($t['damage'],2),number_format($t['debit'],2),number_format($t['credit'],2),number_format($L['closing'],2)]];
    }
    return ['title'=>'Supplier Ledger','sub'=>'Credit = we owe more (purchase, credit note). Debit = we owe less (payment, return, damage claim, debit note). Running balance = amount due to supplier.','head'=>$head,'rows'=>$rows,'num'=>[3,4,5,6,7,8,9],'foot'=>$foot,'ledger'=>$sid,'filters'=>array_merge([['supplier','Supplier','supplier']],$this->dateF())];
  }
  private function dReport(Request $r): array {
    $k = $r->input('r','monthly'); $from = $r->input('from') ?: now('Asia/Dhaka')->startOfYear()->toDateString(); $to = $r->input('to') ?: $this->today(); $sup = (int)$r->input('supplier');
    $R = ['daily'=>'Daily purchase','monthly'=>'Monthly purchase','supplier'=>'Supplier-wise purchase','product'=>'Product-wise purchase','category'=>'Category-wise purchase','store'=>'Store-wise purchase','pay'=>'Supplier payments','due'=>'Supplier due','dmg_product'=>'Damage by product','dmg_supplier'=>'Damage by supplier','dmg_store'=>'Damage by store','dmg_reason'=>'Damage by reason','dmg_value'=>'Damage value (daily)'];
    $head = ['Group','Count','Amount']; $rows = []; $i = 0;
    $pq = fn() => DB::table('purchases as p')->whereNotIn('p.status',P::BAD)->whereBetween('p.purchase_date',[$from,$to])->when($sup, fn($q) => $q->where('p.supplier_id',$sup));
    $dq = fn() => DB::table('damaged_products as d')->whereBetween('d.dmg_date',[$from,$to])->when($sup, fn($q) => $q->where('d.supplier_id',$sup));
    $res = match ($k) {
      'daily' => $pq()->groupBy('p.purchase_date')->orderBy('p.purchase_date')->selectRaw('p.purchase_date g, count(*) c, sum(p.total) a')->get(),
      'monthly' => $pq()->groupByRaw("to_char(p.purchase_date,'YYYY-MM')")->orderByRaw("to_char(p.purchase_date,'YYYY-MM')")->selectRaw("to_char(p.purchase_date,'YYYY-MM') g, count(*) c, sum(p.total) a")->get(),
      'supplier' => $pq()->join('suppliers as s','s.id','=','p.supplier_id')->groupBy('s.name')->orderByDesc('a')->selectRaw('s.name g, count(*) c, sum(p.total) a')->get(),
      'store' => $pq()->leftJoin('stores as st','st.id','=','p.store_id')->groupBy('st.name')->orderByDesc('a')->selectRaw("coalesce(st.name,'No store') g, count(*) c, sum(p.total) a")->get(),
      'product' => DB::table('purchase_items as i')->join('purchases as p','p.id','=','i.purchase_id')->whereNotIn('p.status',P::BAD)->whereBetween('p.purchase_date',[$from,$to])->when($sup, fn($q) => $q->where('p.supplier_id',$sup))->groupBy('i.name')->orderByDesc('a')->selectRaw('i.name g, sum(i.qty) c, sum(i.line_total) a')->get(),
      'category' => DB::table('purchase_items as i')->join('purchases as p','p.id','=','i.purchase_id')->leftJoin('products as pr','pr.id','=','i.product_id')->leftJoin('categories as c','c.id','=','pr.category_id')->whereNotIn('p.status',P::BAD)->whereBetween('p.purchase_date',[$from,$to])->when($sup, fn($q) => $q->where('p.supplier_id',$sup))->groupBy('c.name')->orderByDesc('a')->selectRaw("coalesce(c.name,'Uncategorised') g, sum(i.qty) c, sum(i.line_total) a")->get(),
      'pay' => DB::table('supplier_payments as p')->join('suppliers as s','s.id','=','p.supplier_id')->where('p.status','paid')->whereBetween('p.pay_date',[$from,$to])->when($sup, fn($q) => $q->where('p.supplier_id',$sup))->groupBy('s.name')->orderByDesc('a')->selectRaw('s.name g, count(*) c, sum(p.amount) a')->get(),
      'dmg_product' => $dq()->leftJoin('products as pr','pr.id','=','d.product_id')->groupBy('pr.name')->orderByDesc('a')->selectRaw("coalesce(pr.name,'—') g, sum(d.qty) c, sum(d.value) a")->get(),
      'dmg_supplier' => $dq()->leftJoin('suppliers as s','s.id','=','d.supplier_id')->groupBy('s.name')->orderByDesc('a')->selectRaw("coalesce(s.name,'No supplier') g, sum(d.qty) c, sum(d.value) a")->get(),
      'dmg_store' => $dq()->leftJoin('stores as st','st.id','=','d.store_id')->groupBy('st.name')->orderByDesc('a')->selectRaw("coalesce(st.name,'No store') g, sum(d.qty) c, sum(d.value) a")->get(),
      'dmg_reason' => $dq()->groupBy('d.reason_type')->orderByDesc('a')->selectRaw("coalesce(d.reason_type,'—') g, sum(d.qty) c, sum(d.value) a")->get(),
      'dmg_value' => $dq()->groupBy('d.dmg_date')->orderBy('d.dmg_date')->selectRaw('d.dmg_date g, sum(d.qty) c, sum(d.value) a')->get(),
      default => collect() };
    if ($k === 'due') { $head = ['Supplier','Overdue','Current due']; foreach ($this->dues($r) as $x) { if ($sup && $x['s']->id != $sup) continue; if (abs($x['due']) < 0.01) continue; $rows[] = ['id'=>++$i,'cells'=>[e($x['s']->name),number_format($x['over'],2),number_format($x['due'],2)],'text'=>[$x['s']->name,$x['over'],$x['due']]]; } }
    else { $tot = 0; foreach ($res as $x) { $tot += $x->a; $rows[] = ['id'=>++$i,'cells'=>[e($x->g),(float)$x->c,number_format($x->a,2)],'text'=>[$x->g,$x->c,$x->a]]; } }
    return ['title'=>$R[$k] ?? 'Report','sub'=>$from.' → '.$to,'head'=>$head,'rows'=>$rows,'num'=>[1,2],'foot'=>$k === 'due' ? null : ['Total',2,[number_format($tot ?? 0,2)]],'reportKey'=>$k,'reports'=>$R,
      'filters'=>[['r','Report','select',$R],['from','From','date'],['to','To','date'],['supplier','Supplier','supplier']]];
  }
  public function reports(Request $r) { return $this->list('reports', $r); }

  // ---------------- dashboard ----------------
  public function dashboard(Request $r) {
    $bad = P::BAD; $today = $this->today(); $m0 = now('Asia/Dhaka')->startOfMonth()->toDateString(); $lm0 = now('Asia/Dhaka')->subMonthNoOverflow()->startOfMonth()->toDateString();
    $pq = fn() => DB::table('purchases')->whereNotIn('status',$bad); $dm = P::dueMap(); $paid = $this->paidMap();
    $month = (float)$pq()->where('purchase_date','>=',$m0)->sum('total'); $last = (float)$pq()->whereBetween('purchase_date',[$lm0,$m0])->where('purchase_date','<',$m0)->sum('total');
    $k = ['today'=>(float)$pq()->where('purchase_date',$today)->sum('total'),'todayn'=>$pq()->where('purchase_date',$today)->count(),'month'=>$month,'pct'=>$last > 0 ? round(($month-$last)/$last*100) : null,
      'due'=>array_sum(array_filter($dm, fn($v) => $v > 0)),'duen'=>count(array_filter($dm, fn($v) => $v > 0.009)),'paid'=>(float)DB::table('supplier_payments')->where('status','paid')->sum('amount'),'paidm'=>(float)DB::table('supplier_payments')->where('status','paid')->where('pay_date','>=',$m0)->sum('amount'),
      'damage'=>(float)DB::table('damaged_products')->sum('value'),'returns'=>(float)DB::table('purchase_returns')->sum('value')];
    $recent = DB::table('purchases as p')->join('suppliers as s','s.id','=','p.supplier_id')->orderByDesc('p.purchase_date')->orderByDesc('p.id')->limit(8)->get(['p.id','p.invoice','p.total','p.status','p.purchase_date','s.name as sname'])->map(function ($x) use ($paid) { $x->paid = (float)($paid[$x->id] ?? 0); return $x; });
    $names = DB::table('suppliers')->pluck('name','id'); $dues = collect($dm)->filter(fn($v) => $v > 0.009)->sortDesc()->take(8)->map(fn($v,$id) => ['id'=>$id,'name'=>$names[$id] ?? '#'.$id,'due'=>$v])->values();
    $sup = $this->dSuppliers($r); $sup['rows'] = array_slice($sup['rows'], 0, 12);
    $mon = $pq()->where('purchase_date','>=',now('Asia/Dhaka')->subMonths(11)->startOfMonth()->toDateString())->groupByRaw("to_char(purchase_date,'YYYY-MM')")->selectRaw("to_char(purchase_date,'YYYY-MM') g, sum(total) a")->pluck('a','g'); $months = []; for ($i = 11; $i >= 0; $i--) $months[] = now('Asia/Dhaka')->subMonths($i)->format('Y-m');
    $ch = array_map(fn($m) => [date('M y', strtotime($m.'-01')), (float)($mon[$m] ?? 0)], $months);
    return view('proc.dashboard', compact('k','recent','dues','sup','ch'));
  }

  // ---------------- suppliers ----------------
  public function supplierForm($id = null) { $s = $id ? DB::table('suppliers')->find($id) : null; if ($id && !$s) abort(404); return view('proc.supplier_form', ['s'=>$s]); }
  public function supplierSave(Request $r) {
    $r->validate(['name'=>'required|max:200','phone'=>'nullable|max:40','email'=>'nullable|email|max:160']);
    $d = $r->only(['name','contact_person','phone','whatsapp','email','business_name','address','payment_terms','notes']); $d['opening_balance'] = (float)$r->input('opening_balance',0); $d['credit_limit'] = (float)$r->input('credit_limit',0); $d['payment_terms'] = (int)$r->input('payment_terms',0); $d['active'] = $r->boolean('active');
    $id = (int)$r->input('id');
    if ($id) { $prev = DB::table('suppliers')->find($id); DB::table('suppliers')->where('id',$id)->update($d + ['updated_at'=>now()]); P::audit('proc.supplier.edit','supplier#'.$id,['opening'=>$prev->opening_balance,'name'=>$prev->name],['opening'=>$d['opening_balance'],'name'=>$d['name']]); if ((float)$prev->opening_balance != $d['opening_balance']) P::audit('proc.due.adjust','supplier#'.$id,$prev->opening_balance,$d['opening_balance']); }
    else { $id = DB::table('suppliers')->insertGetId($d + ['token'=>bin2hex(random_bytes(10)),'created_at'=>now(),'updated_at'=>now()]); DB::table('suppliers')->where('id',$id)->update(['code'=>P::code('SUP-',$id) ? 'SUP-'.str_pad($id,5,'0',STR_PAD_LEFT) : null]); P::audit('proc.supplier.add','supplier#'.$id,null,['name'=>$d['name']]); }
    return redirect('/admin/purchase/suppliers/'.$id)->with('ok','Supplier saved');
  }
  public function supplierDelete($id) {
    if (DB::table('purchases')->where('supplier_id',$id)->exists() || DB::table('supplier_payments')->where('supplier_id',$id)->exists()) return back()->with('bad','This supplier has transactions — mark it Inactive instead of deleting.');
    DB::table('suppliers')->where('id',$id)->delete(); P::audit('proc.supplier.delete','supplier#'.$id); return redirect('/admin/purchase/suppliers')->with('ok','Supplier deleted');
  }
  public function supplier($id) {
    $s = DB::table('suppliers')->find($id); if (!$s) abort(404); $L = P::events($id); $paid = $this->paidMap(); $today = $this->today();
    $inv = DB::table('purchases')->where('supplier_id',$id)->orderByDesc('purchase_date')->orderByDesc('id')->get(); $over = 0;
    foreach ($inv as $p) { $p->paid = (float)($paid[$p->id] ?? 0); $p->ps = P::payStatus($p, $p->paid); if (!in_array($p->status,P::BAD) && $p->due_date && $p->due_date < $today) $over += max(0, $p->total - $p->paid); }
    $good = $inv->filter(fn($p) => !in_array($p->status,P::BAD));
    $sum = ['purchase'=>$good->sum('total'),'paid'=>DB::table('supplier_payments')->where('supplier_id',$id)->where('status','paid')->sum('amount'),'due'=>$L['closing'],'over'=>$over,'lastp'=>$good->max('purchase_date'),'lastpay'=>DB::table('supplier_payments')->where('supplier_id',$id)->where('status','paid')->max('pay_date'),
      'returns'=>DB::table('purchase_returns')->where('supplier_id',$id)->sum('value'),'claims'=>DB::table('damaged_products')->where('supplier_id',$id)->where('responsibility','supplier')->sum('value'),'count'=>$good->count()];
    $pays = DB::table('supplier_payments')->where('supplier_id',$id)->orderByDesc('pay_date')->orderByDesc('id')->limit(30)->get();
    $comm = DB::table('sms_logs')->where('ref','like','sup:'.$id.':%')->orWhere(fn($q) => $q->where('event','like','supplier_%')->where('customer_name',$s->name))->orderByDesc('id')->limit(40)->get();
    return view('proc.supplier', compact('s','sum','inv','pays','comm','L'));
  }
  public function supplierSms(Request $r, $id) {
    $s = DB::table('suppliers')->find($id); if (!$s) abort(404); $num = Sms::number((string)($s->phone ?: $s->whatsapp)); if (!$num) return back()->with('bad','This supplier has no valid mobile number.');
    $body = trim((string)$r->input('body')); if ($body === '') return back()->with('bad','Write a message first.');
    Sms::queue($num, $body, 'supplier_manual', ['type'=>'transactional','customer_name'=>$s->name,'ref'=>'sup:'.$id.':-:']); P::audit('proc.sms','supplier#'.$id,null,['body'=>$body]); return back()->with('ok','SMS queued');
  }
  public function remind($id) { $ok = P::notify('due',(int)$id,[],null,true); return back()->with($ok ? 'ok' : 'bad', $ok ? 'Due reminder SMS queued' : 'Could not send — check the supplier mobile number.'); }
  public function sendStatement(Request $r, $id) { $ok = P::notify('statement',(int)$id,[],null,true); return back()->with($ok ? 'ok' : 'bad', $ok ? 'Statement link SMS queued' : 'Could not send — check the supplier mobile number.'); }

  // ---------------- purchase create/edit ----------------
  public function form($id = null) {
    $p = $id ? DB::table('purchases')->find($id) : null; if ($id && !$p) abort(404);
    $items = $p ? DB::table('purchase_items')->where('purchase_id',$id)->orderBy('id')->get() : collect();
    $due = []; foreach (P::dueMap() as $sid=>$v) $due[$sid] = round($v,2);
    return view('proc.purchase_form', ['p'=>$p,'items'=>$items,'sup'=>DB::table('suppliers')->where('active',true)->orWhere('id',$p->supplier_id ?? 0)->orderBy('name')->get(['id','name','code','payment_terms','credit_limit']),'stores'=>$this->stores(),'dues'=>$due,'pre'=>(int)request('supplier')]);
  }
  public function save(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','purchase_date'=>'required|date','items'=>'required|array|min:1']);
    $items = []; foreach ((array)$r->input('items') as $i) { $qty = (int)($i['qty'] ?? 0); if ($qty <= 0 || trim((string)($i['name'] ?? '')) === '') continue; $cost = (float)($i['cost'] ?? 0); $dis = (float)($i['discount'] ?? 0); $tax = (float)($i['tax'] ?? 0);
      $items[] = ['product_id'=>(int)($i['product_id'] ?? 0) ?: null,'name'=>mb_substr(trim($i['name']),0,250),'sku'=>$i['sku'] ?? null,'barcode'=>$i['barcode'] ?? null,'qty'=>$qty,'cost'=>$cost,'discount'=>$dis,'tax'=>$tax,'batch'=>$i['batch'] ?? null,'expiry'=>($i['expiry'] ?? '') ?: null,'damage_qty'=>min($qty,max(0,(int)($i['damage_qty'] ?? 0))),'line_total'=>round($qty*$cost-$dis+$tax,2)]; }
    if (!$items) return back()->withInput()->with('bad','Add at least one product with a quantity.');
    $sub = array_sum(array_map(fn($x) => $x['qty']*$x['cost'], $items)); $idis = array_sum(array_column($items,'discount')); $tax = array_sum(array_column($items,'tax'));
    $edis = (float)$r->input('extra_discount',0); $tr = (float)$r->input('transport',0); $oc = (float)$r->input('other_cost',0); $total = round($sub-$idis-$edis+$tax+$tr+$oc,2);
    $sid = (int)$r->supplier_id; $s = DB::table('suppliers')->find($sid); if (!$s) return back()->withInput()->with('bad','Select a supplier.');
    $act = $r->input('act','save'); $draft = $act === 'draft'; $id = (int)$r->input('id'); $old = $id ? DB::table('purchases')->find($id) : null;
    $status = $draft ? 'draft' : ((P::on('proc_approval') && $total >= (float)P::set('proc_approval_limit','100000') && !P::canApprove()) ? 'pending' : 'received');
    if ($old && !$draft && in_array($old->status,['approved']) ) $status = 'approved';
    $due = $r->input('due_date') ?: ($s->payment_terms ? \Carbon\Carbon::parse($r->purchase_date)->addDays((int)$s->payment_terms)->toDateString() : null);
    $h = ['supplier_id'=>$sid,'purchase_date'=>$r->purchase_date,'supplier_invoice'=>$r->supplier_invoice,'store_id'=>(int)$r->store_id ?: null,'ptype'=>$r->ptype ?: 'Local','pay_type'=>$r->pay_type ?: 'Credit','reference'=>$r->reference,'note'=>$r->note,'due_date'=>$due,
      'subtotal'=>round($sub,2),'item_discount'=>round($idis,2),'extra_discount'=>$edis,'tax'=>round($tax,2),'transport'=>$tr,'other_cost'=>$oc,'total'=>$total,'status'=>$status];
    $msg = DB::transaction(function () use ($r, $h, $items, $id, $old, $sid, $total, $status) {
      if ($old) { P::reverseStock($id); DB::table('purchase_items')->where('purchase_id',$id)->delete(); DB::table('purchases')->where('id',$id)->update($h + ['updated_at'=>now()]);
        P::audit('proc.purchase.edit','purchase#'.$id,['total'=>$old->total,'status'=>$old->status],['total'=>$total,'status'=>$status]); }
      else { $h['prev_due'] = round(P::due($sid),2); $id = DB::table('purchases')->insertGetId($h + ['invoice'=>'TMP'.uniqid(),'token'=>bin2hex(random_bytes(10)),'user_id'=>auth()->id(),'paid'=>0,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('purchases')->where('id',$id)->update(['invoice'=>'PUR-'.str_pad($id,6,'0',STR_PAD_LEFT)]); P::audit('proc.purchase.add','purchase#'.$id,null,['total'=>$total,'supplier'=>$sid,'status'=>$status]); }
      foreach ($items as $i) DB::table('purchase_items')->insert($i + ['purchase_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
      if (!in_array($status,P::BAD)) P::applyStock($id);
      $note = ''; $pn = (float)$r->input('paid_now',0);
      if ($pn > 0 && !in_array($status,P::BAD)) { P::pay(['supplier_id'=>$sid,'purchase_id'=>$id,'pay_date'=>$r->purchase_date,'amount'=>$pn,'method'=>$r->pay_method ?: 'Cash','note'=>'Paid with purchase','reference'=>$r->reference], false); }
      elseif ($pn > 0) $note = ' (payment not recorded — approve the purchase first)';
      return [$id, $note];
    });
    [$id, $note] = $msg;
    if (!in_array($status,P::BAD) && ($r->boolean('sms') || $act === 'sms' || (P::on('proc_sms_purchase') && !$old))) P::notify('purchase',$sid,[],$id,true);
    if ($act === 'print') return redirect('/admin/purchase/invoices/'.$id.'/export/pdf');
    return redirect('/admin/purchase/invoices/'.$id)->with('ok', ($draft ? 'Draft saved' : ($status === 'pending' ? 'Sent for approval' : 'Purchase saved & stock updated')).$note);
  }
  public function show($id) {
    $p = DB::table('purchases')->find($id); if (!$p) abort(404); $d = $this->docData($p);
    return view('proc.invoice_show', $d + ['p'=>$p]);
  }
  private function docData($p): array {
    $s = DB::table('suppliers')->find($p->supplier_id); $items = DB::table('purchase_items')->where('purchase_id',$p->id)->orderBy('id')->get(); $paid = P::paidOf($p->id);
    $pays = DB::table('payment_allocations as a')->join('supplier_payments as y','y.id','=','a.payment_id')->where('a.purchase_id',$p->id)->where('y.status','paid')->orderBy('y.pay_date')->get(['y.code','y.pay_date','y.method','a.amount']);
    return ['s'=>$s,'items'=>$items,'paid'=>$paid,'ps'=>P::payStatus($p,$paid),'pays'=>$pays,'store'=>$p->store_id ? DB::table('stores')->where('id',$p->store_id)->value('name') : null,'cur'=>round(P::due($p->supplier_id),2),'link'=>P::invoiceLink($p)];
  }
  public function publicInvoice($no, Request $r) {
    $p = DB::table('purchases')->where('invoice',$no)->first(); if (!$p || !hash_equals((string)$p->token, (string)$r->query('t')) || in_array($p->status,['draft','cancelled'])) abort(404);
    return response(view('proc.invoice_public', $this->docData($p) + ['p'=>$p,'public'=>true]))->header('X-Robots-Tag','noindex, nofollow');
  }
  public function invoiceExport($id, $fmt) {
    $p = DB::table('purchases')->find($id); if (!$p) abort(404); $html = view('proc.invoice_public', $this->docData($p) + ['p'=>$p,'public'=>false,'bare'=>true])->render(); P::audit('proc.export','invoice#'.$id.'.'.$fmt);
    if ($fmt === 'xls') return response($html)->header('Content-Type','application/vnd.ms-excel; charset=utf-8')->header('Content-Disposition','attachment; filename="'.$p->invoice.'.xls"');
    if ($fmt === 'doc') return response($html)->header('Content-Type','application/msword; charset=utf-8')->header('Content-Disposition','attachment; filename="'.$p->invoice.'.doc"');
    return response($html.'<script>window.onload=function(){window.print()}</script>');
  }
  public function action(Request $r, $id) {
    $p = DB::table('purchases')->find($id); if (!$p) abort(404); $a = $r->input('a'); $prev = ['status'=>$p->status];
    if ($a === 'approve') { if (!P::canApprove()) abort(403); DB::transaction(function () use ($id) { DB::table('purchases')->where('id',$id)->update(['status'=>'approved','approved_by'=>auth()->user()->name,'updated_at'=>now()]); P::applyStock($id); }); $m = 'Approved & stock added'; }
    elseif ($a === 'receive') { DB::table('purchases')->where('id',$id)->update(['status'=>'received','updated_at'=>now()]); DB::transaction(fn() => P::applyStock($id)); $m = 'Marked received'; }
    elseif ($a === 'cancel') { DB::transaction(function () use ($id) { P::reverseStock($id); DB::table('purchases')->where('id',$id)->update(['status'=>'cancelled','updated_at'=>now()]); }); $m = 'Cancelled and stock reversed'; }
    elseif ($a === 'delete') { if (DB::table('payment_allocations')->where('purchase_id',$id)->exists()) return back()->with('bad','This invoice has payments — cancel it instead of deleting.');
      DB::transaction(function () use ($id) { P::reverseStock($id); DB::table('purchase_items')->where('purchase_id',$id)->delete(); DB::table('purchases')->where('id',$id)->delete(); }); P::audit('proc.purchase.delete','purchase#'.$id,['invoice'=>$p->invoice,'total'=>$p->total]); return redirect('/admin/purchase/invoices')->with('ok','Purchase deleted and stock reversed'); }
    elseif ($a === 'sms') { $ok = P::notify('purchase',$p->supplier_id,[],$id,true); return back()->with($ok ? 'ok' : 'bad', $ok ? 'Invoice link SMS queued' : 'Could not send — check the supplier mobile number.'); }
    else abort(404);
    P::audit('proc.purchase.'.$a,'purchase#'.$id,$prev,['status'=>DB::table('purchases')->where('id',$id)->value('status')]); return back()->with('ok',$m);
  }

  // ---------------- payments ----------------
  public function payForm() { $m0 = now('Asia/Dhaka')->startOfMonth()->toDateString(); return view('proc.payment_form', ['sumDue'=>array_sum(array_filter(P::dueMap(), fn($v) => $v > 0)),'sumPaid'=>(float)DB::table('supplier_payments')->where('status','paid')->where('pay_date','>=',$m0)->sum('amount'),'sup'=>$this->suppliers(),'pre'=>(int)request('supplier'),'inv'=>(int)request('invoice'),'methods'=>P::METHODS]); }
  public function paySave(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','pay_date'=>'required|date','amount'=>'required|numeric|min:0.01','method'=>'required','proof'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192']);
    $proof = null; if ($r->hasFile('proof')) { $dir = public_path('uploads/proc'); if (!is_dir($dir)) @mkdir($dir,0775,true); $f = $r->file('proof'); $n = date('ymd').'-'.bin2hex(random_bytes(5)).'.'.strtolower($f->getClientOriginalExtension()); $f->move($dir,$n); $proof = '/uploads/proc/'.$n; }
    $id = P::pay(['supplier_id'=>(int)$r->supplier_id,'purchase_id'=>(int)$r->purchase_id ?: null,'pay_date'=>$r->pay_date,'amount'=>(float)$r->amount,'method'=>$r->method,'account'=>$r->account,'txn_id'=>$r->txn_id,'reference'=>$r->reference,'note'=>$r->note,'proof'=>$proof], !$r->has('nosms'));
    $st = DB::table('supplier_payments')->where('id',$id)->value('status');
    return redirect('/admin/purchase/payments')->with('ok', $st === 'pending' ? 'Payment sent for approval' : 'Payment saved, ledger updated'.($r->has('nosms') ? '' : (P::on('proc_sms_payment') ? ' and SMS queued' : '')));
  }
  public function payAction(Request $r, $id, $a) {
    $p = DB::table('supplier_payments')->find($id); if (!$p) abort(404);
    if ($a === 'approve') { if (!P::canApprove()) abort(403); DB::table('supplier_payments')->where('id',$id)->update(['status'=>'paid','approved_by'=>auth()->user()->name,'updated_at'=>now()]); P::allocate($id); P::audit('proc.payment.approve','payment#'.$id,['status'=>'pending'],['status'=>'paid']); P::notify('payment',$p->supplier_id,['amount'=>number_format($p->amount,2)],$p->purchase_id); return back()->with('ok','Payment approved'); }
    if ($a === 'delete') { if ($p->status === 'paid' && !P::canApprove()) abort(403); $pids = DB::table('payment_allocations')->where('payment_id',$id)->pluck('purchase_id'); DB::table('payment_allocations')->where('payment_id',$id)->delete(); DB::table('supplier_payments')->where('id',$id)->delete();
      foreach ($pids as $pid) DB::table('purchases')->where('id',$pid)->update(['paid'=>P::paidOf($pid)]); P::audit('proc.payment.delete','payment#'.$id,['amount'=>$p->amount,'code'=>$p->code]); return back()->with('ok','Payment deleted'); }
    abort(404);
  }

  // ---------------- damage / return / notes ----------------
  public function damageForm() { return view('proc.damage_form', ['sup'=>$this->suppliers(),'stores'=>$this->stores(),'reasons'=>P::REASONS]); }
  public function drSave(Request $r) {
    $m = $r->input('mode','company');
    if ($m === 'return') { $r->validate(['supplier_id'=>'required|integer','product_id'=>'required|integer','qty'=>'required|integer|min:1','dmg_date'=>'required|date']);
      $qty = max(1,(int)$r->qty); $amt = $r->filled('amount') ? (float)$r->amount : $qty*(float)$r->unit_cost; $name = DB::table('products')->where('id',(int)$r->product_id)->value('name') ?: 'Product';
      $r->merge(['name'=>$name,'rate'=>round($amt/$qty,2),'return_date'=>$r->dmg_date,'reason'=>$r->reason_type.($r->note ? ' — '.$r->note : '')]); return $this->returnSave($r); }
    $r->merge(['responsibility'=>$m === 'claim' ? 'supplier' : 'company']); return $this->damageSave($r);
  }
  public function damageSave(Request $r) {
    $r->validate(['product_id'=>'required|integer','qty'=>'required|integer|min:1','dmg_date'=>'required|date','photo'=>'nullable|image|max:8192']);
    $photo = null; if ($r->hasFile('photo')) { $dir = public_path('uploads/proc'); if (!is_dir($dir)) @mkdir($dir,0775,true); $f = $r->file('photo'); $n = date('ymd').'-'.bin2hex(random_bytes(5)).'.'.strtolower($f->getClientOriginalExtension()); $f->move($dir,$n); $photo = '/uploads/proc/'.$n; }
    $qty = (int)$r->qty; $cost = (float)$r->unit_cost; $resp = $r->responsibility === 'supplier' && $r->supplier_id ? 'supplier' : 'company'; $sid = (int)$r->supplier_id ?: null;
    $id = DB::transaction(function () use ($r, $qty, $cost, $resp, $sid, $photo) {
      $id = DB::table('damaged_products')->insertGetId(['product_id'=>(int)$r->product_id,'qty'=>$qty,'reason'=>$r->reason_type,'supplier_id'=>$sid,'purchase_id'=>(int)$r->purchase_id ?: null,'batch'=>$r->batch,'reason_type'=>$r->reason_type ?: 'Other','responsibility'=>$resp,'photo'=>$photo,'unit_cost'=>$cost,'value'=>$r->filled('amount') ? round((float)$r->amount,2) : round($qty*$cost,2),'dmg_date'=>$r->dmg_date,'note'=>$r->note,'store_id'=>(int)$r->store_id ?: null,'user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
      DB::table('damaged_products')->where('id',$id)->update(['code'=>'DMG-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
      P::stock((int)$r->product_id, -$qty, 'damaged', 'DMG-'.str_pad($id,6,'0',STR_PAD_LEFT)); DB::table('products')->where('id',(int)$r->product_id)->increment('damage_stock',$qty); return $id; });
    P::audit('proc.damage.add','damage#'.$id,null,['qty'=>$qty,'value'=>$qty*$cost,'treatment'=>$resp]);
    if ($resp === 'supplier') P::notify('damage',$sid,['amount'=>number_format($qty*$cost,2)],(int)$r->purchase_id ?: null);
    return redirect('/admin/purchase/damages')->with('ok','Damage recorded'.($resp === 'supplier' ? ' — supplier balance reduced by '.P::tk($qty*$cost) : ' as company loss'));
  }
  public function damageDelete($id) {
    $d = DB::table('damaged_products')->find($id); if (!$d) abort(404);
    DB::transaction(function () use ($d, $id) { P::stock((int)$d->product_id, (int)$d->qty, 'damage-reversal', 'DMG#'.$id); DB::table('products')->where('id',$d->product_id)->update(['damage_stock'=>DB::raw('greatest(damage_stock - '.(int)$d->qty.', 0)')]); DB::table('damaged_products')->where('id',$id)->delete(); });
    P::audit('proc.damage.delete','damage#'.$id,['value'=>$d->value,'qty'=>$d->qty]); return back()->with('ok','Damage deleted, stock restored');
  }
  public function returnForm() { return view('proc.return_form', ['sup'=>$this->suppliers()]); }
  public function returnSave(Request $r) {
    $r->validate(['supplier_id'=>'required|integer','name'=>'required','qty'=>'required|integer|min:1','rate'=>'required|numeric|min:0','return_date'=>'required|date']);
    $qty = (int)$r->qty; $rate = (float)$r->rate;
    $id = DB::transaction(function () use ($r, $qty, $rate) {
      $id = DB::table('purchase_returns')->insertGetId(['supplier_id'=>(int)$r->supplier_id,'purchase_id'=>(int)$r->purchase_id ?: null,'product_id'=>(int)$r->product_id ?: null,'name'=>$r->name,'qty'=>$qty,'rate'=>$rate,'value'=>round($qty*$rate,2),'reason'=>$r->reason,'return_date'=>$r->return_date,'note'=>$r->note,'user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
      DB::table('purchase_returns')->where('id',$id)->update(['code'=>'RET-'.str_pad($id,6,'0',STR_PAD_LEFT)]); if ($r->product_id) P::stock((int)$r->product_id, -$qty, 'purchase-return', 'RET-'.str_pad($id,6,'0',STR_PAD_LEFT)); return $id; });
    P::audit('proc.return.add','return#'.$id,null,['value'=>$qty*$rate,'supplier'=>$r->supplier_id]); P::notify('return',(int)$r->supplier_id,['amount'=>number_format($qty*$rate,2)],(int)$r->purchase_id ?: null);
    return redirect('/admin/purchase/returns')->with('ok','Return saved — stock reduced and supplier balance adjusted');
  }
  public function returnDelete($id) {
    $t = DB::table('purchase_returns')->find($id); if (!$t) abort(404);
    DB::transaction(function () use ($t, $id) { if ($t->product_id) P::stock((int)$t->product_id, (int)$t->qty, 'purchase-return-reversal', $t->code); DB::table('purchase_returns')->where('id',$id)->delete(); });
    P::audit('proc.return.delete','return#'.$id,['value'=>$t->value]); return back()->with('ok','Return deleted, stock restored');
  }
  public function noteForm() { return view('proc.note_form', ['sup'=>$this->suppliers()]); }
  public function noteSave(Request $r) {
    $r->validate(['kind'=>'required|in:debit,credit','supplier_id'=>'required|integer','amount'=>'required|numeric|min:0.01','note_date'=>'required|date']);
    $id = DB::table('supplier_notes')->insertGetId(['kind'=>$r->kind,'supplier_id'=>(int)$r->supplier_id,'purchase_id'=>(int)$r->purchase_id ?: null,'reason'=>$r->reason,'amount'=>(float)$r->amount,'note_date'=>$r->note_date,'note'=>$r->note,'user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
    DB::table('supplier_notes')->where('id',$id)->update(['code'=>($r->kind === 'debit' ? 'DN-' : 'CN-').str_pad($id,6,'0',STR_PAD_LEFT)]); P::audit('proc.due.adjust','note#'.$id,null,['kind'=>$r->kind,'amount'=>$r->amount]);
    return redirect('/admin/purchase/notes')->with('ok','Note saved and supplier ledger updated');
  }
  public function noteDelete($id) { $n = DB::table('supplier_notes')->find($id); if (!$n) abort(404); DB::table('supplier_notes')->where('id',$id)->delete(); P::audit('proc.due.adjust','note#'.$id,['amount'=>$n->amount,'kind'=>$n->kind],null); return back()->with('ok','Note deleted'); }

  // ---------------- ledger / statement ----------------
  public function ledger(Request $r) { $d = $this->dLedger($r); $s = $r->filled('supplier') ? DB::table('suppliers')->find($r->supplier) : null; return $this->listPage($r, 'ledger', $d + ['s'=>$s,'q'=>null]); }
  public function statement($token, Request $r) {
    $s = DB::table('suppliers')->where('token',$token)->first(); if (!$s) abort(404);
    $from = $r->query('from') ?: now('Asia/Dhaka')->startOfMonth()->toDateString(); $to = $r->query('to') ?: $this->today(); $L = P::events($s->id,$from,$to);
    return response(view('proc.statement', compact('s','L','from','to')))->header('X-Robots-Tag','noindex, nofollow');
  }

  // ---------------- settings / audit ----------------
  public function settings() { return view('proc.settings', ['v'=>fn($k,$d='') => P::set($k,$d),'tpl'=>P::TPL]); }
  public function settingsSave(Request $r) {
    $prev = []; foreach (['proc_sms_purchase','proc_sms_payment','proc_sms_due','proc_sms_return','proc_sms_damage','proc_sms_statement','proc_approval'] as $k) { $prev[$k] = P::set($k); P::put($k, $r->boolean($k) ? '1' : '0'); }
    P::put('proc_due_offsets', preg_replace('/[^0-9,\-]/','',(string)$r->input('proc_due_offsets','-3,0,3')) ?: '-3,0,3'); P::put('proc_approval_limit', (string)(float)$r->input('proc_approval_limit',100000)); P::put('proc_pay_approval_limit', (string)(float)$r->input('proc_pay_approval_limit',100000));
    foreach (array_keys(P::TPL) as $k) if ($r->has('tpl_'.$k)) P::put('tpl_'.$k, trim((string)$r->input('tpl_'.$k)));
    P::audit('proc.settings','settings',$prev,$r->except(['_token'])); return back()->with('ok','Settings saved');
  }
  public function audit(Request $r) {
    $q = DB::table('audit_logs')->where('action','like','proc.%'); if ($r->filled('from')) $q->where('created_at','>=',$r->from); if ($r->filled('to')) $q->where('created_at','<',\Carbon\Carbon::parse($r->to)->addDay()); if ($r->filled('a')) $q->where('action','like','%'.$r->a.'%'); if ($r->filled('u')) $q->where('user_name','ilike','%'.$r->u.'%');
    return view('proc.audit', ['rows'=>$q->orderByDesc('id')->paginate(50)->withQueryString()]);
  }

  // ---------------- JSON helpers for the forms ----------------
  public function apiProducts(Request $r) {
    $q = trim((string)$r->input('q')); if ($q === '') return response()->json([]);
    $x = DB::table('products')->where(fn($w) => $w->where('barcode',$q)->orWhere('sku',$q)->orWhere('name','ilike','%'.$q.'%')->orWhere('sku','ilike','%'.$q.'%')->orWhere('barcode','ilike','%'.$q.'%'))->orderByRaw('(barcode = ? or sku = ?) desc',[$q,$q])->limit(15)->get(['id','name','sku','barcode','cost','stock']);
    return response()->json($x);
  }
  public function apiNewProduct(Request $r) {
    $r->validate(['name'=>'required|max:250']); $sku = trim((string)$r->sku) ?: null; if ($sku && DB::table('products')->where('sku',$sku)->exists()) return response()->json(['error'=>'SKU already exists'],422);
    $id = DB::table('products')->insertGetId(['name'=>$r->name,'sku'=>$sku,'barcode'=>$r->barcode ?: null,'cost'=>(float)$r->cost,'price'=>(float)$r->price,'stock'=>0,'published'=>false,'created_at'=>now(),'updated_at'=>now()]);
    P::audit('proc.product.quick','product#'.$id,null,['name'=>$r->name]); return response()->json(['id'=>$id,'name'=>$r->name,'sku'=>$sku,'barcode'=>$r->barcode,'cost'=>(float)$r->cost,'stock'=>0]);
  }
  public function apiBulk(Request $r) {
    $out = []; foreach (preg_split('/\R/', (string)$r->input('lines')) as $ln) { $c = array_map('trim', preg_split('/[,\t]/', $ln)); if (($c[0] ?? '') === '') continue;
      $p = DB::table('products')->where('sku',$c[0])->orWhere('barcode',$c[0])->first(['id','name','sku','barcode','cost','stock']); if ($p) { $p->qty = (int)($c[1] ?? 1) ?: 1; if (isset($c[2]) && $c[2] !== '') $p->cost = (float)$c[2]; $out[] = $p; } else $out[] = ['missing'=>$c[0]]; }
    return response()->json($out);
  }
  public function apiSupplier(Request $r, $id) {
    $s = DB::table('suppliers')->find($id); if (!$s) return response()->json([]); $paid = $this->paidMap();
    $inv = DB::table('purchases')->where('supplier_id',$id)->whereNotIn('status',P::BAD)->orderBy('purchase_date')->get()->map(fn($p) => ['id'=>$p->id,'invoice'=>$p->invoice,'date'=>$p->purchase_date,'total'=>(float)$p->total,'due'=>round($p->total-($paid[$p->id] ?? 0),2)])->all();
    return response()->json(['due'=>round(P::due($id),2),'credit_limit'=>(float)$s->credit_limit,'terms'=>(int)$s->payment_terms,'invoices'=>$inv]);
  }
  public function apiItems(Request $r, $id) {
    $ret = DB::table('purchase_returns')->where('purchase_id',$id)->whereNotNull('product_id')->groupBy('product_id')->selectRaw('product_id, sum(qty) q')->pluck('q','product_id');
    return response()->json(DB::table('purchase_items')->where('purchase_id',$id)->get()->map(fn($i) => ['product_id'=>$i->product_id,'name'=>$i->name,'qty'=>(int)$i->qty,'cost'=>(float)$i->cost,'batch'=>$i->batch,'returned'=>(int)($ret[$i->product_id] ?? 0)])->all());
  }
}
