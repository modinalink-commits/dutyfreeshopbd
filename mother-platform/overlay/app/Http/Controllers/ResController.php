<?php
namespace App\Http\Controllers;
use App\Models\Res;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class ResController extends Controller {
  private array $memo = [];
  private function cfg($slug) {
    $c = Res::all()[$slug] ?? abort(404);
    if (!empty($c['admin']) && !in_array(Auth::user()->role, ['super','admin'])) abort(403);
    return $c + ['slug'=>$slug];
  }
  private function lookups($c) {
    $m = [];
    foreach ($c['fields'] as $f) if ($f['t']==='fk') { [$t,$l] = $f['fk']; $m[$f['n']] = DB::table($t)->orderBy($l)->limit(1500)->pluck($l,'id')->all(); }
    return $m;
  }
  private function show($c, $row, $f, $lk) {
    $v = $row->{$f['n']} ?? null;
    if ($f['t']==='fk') { if ($v===null) return ''; if (isset($lk[$f['n']][$v])) return $lk[$f['n']][$v]; $k = $f['fk'][0].':'.$v; return $this->memo[$k] ??= (DB::table($f['fk'][0])->where('id',$v)->value($f['fk'][1]) ?? "#$v"); }
    if ($f['t']==='bool') return $v ? 'Yes' : 'No';
    if ($f['t']==='select') return $f['o'][$v] ?? $v;
    if ($f['t']==='perm') { $a = json_decode($v ?? '[]', true); return is_array($a) ? implode(', ', $a) : (string)$v; }
    if ($f['t']==='json' && $v !== null && !is_string($v)) return json_encode($v);
    return $v === null ? '' : (string)$v;
  }
  private function query($c, Request $r) {
    $q = DB::table($c['table']);
    if ($r->filled('q') && !empty($c['search'])) $q->where(function ($w) use ($c, $r) { foreach ($c['search'] as $col) $w->orWhere($col, 'ilike', '%'.$r->q.'%'); });
    foreach ($c['filters'] ?? [] as $fn) if ($r->filled($fn)) $q->where($fn, $r->input($fn));
    return $q->orderByDesc($c['order'] ?? 'id')->orderByDesc('id');
  }
  private function audit($act, $subject, $meta = []) {
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$act,'subject'=>$subject,'meta'=>json_encode($meta),'ip'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]);
  }
  private function summary($c) {
    $k = $c['summary'] ?? null; $o = [];
    if ($k==='accounts') {
      foreach (DB::table('money_accounts')->get() as $a) {
        $in = DB::table('account_entries')->where('account_id',$a->id)->where('direction','income')->sum('amount');
        $ex = DB::table('account_entries')->where('account_id',$a->id)->where('direction','expense')->sum('amount');
        $o[] = [$a->name.' balance', '৳'.number_format($a->opening + $in - $ex)];
      }
    } elseif ($k==='entries') {
      $m0 = now('Asia/Dhaka')->startOfMonth()->toDateString();
      $in = DB::table('account_entries')->where('entry_date','>=',$m0)->where('direction','income')->sum('amount');
      $opex = DB::table('account_entries as e')->join('expense_purposes as p','p.id','=','e.purpose_id')->where('e.entry_date','>=',$m0)->where('e.direction','expense')->where('p.is_expense',true)->sum('e.amount');
      $moves = DB::table('account_entries')->where('entry_date','>=',$m0)->where('direction','expense')->sum('amount') - $opex;
      $o = [['Income collected','৳'.number_format($in)],['Operating expenses','৳'.number_format($opex)],['Other money out (transfers, supplier bills)','৳'.number_format($moves)],['Net (income − operating expenses)','৳'.number_format($in-$opex)]];
    } elseif ($k==='purchase') {
      $m0 = now('Asia/Dhaka')->startOfMonth()->toDateString();
      $due = DB::table('purchases')->select('supplier_id', DB::raw('sum(total-paid) d'))->groupBy('supplier_id')->havingRaw('sum(total-paid) > 0')->get();
      $loss = DB::table('damaged_products as d')->join('products as p','p.id','=','d.product_id')->where('d.created_at','>=',$m0)->sum(DB::raw('d.qty*p.cost'));
      $o = [['Suppliers', DB::table('suppliers')->where('active',true)->count().' active'], ['Purchases (month)', '৳'.number_format(DB::table('purchases')->where('purchase_date','>=',$m0)->sum('total')).' · '.DB::table('purchases')->where('purchase_date','>=',$m0)->count().' invoices'], ['Supplier due', '৳'.number_format($due->sum('d')).' · '.$due->count().' suppliers'], ['Damage loss (month)', '৳'.number_format($loss).' · '.DB::table('damaged_products')->where('created_at','>=',$m0)->sum('qty').' items']];
    } elseif ($k==='hr') {
      $m0 = now('Asia/Dhaka')->startOfMonth(); $mon = $m0->format('Y-m');
      $o = [['Employees', DB::table('employees')->where('active',true)->count().' · '.DB::table('employees')->where('joined_at','>=',$m0->toDateString())->count().' new this month'], ['Departments', DB::table('employees')->where('active',true)->whereNotNull('department')->distinct()->count('department')], ['Monthly payroll', '৳'.number_format(DB::table('employees')->where('active',true)->sum('salary')).' gross'], [$m0->format('F').' payroll', DB::table('payroll_entries')->where('month',$mon)->where('status','paid')->count().' paid of '.DB::table('payroll_entries')->where('month',$mon)->count()]];
    } elseif ($k==='marketing') {
      $w = now()->subDays(7);
      $o = [['Orders, last 7 days', DB::table('orders')->where('created_at','>=',$w)->count()], ['From Facebook / Messenger', DB::table('orders')->where('created_at','>=',$w)->whereIn('source',['FB','messenger','Landing Page'])->count()], ['From WhatsApp', DB::table('orders')->where('created_at','>=',$w)->where('source','Whatsapp')->count()], ['From website', DB::table('orders')->where('created_at','>=',$w)->where('source','Web Site')->count()], ['Campaigns', DB::table('campaigns')->count().' · '.DB::table('campaigns')->where('status','scheduled')->count().' scheduled']];
    } elseif ($k==='inventory') {
      $open = ['New','Incomplete','Pre Order','Assign','Pending','AI Order','Approved','Scheduled','WFP','Packaging'];
      $oh = DB::table('products')->sum('stock'); $rs = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->whereIn('o.status',$open)->sum('i.qty');
      $o = [['On hand', number_format($oh)], ['Reserved', number_format($rs)], ['Available', number_format(max(0,$oh-$rs))], ['In transit', number_format(DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.status','Shipment')->sum('i.qty'))], ['Returned, to inspect', number_format(DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.status','Return Pending')->sum('i.qty'))], ['Damaged', number_format(DB::table('damaged_products')->sum('qty'))]];
    } elseif ($k==='reviews') {
      $n = DB::table('reviews')->where('status','published')->count(); $avg = DB::table('reviews')->where('status','published')->avg('rating');
      $o = [['Published reviews',$n],['Real average',$n ? number_format($avg,1) : 'New'],['Verified buyers',DB::table('reviews')->where('verified_buyer',true)->count()]];
    }
    elseif ($k==='customers') {
      $tot = DB::table('customers')->count();
      $rep = DB::table('orders')->select('phone')->groupBy('phone')->havingRaw('count(*) >= 2')->get()->count();
      $buyers = DB::table('orders')->distinct()->count('phone');
      $o = [['Customers', number_format($tot)], ['With email', number_format(DB::table('customers')->whereNotNull('email')->count())], ['New this week', DB::table('customers')->where('created_at','>=',now()->subWeek())->count()], ['Repeat buyers', $buyers ? round($rep/$buyers*100).'%' : '0%'], ['Open messages', DB::table('contact_messages')->where('status','new')->count()]];
    } elseif ($k==='couriers') {
      $def = DB::table('couriers')->where('is_default',true)->value('name') ?? 'None';
      $act = DB::table('couriers')->where('active',true)->count(); $all = DB::table('couriers')->count();
      $o = [['Active courier', $def.' (default)'], ['Couriers set up', $all.' ('.$act.' active, '.($all-$act).' off)'], ['Districts', DB::table('districts')->count()], ['Parcels today', DB::table('orders')->where('status','Shipment')->where('updated_at','>=',now('Asia/Dhaka')->startOfDay()->utc())->count()], ['Failed or returned', DB::table('orders')->whereIn('status',['Returned','Return Pending','Late Delivery'])->where('updated_at','>=',now()->subWeek())->count().' this week']];
    }
    return $o;
  }
  public function extras($c, $rows) {
    if ($c['slug'] === 'accounts') {
      $d0 = now('Asia/Dhaka')->toDateString(); $out = [];
      foreach ($rows->items() as $a) {
        $in = DB::table('account_entries')->where('account_id',$a->id)->where('direction','income'); $ex = DB::table('account_entries')->where('account_id',$a->id)->where('direction','expense');
        $out[$a->id] = ['bal'=>'৳'.number_format($a->opening + (clone $in)->sum('amount') - (clone $ex)->sum('amount')), 'in'=>'৳'.number_format((clone $in)->where('entry_date',$d0)->sum('amount')), 'out'=>'৳'.number_format((clone $ex)->where('entry_date',$d0)->sum('amount'))];
      }
      return $out;
    }
    if ($c['slug'] === 'suppliers') {
      $ids = collect($rows->items())->pluck('id'); $out = [];
      $p = DB::table('purchases')->whereIn('supplier_id',$ids)->select('supplier_id', DB::raw('sum(total) t'), DB::raw('sum(paid) p'))->groupBy('supplier_id')->get()->keyBy('supplier_id');
      $it = DB::table('purchase_items as i')->join('purchases as pu','pu.id','=','i.purchase_id')->whereIn('pu.supplier_id',$ids)->select('pu.supplier_id', DB::raw('count(distinct coalesce(i.product_id::text, i.name)) n'))->groupBy('pu.supplier_id')->pluck('n','supplier_id');
      foreach ($ids as $id) { $t = (float)($p[$id]->t ?? 0); $pd = (float)($p[$id]->p ?? 0); $out[$id] = ['items'=>$it[$id] ?? 0, 'bought'=>'৳'.number_format($t), 'paid'=>'৳'.number_format($pd), 'due'=>'৳'.number_format($t-$pd)]; }
      return $out;
    }
    if ($c['slug'] === 'purchases') {
      $out = []; $n = DB::table('purchase_items')->whereIn('purchase_id', collect($rows->items())->pluck('id'))->select('purchase_id', DB::raw('count(*) n'))->groupBy('purchase_id')->pluck('n','purchase_id');
      foreach ($rows->items() as $r) $out[$r->id] = ['items'=>$n[$r->id] ?? 0, 'state'=>$r->paid >= $r->total && $r->total > 0 ? 'Paid' : ($r->paid > 0 ? 'Part paid' : 'Unpaid')];
      return $out;
    }
    if ($c['slug'] !== 'customers') return [];
    $ph = collect($rows->items())->pluck('phone')->filter()->all(); if (!$ph) return [];
    $agg = DB::table('orders')->whereIn('phone',$ph)->whereNotIn('status',['Cancel'])->select('phone', DB::raw('count(*) n'), DB::raw('coalesce(sum(total),0) s'), DB::raw('max(created_at) l'))->groupBy('phone')->get()->keyBy('phone');
    $lv = DB::table('membership_levels')->where('active',true)->orderByDesc('min_spent')->get();
    $out = [];
    foreach ($rows->items() as $r) { $a = $agg[$r->phone] ?? null; $s = $a ? (float)$a->s : 0;
      $tier = $r->membership ?: (($a && $a->n > 1) ? (optional($lv->first(fn($l) => $s >= $l->min_spent))->name ?? 'Member') : ($a ? 'New' : '—'));
      $out[$r->id] = ['orders'=>($a->n ?? 0) + (int)($r->legacy_orders ?? 0), 'spent'=>'৳'.number_format($s), 'tier'=>$tier, 'last'=>$a ? \Carbon\Carbon::parse($a->l)->diffForHumans() : '—']; }
    return $out;
  }
  public function customerImport(Request $r) {
    $r->validate(['file'=>'required|file|max:25600']);
    @set_time_limit(300);
    $fh = fopen($r->file('file')->getRealPath(), 'r'); $head = fgetcsv($fh);
    if (!$head) return back()->withErrors(['file'=>'Empty file']);
    $head = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h))), $head);
    if (!in_array('phone', $head)) return back()->withErrors(['file'=>'The file needs a phone column']);
    $existing = DB::table('customers')->pluck('id','phone')->all();
    $new = 0; $upd = 0; $skip = 0; $batch = []; $now = now();
    $flush = function () use (&$batch) { if ($batch) { DB::table('customers')->insert($batch); $batch = []; } };
    while (($row = fgetcsv($fh)) !== false) {
      $x = array_combine($head, array_pad(array_slice($row, 0, count($head)), count($head), ''));
      $ph = substr(preg_replace('/\D/', '', $x['phone'] ?? ''), -11);
      if (!preg_match('/^01[3-9]\d{8}$/', $ph)) { $skip++; continue; }
      $data = ['name'=>mb_substr(trim($x['name'] ?? '') ?: $ph, 0, 120), 'address'=>mb_substr($x['address'] ?? '', 0, 1000) ?: null, 'ip'=>mb_substr($x['ip'] ?? '', 0, 45) ?: null, 'username'=>mb_substr($x['username'] ?? '', 0, 60) ?: null, 'legacy_orders'=>(int)($x['legacy_orders'] ?? 0), 'legacy_spent'=>(float)($x['legacy_spent'] ?? 0), 'note'=>mb_substr($x['note'] ?? '', 0, 1000) ?: null, 'updated_at'=>$now];
      if (isset($existing[$ph])) { DB::table('customers')->where('id',$existing[$ph])->update($data); $upd++; }
      else { $batch[] = $data + ['phone'=>$ph, 'created_at'=>$now]; $existing[$ph] = true; $new++; if (count($batch) >= 1000) $flush(); }
    }
    $flush(); fclose($fh);
    $this->audit('import', "customers new=$new updated=$upd skipped=$skip");
    return back()->with('ok', "Customers imported: $new new, $upd updated, $skip rows skipped (invalid phone).");
  }
  public function payrollGenerate() {
    $mon = now('Asia/Dhaka')->format('Y-m'); $n = 0;
    foreach (DB::table('employees')->where('active',true)->get() as $e) {
      if (DB::table('payroll_entries')->where('employee_id',$e->id)->where('month',$mon)->exists()) continue;
      DB::table('payroll_entries')->insert(['employee_id'=>$e->id,'month'=>$mon,'amount'=>$e->salary,'status'=>'unpaid','created_at'=>now(),'updated_at'=>now()]); $n++;
    }
    $this->audit('create','payroll.'.$mon.' x'.$n);
    return back()->with('ok', "$n payroll rows created for $mon");
  }
  public function districtCharge(Request $r) {
    $d = $r->validate(['charge'=>'required|numeric|min:0|max:100000']);
    DB::table('districts')->update(['delivery_charge'=>$d['charge'],'updated_at'=>now()]); $this->audit('update','districts.all_charge='.$d['charge']);
    return back()->with('ok','Charge set for all districts');
  }
  public function index($slug, Request $r) {
    $c = $this->cfg($slug); $lk = $this->lookups($c);
    $rows = $this->query($c, $r)->paginate(50)->withQueryString();
    return view('res_list', ['c'=>$c,'rows'=>$rows,'lk'=>$lk,'sum'=>$this->summary($c),'ctl'=>$this,'ex'=>$this->extras($c,$rows)]);
  }
  public function cell($c, $row, $f, $lk) { return $this->show($c, $row, $f, $lk); }
  public function form($slug, $id = null) {
    $c = $this->cfg($slug); if (!empty($c['ro'])) abort(403);
    if ($id===null && !empty($c['noCreate'])) abort(403);
    if ($id!==null && !empty($c['noEdit'])) abort(403);
    $row = $id ? DB::table($c['table'])->where('id',$id)->first() : null;
    if ($id && !$row) abort(404);
    return view('res_form', ['c'=>$c,'row'=>$row,'lk'=>$this->lookups($c)]);
  }
  public function save($slug, Request $r, $id = null) {
    $c = $this->cfg($slug); if (!empty($c['ro'])) abort(403);
    if ($id===null && !empty($c['noCreate'])) abort(403);
    if ($id!==null && !empty($c['noEdit'])) abort(403);
    $rules = []; $data = [];
    foreach ($c['fields'] as $f) {
      if (!empty($f['auto'])) continue;
      if ($id && !empty($c['editOnly']) && !in_array($f['n'], $c['editOnly'])) continue;
      if ($f['t']==='password' && $r->input($f['n'])==='' && $id) continue;
      $rule = [empty($f['req']) && !($f['t']==='password' && !$id) ? 'nullable' : 'required'];
      $rule[] = match ($f['t']) { 'number'=>'numeric', 'date'=>'date', 'datetime'=>'date', 'bool'=>'boolean', 'fk'=>'integer', 'json'=>'json', 'select'=>'in:'.implode(',', array_keys($f['o'])), default=>'string' };
      if (in_array($f['t'], ['text','textarea','password'])) $rule[] = 'max:5000';
      $rules[$f['n']] = $rule;
    }
    if ($c['slug']==='users') { $rules['email'][] = 'email'; }
    $in = $r->validate($rules);
    foreach ($c['fields'] as $f) {
      $n = $f['n']; if (!array_key_exists($n, $rules)) continue;
      $v = $in[$n] ?? null;
      if ($f['t']==='bool') $v = (bool)($r->input($n) ?? false);
      if ($f['t']==='password') $v = Hash::make($v);
      if ($f['t']==='perm') $v = json_encode(array_values(array_filter(array_map('trim', explode(',', (string)$v)))));
      if ($f['t']==='json' && ($v===null || $v==='')) $v = null;
      $data[$n] = $v;
    }
    $new = !$id; $slugName = $c['slug'];
    try {
      $id = DB::transaction(function () use ($c, $id, $data, $new, $slugName) {
        $now = now(); $t = $c['table'];
        if ($slugName==='pages' && empty($data['slug'])) $data['slug'] = \Illuminate\Support\Str::slug($data['title']) ?: 'page-'.time();
        if ($slugName==='entries' && $new) { $data['reference'] = 'TMP'.uniqid(); $data['user_id'] = Auth::id(); $data['user_name'] = Auth::user()->name; }
        if ($slugName==='purchases' && $new) { $data['invoice'] = 'TMP'.uniqid(); $data['user_id'] = Auth::id(); }
        if ($slugName==='damaged' && $new) { $data['user_id'] = Auth::id(); }
        if ($new) { $data['created_at'] = $now; $data['updated_at'] = $now; $id = DB::table($t)->insertGetId($data); }
        else { $data['updated_at'] = $now; DB::table($t)->where('id',$id)->update($data); }
        if ($slugName==='entries' && $new) DB::table($t)->where('id',$id)->update(['reference'=>($data['direction']==='income'?'INC-':'EXP-').str_pad($id,6,'0',STR_PAD_LEFT)]);
        if ($slugName==='purchases' && $new) DB::table($t)->where('id',$id)->update(['invoice'=>'PUR-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
        if ($slugName==='couriers' && !empty($data['is_default'])) DB::table($t)->where('id','!=',$id)->update(['is_default'=>false]);
        if ($slugName==='purchase-items' && $new) {
          if (!empty($data['product_id'])) $this->stock($data['product_id'], (int)$data['qty'], 'purchase', 'PUR#'.$data['purchase_id']);
          $tot = DB::table('purchase_items')->where('purchase_id',$data['purchase_id'])->selectRaw('coalesce(sum(qty*cost),0) s')->value('s');
          DB::table('purchases')->where('id',$data['purchase_id'])->update(['total'=>$tot]);
        }
        if ($slugName==='damaged' && $new) $this->stock($data['product_id'], -(int)$data['qty'], 'damaged', 'DMG#'.$id);
        if ($slugName==='points' && $new) DB::table('customers')->where('id',$data['customer_id'])->increment('points', (int)$data['points']);
        $this->audit($new?'create':'update', $slugName.'#'.$id);
        return $id;
      });
    } catch (\Illuminate\Database\QueryException $e) {
      return back()->withInput()->withErrors(['db'=>'Could not save: '.(str_contains($e->getMessage(),'unique') ? 'this value already exists' : 'check the values')]);
    }
    return redirect('/admin/m/'.$slugName)->with('ok', 'Saved');
  }
  private function stock($pid, $delta, $source, $ref) {
    $p = DB::table('products')->where('id',$pid)->lockForUpdate()->first(); if (!$p) return;
    DB::table('products')->where('id',$pid)->update(['stock'=>$p->stock + $delta]);
    DB::table('stock_logs')->insert(['product_id'=>$pid,'source'=>$source,'reference'=>$ref,'before_qty'=>$p->stock,'change_qty'=>$delta,'after_qty'=>$p->stock+$delta,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'created_at'=>now(),'updated_at'=>now()]);
  }
  public function destroy($slug, $id) {
    $c = $this->cfg($slug); if (!empty($c['ro']) || !empty($c['noCreate'])) abort(403);
    if ($slug==='users' && (int)$id === (int)Auth::id()) return back()->withErrors(['db'=>'You cannot delete your own account']);
    DB::table($c['table'])->where('id',$id)->delete(); $this->audit('delete', $slug.'#'.$id);
    return back()->with('ok','Deleted');
  }
  public function export($slug, $fmt, Request $r) {
    $c = $this->cfg($slug); $lk = $this->lookups($c);
    $fields = array_values(array_filter($c['fields'], fn($f) => ($f['t']??'') !== 'password'));
    $head = array_map(fn($f) => $f['l'], $fields);
    $gen = function () use ($c, $r, $fields, $lk) {
      foreach ($this->query($c, $r)->cursor() as $row) yield array_map(fn($f) => $this->show($c, $row, $f, $lk), $fields);
    };
    $name = $slug.'-'.date('Ymd-His'); $title = $c['title'];
    $this->audit('export', $slug.'.'.$fmt);
    if ($fmt==='csv') {
      return response()->streamDownload(function () use ($head, $gen) { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $head); foreach ($gen() as $row) fputcsv($o, $row); fclose($o); }, "$name.csv", ['Content-Type'=>'text/csv; charset=utf-8']);
    }
    $table = function () use ($head, $gen) {
      echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>'; foreach ($head as $h) echo '<th style="background:#FF5A00;color:#fff">'.e($h).'</th>'; echo '</tr></thead><tbody>';
      foreach ($gen() as $row) { echo '<tr>'; foreach ($row as $v) echo '<td>'.e($v).'</td>'; echo '</tr>'; }
      echo '</tbody></table>';
    };
    $pre = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}table{border-collapse:collapse;font-size:12px}h2{color:#0B1220}</style></head><body><h2>'.e($title).' — DutyFreeBD / Mother Commerce</h2><p>'.e(now()->format('d M Y H:i')).'</p>';
    if ($fmt==='xls') return response()->streamDownload(function () use ($pre, $table) { echo $pre; $table(); echo '</body></html>'; }, "$name.xls", ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8']);
    if ($fmt==='doc') return response()->streamDownload(function () use ($pre, $table) { echo $pre; $table(); echo '</body></html>'; }, "$name.doc", ['Content-Type'=>'application/msword; charset=utf-8']);
    if ($fmt==='pdf') return response()->stream(function () use ($pre, $table) { echo $pre; $table(); echo '<script>window.onload=function(){window.print()}</script></body></html>'; }, 200, ['Content-Type'=>'text/html; charset=utf-8']);
    abort(404);
  }
  public function reports() {
    $docs = []; foreach (Res::all() as $s => $c) if (empty($c['admin']) || in_array(Auth::user()->role,['super','admin'])) $docs[$c['group']][] = [$s, $c['title'], DB::table($c['table'])->count()];
    $byDay = DB::table('orders')->selectRaw("to_char(created_at,'YYYY-MM-DD') d, count(*) n, coalesce(sum(total),0) s")->where('created_at','>=',now()->subDays(30))->groupBy('d')->orderByDesc('d')->get();
    $byStatus = DB::table('orders')->select('status', DB::raw('count(*) n'), DB::raw('coalesce(sum(total),0) s'))->groupBy('status')->get();
    $top = DB::table('order_items')->select('name', DB::raw('sum(qty) q'), DB::raw('sum(qty*price) s'))->groupBy('name')->orderByDesc('q')->limit(10)->get();
    $dl = DB::table('audit_logs')->where('action','export')->orderByDesc('id')->limit(10)->get();
    return view('reports', compact('docs','byDay','byStatus','top','dl'));
  }
  public function details(Request $r) {
    $from = $r->date('from') ?? now('Asia/Dhaka')->startOfMonth(); $to = $r->date('to') ?? now('Asia/Dhaka');
    $f0 = $from->copy()->startOfDay(); $t1 = $to->copy()->endOfDay();
    $kp = [['Total stock', number_format(DB::table('products')->sum('stock')), 'units, all categories'], ['Stock value', '৳'.number_format(DB::table('products')->sum(DB::raw('greatest(stock,0)*cost'))), 'at cost'], ['Purchases', number_format(DB::table('purchases')->count()), 'all time'], ['Supplier due', '৳'.number_format(DB::table('purchases')->sum(DB::raw('total-paid'))), DB::table('suppliers')->count().' suppliers'], ['Categories', DB::table('categories')->whereNull('parent_id')->count(), 'top level']];
    $pur = DB::table('purchases as p')->leftJoin('suppliers as s','s.id','=','p.supplier_id')->whereBetween('p.purchase_date',[$f0->toDateString(),$t1->toDateString()])->select('p.*','s.name as sname', DB::raw('(select coalesce(sum(qty),0) from purchase_items i where i.purchase_id=p.id) as qty'))->orderBy('s.name')->orderByDesc('p.purchase_date')->limit(500)->get();
    $sup = DB::table('suppliers as s')->leftJoin('purchases as p','p.supplier_id','=','s.id')->select('s.name', DB::raw('count(p.id) n'), DB::raw('coalesce(sum(p.total),0) t'), DB::raw('coalesce(sum(p.paid),0) pd'))->groupBy('s.id','s.name')->orderByDesc('t')->limit(100)->get();
    $cat = DB::table('categories as c')->leftJoin('products as p','p.category_id','=','c.id')->whereNull('c.parent_id')->select('c.name', DB::raw('coalesce(sum(p.stock),0) st'), DB::raw('coalesce(sum(greatest(p.stock,0)*p.cost),0) amt'))->groupBy('c.id','c.name')->orderBy('c.name')->get();
    $sale = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->leftJoin('products as p','p.id','=','i.product_id')->leftJoin('categories as c','c.id','=','p.category_id')->whereBetween('o.created_at',[$f0->utc(),$t1->copy()->utc()])->whereNotIn('o.status',['Cancel','Returned'])->when($r->filled('category_id'), fn($q) => $q->where('p.category_id',(int)$r->category_id))->select('i.name', DB::raw('sum(i.qty) q'), DB::raw('sum(i.qty*i.price) v'))->groupBy('i.name')->orderBy('q', $r->input('order')==='asc' ? 'asc' : 'desc')->limit(100)->get();
    $views = DB::table('product_views')->whereNotNull('term')->select('term', DB::raw('count(*) n'), DB::raw('max(day) d'))->groupBy('term')->orderByDesc('n')->limit(20)->get();
    $days = ['today'=>0,'yesterday'=>1,'7'=>7,'30'=>30]; $d = $r->input('days','30');
    $logq = DB::table('stock_logs as l')->leftJoin('products as p','p.id','=','l.product_id')->select('l.*','p.name as pname')->orderByDesc('l.id');
    if (isset($days[$d])) $logq->where('l.created_at','>=', now('Asia/Dhaka')->startOfDay()->subDays($days[$d])->utc());
    if ($r->filled('source')) $logq->where('l.source',$r->source);
    $logs = $logq->limit(200)->get();
    $sources = DB::table('stock_logs')->distinct()->pluck('source');
    return view('report_details', compact('kp','pur','sup','cat','sale','views','logs','sources','from','to'));
  }
}
