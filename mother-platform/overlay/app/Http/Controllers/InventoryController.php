<?php
namespace App\Http\Controllers;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class InventoryController extends Controller {
  /** Every product from every store as one list: this site's own catalogue + all scanned stores. */
  private function union() {
    $own = DB::table('products as p')->leftJoin('categories as c','c.id','=','p.category_id')->selectRaw("'own' as src, p.id as id, 0 as store_id, 'DutyFreeShopBD (this site)' as store_name, p.name as name, p.sku as sku, p.image as image, p.price as price, p.stock as stock, null::boolean as in_stock, coalesce(c.name,'') as category, null as url, p.published as published, p.updated_at as updated_at");
    $ext = DB::table('store_products as sp')->join('stores as s','s.id','=','sp.store_id')->selectRaw("'ext' as src, sp.id as id, sp.store_id as store_id, s.name as store_name, sp.name as name, sp.sku as sku, sp.image as image, sp.price as price, sp.stock as stock, sp.in_stock as in_stock, coalesce(sp.category,'') as category, sp.url as url, sp.published as published, sp.updated_at as updated_at");
    return DB::query()->fromSub($own->unionAll($ext), 'u');
  }
  private function filtered(Request $r) {
    $q = $this->union();
    if ($r->filled('store')) $q->where('store_id', (int)$r->store);
    if ($r->filled('q')) { $t = '%'.str_replace(['%','_'], ['\%','\_'], trim($r->q)).'%'; $q->where(fn($w) => $w->where('name','ilike',$t)->orWhere('sku','ilike',$t)->orWhere('category','ilike',$t)); }
    if ($r->filled('sku')) $q->where('sku','ilike','%'.str_replace(['%','_'], ['\%','\_'], trim($r->sku)).'%');
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
      $rows = DB::query()->fromSub($base, 'f')->selectRaw("$key as k, min(name) as name, min(sku) as sku, (array_agg(image) filter (where image is not null))[1] as image, count(*) as stores, sum(coalesce(stock,0)) as total, max(price) as price, string_agg(store_name || ': ' || coalesce(stock::text, case when in_stock is true then 'In stock' when in_stock is false then 'Out' else '—' end), '  ·  ' order by store_name) as per_store")->groupByRaw($key)->orderByRaw('min(name)')->paginate(50)->withQueryString();
    } else {
      $rows = $base->orderBy('name')->orderBy('store_name')->paginate(50)->withQueryString();
    }
    $stores = DB::table('stores')->orderBy('name')->get(['id','name','domain']);
    $perStore = DB::table('store_products')->select('store_id', DB::raw('count(*) n'))->groupBy('store_id')->pluck('n','store_id');
    $ownN = DB::table('products')->count();
    $outN = DB::table('products')->where('stock','<=',0)->count() + DB::table('store_products')->where(fn($w) => $w->where('stock','<=',0)->orWhere(fn($x) => $x->whereNull('stock')->where('in_stock', false)))->count();
    $stats = [['All products', number_format($ownN + $perStore->sum()), ($stores->count() + 1).' stores'], ['This site', number_format($ownN), 'DutyFreeShopBD'], ['Other stores', number_format($perStore->sum()), $perStore->count().' scanned'], ['Out of stock', number_format($outN), 'all stores'], ['Unpublished', number_format(DB::table('products')->where('published',false)->count() + DB::table('store_products')->where('published',false)->count()), 'all stores']];
    return view('inventory', compact('rows','stores','stats','grouped','perStore','ownN'));
  }
  public function export(Request $r, $fmt) {
    abort_unless(in_array($fmt, ['csv','xls','doc','pdf']), 404);
    $head = ['Image URL','Product Name','SKU','Store / Domain','Stock Qty','Status','Price','Category','Product URL'];
    $gen = function () use ($r) { foreach ($this->filtered($r)->orderBy('name')->orderBy('store_name')->limit(60000)->cursor() as $x) yield [$x->image ?: '', $x->name, $x->sku ?: '', $x->store_name, self::stockText($x), self::statusText($x), $x->price !== null ? number_format((float)$x->price, 2, '.', '') : '', $x->category, $x->url ?: '']; };
    $name = 'inventory-'.date('Ymd-His');
    if ($fmt === 'csv') return response()->streamDownload(function () use ($head, $gen) { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $head); foreach ($gen() as $row) fputcsv($o, $row); fclose($o); }, "$name.csv", ['Content-Type'=>'text/csv; charset=utf-8']);
    $table = function () use ($head, $gen, $fmt) {
      echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr><th style="background:#FF5A00;color:#fff">Image</th>'; foreach (array_slice($head, 1) as $h) echo '<th style="background:#FF5A00;color:#fff">'.e($h).'</th>'; echo '</tr></thead><tbody>';
      foreach ($gen() as $row) { echo '<tr><td>'.($row[0] && $fmt !== 'xls' ? '<img src="'.e($row[0]).'" width="48" height="48">' : e($row[0])).'</td>'; foreach (array_slice($row, 1) as $v) echo '<td>'.e($v).'</td>'; echo '</tr>'; }
      echo '</tbody></table>';
    };
    $pre = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><title>Central Inventory</title><style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}table{border-collapse:collapse;font-size:12px}h2{color:#0B1220}</style></head><body><h2>Central Inventory — DutyFreeBD / Mother Commerce</h2><p>'.e(now()->format('d M Y H:i')).'</p>';
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
  public function storeRemoveProducts($id) {
    DB::table('store_products')->where('store_id',$id)->delete();
    DB::table('stores')->where('id',$id)->update(['scan_status'=>'never','scan_total'=>0,'scan_published'=>0,'scan_draft'=>0,'scan_imported'=>0,'scan_failed'=>0,'scan_percent'=>0,'scan_note'=>null,'last_scan_at'=>null,'updated_at'=>now()]);
    return back()->with('ok','Imported products of this store were cleared.');
  }
  private function payload($s) {
    return ['id'=>$s->id, 'status'=>$s->scan_status, 'percent'=>(int)$s->scan_percent, 'total'=>(int)$s->scan_total, 'published'=>(int)$s->scan_published, 'draft'=>(int)$s->scan_draft, 'imported'=>(int)$s->scan_imported, 'failed'=>(int)$s->scan_failed, 'note'=>$s->scan_note, 'platform'=>$s->platform, 'last'=>$s->last_scan_at ? \Carbon\Carbon::parse($s->last_scan_at)->timezone('Asia/Dhaka')->format('d M Y, H:i') : null];
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
    try { $s = StoreScanner::step((int)$id); } finally { $lock->release(); }
    return response()->json($this->payload($s));
  }
}
