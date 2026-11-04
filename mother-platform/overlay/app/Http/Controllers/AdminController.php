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
    $tz = 'Asia/Dhaka'; $now = now($tz); $A = \App\Support\Analytics::class; $cd = fn(array $ff) => \Illuminate\Support\Facades\Cache::remember('dash.v1.'.md5(json_encode($ff)), 45, fn() => $A::dashboard($ff));
    $c60 = fn(string $k, $a, callable $fn) => \Illuminate\Support\Facades\Cache::remember('dash.v2.'.$k.'.'.md5(json_encode($a)), 60, $fn);
    $safe = function (callable $fn, $def = []) { try { return $fn(); } catch (\Throwable $e) { \Log::warning('dashboard: '.$e->getMessage()); return $def; } };
    $range = in_array($r->query('range'), ['today', '7', '30', 'custom'], true) ? $r->query('range') : '30';
    $dOk = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r->query($k));
    if ($range === 'custom' && !($dOk('from') && $dOk('to'))) $range = '30';
    $storeId = (int)$r->query('store'); $ch = mb_substr(trim((string)$r->query('channel')), 0, 40); $sf = ($storeId ? ['stores'=>[$storeId]] : []) + ($ch !== '' ? ['source'=>$ch] : []);
    if ($range === 'custom') {
      $from = \Carbon\Carbon::parse($r->query('from'), $tz); $to = \Carbon\Carbon::parse($r->query('to'), $tz); if ($from->gt($to)) [$from, $to] = [$to, $from];
      $days = max(1, min(366, $from->diffInDays($to) + 1)); $f = $A::filters(['from'=>$from->format('Y-m-d'), 'to'=>$to->format('Y-m-d')] + $sf);
      $fp = $A::filters(['from'=>$from->copy()->subDays($days)->format('Y-m-d'), 'to'=>$from->copy()->subDay()->format('Y-m-d')] + $sf); $prev = 'previous '.$days.' days'; $label = $from->format('j M').' – '.$to->format('j M');
    } else {
      $days = $range === 'today' ? 1 : (int)$range;
      $f = $A::filters(['from'=>$now->copy()->subDays($days-1)->format('Y-m-d'), 'to'=>$now->format('Y-m-d')] + $sf);
      if ($range === 'today') { $pd = $now->copy()->subDay()->format('Y-m-d'); $fp = $A::filters(['from'=>$pd, 'to'=>$pd] + $sf); $prev = 'yesterday'; }
      else { $fp = $A::filters(['from'=>$now->copy()->subDays(2*$days-1)->format('Y-m-d'), 'to'=>$now->copy()->subDays($days)->format('Y-m-d')] + $sf); $prev = 'previous '.$days.' days'; }
      $label = $range === 'today' ? 'today' : 'last '.$days.' days';
    }
    $x = $safe(fn() => $cd($f)); $xp = $safe(fn() => $cd($fp));
    $n = fn($a, $k) => (float)($a[$k] ?? 0);
    $tren = function ($a, $b, $goodUp = true) use ($prev) { // [direction -1/0/1, text, good?]
      if ($b <= 0) return [0, $a > 0 ? 'new vs '.$prev : 'no data for '.$prev, null];
      $p = round(($a-$b)/$b*100); if ($p == 0) return [0, 'same as '.$prev, null];
      return [$p > 0 ? 1 : -1, abs($p).'% vs '.$prev, ($p > 0) === $goodUp];
    };
    $canFin = \App\Http\Middleware\ModulePermission::can(auth()->user(), 'reports', 'view');
    $sales = $n($x, 'sales'); $ord = (int)$n($x, 'orders'); $profit = $sales - $n($x, 'cost') - $n($x, 'cfee');
    $psales = $n($xp, 'sales'); $pord = (int)$n($xp, 'orders'); $pprofit = $psales - $n($xp, 'cost') - $n($xp, 'cfee');
    $del = (int)$n($x, 'delivered'); $retn = (int)$n($x, 'returned'); $fin = $del + $retn; $pdel = (int)$n($xp, 'delivered'); $pret = (int)$n($xp, 'returned'); $pfin = $pdel + $pret;
    $oq = fn() => DB::table('orders')->when($storeId, fn($q) => $q->where('store_id', $storeId))->when($ch !== '', fn($q) => $q->where('source', $ch));
    $newQ = $oq()->where('status','New'); $newN = (clone $newQ)->count(); $oldest = (clone $newQ)->min('created_at');
    $ready = $oq()->whereIn('status',['Packaging','WFP'])->count();
    $aov = $ord ? $sales/$ord : 0; $paov = $pord ? $psales/$pord : 0; $dr = $fin ? $del/$fin*100 : 0; $pdr = $pfin ? $pdel/$pfin*100 : 0; $rr = $fin ? $retn/$fin*100 : 0; $prr = $pfin ? $pret/$pfin*100 : 0;
    $incOpen = $safe(fn() => (int)DB::table('incomplete_orders')->where('status','open')->count(), 0);
    $nc = function ($a, $b) use ($oq, $tz) { $s0 = \Carbon\Carbon::parse($a, $tz)->startOfDay()->utc(); $e0 = \Carbon\Carbon::parse($b, $tz)->endOfDay()->utc();
      return (int)$oq()->where('created_at','>=',$s0)->where('created_at','<=',$e0)->where('status','<>','Cancel')->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('orders as p')->whereColumn('p.phone','orders.phone')->whereColumn('p.created_at','<','orders.created_at'))->distinct()->count('phone'); };
    $newC = $safe(fn() => $c60('nc', [$f['from'], $f['to'], $storeId, $ch], fn() => $nc($f['from'], $f['to'])), 0); $newCp = $safe(fn() => $c60('nc', [$fp['from'], $fp['to'], $storeId, $ch], fn() => $nc($fp['from'], $fp['to'])), 0);
    // sales trend: at least the last 7 days, filled with zeros for quiet days
    $N = max(7, $days); $tEnd = $range === 'custom' ? $to : $now; $tf = $A::filters(['from'=>$tEnd->copy()->subDays($N-1)->format('Y-m-d'), 'to'=>$tEnd->format('Y-m-d')] + $sf);
    $tr = collect($safe(fn() => $c60('trend', $tf, fn() => $A::trend($tf))))->keyBy(fn($r) => substr($r['date'], 0, 10)); $trend = [];
    for ($i = $N-1; $i >= 0; $i--) { $d = $tEnd->copy()->subDays($i); $k = $d->format('Y-m-d'); $trend[] = ['d'=>$d->format('j M'), 'sales'=>(float)($tr[$k]['sales'] ?? 0), 'orders'=>(int)($tr[$k]['orders'] ?? 0), 'delivered'=>(int)($tr[$k]['delivered'] ?? 0), 'returned'=>(int)($tr[$k]['returned'] ?? 0), 'profit'=>$canFin ? (float)($tr[$k]['profit'] ?? 0) : 0]; }
    $sp = fn($fn) => array_map($fn, $trend);
    $spS = $sp(fn($t) => $t['sales']); $spO = $sp(fn($t) => $t['orders']); $spA = $sp(fn($t) => $t['orders'] ? $t['sales']/$t['orders'] : 0); $spP = $canFin ? $sp(fn($t) => $t['profit']) : null;
    $spD = $sp(fn($t) => $t['delivered']); $spR = $sp(fn($t) => $t['returned']);
    $kpis = [
      ['Sales '.$label, '৳'.number_format($sales), $ord.' orders', 'sales', $tren($sales, $psales), '/admin/analytics/sales-summary', 'orange', $spS],
      ['Orders '.$label, number_format($ord), '৳'.number_format($aov).' avg', 'cart', $tren($ord, $pord), '/admin/orders', 'blue', $spO],
      ['Avg order value', '৳'.number_format($aov), 'per valid order', 'tag', $tren($aov, $paov), '/admin/analytics', 'violet', $spA],
      ['Gross profit', $canFin ? '৳'.number_format($profit) : '—', $canFin ? ($n($x, 'cost') > 0 ? round($sales > 0 ? $profit/$sales*100 : 0, 1).'% margin' : 'Set product costs') : 'Permission needed', 'coin', $tren($profit, $pprofit), '/admin/analytics/profit-loss', 'green', $spP],
      ['Delivery success', $fin ? round($dr, 1).'%' : '—', $del.' of '.$fin.' finished', 'truck', $fin ? $tren($dr, $pdr) : [0, '', null], '/admin/orders?status=Delivered', 'teal', $spD],
      ['Return rate', $fin ? round($rr, 1).'%' : '0%', $retn.' returned', 'undo', $fin ? $tren($rr, $prr, false) : [0, '', null], '/admin/orders?status=Returned', 'red', $spR],
      ['Awaiting confirmation', (string)$newN, $oldest ? 'Oldest '.\Carbon\Carbon::parse($oldest)->diffForHumans(null, true) : 'Nothing waiting', 'clock', [0, '', null], '/admin/orders?status=New', 'amber', null],
      ['Ready to ship', (string)$ready, 'Packaging + WFP', 'box', [0, '', null], '/admin/orders?status=Packaging', 'slate', null],
      ['Incomplete orders', (string)$incOpen, 'Open checkouts', 'alert', [0, '', null], '/admin/orders?status=Incomplete', 'amber', null],
      ['New customers', (string)$newC, 'first order '.$label, 'users', $tren($newC, $newCp), '/admin/m/customers', 'blue', null],
    ];
    $stores = collect($safe(fn() => $A::byStore($f)))->map(fn($r) => [$r['store'], (float)$r['sales']]);
    $max = max(1, $stores->max(fn($r) => $r[1]) ?? 1);
    $topProducts = collect($safe(fn() => $c60('prod', $f, fn() => $A::rows('product-sales', $f)['rows'])))->sortByDesc(fn($r) => (float)$r['gross'])->take(5)->map(fn($r) => [$r['product'], (int)$r['qty'], (float)$r['gross'], (string)($r['image'] ?? '')])->values();
    $catAll = collect($safe(fn() => $A::categorySalesChart($f)))->sortByDesc(fn($r) => (float)$r['value'])->values();
    $cats = $catAll->take(4)->map(fn($r) => [$r['label'], (float)$r['value']])->values();
    if ($catAll->count() > 4) $cats->push(['Others', (float)$catAll->slice(4)->sum(fn($r) => (float)$r['value'])]);
    $topCust = collect($safe(fn() => $c60('topc', $f, fn() => $A::rows('top-customers', $f)['rows'])))->sortByDesc(fn($r) => (float)$r['spend'])->take(5)->map(fn($r) => [(string)$r['name'], (string)$r['category'], (int)$r['orders'], (float)$r['spend'], (string)$r['risk'], (string)$r['phone']])->values();
    $couriers = collect($safe(fn() => $A::courierChart($f)))->take(5)->map(fn($r) => [$r['courier'], (int)$r['parcels'], (float)$r['success'], (int)$r['delivered'], (int)$r['returned']])->values();
    $sb = $safe(function () use ($oq, $f, $tz) { $s0 = \Carbon\Carbon::parse($f['from'], $tz)->startOfDay()->utc(); $e0 = \Carbon\Carbon::parse($f['to'], $tz)->endOfDay()->utc();
      return $oq()->where('created_at','>=',$s0)->where('created_at','<=',$e0)->select('store_id', DB::raw("sum(case when status in ('Delivered','Partial Delivered','Late Delivery') then 1 else 0 end) d"), DB::raw("sum(case when status in ('Returned','Return Pending') then 1 else 0 end) r"))->groupBy('store_id')->get()->keyBy('store_id'); }, collect());
    $storeNames = $safe(fn() => DB::table('stores')->pluck('name','id'), collect());
    $storePerf = collect($safe(fn() => $A::byStore($f)))->map(function ($r) use ($sb, $storeNames) { $id = $storeNames->search($r['store']); $q = $id !== false ? ($sb[$id] ?? null) : null; $fin = $q ? (int)$q->d + (int)$q->r : 0; return [$r['store'], (int)$r['orders'], (float)$r['sales'], $fin ? round($q->d / $fin * 100) : null]; })->values();
    $channels = $safe(fn() => collect(\Illuminate\Support\Facades\Cache::remember('dash.channels.v2', 600, fn() => DB::table('orders')->whereNotNull('source')->where('source','<>','')->distinct()->orderBy('source')->pluck('source')->all())), collect());
    $vipN = $safe(fn() => (int)\Illuminate\Support\Facades\Cache::remember('dash.vipn.v1', 300, fn() => DB::table('customers')->whereRaw("lower(coalesce(type,'')) = 'vip'")->count()), 0);
    $memN = $safe(fn() => (int)\Illuminate\Support\Facades\Cache::remember('dash.memn.v1', 300, fn() => DB::table('customers')->whereNotNull('membership')->where('membership','<>','')->count()), 0);
    $chatUnread = \Illuminate\Support\Facades\Schema::hasTable('chat_threads') ? (int)DB::table('chat_threads')->sum('unread') : 0;
    $counts = collect(\Illuminate\Support\Facades\Cache::remember('dash.counts.v2.'.(int)$storeId.'.'.md5((string)$ch), 20, fn() => $oq()->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status')->all()));
    $counts['Incomplete'] = $incOpen;
    $P5 = [['New', ['New'], 'New'], ['Pending', ['Pending','Assign','AI Order','Pre Order'], 'Pending'], ['Processing', ['Approved','Scheduled','WFP','Packaging'], 'Approved'], ['Shipment', ['Shipment','Late Delivery'], 'Shipment'], ['Delivered', ['Delivered','Partial Delivered'], 'Delivered']];
    $pv = $safe(function () use ($oq, $f, $tz) { $s0 = \Carbon\Carbon::parse($f['from'], $tz)->startOfDay()->utc(); $e0 = \Carbon\Carbon::parse($f['to'], $tz)->endOfDay()->utc();
      return $oq()->where('created_at','>=',$s0)->where('created_at','<=',$e0)->select('status', DB::raw('count(*) c'), DB::raw('coalesce(sum(total),0) v'))->groupBy('status')->get()->keyBy('status'); }, collect());
    $pipe5 = array_map(function ($g) use ($pv) { $c = 0; $v = 0.0; foreach ($g[1] as $st) { $c += (int)($pv[$st]->c ?? 0); $v += (float)($pv[$st]->v ?? 0); } return ['name'=>$g[0], 'count'=>$c, 'value'=>$v, 'filter'=>implode(',', $g[1])]; }, $P5);
    $pipeAct = ['count'=>array_sum(array_column(array_slice($pipe5, 0, 4), 'count')), 'value'=>array_sum(array_column(array_slice($pipe5, 0, 4), 'value'))];
    $orders = DB::table('orders')->leftJoin('stores','stores.id','=','orders.store_id')->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))->select('orders.*','stores.name as store')->orderByDesc('orders.created_at')->orderByDesc('orders.id')->limit(8)->get();
    $bad = DB::table('orders')->whereIn('status',['Cancel','Returned'])->whereIn('phone',$orders->pluck('phone'))->select('phone',DB::raw('count(*) c'))->groupBy('phone')->pluck('c','phone');
    $alerts = []; $failed = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at','>=',now()->subHour())->count() : 0;
    $late = $counts['Late Delivery'] ?? 0; $rp = $counts['Return Pending'] ?? 0;
    $stale = $oq()->where('status','New')->where('created_at','<',now()->subMinutes(30))->count();
    if ($late) $alerts[] = ['CRITICAL', "$late orders are Late Delivery", 'Follow up with the courier today', '/admin/late-delivery'];
    $invLeft = \App\Support\InvoiceNumber::remaining(); if ($invLeft !== null && $invLeft < 20000) $alerts[] = [$invLeft < 2000 ? 'CRITICAL' : 'WARNING', 'Only '.number_format(max(0, $invLeft)).' invoice numbers left (6-digit range ends at 999999)', 'Decide what happens next before the range is used up; the system will NOT switch to 7 digits on its own', '/admin/orders'];
    if ($rp) $alerts[] = ['CRITICAL', "$rp orders are Return Pending", 'Receive the parcel and update stock', '/admin/orders?status='.urlencode('Return Pending')];
    if ($stale) $alerts[] = ['WARNING', "$stale new orders waiting over 30 min", 'Confirm or call the customer', '/admin/orders?status=New'];
    $payP = $safe(fn() => (int)\Illuminate\Support\Facades\Cache::remember('dash.payp.v1', 20, fn() => DB::table('orders')->where('pay_status','Verification Pending')->count()), 0);
    if ($payP) $alerts[] = ['WARNING', "$payP payments waiting for verification", 'Check the transaction ID and mark verified', '/admin/orders'];
    if ($incOpen) $alerts[] = ['INFO', "$incOpen incomplete checkouts not processed", 'Call these customers to recover the sale', '/admin/orders?status=Incomplete'];
    $low = (int)\Illuminate\Support\Facades\Cache::remember('dash.low.v1', 120, fn() => DB::table('products')->where('published',true)->where('stock','<=',2)->count());
    if ($low) $alerts[] = ['WARNING', "$low published products low on stock", '2 or fewer left', '/admin/inventory'];
    $noimg = (int)\Illuminate\Support\Facades\Cache::remember('dash.noimg.v1', 120, fn() => DB::table('products')->where('published',true)->where(fn($w)=>$w->whereNull('image')->orWhere('image',''))->count());
    if ($noimg) $alerts[] = ['WARNING', "$noimg published products have a Missing Product Image", 'Open Products and filter by missing image', '/admin/products?missing_image=1'];
    $hr = $safe(fn() => (int)\Illuminate\Support\Facades\Cache::remember('dash.hr.v1', 300, fn() => DB::table('orders')->whereIn('status',['Cancel','Returned'])->select('phone', DB::raw('count(*) c'))->groupBy('phone')->havingRaw('count(*) >= 3')->get()->count()), 0);
    if ($hr) $alerts[] = ['WARNING', "$hr customers with 3 or more returns/cancels", 'Verify by call or take advance payment', '/admin/m/customers'];
    if ($failed ?? 0) $alerts[] = ['WARNING', $failed.' failed background jobs (last hour)', 'Check API sync and courier calls', '/admin'];
    $fd = $safe(fn() => \Illuminate\Support\Facades\Schema::hasTable('deployments') ? DB::table('deployments')->whereIn('status',['Failed','Rolled Back'])->where('finished_at','>=',now()->subDay())->latest('id')->first() : null, null);
    if ($fd) $alerts[] = ['WARNING', 'Deployment '.strtolower($fd->status).': '.\Illuminate\Support\Str::limit((string)$fd->subject, 50), 'The previous version is still live', '/admin/deploy'];
    $bills = DB::table('bill_statements')->where('status','unpaid')->count();
    if ($bills) $alerts[] = ['INFO', "$bills unpaid bills", 'See Accounts, Bill statements', '/admin/m/bills'];
    $wd = DB::table('withdraw_requests')->where('status','pending')->count();
    if ($wd) $alerts[] = ['INFO', "$wd withdraw requests pending", 'See Payments', '/admin/m/withdrawals'];
    $t = microtime(true); DB::select('select 1'); $dbms = round((microtime(true)-$t)*1000, 1);
    $health = [['Database response', $dbms.' ms'], ['Failed jobs, 1 h', $failed], ['Stores online', DB::table('stores')->where('active',true)->count().' of '.DB::table('stores')->count()], ['Server time', $now->format('H:i')]];
    // smart widgets
    $dayStart = $now->copy()->startOfDay()->utc(); $todayS = (float)$n($safe(fn() => $cd($A::filters(['from'=>$now->format('Y-m-d'), 'to'=>$now->format('Y-m-d')] + $sf))), 'sales');
    $wk = $safe(fn() => $cd($A::filters(['from'=>$now->copy()->subDays(7)->format('Y-m-d'), 'to'=>$now->copy()->subDay()->format('Y-m-d')] + $sf))); $target = max(1, $n($wk, 'sales') / 7);
    $goal = ['today'=>$todayS, 'target'=>round($target), 'pct'=>round($todayS/$target*100)];
    $fromUtc = $A::filters(['from'=>$f['from'], 'to'=>$f['to']]); $startUtc = \Carbon\Carbon::parse($f['from'], $tz)->startOfDay()->utc();
    $phones = $safe(fn() => $oq()->where('created_at','>=',$startUtc)->whereNotIn('status',['Cancel'])->distinct()->pluck('phone')->filter()->values(), collect());
    $ret = $phones->isEmpty() ? 0 : DB::table('orders')->whereIn('phone', $phones)->where('created_at','<',$startUtc)->distinct()->count('phone');
    $cov = $safe(fn() => \App\Support\CourierOverview::get($startUtc->format('Y-m-d H:i:s'), \Carbon\Carbon::parse($f['to'], $tz)->endOfDay()->utc()->format('Y-m-d H:i:s')), ['couriers'=>[], 'outcomes'=>[], 'flagged'=>0]);
    $cust = ['new'=>max(0, $phones->count()-$ret), 'returning'=>$ret, 'vip'=>$vipN, 'member'=>$memN];
    $cx = $safe(fn() => \Illuminate\Support\Facades\Cache::remember('dash.cust.v1', 600, function () {
      $r = DB::selectOne("select count(*) as n, coalesce(sum(case when c > 1 then 1 else 0 end),0) as rep from (select phone, count(*) as c from orders where status not in ('Cancel','Incomplete') and phone is not null and phone <> '' group by phone) t");
      $lv = ['silver'=>0, 'gold'=>0, 'platinum'=>0, 'diamond'=>0, 'none'=>0];
      foreach (DB::table('customers')->selectRaw("coalesce(customer_level,'') as cl, coalesce(membership,'') as m, count(*) as n")->groupByRaw('1,2')->get() as $x) {
        $l = \App\Support\OrderDiscount::levelOf($x->cl, \App\Support\OrderDiscount::starsOf((string)$x->m)); $lv[$l['key'] ?? 'none'] += (int)$x->n;
      }
      return ['ordered'=>(int)$r->n, 'repeat'=>(int)$r->rep, 'levels'=>$lv];
    }), ['ordered'=>0, 'repeat'=>0, 'levels'=>[]]);
    $cust += ['ordered'=>$cx['ordered'] ?? 0, 'repeat'=>$cx['repeat'] ?? 0, 'repeat_pct'=>($cx['ordered'] ?? 0) > 0 ? round(($cx['repeat'] ?? 0) / $cx['ordered'] * 100, 1) : 0, 'levels'=>$cx['levels'] ?? []];
    $calls = $safe(function () use ($now) { if (!\Illuminate\Support\Facades\Schema::hasTable('call_logs')) return null; $s = $now->copy()->startOfDay()->utc(); $q = DB::table('call_logs')->where('started_at','>=',$s); return ['total'=>(clone $q)->count(), 'missed'=>(clone $q)->where('status','missed')->count(), 'talk'=>(int)(clone $q)->sum('duration')]; }, null);
    $risk = ['high'=>$hr, 'watch'=>$safe(fn() => DB::table('orders')->whereIn('status',['Cancel','Returned'])->where('created_at','>=',now()->subDays(30))->count(), 0)];
    // recent activity feed
    $feed = collect();
    $safe(function () use (&$feed, $orders) { foreach ($orders->take(6) as $o) $feed->push(['order', 'New order '.$o->invoice.' · ৳'.number_format($o->total), $o->created_at, '/admin/orders/'.$o->id]); });
    $safe(function () use (&$feed) { foreach (DB::table('audit_logs')->orderByDesc('id')->limit(8)->get() as $a) $feed->push([str_contains($a->action, 'stock') ? 'stock' : (str_contains($a->action, 'export') ? 'export' : 'edit'), trim(($a->user_name ?: 'System').' · '.$a->action.' · '.\Illuminate\Support\Str::limit((string)$a->subject, 60)), $a->created_at, null]); });
    $safe(function () use (&$feed) { if (\Illuminate\Support\Facades\Schema::hasTable('incomplete_orders')) foreach (DB::table('incomplete_orders')->orderByDesc('id')->limit(3)->get() as $i) $feed->push(['incomplete', 'Incomplete checkout · ৳'.number_format($i->total ?? 0), $i->last_activity_at ?? $i->created_at, '/admin/orders?status=Incomplete']); });
    $safe(function () use (&$feed) { if (\Illuminate\Support\Facades\Schema::hasTable('call_logs')) foreach (DB::table('call_logs')->orderByDesc('id')->limit(3)->get() as $c) $feed->push(['call', ucfirst($c->direction).' call · '.($c->customer_name ?: $c->phone).' · '.$c->status, $c->started_at ?? $c->created_at, '/admin/call-center/history']); });
    $feed = $feed->filter(fn($e) => $e[2])->sortByDesc(fn($e) => \Carbon\Carbon::parse($e[2])->timestamp)->take(10)->values()->map(fn($e) => [$e[0], $e[1], \Carbon\Carbon::parse($e[2])->timezone($tz)->diffForHumans(), \Carbon\Carbon::parse($e[2])->timezone($tz)->format('d M Y, h:i A'), $e[3]]);
    $storeList = $safe(fn() => DB::table('stores')->where('active',true)->orderBy('name')->get(['id','name']), collect());
    return view('dashboard', compact('pipe5','pipeAct','ch','channels','topCust','storePerf','now','kpis','stores','max','counts','orders','bad','alerts','health','range','label','trend','topProducts','cats','couriers','chatUnread','goal','cust','cov','calls','risk','feed','storeList','storeId','canFin','prev','f','incOpen','newN','stale') + ['isCustom'=>$range === 'custom']);
  }
  public function orders(Request $r) { \App\Support\CourierSync::auto(); \App\Support\LateDelivery::auto(); \App\Support\PaymentSettle::auto();
    $isInc = $r->input('status') === 'Incomplete'; $q = DB::table('orders'); $q->orderByDesc('created_at')->orderByDesc('id'); // strict chronological: no batch/status/invoice-match priority
    if ($isInc) $q->whereRaw('1 = 0'); elseif ($r->filled('status')) { $sts = array_filter(array_map('trim', explode(',', (string)$r->status))); count($sts) > 1 ? $q->whereIn('status', $sts) : $q->where('status', $r->status); }
    if ($r->filled('q')) { $qs = trim((string)$r->q); $qd = preg_replace('/\D/', '', $qs); if (str_starts_with($qd, '880')) $qd = substr($qd, 2); $qInv = \App\Support\Inv::candidates($qs); $q->where(fn($w) => $w->where('invoice','ilike','%'.$qs.'%')->when(preg_match('/^(INV-?)?\d{6,8}$/i', $qs) && $qInv, fn($x) => $x->orWhereIn('invoice', $qInv))->orWhere('phone','like','%'.($qd !== '' ? $qd : $qs).'%')->orWhere('customer_name','ilike','%'.$qs.'%')); }
    $rt = (string)$r->input('rtype'); $rs = (string)$r->input('rstat');
    if (in_array($rt, ['partial','full'], true) || in_array($rs, ['pending','received','completed'], true)) { $rset = ['Return Pending','Returned']; if ($rs === 'pending') $rset = ['Return Pending']; elseif ($rs !== '') $rset = ['Returned']; $q->whereIn('status', $rset); if ($rt === 'partial') $q->where('return_type', 'Partial'); elseif ($rt === 'full') $q->where(fn($w) => $w->whereNull('return_type')->orWhere('return_type', 'Full')); }
    $pf = (string)$r->input('print'); if ($pf === 'inv_not') $q->where('invoice_print_count', 0); elseif ($pf === 'inv') $q->where('invoice_print_count', '>', 0); elseif ($pf === 'lbl_not') $q->where('label_print_count', 0); elseif ($pf === 'lbl') $q->where('label_print_count', '>', 0);
    $per = in_array((int)$r->input('per'), [25,50,100,200]) ? (int)$r->input('per') : 50;
    $range = null; try { if ($r->filled('from') || $r->filled('to')) { $tz = 'Asia/Dhaka'; $ft = preg_match('/^\d{2}:\d{2}$/', (string)$r->ft) ? $r->ft : '00:00'; $tt = preg_match('/^\d{2}:\d{2}$/', (string)$r->tt) ? $r->tt : '23:59';
      $a = $r->filled('from') ? \Carbon\Carbon::parse($r->from.' '.$ft, $tz)->utc() : null; $b = $r->filled('to') ? \Carbon\Carbon::parse($r->to.' '.$tt.':59', $tz)->utc() : null; $range = [$a, $b]; } } catch (\Throwable $e) { $range = null; }
    if ($range) { if ($range[0]) $q->where('created_at', '>=', $range[0]); if ($range[1]) $q->where('created_at', '<=', $range[1]); }
    $orders = $q->paginate($per)->withQueryString();
    $items = DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->whereIn('i.order_id', collect($orders->items())->pluck('id'))->select('i.*', 'p.sku as sku', DB::raw('coalesce(p.image,(select pp.image from products pp where pp.name = i.name limit 1)) as image'))->get()->groupBy('order_id');
    $cq = DB::table('orders')->select('status', DB::raw('count(*) c'), DB::raw('coalesce(sum(total),0) v'))->groupBy('status'); if ($range) { if ($range[0]) $cq->where('created_at', '>=', $range[0]); if ($range[1]) $cq->where('created_at', '<=', $range[1]); } $__cr = $cq->get(); $counts = $__cr->pluck('c','status'); $vals = $__cr->pluck('v','status');
    $intel = \App\Support\CustomerIntel::forOrders($orders->items());
    $__ids = collect($orders->items())->pluck('id')->all(); $dupHint = $__ids ? \App\Support\OrderDedupe::possible($__ids) : []; $dupInv = $__ids ? DB::table('orders as d')->join('orders as p','p.id','=','d.duplicate_of_order_id')->whereIn('d.id', $__ids)->pluck('p.invoice','d.id')->all() : [];
    $cmLast = []; // a note belongs to the order it was written on: last comment per order id
    if ($__ids) foreach (DB::table('order_staff_comments')->whereIn('order_id', $__ids)->orderByDesc('id')->select('body','order_id')->get() as $c) { if (!isset($cmLast[$c->order_id])) $cmLast[$c->order_id] = $c->body; }
    $incOpen = (int)DB::table('incomplete_orders')->where('status', 'open')->count(); try { $vals['Incomplete'] = (float)DB::table('incomplete_orders')->where('status', 'open')->sum('total'); } catch (\Throwable $e) { $vals['Incomplete'] = 0; }
    return view('orders', compact('orders','counts','vals','items','per','intel','incOpen','cmLast','dupHint','dupInv') + ['isInc'=>$isInc] + ($isInc ? \App\Http\Controllers\IncompleteController::data($r, $range) : \App\Http\Controllers\IncompleteController::mix($r, $orders, $range)));
  }
  public function orderBulk(Request $r) {
    $d = $r->validate(['ids'=>'required|array|max:500','ids.*'=>'integer','status'=>'required|in:'.implode(',',Mother::STAGES)]);
    if (in_array($d['status'], \App\Support\ShipmentOutcome::SYSTEM_ONLY, true)) return back()->with('bad', 'Partial Return statuses are calculated from the items. Open the order and use Delivery Outcome.');
    if ($d['status'] === 'Scheduled') return back()->with('bad', 'Scheduled needs a date/time. Open the order (or use the quick-action menu on its row) and pick Scheduled with a date.');
    $n = 0; $okB = 0; $failB = []; @set_time_limit(240);
    if ($d['status'] === 'Shipment') {
      $skip = []; $tried = 0;
      foreach (DB::table('orders')->whereIn('id',$d['ids'])->where('status','!=','Shipment')->pluck('id') as $sid) { if ($sm = \App\Support\StockGuard::shipBlock((int)$sid)) { $skip[] = (int)$sid; $failB[] = '#'.$sid.': '.$sm.' (stays in its old status)'; } }
      foreach (DB::table('orders')->whereIn('id',$d['ids'])->where('status','!=','Shipment')->whereNull('consignment_id')->whereNotIn('id', $skip)->orderBy('id')->pluck('id') as $bid) {
        if ($tried >= 60) { $skip[] = (int)$bid; $failB[] = '#'.$bid.': over the 60-parcel limit per click, stays in its old status'; continue; }
        $tried++; $b = \App\Support\CourierSync::autoBook((int)$bid);
        if ($b === null) continue; if ($b[0]) $okB++; else { $skip[] = (int)$bid; $failB[] = '#'.$bid.': '.$b[1].' (stays in its old status)'; }
      }
      $d['ids'] = array_values(array_diff($d['ids'], $skip));
    }
    $locked = 0;
    DB::transaction(function () use ($d, &$n, &$locked) {
      foreach (DB::table('orders')->whereIn('id',$d['ids'])->lockForUpdate()->get() as $o) {
        if ($o->status === $d['status']) continue;
        if (\App\Support\ShipmentOutcome::manualBlock($o, $d['status'])) { $locked++; continue; }
        DB::table('orders')->where('id',$o->id)->update(['status'=>$d['status'],'updated_at'=>now()]);
        DB::table('order_status_logs')->insert(['order_id'=>$o->id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$d['status'],'note'=>'Bulk action','created_at'=>now(),'updated_at'=>now()]);
        $n++;
      }
    });
    if ($r->expectsJson()) return response()->json(['success'=>true,'moved'=>$n,'status'=>$d['status'],'booked'=>$okB,'parcel_errors'=>array_slice($failB, 0, 6) + ($locked ? [$locked.' order(s) skipped: the courier decides their delivery/return status'] : [])]);
    return back()->with('ok', "$n orders moved to {$d['status']}".($locked ? " · $locked skipped (courier decides their delivery/return status)" : ''));
  }
  public function orderNew(Request $r) {
    $pre = null; if ($r->filled('inc') && ($row = DB::table('incomplete_orders')->find((int)$r->query('inc')))) $pre = ['inc'=>$row->id, 'phone'=>$row->phone, 'name'=>$row->name, 'address'=>$row->address, 'district'=>$row->district, 'note'=>$row->note, 'items'=>json_decode($row->items ?: '[]', true) ?: []];
    return view('order_new', ['pre'=>$pre]);
  }
  public function orderProducts(Request $r) {
    $q = trim((string)$r->query('q', '')); if (mb_strlen($q) < 1) return response()->json([]);
    $like = '%'.str_replace(['%', '_'], '', $q).'%';
    return response()->json(DB::table('products')->where(fn($w) => \App\Support\SkuSearch::orSku($w->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like), $q))->orderByRaw('published desc')->limit(12)->get(['id', 'name', 'sku', 'price', 'stock'])->map(fn($p) => ['id'=>$p->id, 'name'=>$p->name, 'sku'=>$p->sku, 'price'=>(float)$p->price, 'stock'=>(int)$p->stock])->all());
  }
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
      'membership'=>(\App\Support\OrderDiscount::tier($p, $c)['name'] ?? null), 'courier_pref'=>(($c->courier_pref ?? '') !== '' ? $c->courier_pref : (DB::table('orders')->whereIn('phone', $alt)->whereNotNull('courier')->orderByDesc('id')->value('courier'))), 'assigned'=>$c->assigned_to ?? null, 'cnote'=>$c->note ?? null,
      'delivered'=>$i['delivered'] ?? 0, 'returned'=>$i['returned'] ?? 0, 'last'=>$last ? ['id'=>$last->id, 'invoice'=>$last->invoice, 'status'=>$last->status, 'total'=>(float)$last->total] : null]);
  }
  public function orderDiscount(Request $r) {
    $p = \App\Support\Chat::phone($r->query('phone')); $sub = max(0, (float)$r->query('subtotal', 0));
    $can = \App\Http\Middleware\ModulePermission::can(Auth::user(), 'marketing', 'edit');
    if (!$p || strlen($p) < 10) return response()->json(['lines'=>[], 'total'=>0, 'mode'=>\App\Support\OrderDiscount::mode(), 'can'=>$can, 'coupon_error'=>null, 'profile'=>null]);
    if ($sub <= 0) return response()->json(['lines'=>[], 'total'=>0, 'mode'=>\App\Support\OrderDiscount::mode(), 'can'=>$can, 'coupon_error'=>null, 'profile'=>\App\Support\OrderDiscount::profile($p)]);
    $__it = array_values(array_filter(array_map(function($t) { $a = explode(':', $t); return count($a) === 3 ? ['product_id'=>(int)$a[0], 'price'=>(float)$a[1], 'qty'=>(int)$a[2]] : null; }, explode(',', (string)$r->query('items', '')))));
    return response()->json(\App\Support\OrderDiscount::compute($p, $sub, $r->query('coupon'), ['qty'=>(int)$r->query('qty', 0), 'items'=>$__it]) + ['can'=>$can]);
  }
  public function orderStore(Request $r) {
    \App\Support\PhoneRule::trim($r);
    $d = $r->validate(['customer_name'=>'required|string|max:120','phone'=>['required','string',new \App\Support\PhoneRule],'address'=>'nullable|string','district'=>'nullable|string|max:60','source'=>'nullable|string|max:40','shipping'=>'nullable|numeric|min:0','note'=>'nullable|string','courier'=>'nullable|string|max:60','assigned_admin'=>'nullable|string|max:80','sub_city'=>'nullable|string|max:80','items'=>'required|array|min:1|max:60','items.*.name'=>'required|string|max:200','items.*.product_id'=>'nullable|integer','items.*.qty'=>'required|integer|min:1|max:100000','items.*.price'=>'required|numeric|min:0','inc_id'=>'nullable|integer','coupon'=>'nullable|string|max:40','disc_off'=>'nullable|array','disc_manual'=>'nullable|numeric|min:0','disc_extra'=>'nullable|numeric|min:0']);
    $d['phone'] = \App\Support\Chat::phone($d['phone']) ?: $d['phone'];
    if (!empty($d['sub_city'])) $d['address'] = trim(($d['address'] ?? '').' ('.$d['sub_city'].')');
    $sub = round(array_sum(array_map(fn($i) => $i['qty'] * $i['price'], $d['items'])), 2); if ($sub <= 0) return back()->withInput()->withErrors(['items'=>'Add at least one product with a price']); $auto = \App\Support\OrderDiscount::compute($d['phone'], $sub, $d['coupon'] ?? null, ['qty'=>array_sum(array_column($d['items'], 'qty')), 'items'=>array_map(fn($i) => ['product_id'=>$i['product_id'] ?? 0, 'price'=>$i['price'], 'qty'=>$i['qty']], $d['items'])]);
    $can = \App\Http\Middleware\ModulePermission::can(Auth::user(), 'marketing', 'edit');
    $off = $can ? (array)($d['disc_off'] ?? []) : []; $kept = array_values(array_filter($auto['lines'], fn($l) => $l['applied'] && !in_array($l['key'], $off)));
    $disc = min($sub, round(array_sum(array_column($kept, 'amount')), 2)); $labels = array_column($kept, 'label');
    if ($can && isset($d['disc_manual']) && $d['disc_manual'] !== '') { $disc = (float)$d['disc_manual']; $labels = ['Manual discount']; }
    if ($can && !empty($d['disc_extra'])) { $disc += (float)$d['disc_extra']; $labels[] = 'Extra discount'; }
    $disc = max(0, min($sub, round($disc, 2))); $changed = abs($disc - $auto['total']) > 0.009;
    if ($pfE = \App\Support\ProfitFloor::orderError(array_map(fn($i) => ['product_id'=>$i['product_id'] ?? 0, 'price'=>$i['price'], 'qty'=>$i['qty']], $d['items']), $disc)) { \App\Support\ProfitFloor::audit('Profit floor blocked order', 'Create order '.$d['phone'], null, ['discount'=>$disc, 'by'=>Auth::user()->name]); return back()->withInput()->withErrors(['items'=>$pfE]); }
    $cpn = null; foreach ($kept as $l) if (($l['key'] ?? '') === 'coupon') $cpn = $l['code'] ?? null; if ($changed && $labels === ['Manual discount']) $cpn = null;
    $dv = \App\Support\Delivery::compute((float)$sub, 0, trim(($d['address'] ?? '').' '.($d['sub_city'] ?? '')), (string)($d['district'] ?? ''), array_values(array_filter(array_map(fn($i) => (int)($i['product_id'] ?? 0), $d['items']))));
    $shipIn = ($d['shipping'] ?? '') === '' ? $dv['charge'] : round((float)$d['shipping'], 2); $d['shipping'] = $shipIn; $adj = abs($shipIn - $dv['charge']) > 0.009 ? round($shipIn - $dv['charge'], 2) : null;
    $dcols = ['delivery_zone'=>mb_substr((string)$dv['zone'], 0, 160), 'delivery_original'=>$dv['original'], 'delivery_free_discount'=>$dv['free_discount'], 'delivery_manual_adj'=>$adj, 'delivery_manual_by'=>$adj !== null ? Auth::user()->name : null, 'delivery_note'=>$dv['reason'] ? mb_substr($dv['reason'], 0, 200) : null];
    $d['total'] = round($sub - $disc + $shipIn, 2);
    $manual = $changed || $labels !== array_column($kept, 'label');
    if ($manual && $disc > 0) { $meta = ['discount_type'=>'Manual Discount', 'discount_rule'=>'Admin override', 'discount_pct'=>null, 'discount_source'=>'Manual by '.Auth::user()->name, 'discount_by'=>'Manual', 'customer_category'=>$auto['profile']['category'], 'membership_status'=>$auto['profile']['membership'].($auto['profile']['membership_name'] ? ' – '.$auto['profile']['membership_name'] : '')];
      if (\App\Support\OrderDiscount::hasLines()) {
        $lr = ($can && isset($d['disc_manual']) && $d['disc_manual'] !== '') ? [\App\Support\OrderDiscount::manualLine((float)$d['disc_manual'], Auth::user()->name, 'Admin override at order creation')] : \App\Support\OrderDiscount::lineRows($kept, 'Automatic', true);
        if ($can && !empty($d['disc_extra'])) $lr[] = \App\Support\OrderDiscount::manualLine((float)$d['disc_extra'], Auth::user()->name, 'Extra discount at order creation', 'Extra Discount');
        $meta['discount_lines'] = \App\Support\OrderDiscount::linesJson($lr);
      } }
    else $meta = \App\Support\OrderDiscount::meta($kept, $auto['profile'], 'Automatic', $disc);
    if ($disc <= 0) $meta['discount_by'] = null;
    $cu = DB::table('customers')->where('phone', $d['phone'])->orderBy('id')->first();
    if (!$cu) $cid = DB::table('customers')->insertGetId(['name'=>$d['customer_name'], 'phone'=>$d['phone'], 'address'=>$d['address'] ?? null, 'created_at'=>now(), 'updated_at'=>now()]);
    else { $cid = $cu->id; if (trim((string)($cu->address ?? '')) === '' && !empty($d['address'])) DB::table('customers')->where('id', $cid)->update(['address'=>$d['address'], 'updated_at'=>now()]); }
    $fp = \App\Support\OrderDedupe::fingerprint($d['phone'], $d['items'], $d['total'], (string)($d['address'] ?? ''), 'admin'); $idemTok = (string)$r->input('idem'); $dupId = null;
    $id = DB::transaction(function () use ($d, $cid, $disc, $cpn, $changed, $auto, $labels, $meta, $dcols, $dv, $fp, $idemTok, &$dupId) {
      \App\Support\OrderDedupe::lock($d['phone']); [$exOrder, $idemKey] = \App\Support\OrderDedupe::resolve($idemTok, $fp); // staff double-click on Save returns the order just created
      if ($exOrder) { $dupId = (int)$exOrder->id; return $dupId; }
      $invNo = \App\Support\InvoiceNumber::reserved(null, $d['inc_id'] ?? null, $d['phone']) ?: \App\Support\InvoiceNumber::next();
      $id = DB::table('orders')->insertGetId(['invoice'=>$invNo,'idempotency_key'=>$idemKey,'order_fingerprint'=>$fp,'store_id'=>1,'customer_name'=>$d['customer_name'],'phone'=>$d['phone'],'customer_id'=>$cid,'address'=>$d['address']??null,'district'=>$d['district']??null,'source'=>$d['source']??'Web Site','shipping'=>$d['shipping']??0,'total'=>$d['total'],'status'=>'New','note'=>$d['note']??null,'courier'=>($d['courier'] ?? null) ?: \App\Support\CourierSync::defaultName(),'assigned_admin'=>$d['assigned_admin']??null,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('orders')->where('id',$id)->update(['discount'=>$disc, 'coupon'=>$cpn] + $meta + $dcols);
      if ($dcols['delivery_manual_adj'] !== null) \App\Support\Procure::audit('delivery.manual_adjust', $invNo, ['charge'=>$dv['charge']], ['charge'=>$d['shipping']]);
      if ($cpn) DB::table('coupons')->where('code', $cpn)->increment('used_count');
      if ($disc > 0 || $changed) DB::table('order_discount_logs')->insert(['order_id'=>$id, 'kind'=>$changed ? 'override' : 'applied', 'label'=>mb_substr(implode(' + ', $labels) ?: 'No discount', 0, 200), 'prev_amount'=>$auto['total'], 'new_amount'=>$disc, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'created_at'=>now(), 'updated_at'=>now()]);
      foreach ($d['items'] as $it) DB::table('order_items')->insert(['order_id'=>$id,'product_id'=>$it['product_id'] ?? null,'name'=>$it['name'],'qty'=>$it['qty'],'price'=>$it['price'],'created_at'=>now(),'updated_at'=>now()] + \App\Support\BundlePricing::snapshot(isset($it['product_id']) ? (int)$it['product_id'] : null, (int)$it['qty'], (float)$it['price'], (float)$it['qty'] * (float)$it['price']));
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>null,'to_status'=>'New','note'=>'Order created','created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    if ($dupId) return redirect("/admin/orders/$dupId")->with('ok', 'This order was already saved (duplicate click ignored).');
    if (!empty($d['inc_id'])) DB::table('incomplete_orders')->where('id', (int)$d['inc_id'])->whereIn('status', ['open','recovered'])->update(['status'=>'converted', 'order_id'=>$id, 'updated_at'=>now()]);
    DB::table('incomplete_orders')->where('status', 'open')->where('phone', $d['phone'])->update(['status'=>'converted', 'order_id'=>$id, 'updated_at'=>now()]);
    try { \App\Support\Voice::onOrder($d['phone'], (int)$id, $kept); } catch (\Throwable $e) {}
    \App\Support\OrderSms::placed((int)$id);
    return redirect("/admin/orders/$id");
  }
  public function orderShow($id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    $items = DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->where('i.order_id',$id)->select('i.*','p.sku as sku', DB::raw('coalesce(p.image,(select pp.image from products pp where pp.name = i.name limit 1)) as image'))->get();
    $logs = DB::table('order_status_logs')->where('order_id',$id)->orderBy('id')->get();
    $ci = \App\Support\CustomerIntel::forOrders([$o])[$o->id];
    $comments = DB::table('order_staff_comments as c')->join('orders as oo','oo.id','=','c.order_id')->where('c.order_id',$id)->orderByDesc('c.id')->select('c.*','oo.invoice as inv')->get();
    $prints = \Illuminate\Support\Facades\Schema::hasTable('order_print_logs') ? DB::table('order_print_logs')->where('order_id',$id)->orderByDesc('printed_at')->orderByDesc('id')->get() : collect();
    $ops = \Illuminate\Support\Facades\Schema::hasTable('order_payments') ? DB::table('order_payments')->where('order_id',$id)->orderByDesc('id')->get() : collect();
    return view('order_show', compact('o','items','logs','ci','comments','prints','ops'));
  }
  public function orderComment(Request $r, $id) {
    $d = $r->validate(['body'=>'required|string|max:2000','category'=>'nullable|string|max:80']);
    abort_unless(DB::table('orders')->where('id',$id)->exists(), 404);
    DB::table('order_staff_comments')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'category'=>$d['category']??null,'body'=>trim($d['body']),'created_at'=>now(),'updated_at'=>now()]);
    if ($r->expectsJson()) return response()->json(['ok'=>true] + self::commentPayload($id));
    return redirect("/admin/orders/$id#comments");
  }
  /** Courier note = orders.note; it is what Pathao receives as the delivery instruction when the parcel is booked. */
  public function orderCourierNote(Request $r, $id) {
    $o = DB::table('orders')->where('id',$id)->first(); abort_unless($o, 404);
    $new = mb_substr(trim((string)$r->input('note')), 0, 200); $old = trim((string)$o->note);
    if ($new !== $old) {
      DB::table('orders')->where('id',$id)->update(['note'=>$new !== '' ? $new : null, 'updated_at'=>now()]);
      try { \App\Support\OrderActivity::log((int)$id, 'courier_note', $old, $new, 'Courier note '.($new === '' ? 'cleared' : ($old === '' ? 'added' : 'changed'))); } catch (\Throwable $e) {}
    }
    return response()->json(['ok'=>true, 'note'=>$new, 'booked'=>!empty($o->consignment_id)]);
  }
  public static function commentPayload($id): array {
    $o = DB::table('orders')->find($id); $ph = (string)($o->phone ?? '');
    $rows = DB::table('order_staff_comments as c')->join('orders as oo','oo.id','=','c.order_id')->where('c.order_id',$id)->orderByDesc('c.id')->limit(50)->select('c.*','oo.invoice as inv')->get();
    return ['inv'=>$o->invoice ?? '', 'last'=>$rows->first()->body ?? '', 'n'=>$rows->count(), 'rows'=>$rows->map(fn($c) => ['t'=>\Carbon\Carbon::parse($c->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A'), 'by'=>$c->user_name, 'cat'=>$c->category, 'body'=>$c->body, 'inv'=>$c->order_id == $id ? null : $c->inv])->all()];
  }
  public function orderComments($id) { abort_unless(DB::table('orders')->where('id',$id)->exists(), 404); return response()->json(self::commentPayload($id)); }
  public function orderStatus(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:'.implode(',',Mother::STAGES),'note'=>'nullable|string|max:500','sched_date'=>'nullable|date_format:Y-m-d','sched_time'=>'nullable|string|max:8']);
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    if ($mb = \App\Support\ShipmentOutcome::manualBlock($o, $d['status'])) return back()->with('bad', $mb);
    if (in_array($d['status'], \App\Support\ShipmentOutcome::SYSTEM_ONLY, true) && $o->status !== $d['status']) return back()->with('bad', 'Partial Return statuses are calculated from the items. Use "Delivery Outcome" on the order page, then "Receive Partial Return".');
    if ($d['status'] === 'Scheduled') {
      [$sok, $smsg] = \App\Support\OrderSchedule::set((int)$id, $d['sched_date'] ?? null, $d['sched_time'] ?? null, $d['note'] ?? null, Auth::id(), Auth::user()->name);
      return back()->with($sok ? 'ok' : 'bad', $smsg);
    }
    if ($d['status'] === 'Shipment' && $o->status !== 'Shipment' && ($sm = \App\Support\StockGuard::shipBlock((int)$id))) return back()->with('bad', $sm);
    if ($d['status'] === 'Shipment' && $o->status !== 'Shipment') { $b = \App\Support\CourierSync::autoBook((int)$id); if ($b && !$b[0]) return back()->with('bad', 'Parcel booking failed, the order stays in '.$o->status.': '.$b[1]); $o = DB::table('orders')->find($id); }
    if ($o->status !== $d['status']) DB::transaction(function () use ($o, $d, $id) {
      DB::table('orders')->where('id',$id)->update(['status'=>$d['status'],'updated_at'=>now()] + ($o->status === 'Scheduled' ? ['scheduled_at'=>null,'scheduled_note'=>null] : []));
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$d['status'],'note'=>$d['note']??null,'created_at'=>now(),'updated_at'=>now()]);
    });
    if ($r->input('go') === 'list') {
      parse_str(ltrim((string)$r->input('ret'), '?'), $keep);
      $keep = array_filter(array_intersect_key($keep, array_flip(['q','from','to','ft','tt','per'])), fn($v) => is_scalar($v) && $v !== '');
      return redirect('/admin/orders?'.http_build_query($keep + ['status'=>$d['status'],'hl'=>(int)$id]))->with('ok', 'Order status updated successfully. / অর্ডারের স্ট্যাটাস সফলভাবে আপডেট হয়েছে।');
    }
    return back();
  }
  /** One-click status change from the order list (AJAX): same rules and audit log as orderStatus. */
  public function orderQuickStatus(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:'.implode(',',Mother::STAGES),'note'=>'nullable|string|max:500','sched_date'=>'nullable|date_format:Y-m-d','sched_time'=>'nullable|string|max:8']);
    $o = DB::table('orders')->find($id); abort_unless($o, 404);
    if ($mb = \App\Support\ShipmentOutcome::manualBlock($o, $d['status'])) return response()->json(['success'=>false,'message'=>$mb]);
    if (in_array($d['status'], \App\Support\ShipmentOutcome::SYSTEM_ONLY, true) && $o->status !== $d['status']) return response()->json(['success'=>false,'message'=>'Partial Return statuses are calculated from the items. Use Delivery Outcome on the order page.']);
    if ($d['status'] === 'Scheduled') {
      if (empty($d['sched_date'])) return response()->json(['success'=>false,'need_schedule'=>true,'message'=>'Scheduled date is required']);
      [$sok, $smsg] = \App\Support\OrderSchedule::set((int)$id, $d['sched_date'], $d['sched_time'] ?? null, $d['note'] ?? null, Auth::id(), Auth::user()->name);
      return response()->json(['success'=>$sok,'message'=>$smsg,'status'=>'Scheduled','cls'=>'s-scheduled','sched'=>\App\Support\OrderSchedule::short(DB::table('orders')->where('id',$id)->value('scheduled_at'))]);
    }
    if ($d['status'] === 'Shipment' && $o->status !== 'Shipment' && ($sm = \App\Support\StockGuard::shipBlock((int)$id))) return response()->json(['success'=>false,'message'=>$sm]);
    if ($d['status'] === 'Shipment' && $o->status !== 'Shipment') { $b = \App\Support\CourierSync::autoBook((int)$id); if ($b && !$b[0]) return response()->json(['success'=>false,'message'=>'Parcel booking failed, the order stays in '.$o->status.': '.$b[1]]); $o = DB::table('orders')->find($id); }
    if ($o->status !== $d['status']) DB::transaction(function () use ($o, $d, $id) {
      DB::table('orders')->where('id',$id)->update(['status'=>$d['status'],'updated_at'=>now()] + ($o->status === 'Scheduled' ? ['scheduled_at'=>null,'scheduled_note'=>null] : []));
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'from_status'=>$o->status,'to_status'=>$d['status'],'note'=>trim('Quick Action '.($d['note'] ?? '')),'created_at'=>now(),'updated_at'=>now()]);
    });
    return response()->json(['success'=>true,'status'=>$d['status'],'cls'=>'s-'.\Illuminate\Support\Str::slug($d['status'])]);
  }
  public function orderSchedule(Request $r, $id) {
    $d = $r->validate(['sched_date'=>'required|date_format:Y-m-d','sched_time'=>'nullable|string|max:8','note'=>'nullable|string|max:500']);
    [$ok, $m] = \App\Support\OrderSchedule::set((int)$id, $d['sched_date'], $d['sched_time'] ?? null, $d['note'] ?? null, Auth::id(), Auth::user()->name);
    if ($r->expectsJson()) return response()->json(['ok'=>$ok, 'message'=>$m]);
    return back()->with($ok ? 'ok' : 'bad', $m);
  }
  public function orderScheduleCancel($id) {
    [$ok, $m] = \App\Support\OrderSchedule::cancel((int)$id, Auth::id(), Auth::user()->name);
    return back()->with($ok ? 'ok' : 'bad', $m);
  }
  public function ordersCsv() {
    return response()->streamDownload(function () {
      $f = fopen('php://output','w'); fputcsv($f,['Invoice','Customer','Phone','District','Source','Total','Shipping','Status','Created']);
      foreach (DB::table('orders')->orderByDesc('id')->cursor() as $o) fputcsv($f,[\App\Support\Inv::show($o->invoice),$o->customer_name,$o->phone,$o->district,$o->source,$o->total,$o->shipping,$o->status,$o->created_at]);
      fclose($f);
    }, 'orders.csv', ['Content-Type'=>'text/csv']);
  }
  public function staffLog(Request $r) {
    $d0 = now('Asia/Dhaka')->startOfDay()->utc();
    $logs = DB::table('order_status_logs as l')->join('orders as o','o.id','=','l.order_id')->select('l.*','o.invoice')->when($r->filled('staff'), fn($q) => $q->where('l.user_name',$r->staff))->orderByDesc('l.id')->paginate(\App\Support\Pg::per(50))->withQueryString();
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
