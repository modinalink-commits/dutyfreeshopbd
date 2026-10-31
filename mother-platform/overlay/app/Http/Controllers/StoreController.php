<?php
namespace App\Http\Controllers;
use App\Console\Commands\MotherSyncFeed;
use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class StoreController extends Controller {
  /** Cache helper: stores a serialized string so cached collections/objects come back intact. */
  public static function rem(string $k, int $ttl, \Closure $fn) {
    $raw = Cache::get($k);
    if (is_string($raw) && ($v = @unserialize($raw)) !== false) return $v;
    $v = $fn(); Cache::put($k, serialize($v), $ttl); return $v;
  }
  public static function slugOf(string $name): string {
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-'); $s = trim(substr($s, 0, 60), '-');
    return $s !== '' ? $s : 'product';
  }
  public static function url($p): string { return '/p/'.($p->ext_id ?? 'm'.$p->id).'-'.self::slugOf($p->name).'/'; }
  public static function set(): array {
    return self::rem('store.settings', 300, function () {
      $cur = DB::table('settings')->pluck('value','key'); $o = [];
      foreach (SiteController::schema() as [$k,$l,$t,$d,$g]) $o[$k] = $cur[$k] ?? $d;
      return $o;
    });
  }
  private function cats() {
    return self::rem('store.cats', 300, fn() => DB::table('categories')->whereNotNull('slug')->where('active',true)->orderBy('position')->get()->keyBy('slug'));
  }
  private function base(array $x = []): array {
    $s = self::set(); $wa = preg_replace('/\D/', '', $s['whatsapp_number']); if (str_starts_with($wa, '0')) $wa = '88'.$wa;
    return $x + ['s'=>$s, 'cats'=>$this->cats(), 'wa'=>$wa];
  }
  private function q() { return DB::table('products')->where('published',true); }
  private function sorted($q, $sort) {
    return match ($sort) { 'price_asc'=>$q->orderBy('price'), 'price_desc'=>$q->orderByDesc('price'), 'popular'=>$q->orderByDesc('stock'), default=>$q->orderByDesc('id') };
  }
  public function home() {
    $s = self::set();
    $data = self::rem('store.home', 300, function () {
      $sec = [];
      foreach ($this->cats() as $c) $sec[] = [$c, DB::table('products')->where('published',true)->where('category_id',$c->id)->orderByDesc('id')->limit(12)->get()];
      $top = DB::table('order_items as i')->join('products as p','p.id','=','i.product_id')->where('p.published',true)->select('p.*', DB::raw('sum(i.qty) q'))->groupBy('p.id')->orderByDesc('q')->limit(12)->get();
      return ['new'=>DB::table('products')->where('published',true)->orderByDesc('id')->limit(12)->get(), 'sec'=>$sec, 'top'=>$top, 'slides'=>DB::table('sliders')->where('active',true)->orderBy('position')->get(), 'count'=>DB::table('products')->where('published',true)->count()];
    });
    if ($data['count'] === 0) return response()->view('coming', [], 503)->header('Retry-After','3600');
    $live = $s['home_live_orders'] === '1' ? $this->liveOrders() : collect();
    return response()->view('store.home', $this->base($data + ['live'=>$live, 'title'=>$s['site_title'], 'desc'=>$s['meta_description'] ?: 'ডিউটি ফ্রি দামে অরিজিনাল ইমপোর্টেড পারফিউম, আতর, সিগারেট, ভেপ, স্কিন কেয়ার ও ডেইলি নিডস। অর্ডার করুন, সারা বাংলাদেশে হোম ডেলিভারি।', 'canon'=>url('/').'/']))->header('Cache-Control','public, max-age=120');
  }
  private function liveOrders() {
    return self::rem('store.live', 60, fn() => DB::table('orders as o')->join('order_items as i','i.order_id','=','o.id')->whereNotIn('o.status',['Cancel'])->where('o.created_at','>=',now()->subDays(3))->select('o.customer_name','o.district','o.created_at','i.name')->orderByDesc('o.id')->limit(10)->get()->map(function ($r) { $n = trim($r->customer_name); $r->masked = mb_substr($n,0,1).str_repeat('•', max(2, min(5, mb_strlen($n)-1))); return $r; }));
  }
  public function category(Request $r, string $slug) {
    $c = $this->cats()[$slug] ?? null;
    if (!$c) { $page = DB::table('pages')->where('slug',$slug)->where('active',true)->first(); if ($page) return $this->page($page); return $this->notFound(); }
    $s = self::set(); $per = max(12, (int)$s['per_page']);
    $q = $this->q()->where('category_id',$c->id); if ($r->filled('sub')) $q->where('sub',$r->sub);
    $items = $this->sorted($q, $r->input('sort', $s['default_sort']))->paginate($per)->withQueryString();
    $subs = DB::table('products')->where('published',true)->where('category_id',$c->id)->whereNotNull('sub')->select('sub', DB::raw('count(*) n'))->groupBy('sub')->orderByDesc('n')->get();
    return response()->view('store.category', $this->base(['c'=>$c, 'items'=>$items, 'subs'=>$subs, 'title'=>$c->title ?: $c->name.' | DutyFree Shop BD', 'desc'=>$c->meta_description, 'canon'=>url('/'.$slug).'/']))->header('Cache-Control','public, max-age=120');
  }
  public function product(string $key) {
    if (!preg_match('/^(m?)(\d+)(?:-(.*))?$/', $key, $m)) return $this->notFound();
    $p = $m[1] === 'm' ? $this->q()->where('id',(int)$m[2])->first() : $this->q()->where('ext_id',(int)$m[2])->first();
    if (!$p) return $this->notFound();
    if (rtrim(request()->getPathInfo(),'/').'/' !== self::url($p)) return redirect(self::url($p), 301);
    $c = DB::table('categories')->find($p->category_id);
    $rel = $this->q()->where('category_id',$p->category_id)->where('id','!=',$p->id)->orderByRaw('(sub = ?) desc', [$p->sub ?? ''])->orderByDesc('id')->limit(12)->get();
    $districts = self::rem('store.districts', 600, fn() => DB::table('districts')->where('active',true)->orderBy('name')->get(['name','delivery_charge']));
    DB::table('product_views')->insert(['product_id'=>$p->id,'day'=>now('Asia/Dhaka')->toDateString(),'created_at'=>now(),'updated_at'=>now()]);
    $price = (float)$p->price;
    return response()->view('store.product', $this->base(['p'=>$p, 'c'=>$c, 'rel'=>$rel, 'districts'=>$districts, 'title'=>$p->name.' দাম ৳ '.number_format($price).' | '.($c->name ?? 'Shop').' | DutyFree Shop BD', 'desc'=>$p->name.' – দাম ৳ '.number_format($price).'। অরিজিনাল ইমপোর্টেড পণ্য, অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি। DutyFree Shop BD.', 'canon'=>url(self::url($p)), 'ogimg'=>$p->image]))->header('Cache-Control','public, max-age=120');
  }
  public function search(Request $r) {
    $t = trim(mb_substr((string)$r->q, 0, 80)); $items = collect();
    if ($t !== '') { $items = $this->q()->where(fn($w) => $w->where('name','ilike','%'.$t.'%')->orWhere('sub','ilike','%'.$t.'%'))->orderByDesc('id')->paginate(48)->withQueryString(); DB::table('product_views')->insert(['term'=>$t,'day'=>now('Asia/Dhaka')->toDateString(),'created_at'=>now(),'updated_at'=>now()]); }
    return response()->view('store.search', $this->base(['t'=>$t, 'items'=>$items, 'title'=>($t ? $t.' – ' : '').'Search | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/search'), 'noindex'=>true]));
  }
  public function order(Request $r) {
    $ip = $r->ip();
    $p = $this->q()->where('id',(int)$r->input('product'))->first(); if (!$p) return $this->notFound();
    $back = fn($m) => redirect(self::url($p).'?e='.rawurlencode($m).'#order');
    if (DB::table('blocked_ips')->where('ip',$ip)->exists()) return $back('অনুগ্রহ করে কল করে অর্ডার করুন।');
    $v = \Illuminate\Support\Facades\Validator::make($r->all(), ['qty'=>'required|integer|min:1|max:50','name'=>'required|string|max:120','phone'=>['required','regex:/^(\+?88)?01[3-9]\d{8}$/'],'address'=>'required|string|max:500','district'=>'nullable|string|max:60','note'=>'nullable|string|max:500','website'=>'max:0'], ['phone.regex'=>'সঠিক মোবাইল নম্বর লিখুন (01XXXXXXXXX)','required'=>'নাম, মোবাইল ও ঠিকানা লিখুন।']);
    if ($v->fails()) return $back($v->errors()->first());
    $d = $v->validated();
    $phone = substr(preg_replace('/\D/','',$d['phone']), -11);
    $charge = (float)(DB::table('districts')->where('name',$d['district'] ?? '')->value('delivery_charge') ?? 0);
    $id = DB::transaction(function () use ($d, $p, $phone, $charge, $ip) {
      $cust = DB::table('customers')->where('phone',$phone)->value('id') ?? DB::table('customers')->insertGetId(['name'=>$d['name'],'phone'=>$phone,'ip'=>$ip,'created_at'=>now(),'updated_at'=>now()]);
      $sub = (float)$p->price * $d['qty'];
      $id = DB::table('orders')->insertGetId(['invoice'=>'TMP'.uniqid(),'store_id'=>DB::table('stores')->value('id'),'customer_id'=>$cust,'customer_name'=>$d['name'],'phone'=>$phone,'address'=>$d['address'],'district'=>$d['district'] ?? null,'source'=>'Web Site','shipping'=>$charge,'total'=>$sub + $charge,'status'=>'New','note'=>$d['note'] ?? null,'payment_method'=>'COD','ip'=>$ip,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('orders')->where('id',$id)->update(['invoice'=>'INV-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
      DB::table('order_items')->insert(['order_id'=>$id,'product_id'=>$p->id,'name'=>$p->name,'qty'=>$d['qty'],'price'=>$p->price,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>null,'user_name'=>'Website','from_status'=>null,'to_status'=>'New','note'=>'Order placed on website','created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    Cache::forget('store.live');
    $inv = DB::table('orders')->where('id',$id)->value('invoice');
    return redirect('/track?invoice='.$inv.'&phone='.$phone.'&new=1');
  }
  public function track(Request $r) {
    $o = null; $logs = collect();
    if ($r->filled('invoice') && $r->filled('phone')) {
      $o = DB::table('orders')->where('invoice', strtoupper(trim($r->invoice)))->where('phone', substr(preg_replace('/\D/','',$r->phone), -11))->first();
      if ($o) $logs = DB::table('order_status_logs')->where('order_id',$o->id)->orderBy('id')->get(['to_status','created_at']);
    }
    return response()->view('store.track', $this->base(['o'=>$o, 'logs'=>$logs, 'items'=>$o ? DB::table('order_items')->where('order_id',$o->id)->get() : collect(), 'title'=>'Order Tracking | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/track'), 'noindex'=>true]));
  }
  private function page($page) {
    return response()->view('store.page', $this->base(['page'=>$page, 'title'=>($page->meta_title ?: $page->title).' | DutyFree Shop BD', 'desc'=>$page->meta_description, 'canon'=>url('/'.$page->slug).'/']));
  }
  public function notFound() {
    return response()->view('store.missing', $this->base(['title'=>'পেজ পাওয়া যায়নি | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/'), 'noindex'=>true]), 404);
  }
  public function sitemap() {
    $xml = self::rem('store.sitemap', 3600, function () {
      $u = fn($loc, $img = null) => '<url><loc>'.e($loc).'</loc><changefreq>daily</changefreq>'.($img ? '<image:image><image:loc>'.e($img).'</image:loc></image:image>' : '').'</url>';
      $o = $u(url('/').'/');
      foreach ($this->cats() as $c) $o .= $u(url('/'.$c->slug).'/');
      foreach (DB::table('products')->where('published',true)->orderBy('id')->cursor() as $p) $o .= $u(url(self::url($p)), $p->image);
      return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'.$o.'</urlset>';
    });
    return response($xml, 200, ['Content-Type'=>'application/xml; charset=utf-8']);
  }
}
