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
    return view('products', ['products'=>$q->paginate(50)->withQueryString()]);
  }
  public function productForm($id = null) {
    return view('product_form', ['p'=>$id ? DB::table('products')->find($id) : null, 'cats'=>DB::table('categories')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get()]);
  }
  public function productSave(Request $r, $id = null) {
    $d = $r->validate(['name'=>'required|string|max:200','sku'=>'nullable|string|max:60|unique:products,sku'.($id?','.$id:''),'category_id'=>'nullable|integer','brand_id'=>'nullable|integer','price'=>'required|numeric|min:0','cost'=>'nullable|numeric|min:0','stock'=>'required|integer|min:0']);
    $d['cost'] = $d['cost'] ?? 0; $d['updated_at'] = now();
    if ($id) DB::table('products')->where('id',$id)->update($d);
    else { $d['created_at'] = now(); $d['published'] = false; $id = DB::table('products')->insertGetId($d); }
    return redirect('/admin/products');
  }
  public function productToggle($id) {
    $p = DB::table('products')->find($id); abort_unless($p, 404);
    $new = !$p->published;
    DB::transaction(function () use ($id, $new) {
      DB::table('products')->where('id',$id)->update(['published'=>$new,'updated_at'=>now()]);
      DB::table('product_status_logs')->insert(['product_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$new?'Published':'Unpublished','created_at'=>now(),'updated_at'=>now()]);
    });
    return back();
  }
  public function taxonomy() {
    return view('taxonomy', ['cats'=>DB::table('categories')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get()]);
  }
  public function categorySave(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120','parent_id'=>'nullable|integer']);
    DB::table('categories')->insert($d + ['created_at'=>now(),'updated_at'=>now()]); return back();
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
