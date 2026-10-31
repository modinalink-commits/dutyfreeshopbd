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
  public function logout(Request $r) { Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/admin/login'); }
  public function dashboard(Request $r) {
    $tz = 'Asia/Dhaka'; $now = now($tz); $A = \App\Support\Analytics::class;
    $range = in_array($r->query('range'), ['today', '7', '30'], true) ? $r->query('range') : 'today';
    $days = $range === 'today' ? 1 : (int)$range;
    $f = $A::filters(['from'=>$now->copy()->subDays($days-1)->format('Y-m-d'), 'to'=>$now->format('Y-m-d')]);
    if ($range === 'today') { $pd = $now->copy()->subWeek()->format('Y-m-d'); $fp = $A::filters(['from'=>$pd, 'to'=>$pd]); $prev = 'last '.$now->format('l'); }
    else { $fp = $A::filters(['from'=>$now->copy()->subDays(2*$days-1)->format('Y-m-d'), 'to'=>$now->copy()->subDays($days)->format('Y-m-d')]); $prev = 'previous '.$days.' days'; }
    $label = $range === 'today' ? 'today' : 'last '.$days.' days';
    $safe = function (callable $fn, $def = []) { try { return $fn(); } catch (\Throwable $e) { \Log::warning('dashboard: '.$e->getMessage()); return $def; } };
    $x = $safe(fn() => $A::dashboard($f)); $xp = $safe(fn() => $A::dashboard($fp));
    $n = fn($a, $k) => (float)($a[$k] ?? 0);
    $pct = fn($a, $b) => $b > 0 ? (($a-$b) >= 0 ? '+' : '').round(($a-$b)/$b*100).'% vs '.$prev : 'No data for '.$prev;
    $sales = $n($x, 'sales'); $ord = (int)$n($x, 'orders'); $profit = $sales - $n($x, 'cost') - $n($x, 'cfee');
    $del = (int)$n($x, 'delivered'); $retn = (int)$n($x, 'returned'); $fin = $del + $retn;
    $newQ = DB::table('orders')->where('status','New'); $newN = (clone $newQ)->count(); $oldest = (clone $newQ)->min('created_at');
    $ready = DB::table('orders')->whereIn('status',['Packaging','WFP'])->count();
    $kpis = [
      ['Sales '.$label, '৳'.number_format($sales), $pct($sales, $n($xp, 'sales')), '#FF5A00'],
      ['Orders '.$label, number_format($ord), $pct($ord, $n($xp, 'orders')), '#FF5A00'],
      ['Average order value', '৳'.number_format($ord ? $sales/$ord : 0), $ord.' orders', '#6B7280'],
      ['Gross profit', '৳'.number_format($profit), $n($x, 'cost') > 0 ? round($sales > 0 ? $profit/$sales*100 : 0, 1).'% margin' : 'Set product cost prices', $n($x, 'cost') > 0 ? '#166534' : '#C2410C'],
      ['Delivery success', $fin ? round($del/$fin*100, 1).'%' : '—', $del.' delivered of '.$fin.' finished', '#166534'],
      ['Return rate', $fin ? round($retn/$fin*100, 1).'%' : '0%', $retn.' returned · '.(int)$n($x, 'cancelled').' cancelled', '#A3281E'],
      ['Awaiting confirmation', $newN, $oldest ? 'Oldest waiting '.\Carbon\Carbon::parse($oldest)->diffForHumans(null, true) : 'Nothing waiting', '#C2410C'],
      ['Ready to ship', $ready, 'Packaging + WFP', '#6B7280'],
    ];
    // sales trend: at least the last 7 days, filled with zeros for quiet days
    $N = max(7, $days); $tf = $A::filters(['from'=>$now->copy()->subDays($N-1)->format('Y-m-d'), 'to'=>$now->format('Y-m-d')]);
    $tr = collect($safe(fn() => $A::trend($tf)))->keyBy(fn($r) => substr($r['date'], 0, 10)); $trend = [];
    for ($i = $N-1; $i >= 0; $i--) { $d = $now->copy()->subDays($i); $k = $d->format('Y-m-d'); $trend[] = ['d'=>$d->format('j M'), 'sales'=>(float)($tr[$k]['sales'] ?? 0), 'orders'=>(int)($tr[$k]['orders'] ?? 0), 'delivered'=>(int)($tr[$k]['delivered'] ?? 0)]; }
    $stores = collect($safe(fn() => $A::byStore($f)))->map(fn($r) => [$r['store'], (float)$r['sales']]);
    $max = max(1, $stores->max(fn($r) => $r[1]) ?? 1);
    $topProducts = collect($safe(fn() => $A::rows('product-sales', $f)['rows']))->sortByDesc(fn($r) => (float)$r['gross'])->take(5)->map(fn($r) => [$r['product'], (int)$r['qty'], (float)$r['gross']])->values();
    $cats = collect($safe(fn() => $A::categorySalesChart($f)))->sortByDesc(fn($r) => (float)$r['value'])->take(6)->map(fn($r) => [$r['label'], (float)$r['value']])->values();
    $couriers = collect($safe(fn() => $A::courierChart($f)))->take(5)->map(fn($r) => [$r['courier'], (int)$r['parcels'], (float)$r['success']])->values();
    $chatUnread = \Illuminate\Support\Facades\Schema::hasTable('chat_threads') ? (int)DB::table('chat_threads')->sum('unread') : 0;
    $counts = DB::table('orders')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status');
    $orders = DB::table('orders')->leftJoin('stores','stores.id','=','orders.store_id')->select('orders.*','stores.name as store')->orderByDesc('orders.id')->limit(8)->get();
    $bad = DB::table('orders')->whereIn('status',['Cancel','Returned'])->whereIn('phone',$orders->pluck('phone'))->select('phone',DB::raw('count(*) c'))->groupBy('phone')->pluck('c','phone');
    $alerts = [];
    $late = $counts['Late Delivery'] ?? 0; $rp = $counts['Return Pending'] ?? 0;
    $stale = DB::table('orders')->where('status','New')->where('created_at','<',now()->subMinutes(30))->count();
    if ($late) $alerts[] = ['CRITICAL', "$late orders are Late Delivery", 'Follow up with the courier today'];
    if ($rp) $alerts[] = ['CRITICAL', "$rp orders are Return Pending", 'Receive the parcel and update stock'];
    if ($stale) $alerts[] = ['WARNING', "$stale new orders waiting over 30 min", 'Confirm or call the customer'];
    $low = DB::table('products')->where('published',true)->where('stock','<=',2)->count();
    if ($low) $alerts[] = ['WARNING', "$low published products low on stock", '2 or fewer left'];
    $noimg = DB::table('products')->where('published',true)->where(fn($w)=>$w->whereNull('image')->orWhere('image',''))->count();
    if ($noimg) $alerts[] = ['WARNING', "$noimg published products have a Missing Product Image", 'Open Products and filter by missing image: /admin/products?missing_image=1'];
    $bills = DB::table('bill_statements')->where('status','unpaid')->count();
    if ($bills) $alerts[] = ['INFO', "$bills unpaid bills", 'See Accounts, Bill statements'];
    $wd = DB::table('withdraw_requests')->where('status','pending')->count();
    if ($wd) $alerts[] = ['INFO', "$wd withdraw requests pending", 'See Payments'];
    $t = microtime(true); DB::select('select 1'); $dbms = round((microtime(true)-$t)*1000, 1);
    $failed = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at','>=',now()->subHour())->count() : 0;
    $health = [['Database response', $dbms.' ms'], ['Failed jobs, 1 h', $failed], ['Stores online', DB::table('stores')->where('active',true)->count().' of '.DB::table('stores')->count()], ['Server time', $now->format('H:i')]];
    return view('dashboard', compact('now','kpis','stores','max','counts','orders','bad','alerts','health','range','label','trend','topProducts','cats','couriers','chatUnread'));
  }
  public function orders(Request $r) { \App\Support\CourierSync::auto();
    $q = DB::table('orders')->orderByDesc('id');
    if ($r->filled('status')) $q->where('status', $r->status);
    if ($r->filled('q')) $q->where(fn($w) => $w->where('invoice','like','%'.$r->q.'%')->orWhere('phone','like','%'.$r->q.'%')->orWhere('customer_name','ilike','%'.$r->q.'%'));
    $per = in_array((int)$r->input('per'), [25,50,100,200]) ? (int)$r->input('per') : 50;
    $orders = $q->paginate($per)->withQueryString();
    $items = DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->whereIn('i.order_id', collect($orders->items())->pluck('id'))->select('i.*', DB::raw('coalesce(p.image,(select pp.image from products pp where pp.name = i.name limit 1)) as image'))->get()->groupBy('order_id');
    $counts = DB::table('orders')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status');
    $intel = \App\Support\CustomerIntel::forOrders($orders->items());
    return view('orders', compact('orders','counts','items','per','intel'));
  }
  public function orderBulk(Request $r) {
    $d = $r->validate(['ids'=>'required|array|max:500','ids.*'=>'integer','status'=>'required|in:'.implode(',',Mother::STAGES)]);
    $n = 0;
    DB::transaction(function () use ($d, &$n) {
      foreach (DB::table('orders')->whereIn('id',$d['ids'])->lockForUpdate()->get() as $o) {
        if ($o->status === $d['status']) continue;
        DB::table('orders')->where('id',$o->id)->update(['status'=>$d['status'],'updated_at'=>now()]);
        DB::table('order_status_logs')->insert(['order_id'=>$o->id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$d['status'],'note'=>'Bulk action','created_at'=>now(),'updated_at'=>now()]);
        $n++;
      }
    });
    return back()->with('ok', "$n orders moved to {$d['status']}");
  }
  public function orderNew() { return view('order_new'); }
  /** Create Order: find a saved customer by mobile number and return what we know about them. */
  public function orderLookup(Request $r) {
    $p = \App\Support\Chat::phone($r->query('phone')); if (!$p || strlen($p) < 10) return response()->json(['found'=>false]);
    $alt = [$p, '+88'.$p, '88'.$p];
    $c = DB::table('customers')->whereIn('phone', $alt)->orderBy('id')->first();
    $last = DB::table('orders')->whereIn('phone', $alt)->orderByDesc('id')->first(['id','invoice','phone','customer_name','address','district','status','total','created_at']);
    if (!$c && !$last) return response()->json(['found'=>false, 'phone'=>$p]);
    $i = $last ? (\App\Support\CustomerIntel::forOrders([(object)['id'=>$last->id, 'phone'=>$last->phone ?? $p, 'total'=>0]])[$last->id] ?? null) : null;
    $cnt = DB::table('orders')->whereIn('phone', $alt)->count();
    return response()->json(['found'=>true, 'phone'=>$p, 'name'=>$c->name ?? $last->customer_name ?? '', 'address'=>(($c->address ?? '') !== '' ? $c->address : ($last->address ?? '')), 'district'=>$last->district ?? '',
      'email'=>$c->email ?? '', 'customer_id'=>$c->id ?? null, 'blocked'=>(bool)($c->blocked ?? false), 'orders'=>$cnt + (int)($c->legacy_orders ?? 0), 'cat'=>$i['cat'] ?? 'New', 'risk'=>$i['risk'] ?? 'Low',
      'delivered'=>$i['delivered'] ?? 0, 'returned'=>$i['returned'] ?? 0, 'last'=>$last ? ['id'=>$last->id, 'invoice'=>$last->invoice, 'status'=>$last->status, 'total'=>(float)$last->total] : null]);
  }
  public function orderStore(Request $r) {
    $d = $r->validate(['customer_name'=>'required|string|max:120','phone'=>'required|string|max:20','address'=>'nullable|string','district'=>'nullable|string|max:60','source'=>'nullable|string|max:40','total'=>'required|numeric|min:0','shipping'=>'nullable|numeric|min:0','note'=>'nullable|string','item_name'=>'required|string|max:200','qty'=>'required|integer|min:1']);
    $d['phone'] = \App\Support\Chat::phone($d['phone']) ?: $d['phone'];
    $cu = DB::table('customers')->where('phone', $d['phone'])->orderBy('id')->first();
    if (!$cu) $cid = DB::table('customers')->insertGetId(['name'=>$d['customer_name'], 'phone'=>$d['phone'], 'address'=>$d['address'] ?? null, 'created_at'=>now(), 'updated_at'=>now()]);
    else { $cid = $cu->id; if (trim((string)($cu->address ?? '')) === '' && !empty($d['address'])) DB::table('customers')->where('id', $cid)->update(['address'=>$d['address'], 'updated_at'=>now()]); }
    $id = DB::transaction(function () use ($d, $cid) {
      $id = DB::table('orders')->insertGetId(['invoice'=>'TMP'.uniqid(),'store_id'=>1,'customer_name'=>$d['customer_name'],'phone'=>$d['phone'],'customer_id'=>$cid,'address'=>$d['address']??null,'district'=>$d['district']??null,'source'=>$d['source']??'Web Site','shipping'=>$d['shipping']??0,'total'=>$d['total'],'status'=>'New','note'=>$d['note']??null,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('orders')->where('id',$id)->update(['invoice'=>'INV-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
      DB::table('order_items')->insert(['order_id'=>$id,'name'=>$d['item_name'],'qty'=>$d['qty'],'price'=>$d['total'],'created_at'=>now(),'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>null,'to_status'=>'New','note'=>'Order created','created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    return redirect("/admin/orders/$id");
  }
  public function orderShow($id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    $items = DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->where('i.order_id',$id)->select('i.*','p.sku as sku', DB::raw('coalesce(p.image,(select pp.image from products pp where pp.name = i.name limit 1)) as image'))->get();
    $logs = DB::table('order_status_logs')->where('order_id',$id)->orderBy('id')->get();
    $ci = \App\Support\CustomerIntel::forOrders([$o])[$o->id];
    return view('order_show', compact('o','items','logs','ci'));
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
  public function staffLog(Request $r) {
    $d0 = now('Asia/Dhaka')->startOfDay()->utc();
    $logs = DB::table('order_status_logs as l')->join('orders as o','o.id','=','l.order_id')->select('l.*','o.invoice')->when($r->filled('staff'), fn($q) => $q->where('l.user_name',$r->staff))->orderByDesc('l.id')->paginate(50)->withQueryString();
    $today = DB::table('order_status_logs')->where('created_at','>=',$d0);
    $conf = DB::selectOne("select avg(extract(epoch from (l.created_at - o.created_at))/60) m from order_status_logs l join orders o on o.id=l.order_id where l.to_status='Approved' and l.created_at >= ?", [$d0]);
    $stuck = DB::table('orders')->whereIn('status',['New','Pending'])->where('updated_at','<',now()->subMinutes(30))->count();
    $users = DB::table('users')->where('active',true)->get();
    $kpi = [['Staff active today', (clone $today)->whereNotNull('user_id')->distinct()->count('user_id'), 'of '.$users->count().' staff'], ['Orders handled today', (clone $today)->distinct()->count('order_id'), 'by staff'], ['Average time to confirm', $conf && $conf->m !== null ? round($conf->m).' min' : '—', 'New → Approved, today'], ['Orders stuck over 30 min', $stuck, 'New or Pending'], ['Actions logged today', (clone $today)->count() + DB::table('audit_logs')->where('created_at','>=',$d0)->count(), 'all recorded']];
    $live = [];
    foreach ($users as $u) {
      $last = DB::table('order_status_logs as l')->join('orders as o','o.id','=','l.order_id')->where('l.user_id',$u->id)->select('l.*','o.invoice')->orderByDesc('l.id')->first();
      $cnt = (clone $today)->where('user_id',$u->id)->distinct()->count('order_id');
      $mins = $last ? now()->diffInMinutes($last->created_at, true) : null;
      $live[] = ['name'=>$u->name, 'role'=>$u->role, 'now'=>$last ? $last->invoice.' · '.$last->to_status : '—', 'since'=>$last ? \Carbon\Carbon::parse($last->created_at)->timezone('Asia/Dhaka')->format('H:i') : '—', 'today'=>$cnt, 'status'=>$mins === null ? 'Offline' : ($mins <= 15 ? 'Active' : ($mins <= 120 ? 'Idle' : 'Offline'))];
    }
    $perf = DB::select("select l.user_name, count(distinct l.order_id) n, avg(case when l.to_status='Approved' then extract(epoch from (l.created_at - o.created_at))/60 end) conf, sum(case when l.to_status='Cancel' then 1 else 0 end) cancels, sum(case when l.to_status in ('Returned','Return Pending') then 1 else 0 end) rets from order_status_logs l join orders o on o.id=l.order_id where l.created_at >= ? and l.user_name is not null group by l.user_name order by n desc", [$d0]);
    $stages = DB::select("select from_status, to_status, avg(mins) av, count(*) n, max(mins) mx from (select l.from_status, l.to_status, extract(epoch from (l.created_at - coalesce(lag(l.created_at) over (partition by l.order_id order by l.id), o.created_at)))/60 mins from order_status_logs l join orders o on o.id=l.order_id where l.from_status is not null) t group by from_status, to_status order by n desc limit 20");
    $staffNames = DB::table('order_status_logs')->whereNotNull('user_name')->distinct()->pluck('user_name');
    return view('stafflog', compact('logs','kpi','live','perf','stages','staffNames'));
  }
}
