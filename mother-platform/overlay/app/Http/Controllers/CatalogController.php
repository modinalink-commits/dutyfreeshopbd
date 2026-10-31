<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class CatalogController extends Controller {
  public function products(Request $r) {
    $q = DB::table('products as p')->leftJoin('categories as c','c.id','=','p.category_id')->leftJoin('brands as b','b.id','=','p.brand_id')->select('p.*','c.name as category','b.name as brand')->orderByDesc('p.id');
    if ($r->filled('q')) $q->where(fn($w)=>$w->where('p.name','ilike','%'.$r->q.'%')->orWhere('p.sku','ilike','%'.$r->q.'%'));
    if ($r->filled('published')) $q->where('p.published', $r->published === '1');
    if ($r->boolean('missing_image')) $q->where(fn($w)=>$w->whereNull('p.image')->orWhere('p.image',''));
    if ($r->input('stock') === 'low') $q->whereBetween('p.stock',[1,5]);
    if ($r->input('stock') === 'out') $q->where('p.stock','<=',0);
    if ($r->filled('category_id')) $q->where('p.category_id', (int)$r->category_id);
    if ($r->filled('brand_id')) $q->where('p.brand_id', (int)$r->brand_id);
    $all = DB::table('products')->count(); $act = DB::table('products')->where('published',true)->count();
    $stats = [['Products', number_format($all), 'all stores'], ['Active', number_format($act), $all ? round($act/$all*100).'%' : '0%'], ['Low stock', DB::table('products')->whereBetween('stock',[1,5])->count(), 'needs buying'], ['Out of stock', DB::table('products')->where('stock','<=',0)->count(), 'hidden on site'], ['Reviews waiting', DB::table('reviews')->where('status','pending')->count(), 'approve']];
    $smsOn = (bool) DB::table('settings')->where('key','owner_phone')->value('value');
    return view('products', ['products'=>$q->paginate(50)->withQueryString(), 'stats'=>$stats, 'cats'=>DB::table('categories')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get(), 'smsOn'=>$smsOn]);
  }
  public function productForm($id = null) {
    return view('product_form', ['p'=>$id ? DB::table('products')->find($id) : null, 'cats'=>DB::table('categories')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get()]);
  }
  public function productSave(Request $r, $id = null) {
    $d = $r->validate(['name'=>'required|string|max:200','sku'=>'nullable|string|max:60|unique:products,sku'.($id?','.$id:''),'barcode'=>'nullable|string|max:60','size'=>'nullable|string|max:60','category_id'=>'nullable|integer','brand_id'=>'nullable|integer','price'=>'required|numeric|min:0','offer_price'=>'nullable|numeric|min:0','cost'=>'nullable|numeric|min:0','stock'=>'required|integer|min:0','description'=>'nullable|string|max:20000','slug'=>'nullable|string|max:200','meta_title'=>'nullable|string|max:200','meta_description'=>'nullable|string|max:500']);
    $d['cost'] = $d['cost'] ?? 0; $d['updated_at'] = now();
    $d['slug'] = \Illuminate\Support\Str::slug(($d['slug'] ?? null) ?: $d['name']) ?: null;
    if ($id) DB::table('products')->where('id',$id)->update($d);
    else { $d['created_at'] = now(); $d['published'] = false; $id = DB::table('products')->insertGetId($d); }
    \App\Support\Slugs::assignAll();
    return redirect('/admin/products');
  }
  public function productToggle($id) {
    $p = DB::table('products')->find($id); abort_unless($p, 404);
    $new = !$p->published;
    DB::transaction(function () use ($id, $new) {
      DB::table('products')->where('id',$id)->update(['published'=>$new,'updated_at'=>now()]);
      DB::table('product_status_logs')->insert(['product_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$new?'Published':'Unpublished','created_at'=>now(),'updated_at'=>now()]);
    });
    $this->notifyStatus([$p->name], $new);
    return back();
  }
  private function notifyStatus(array $names, bool $published) {
    $set = DB::table('settings')->whereIn('key',['owner_phone','manager_phones','store_name'])->pluck('value','key');
    $to = array_filter(array_map('trim', array_merge([$set['owner_phone'] ?? ''], explode(',', $set['manager_phones'] ?? ''))));
    if (!$to) return;
    $what = count($names) === 1 ? '"'.$names[0].'"' : count($names).' products';
    $body = "Product $what was ".($published ? 'PUBLISHED' : 'UNPUBLISHED').' by '.Auth::user()->name.' at '.now('Asia/Dhaka')->format('H:i').'. Store: '.($set['store_name'] ?? 'DutyFreeBD').'.';
    foreach ($to as $ph) \App\Support\Sms::queue($ph, $body, $published ? 'product_published' : 'product_unpublished');
  }
  public function productBulk(Request $r) {
    $d = $r->validate(['ids'=>'required|array|max:500','ids.*'=>'integer','action'=>'required|in:publish,unpublish']);
    $new = $d['action'] === 'publish'; $names = [];
    DB::transaction(function () use ($d, $new, &$names) {
      foreach (DB::table('products')->whereIn('id',$d['ids'])->where('published','!=',$new)->get() as $p) {
        DB::table('products')->where('id',$p->id)->update(['published'=>$new,'updated_at'=>now()]);
        DB::table('product_status_logs')->insert(['product_id'=>$p->id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$new?'Published':'Unpublished','created_at'=>now(),'updated_at'=>now()]);
        $names[] = $p->name;
      }
    });
    if ($names) $this->notifyStatus($names, $new);
    return back()->with('ok', count($names).' products '.($new?'published':'unpublished'));
  }
  public function productImport(Request $r) {
    $r->validate(['file'=>'required|file|max:10240']);
    $fh = fopen($r->file('file')->getRealPath(), 'r'); $head = fgetcsv($fh); if (!$head) return back()->withErrors(['file'=>'Empty file']);
    $head = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), $head);
    $n = 0; $bad = 0;
    $cat = fn($name) => $name === '' ? null : (DB::table('categories')->where('name',$name)->value('id') ?? DB::table('categories')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]));
    $brand = fn($name) => $name === '' ? null : (DB::table('brands')->where('name',$name)->value('id') ?? DB::table('brands')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]));
    while (($row = fgetcsv($fh)) !== false) {
      if (count($row) < count($head)) { $bad++; continue; }
      $x = array_combine($head, array_slice($row, 0, count($head)));
      if (empty($x['name']) || !is_numeric($x['price'] ?? null)) { $bad++; continue; }
      $data = ['name'=>mb_substr($x['name'],0,200), 'price'=>$x['price'], 'cost'=>is_numeric($x['cost'] ?? null) ? $x['cost'] : 0, 'offer_price'=>is_numeric($x['offer_price'] ?? null) ? $x['offer_price'] : null, 'stock'=>(int)($x['stock'] ?? 0), 'barcode'=>$x['barcode'] ?? null, 'size'=>$x['size'] ?? null, 'category_id'=>$cat(trim($x['category'] ?? '')), 'brand_id'=>$brand(trim($x['brand'] ?? '')), 'updated_at'=>now()];
      $sku = trim($x['sku'] ?? '');
      if ($sku !== '' && DB::table('products')->where('sku',$sku)->exists()) DB::table('products')->where('sku',$sku)->update($data);
      else DB::table('products')->insert($data + ['sku'=>$sku ?: null, 'slug'=>\Illuminate\Support\Str::slug($data['name']), 'published'=>false, 'created_at'=>now()]);
      $n++;
    }
    fclose($fh); \App\Support\Slugs::assignAll();
    return back()->with('ok', "$n products imported or updated".($bad ? ", $bad rows skipped" : '').'. Imported products stay unpublished until you publish them.');
  }
  public function importTemplate() {
    return response()->streamDownload(function () { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['name','sku','barcode','category','brand','size','cost','price','offer_price','stock']); fputcsv($o, ['Sample product','SKU-001','','Perfume','Brand X','100 ml','3100','4400','4299','10']); fclose($o); }, 'product-import-template.csv', ['Content-Type'=>'text/csv; charset=utf-8']);
  }
  public function taxonomy() {
    $pc = DB::table('products')->select('category_id', DB::raw('count(*) c'))->groupBy('category_id')->pluck('c','category_id');
    $bc = DB::table('products')->select('brand_id', DB::raw('count(*) c'))->groupBy('brand_id')->pluck('c','brand_id');
    return view('taxonomy', ['cats'=>DB::table('categories')->orderBy('position')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get(), 'pc'=>$pc, 'bc'=>$bc]);
  }
  public function categorySave(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120','parent_id'=>'nullable|integer','image'=>'nullable|string|max:500','position'=>'nullable|integer']);
    if (!empty($d['parent_id'])) { $depth = 1; $pid = $d['parent_id']; while ($pid && $depth < 5) { $pid = DB::table('categories')->where('id',$pid)->value('parent_id'); $depth++; } if ($depth > 3) return back()->withErrors(['name'=>'Only 3 levels: category, sub category, sub sub category']); }
    $d['position'] = $d['position'] ?? 0;
    DB::table('categories')->insert($d + ['created_at'=>now(),'updated_at'=>now()]); return back();
  }
  public function categoryToggle($id) { DB::table('categories')->where('id',$id)->update(['active'=>DB::raw('not active'),'updated_at'=>now()]); return back();
  }
  public function brandToggle($id) { DB::table('brands')->where('id',$id)->update(['active'=>DB::raw('not active'),'updated_at'=>now()]); return back();
  }
  public function brandSave(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120','position'=>'nullable|integer']);
    DB::table('brands')->insert(['name'=>$d['name'],'position'=>$d['position']??0,'created_at'=>now(),'updated_at'=>now()]); return back();
  }
  public function productsCsv() {
    return response()->streamDownload(function () {
      $f = fopen('php://output','w'); fputcsv($f,['ID','Name','SKU','Price','Cost','Stock','Published']);
      foreach (DB::table('products')->orderBy('id')->cursor() as $p) fputcsv($f,[$p->id,$p->name,$p->sku,$p->price,$p->cost,$p->stock,$p->published?'yes':'no']);
      fclose($f);
    }, 'products.csv', ['Content-Type'=>'text/csv']);
  }
}
