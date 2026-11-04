<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Procurement engine: supplier ledger, payments, stock, links, SMS and the audit trail. One place so every screen agrees on the numbers. */
class Procure {
  const BAD = ['draft','pending','cancelled'];            // purchase statuses that do not touch the ledger or stock
  const METHODS = ['Cash','Bank Transfer','bKash','Nagad','Cheque','Other'];
  const REASONS = ['Broken','Expired','Leakage','Packaging Damage','Transit Damage','Supplier Damage','Warehouse Damage','Customer Return Damage','Other'];
  const TPL = [
    'purchase' => 'প্রিয় {supplier_name}, Purchase Invoice {invoice_no} তৈরি হয়েছে। Total: ৳{total}. Paid: ৳{paid}. Due: ৳{due}. Invoice: {invoice_link}',
    'payment'  => 'প্রিয় {supplier_name}, আজ ৳{amount} পেমেন্ট করা হয়েছে। আপনার বর্তমান বকেয়া ৳{due}। Invoice/Statement: {invoice_link}',
    'due'      => 'প্রিয় {supplier_name}, আপনার বর্তমান পাওনা ৳{due}। বিস্তারিত: {statement_link}',
    'return'   => 'DutyFreeBD: {invoice_no} এর পণ্য ফেরত (৳{amount}) হিসাবে সমন্বয় করা হয়েছে। বর্তমান বকেয়া ৳{due}। Statement: {statement_link}',
    'damage'   => 'DutyFreeBD: ড্যামেজ ক্লেইম ৳{amount} আপনার হিসাবে সমন্বয় করা হয়েছে। বর্তমান বকেয়া ৳{due}। Statement: {statement_link}',
    'statement'=> 'DutyFreeBD: আপনার Statement তৈরি হয়েছে। বর্তমান পাওনা ৳{due}। দেখুন: {statement_link}',
  ];
  public static function set(string $k, string $d = ''): string { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null ? $d : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>$v,'updated_at'=>now(),'created_at'=>now()]); }
  public static function on(string $k): bool { return self::set($k, '0') === '1'; }
  public static function base(): string { $u = rtrim((string)config('app.url'), '/'); return ($u === '' || str_contains($u, 'localhost')) ? 'https://dutyfreebd.com' : $u; }
  public static function invoiceLink($p): string { return self::base().'/purchase/invoice/'.$p->invoice.'?t='.$p->token; }
  public static function statementLink($s, $from = null, $to = null): string { return self::base().'/supplier/statement/'.$s->token.($from ? '?from='.$from.'&to='.$to : ''); }
  public static function audit(string $act, string $subject, $prev = null, $new = null): void {
    try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name ?? 'system','action'=>$act,'subject'=>$subject,'meta'=>json_encode(['previous'=>$prev,'new'=>$new], JSON_UNESCAPED_UNICODE),'ip'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {}
  }
  public static function canApprove(): bool { return in_array(auth()->user()->role ?? '', ['super','admin']); }
  public static function tk($n): string { return '৳'.number_format((float)$n, 2); }

  /** Invoice money: paid = allocated approved payments. */
  public static function paidOf(int $pid): float { return (float)DB::table('payment_allocations as a')->join('supplier_payments as p','p.id','=','a.payment_id')->where('a.purchase_id',$pid)->where('p.status','paid')->sum('a.amount'); }
  public static function payStatus($p, float $paid): string {
    if (in_array($p->status, ['cancelled','draft'])) return $p->status;
    $due = (float)$p->total - $paid;
    if ($due <= 0.009 && (float)$p->total > 0) return 'paid';
    if ($due > 0 && $p->due_date && $p->due_date < now('Asia/Dhaka')->toDateString()) return 'overdue';
    return $paid > 0.009 ? 'partial' : 'due';
  }
  /** Everything a supplier balance is made of, as dated events. Positive balance = we owe the supplier. */
  public static function events(int $sid, ?string $from = null, ?string $to = null): array {
    $s = DB::table('suppliers')->find($sid); $ev = [];
    foreach (DB::table('purchases')->where('supplier_id',$sid)->whereNotIn('status', self::BAD)->get() as $p) $ev[] = ['d'=>$p->purchase_date,'o'=>1,'t'=>'Purchase','ref'=>$p->invoice,'purchase'=>(float)$p->total,'payment'=>0,'return'=>0,'damage'=>0,'debit'=>0,'credit'=>(float)$p->total,'id'=>$p->id,'kind'=>'purchase'];
    foreach (DB::table('supplier_payments')->where('supplier_id',$sid)->where('status','paid')->get() as $p) $ev[] = ['d'=>$p->pay_date,'o'=>2,'t'=>'Payment ('.$p->method.')','ref'=>$p->code,'purchase'=>0,'payment'=>(float)$p->amount,'return'=>0,'damage'=>0,'debit'=>(float)$p->amount,'credit'=>0,'id'=>$p->id,'kind'=>'payment'];
    foreach (DB::table('purchase_returns')->where('supplier_id',$sid)->get() as $p) $ev[] = ['d'=>$p->return_date,'o'=>3,'t'=>'Purchase Return','ref'=>$p->code,'purchase'=>0,'payment'=>0,'return'=>(float)$p->value,'damage'=>0,'debit'=>(float)$p->value,'credit'=>0,'id'=>$p->id,'kind'=>'return'];
    foreach (DB::table('damaged_products')->where('supplier_id',$sid)->where('responsibility','supplier')->get() as $p) $ev[] = ['d'=>$p->dmg_date ?: substr((string)$p->created_at,0,10),'o'=>4,'t'=>'Damage claim','ref'=>$p->code ?: 'DMG-'.str_pad($p->id,6,'0',STR_PAD_LEFT),'purchase'=>0,'payment'=>0,'return'=>0,'damage'=>(float)$p->value,'debit'=>(float)$p->value,'credit'=>0,'id'=>$p->id,'kind'=>'damage'];
    foreach (DB::table('supplier_notes')->where('supplier_id',$sid)->get() as $p) { $deb = $p->kind === 'debit'; $ev[] = ['d'=>$p->note_date,'o'=>5,'t'=>($deb?'Debit':'Credit').' note'.($p->reason?' — '.$p->reason:''),'ref'=>$p->code,'purchase'=>0,'payment'=>0,'return'=>0,'damage'=>0,'debit'=>$deb?(float)$p->amount:0,'credit'=>$deb?0:(float)$p->amount,'id'=>$p->id,'kind'=>'note']; }
    usort($ev, fn($a,$b) => [$a['d'],$a['o'],$a['id']] <=> [$b['d'],$b['o'],$b['id']]);
    $open = (float)($s->opening_balance ?? 0); $rows = []; $bal = $open; $pre = $open; $tot = ['purchase'=>0,'payment'=>0,'return'=>0,'damage'=>0,'debit'=>0,'credit'=>0];
    foreach ($ev as $e) {
      if ($from && $e['d'] < $from) { $pre += $e['credit'] - $e['debit']; $bal = $pre; continue; }
      if ($to && $e['d'] > $to) continue;
      $bal = ($rows ? $bal : $pre) + $e['credit'] - $e['debit']; $e['bal'] = $bal; $rows[] = $e;
      foreach ($tot as $k=>$_) $tot[$k] += $e[$k];
    }
    return ['opening'=>$from ? $pre : $open, 'rows'=>$rows, 'closing'=>$rows ? $bal : ($from ? $pre : $open), 'tot'=>$tot, 'supplier'=>$s];
  }
  public static function due(int $sid): float { return self::events($sid)['closing']; }
  /** Bulk per-supplier figures for list pages (one pass, no per-row queries). */
  public static function dueMap(): array {
    $o = DB::table('suppliers')->pluck('opening_balance','id')->map(fn($v)=>(float)$v)->all(); $m = $o;
    foreach (DB::table('purchases')->whereNotIn('status', self::BAD)->groupBy('supplier_id')->selectRaw('supplier_id, sum(total) s')->get() as $r) $m[$r->supplier_id] = ($m[$r->supplier_id] ?? 0) + $r->s;
    foreach (DB::table('supplier_payments')->where('status','paid')->groupBy('supplier_id')->selectRaw('supplier_id, sum(amount) s')->get() as $r) $m[$r->supplier_id] = ($m[$r->supplier_id] ?? 0) - $r->s;
    foreach (DB::table('purchase_returns')->groupBy('supplier_id')->selectRaw('supplier_id, sum(value) s')->get() as $r) $m[$r->supplier_id] = ($m[$r->supplier_id] ?? 0) - $r->s;
    foreach (DB::table('damaged_products')->where('responsibility','supplier')->whereNotNull('supplier_id')->groupBy('supplier_id')->selectRaw('supplier_id, sum(value) s')->get() as $r) $m[$r->supplier_id] = ($m[$r->supplier_id] ?? 0) - $r->s;
    foreach (DB::table('supplier_notes')->groupBy('supplier_id','kind')->selectRaw('supplier_id, kind, sum(amount) s')->get() as $r) $m[$r->supplier_id] = ($m[$r->supplier_id] ?? 0) + ($r->kind === 'debit' ? -$r->s : $r->s);
    return $m;
  }

  /** Stock in/out with an inventory ledger line (same shape the old module wrote). */
  public static function stock(int $pid, int $delta, string $source, string $ref): void {
    $p = DB::table('products')->where('id',$pid)->lockForUpdate()->first(); if (!$p || $delta === 0) return;
    DB::table('products')->where('id',$pid)->update(['stock'=>$p->stock + $delta]);
    DB::table('stock_logs')->insert(['product_id'=>$pid,'source'=>$source,'reference'=>$ref,'before_qty'=>$p->stock,'change_qty'=>$delta,'after_qty'=>$p->stock+$delta,'user_id'=>auth()->id(),'user_name'=>auth()->user()->name ?? 'system','created_at'=>now(),'updated_at'=>now()]);
  }
  public static function applyStock(int $purchaseId): void {
    $p = DB::table('purchases')->where('id',$purchaseId)->lockForUpdate()->first(); if (!$p || $p->stock_applied) return;
    foreach (DB::table('purchase_items')->where('purchase_id',$purchaseId)->get() as $i) if ($i->product_id) { $in = (int)$i->qty - (int)$i->damage_qty; $was = DB::table('products')->where('id',$i->product_id)->first();
      self::stock($i->product_id, $in, 'purchase', $p->invoice);
      if ((float)$i->cost > 0) DB::table('products')->where('id',$i->product_id)->update(['cost'=>$i->cost, 'avg_cost'=>ProfitFloor::blend($was, $in, (float)$i->cost)]); }
    DB::table('purchases')->where('id',$purchaseId)->update(['stock_applied'=>true]);
  }
  public static function reverseStock(int $purchaseId): void {
    $p = DB::table('purchases')->where('id',$purchaseId)->lockForUpdate()->first(); if (!$p || !$p->stock_applied) return;
    foreach (DB::table('purchase_items')->where('purchase_id',$purchaseId)->get() as $i) if ($i->product_id) { $out = (int)$i->qty - (int)$i->damage_qty; $was = DB::table('products')->where('id',$i->product_id)->first();
      self::stock($i->product_id, -$out, 'purchase-reversal', $p->invoice);
      if ($was && (float)$i->cost > 0) DB::table('products')->where('id',$i->product_id)->update(['avg_cost'=>ProfitFloor::unblend($was, $out, (float)$i->cost)]); }
    DB::table('purchases')->where('id',$purchaseId)->update(['stock_applied'=>false]);
  }

  /** Save a payment, allocate it to invoices (chosen one first, then oldest due) and optionally text the supplier. */
  public static function pay(array $d, bool $sms = true): int {
    return DB::transaction(function () use ($d, $sms) {
      $limit = (float)self::set('proc_pay_approval_limit', '100000'); $needs = self::on('proc_approval') && (float)$d['amount'] >= $limit && !self::canApprove();
      $id = DB::table('supplier_payments')->insertGetId($d + ['status'=>$needs ? 'pending' : 'paid','user_id'=>auth()->id(),'user_name'=>auth()->user()->name ?? null,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('supplier_payments')->where('id',$id)->update(['code'=>'PAY-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
      if (!$needs) self::allocate($id);
      self::audit('proc.payment.add', 'payment#'.$id, null, ['amount'=>$d['amount'],'supplier'=>$d['supplier_id'],'status'=>$needs?'pending':'paid']);
      if (!$needs && $sms) self::notify('payment', $d['supplier_id'], ['amount'=>number_format($d['amount'],2),'payment_id'=>$id], $d['purchase_id'] ?? null);
      return $id;
    });
  }
  public static function allocate(int $payId): void {
    $pay = DB::table('supplier_payments')->find($payId); if (!$pay || $pay->status !== 'paid') return;
    $left = (float)$pay->amount - (float)DB::table('payment_allocations')->where('payment_id',$payId)->sum('amount');
    $order = []; if ($pay->purchase_id) $order[] = $pay->purchase_id;
    foreach (DB::table('purchases')->where('supplier_id',$pay->supplier_id)->whereNotIn('status', self::BAD)->orderBy('purchase_date')->orderBy('id')->pluck('id') as $i) if (!in_array($i,$order)) $order[] = $i;
    foreach ($order as $pid) {
      if ($left <= 0.009) break; $p = DB::table('purchases')->find($pid); if (!$p) continue;
      $due = (float)$p->total - self::paidOf($pid); if ($due <= 0.009) continue;
      $a = min($left, $due); DB::table('payment_allocations')->insert(['payment_id'=>$payId,'purchase_id'=>$pid,'amount'=>$a,'created_at'=>now(),'updated_at'=>now()]); $left -= $a;
    }
    // keep the legacy "paid" column on invoices in step for the old screens and reports
    foreach (DB::table('payment_allocations')->where('payment_id',$payId)->pluck('purchase_id') as $pid) DB::table('purchases')->where('id',$pid)->update(['paid'=>self::paidOf($pid)]);
  }

  public static function render(string $tpl, array $v): string { foreach ($v as $k=>$x) $tpl = str_replace('{'.$k.'}', (string)$x, $tpl); return $tpl; }
  /** Text a supplier. $kind: purchase|payment|due|return|damage|statement. Respects the On/Off switches unless $force. */
  public static function notify(string $kind, int $sid, array $x = [], $purchaseId = null, bool $force = false, ?string $ref = null): bool {
    if (!$force && !self::on('proc_sms_'.$kind)) return false;
    $s = DB::table('suppliers')->find($sid); if (!$s) return false; $num = Sms::number((string)($s->phone ?: $s->whatsapp)); if (!$num) return false;
    $p = $purchaseId ? DB::table('purchases')->find($purchaseId) : null; $due = self::due($sid);
    $v = ['supplier_name'=>$s->name,'due'=>number_format($due,2),'statement_link'=>self::statementLink($s),'invoice_no'=>$p->invoice ?? '','invoice_total'=>$p ? number_format($p->total,2) : '','total'=>$p ? number_format($p->total,2) : '','paid'=>$p ? number_format(self::paidOf($p->id),2) : '','invoice_link'=>$p ? self::invoiceLink($p) : self::statementLink($s),'date'=>now('Asia/Dhaka')->format('d M Y')] + $x;
    $body = self::render(self::set('tpl_'.$kind, self::TPL[$kind]) ?: self::TPL[$kind], $v);
    if ($ref && DB::table('sms_logs')->where('ref',$ref)->exists()) return false;
    Sms::queue($num, $body, 'supplier_'.$kind, ['type'=>'transactional','customer_name'=>$s->name,'ref'=>$ref ?: 'sup:'.$sid.':'.($p->invoice ?? '-').':'.($x['amount'] ?? $v['invoice_total'] ?? '')]);
    return true;
  }
  /** Worker hook: due-date reminders (offsets in days from the invoice due date, e.g. -3,0,3). Once per invoice and offset. */
  public static function tick(): void {
    if (!self::on('proc_sms_due')) return; $today = now('Asia/Dhaka')->toDateString();
    if (self::set('proc_due_last') === $today) return; self::put('proc_due_last', $today);
    $offs = array_values(array_filter(array_map('trim', explode(',', self::set('proc_due_offsets', '-3,0,3'))), fn($x)=>is_numeric($x)));
    foreach (DB::table('purchases')->whereNotIn('status', self::BAD)->whereNotNull('due_date')->get() as $p) {
      if ((float)$p->total - self::paidOf($p->id) <= 0.009) continue;
      foreach ($offs as $o) if (\Carbon\Carbon::parse($p->due_date)->addDays((int)$o)->toDateString() === $today) self::notify('due', $p->supplier_id, [], $p->id, false, 'supdue:'.$p->id.':'.(int)$o);
    }
  }
  public static function code(string $prefix, int $id): string { return $prefix.str_pad($id, 6, '0', STR_PAD_LEFT); }
}
