<?php
namespace App\Http\Controllers;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class InventoryController extends Controller {
  /** Every product from every store as one list: this site's own catalogue + all scanned stores. */
  private function union() {
    $own = DB::table('products as p')->leftJoin('categories as c','c.id','=','p.category_id')->selectRaw("'own' as src, p.id as id, 0 as store_id, 'DutyFreeBD (www.dutyfreebd.com)' as store_name, p.name as name, p.sku as sku, p.image as image, p.price as price, p.stock as stock, null::boolean as in_stock, coalesce(c.name,'') as category, null as url, p.published as published, p.updated_at as updated_at, p.cost as last_cost, " . \App\Support\Costing::avgSql('p') . " as avg_cost, " . \App\Support\Costing::fifoSql('p') . " as fifo_cost, least(p.price, nullif(p.buy2_price,0)/2, nullif(p.buy3_price,0)/3, nullif(p.buy4_price,0)/4, nullif(p.buy5_price,0)/5) as worst_unit");
    $ext = DB::table('store_products as sp')->leftJoin('stores as s','s.id','=','sp.store_id')->selectRaw("'ext' as src, sp.id as id, sp.store_id as store_id, coalesce(s.name, sp.store_label || ' (removed)', 'Removed store') as store_name, sp.name as name, sp.sku as sku, sp.image as image, sp.price as price, sp.stock as stock, sp.in_stock as in_stock, coalesce(sp.category,'') as category, sp.url as url, sp.published as published, sp.updated_at as updated_at, null::numeric as last_cost, null::numeric as avg_cost, null::numeric as fifo_cost, null::numeric as worst_unit");
    return DB::query()->fromSub($own->unionAll($ext), 'u');
  }
  private function filtered(Request $r) {
    $q = $this->union();
    if ($r->filled('store')) $q->where('store_id', (int)$r->store);
    if ($r->filled('q')) { $t = '%'.str_replace(['%','_'], ['\%','\_'], trim($r->q)).'%'; $q->where(fn($w) => \App\Support\SkuSearch::orSku($w->where('name','ilike',$t)->orWhere('sku','ilike',$t)->orWhere('category','ilike',$t), (string)$r->q)); }
    if ($r->filled('sku')) $q->where(fn($w) => \App\Support\SkuSearch::orSku($w->where('sku','ilike','%'.str_replace(['%','_'], ['\%','\_'], trim($r->sku)).'%'), (string)$r->sku));
    if ($r->filled('published')) $q->where('published', $r->published === '1');
    $st = $r->input('stock');
    if ($st === 'in') $q->where(fn($w) => $w->where('stock','>',5)->orWhere(fn($x) => $x->whereNull('stock')->where(fn($y) => $y->whereNull('in_stock')->orWhere('in_stock', true))));
    if ($st === 'low') $q->whereBetween('stock', [1,5]);
    if ($st === 'out') $q->where(fn($w) => $w->where('stock','<=',0)->orWhere(fn($x) => $x->whereNull('stock')->where('in_stock', false)));
    return $q;
  }
  public static function stockText($r): string {
    if ($r->stock !== null) return (string)$r->stock;
    return $r->in_stock === null ? '—' : ($r->in_stock ? 'In stock' : 'Out of stock');
  }
  /** Pricing cells for a row (own products only; other stores' purchase prices are unknown). Hybrid costing: the 15% rule and the status use the current FIFO batch cost, the average is for reporting.
   *  Returns plain values for exports (keys 9-17), or the named cells for the page. */
  public static function marginCols($x, bool $export = false): array {
    $avg = (float)($x->avg_cost ?? 0); $fifo = (float)($x->fifo_cost ?? 0); $cost = $fifo > 0 ? $fifo : $avg; $pr = (float)($x->price ?? 0); $has = $cost > 0 && ($x->src ?? 'own') === 'own';
    if (!$has) return $export ? [9=>'',10=>'',11=>'',12=>'',13=>'',14=>'',15=>'',16=>'',17=>''] : ['last'=>null,'avg'=>null,'fifo'=>null,'profit'=>null,'pct'=>null,'state'=>'—','col'=>'#6B7280','min'=>null,'bundle'=>'—','bcol'=>'#6B7280'];
    $worst = isset($x->worst_unit) && (float)$x->worst_unit > 0 ? min($pr, (float)$x->worst_unit) : $pr; // weakest per-unit price among single and Buy 2-5
    [$lab, $col] = \App\Support\ProfitFloor::state($cost, $worst); $pct = ($pr - $cost) / $cost * 100; $fl = \App\Support\ProfitFloor::floor($cost);
    [$bl, $bc] = $worst < $pr - 0.004 ? \App\Support\ProfitFloor::state($cost, $worst) : ['No bundle', '#6B7280'];
    if ($export) return [9=>round((float)$x->last_cost, 2), 10=>round($avg, 2), 11=>round($cost, 2), 12=>\App\Support\ProfitFloor::percent(), 13=>$fl, 14=>round($pr - $cost, 2), 15=>round($pct, 2), 16=>preg_replace('/^[^A-Za-z]+/u', '', $lab), 17=>preg_replace('/^[^A-Za-z]+/u', '', $bl)];
    return ['last'=>(float)$x->last_cost, 'avg'=>$avg, 'fifo'=>$cost, 'profit'=>$pr - $cost, 'pct'=>$pct, 'state'=>$lab, 'col'=>$col, 'min'=>$fl, 'bundle'=>$bl, 'bcol'=>$bc];
  }
  public static function statusText($r): string {
    if (!$r->published) return 'Unpublished';
    if ($r->stock !== null) return $r->stock <= 0 ? 'Out of stock' : ($r->stock <= 5 ? 'Low stock' : 'Active');
    return $r->in_stock === false ? 'Out of stock' : 'Active';
  }
  public function index(Request $r) {
    $grouped = $r->input('view') === 'grouped';
    $base = $this->filtered($r);
    if ($grouped) {
      $key = "coalesce(nullif(lower(sku),''), lower(name))";
      $rows = DB::query()->fromSub($base, 'f')->selectRaw("$key as k, min(name) as name, min(sku) as sku, (array_agg(image) filter (where image is not null))[1] as image, count(*) as stores, sum(coalesce(stock,0)) as total, max(price) as price, max(avg_cost) as avg_cost, max(fifo_cost) as fifo_cost, max(last_cost) as last_cost, min(worst_unit) as worst_unit, string_agg(store_name || ': ' || coalesce(stock::text, case when in_stock is true then 'In stock' when in_stock is false then 'Out' else '—' end), '  ·  ' order by store_name) as per_store")->groupByRaw($key)->orderByRaw('min(name)')->paginate(\App\Support\Pg::per(50))->withQueryString();
    } else {
      $rows = $base->orderBy('name')->orderBy('store_name')->paginate(\App\Support\Pg::per(50))->withQueryString();
    }
    $stores = DB::table('stores')->orderBy('name')->get(['id','name','domain']);
    $perStore = DB::table('store_products')->select('store_id', DB::raw('count(*) n'))->groupBy('store_id')->pluck('n','store_id');
    $ownN = DB::table('products')->count();
    $outN = DB::table('products')->where('stock','<=',0)->count() + DB::table('store_products')->where(fn($w) => $w->where('stock','<=',0)->orWhere(fn($x) => $x->whereNull('stock')->where('in_stock', false)))->count();
    $stats = [['All products', number_format($ownN + $perStore->sum()), ($stores->count() + 1).' stores'], ['This site', number_format($ownN), 'DutyFreeBD'], ['Other stores', number_format($perStore->sum()), $perStore->count().' scanned'], ['Out of stock', number_format($outN), 'all stores'], ['Unpublished', number_format(DB::table('products')->where('published',false)->count() + DB::table('store_products')->where('published',false)->count()), 'all stores']];
    if (\App\Support\ProfitFloor::admin()) { try { $stats[] = ['Below Minimum Margin', number_format(DB::table('products')->whereRaw(\App\Support\Costing::fifoSql('products').' > 0 and price + 0.004 < '.\App\Support\Costing::fifoSql('products').' * '.\App\Support\ProfitFloor::rate())->count()), 'vs current FIFO batch cost']; } catch (\Throwable $e) {} }
    return view('inventory', compact('rows','stores','stats','grouped','perStore','ownN'));
  }
  public function export(Request $r, $fmt) {
    abort_unless(in_array($fmt, ['csv','xls','doc','pdf']), 404);
    $adm = \App\Support\ProfitFloor::admin(); $head = ['Image URL','Product Name','SKU','Store / Domain','Stock Qty','Status','Sale Price','Category','Product URL'] + ($adm ? [9=>'Last Purchase Price',10=>'Average Cost',11=>'Current FIFO Cost',12=>'Min Margin %',13=>'Minimum Safe Sale',14=>'Estimated Profit',15=>'Profit %',16=>'Margin Status (weakest of single and bundles)',17=>'Bundle Status (Buy 2-5)'] : []);
    $gen = function () use ($r, $adm) { foreach ($this->filtered($r)->orderBy('name')->orderBy('store_name')->limit(60000)->cursor() as $x) yield [$x->image ?: '', $x->name, $x->sku ?: '', $x->store_name, self::stockText($x), self::statusText($x), $x->price !== null ? number_format((float)$x->price, 2, '.', '') : '', $x->category, $x->url ?: ''] + ($adm ? self::marginCols($x, true) : []); };
    $name = 'inventory-'.date('Ymd-His');
    if ($fmt === 'csv') return response()->streamDownload(function () use ($head, $gen) { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $head); foreach ($gen() as $row) fputcsv($o, $row); fclose($o); }, "$name.csv", ['Content-Type'=>'text/csv; charset=utf-8']);
    $table = function () use ($head, $gen, $fmt) {
      echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr><th style="background:#FF5A00;color:#fff">Image</th>'; foreach (array_slice($head, 1) as $h) echo '<th style="background:#FF5A00;color:#fff">'.e($h).'</th>'; echo '</tr></thead><tbody>';
      foreach ($gen() as $row) { echo '<tr><td>'.($row[0] && $fmt !== 'xls' ? '<img src="'.e($row[0]).'" width="48" height="48">' : e($row[0])).'</td>'; foreach (array_slice($row, 1) as $v) echo '<td>'.e($v).'</td>'; echo '</tr>'; }
      echo '</tbody></table>';
    };
    $pre = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><title>Central Inventory</title><style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}table{border-collapse:collapse;font-size:12px}h2{color:#0B1220}</style></head><body>'.\App\Support\PrintBrand::top($fmt).'<h2>Central Inventory — DutyFreeBD / Mother Commerce</h2><p>'.e(now()->format('d M Y H:i')).'</p>';
    if ($fmt === 'xls') return response()->streamDownload(function () use ($pre, $table) { echo $pre; $table(); echo '</body></html>'; }, "$name.xls", ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8']);
    if ($fmt === 'doc') return response()->streamDownload(function () use ($pre, $table) { echo $pre; $table(); echo '</body></html>'; }, "$name.doc", ['Content-Type'=>'application/msword; charset=utf-8']);
    return response()->stream(function () use ($pre, $table) { echo $pre; $table(); echo '<script>window.onload=function(){window.print()}</script></body></html>'; }, 200, ['Content-Type'=>'text/html; charset=utf-8']);
  }
  // ---------- Stores & scanning ----------
  public function stores() {
    $stores = DB::table('stores')->orderBy('name')->get();
    $counts = DB::table('store_products')->select('store_id', DB::raw('count(*) n'), DB::raw('sum(case when published then 1 else 0 end) pub'))->groupBy('store_id')->get()->keyBy('store_id');
    $own = ['n'=>DB::table('products')->count(), 'pub'=>DB::table('products')->where('published',true)->count()];
    return view('stores', compact('stores','counts','own'));
  }
  public function storeAdd(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120','domain'=>'required|string|max:200']);
    $dom = StoreScanner::cleanDomain($d['domain']);
    if ($dom === '') return back()->withErrors(['domain'=>'Enter a valid domain such as example.com']);
    if (DB::table('stores')->where('domain',$dom)->exists()) return back()->withErrors(['domain'=>'This domain is already added']);
    DB::table('stores')->insert(['name'=>$d['name'],'domain'=>$dom,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    return redirect('/admin/stores')->with('ok','Store added. Press "Scan Products" to import its products.');
  }
  public function storeEdit($id) {
    $s = DB::table('stores')->find($id); abort_unless($s, 404);
    return view('store_edit', compact('s'));
  }
  public function storeUpdate(Request $r, $id) {
    $s = DB::table('stores')->find($id); abort_unless($s, 404);
    $d = $r->validate(['name'=>'required|string|max:120','domain'=>'required|string|max:200']);
    $dom = StoreScanner::cleanDomain($d['domain']);
    if ($dom === '') return back()->withErrors(['domain'=>'Enter a valid domain such as example.com'])->withInput();
    if (DB::table('stores')->where('domain',$dom)->where('id','!=',$id)->exists()) return back()->withErrors(['domain'=>'This domain is already added'])->withInput();
    DB::table('stores')->where('id',$id)->update(['name'=>$d['name'],'domain'=>$dom,'active'=>$r->boolean('active'),'updated_at'=>now()]);
    DB::table('store_products')->where('store_id',$id)->update(['store_label'=>$d['name']]);
    return redirect('/admin/stores')->with('ok','Store updated.');
  }
  public function storeDelete(Request $r, $id) {
    $s = DB::table('stores')->find($id); abort_unless($s, 404);
    $mode = $r->input('mode') === 'all' ? 'all' : 'store';
    DB::transaction(function () use ($id, $s, $mode) {
      if ($mode === 'all') DB::table('store_products')->where('store_id',$id)->delete();
      else DB::table('store_products')->where('store_id',$id)->update(['store_label'=>$s->name.' / '.$s->domain]);
      DB::table('stores')->where('id',$id)->delete();
      DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>'delete','subject'=>'store#'.$id.' '.$s->domain.' ('.($mode==='all'?'with products':'store only').')','ip'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]);
    });
    @unlink(storage_path('app/scan-'.$id.'.json'));
    return redirect('/admin/stores')->with('ok', $mode === 'all' ? 'Store and its imported products were deleted.' : 'Store deleted. Its imported products stay in the inventory.');
  }
  public function storeRemoveProducts($id) {
    DB::table('store_products')->where('store_id',$id)->delete();
    DB::table('stores')->where('id',$id)->update(['scan_status'=>'never','scan_total'=>0,'scan_published'=>0,'scan_draft'=>0,'scan_imported'=>0,'scan_failed'=>0,'scan_percent'=>0,'scan_note'=>null,'last_scan_at'=>null,'updated_at'=>now()]);
    return back()->with('ok','Imported products of this store were cleared.');
  }
  private function payload($s) {
    return ['id'=>$s->id, 'status'=>$s->scan_status, 'percent'=>(int)$s->scan_percent, 'total'=>(int)$s->scan_total, 'published'=>(int)$s->scan_published, 'draft'=>(int)$s->scan_draft, 'imported'=>(int)$s->scan_imported, 'failed'=>(int)$s->scan_failed, 'note'=>$s->scan_note, 'platform'=>$s->platform, 'last'=>$s->last_scan_at ? \Carbon\Carbon::parse($s->last_scan_at)->timezone('Asia/Dhaka')->format('d M Y, H:i') : null];
  }
  /** Diagnose why a store's pages are not readable: fetches the first pages straight from the server and reports status, time and the PHP error. */
  public function scanDiag($id) {
    $s = DB::table('stores')->find($id); abort_unless($s && $s->domain, 404);
    $q = json_decode((string)@file_get_contents(storage_path('app/scan-'.(int)$id.'.json')), true) ?: []; $out = ['domain'=>$s->domain, 'queue'=>count($q), 'tests'=>[]];
    $urls = request('u') ? [(string)request('u')] : (array_slice($q, 0, 3) ?: ['https://'.$s->domain.'/']);
    if (request('sm')) { $r = StoreScanner::get('https://'.$s->domain.'/'.ltrim((string)request('sm'), '/'), 15, 3000000); preg_match_all('#<loc>\s*([^<\s]+)#i', (string)($r['body'] ?? ''), $mm); $out['sitemap'] = ['code'=>$r['code'] ?? null, 'len'=>isset($r['body']) ? strlen($r['body']) : null, 'locs'=>array_slice($mm[1] ?? [], 0, 12)]; }
    foreach ($urls as $u) {
      $t = microtime(true); $err = null; $r = null;
      try { $r = StoreScanner::get($u, 12, 700000); } catch (\Throwable $e) { $err = $e->getMessage(); }
      $pg = StoreScanner::pget([$u], 10)[$u] ?? null; $le = error_get_last(); $host = parse_url($u, PHP_URL_HOST);
      $out['tests'][] = ['url'=>$u, 'safeHost'=>StoreScanner::safeHost((string)$host), 'ips'=>@gethostbynamel((string)$host), 'code'=>$r['code'] ?? null, 'final'=>$r['url'] ?? null, 'len'=>isset($r['body']) ? strlen($r['body']) : null, 'sec'=>round(microtime(true)-$t, 2), 'pget_code'=>$pg['code'] ?? null, 'pget_len'=>isset($pg['body']) ? strlen($pg['body']) : null, 'err'=>$err, 'php'=>$le['message'] ?? null, 'head'=>isset($r['body']) ? mb_substr(strip_tags((string)$r['body']), 0, 160) : null];
    }
    return response()->json($out);
  }
  public function scanStart($id) {
    $s = DB::table('stores')->find($id); abort_unless($s && $s->domain, 404);
    if ($s->scan_status === 'running' && $s->updated_at && (time() - strtotime((string)$s->updated_at)) < 120) return response()->json($this->payload($s));
    StoreScanner::begin($s);
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>'scan','subject'=>'store#'.$id.' '.$s->domain,'ip'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]);
    return response()->json($this->payload(DB::table('stores')->find($id)));
  }
  public function scanStep($id) {
    @set_time_limit(60);
    $lock = Cache::lock('scan-'.(int)$id, 50);
    if (!$lock->get()) return response()->json($this->payload(DB::table('stores')->find($id)));
    try { $s = StoreScanner::step((int)$id); DB::table('store_products')->where('store_id',$id)->whereNull('store_label')->update(['store_label'=>$s->name]); } finally { $lock->release(); }
    return response()->json($this->payload($s));
  }
}
