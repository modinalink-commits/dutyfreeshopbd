<?php
namespace App\Http\Controllers;
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
  /** Unix end time of a campaign: the admin-set Dhaka time, or the next Dhaka midnight when blank. Returns [endTs, fixed]. */
  public static function endAt(?string $v): array {
    $v = trim((string)$v);
    if ($v !== '') { try { return [\Carbon\Carbon::parse($v, 'Asia/Dhaka')->timestamp, true]; } catch (\Throwable $e) {} }
    return [now('Asia/Dhaka')->addDay()->startOfDay()->timestamp, false];
  }
  public static function slugOf(string $name): string {
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-'); $s = trim(substr($s, 0, 60), '-');
    return $s !== '' ? $s : 'product';
  }
  /** Canonical product URL: /main-category/secondary-category/product-name (legacy /p/ID-name only when a product has no slug yet). */
  public static function url($p): string {
    if (!empty($p->path_slug) && !empty($p->category_id)) {
      $cs = self::rem('store.catslugs', 300, fn() => DB::table('categories')->whereNotNull('slug')->pluck('slug','id')->all())[$p->category_id] ?? null;
      if ($cs) return '/'.$cs.'/'.\App\Support\Slugs::subSlug($p->sub ?? null).'/'.$p->path_slug;
    }
    return '/p/'.($p->ext_id ?? 'm'.$p->id).'-'.self::slugOf($p->name).'/';
  }
  public static function set(): array {
    $o = self::rem('store.settings2', 300, function () {
      $cur = DB::table('settings')->pluck('value','key'); $o = [];
      foreach (SiteController::schema() as [$k,$l,$t,$d,$g]) $o[$k] = $cur[$k] ?? $d;
      return $o;
    });
    // a cached copy from before a deploy may lack newer keys: fill them with their defaults
    foreach (SiteController::schema() as [$k,$l,$t,$d,$g]) if (!array_key_exists($k, $o)) $o[$k] = $d;
    return $o;
  }
  /** Discount percent shown on cards (from the compare price). */
  public static function dealBase(float $min = 0) {
    $ex = DB::table('deal_flags')->where('mode','exclude')->pluck('product_id')->all();
    $pin = array_map('intval', DB::table('deal_flags')->where('mode','pin')->pluck('product_id')->all());
    $q = DB::table('products')->where('published',true)->whereColumn('compare_price','>','price')->where('compare_price','>',0);
    if ($ex) $q->whereNotIn('id', $ex);
    $q->where(function ($w) use ($min, $pin) { $w->whereRaw('(compare_price - price) / compare_price * 100 >= ?', [$min]); if ($pin) $w->orWhereIn('id', $pin); });
    if ($pin) $q->orderByRaw('case when id in ('.implode(',', $pin).') then 0 else 1 end');
    return $q->orderByRaw('(compare_price - price) / compare_price desc')->orderByDesc('id');
  }
  public static function off($p): int { return $p->compare_price && $p->compare_price > $p->price ? (int)round(($p->compare_price - $p->price) / $p->compare_price * 100) : 0; }
  public static function tk($v): string { return number_format((float)$v).' TK'; }
  private function cats() {
    return self::rem('store.cats', 300, fn() => DB::table('categories')->whereNotNull('slug')->where('active',true)->orderBy('position')->get()->keyBy('slug'));
  }
  /** Top brands (sub groups) per category, used by the menu dropdowns, brand tabs and brand chips. */
  private function subs() {
    return self::rem('store.subs', 300, function () {
      $o = [];
      foreach (DB::table('products')->where('published',true)->whereNotNull('sub')->select('category_id','sub', DB::raw('count(*) n'))->groupBy('category_id','sub')->orderByDesc('n')->get() as $r) $o[$r->category_id][] = $r;
      return $o;
    });
  }
  private function rules(): array {
    return self::rem('store.rules', 300, fn() => DB::table('discount_rules')->where('enabled',true)->get()->mapWithKeys(fn($r) => [$r->key => json_decode($r->config, true) ?: []])->all());
  }
  protected function base(array $x = []): array {
    $s = self::set(); $wa = preg_replace('/\D/', '', $s['whatsapp_number']); if (str_starts_with($wa, '0')) $wa = '88'.$wa;
    $areas = []; foreach ([1,2,3] as $i) if (trim($s["ship_{$i}_name"]) !== '') $areas[$i] = ['n'=>$s["ship_{$i}_name"], 'c'=>(float)$s["ship_{$i}_charge"]];
    return $x + ['s'=>$s, 'cats'=>$this->cats(), 'subs'=>$this->subs(), 'wa'=>$wa, 'areas'=>$areas, 'rules'=>$this->rules()];
  }
  private function q() { return DB::table('products')->where('published',true); }
  private function sorted($q, $sort) {
    return match ($sort) { 'price_asc'=>$q->orderBy('price'), 'price_desc'=>$q->orderByDesc('price'), 'popular'=>$q->orderByDesc('stock'), default=>$q->orderByDesc('id') };
  }
  public function home() {
    $s = self::set();
    $data = self::rem('store.home6', 300, function () use ($s) {
      $pub = fn() => DB::table('products')->where('published',true);
      $sold = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.status','!=','Cancel')->where('o.created_at','>=',now()->subDays(30))->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q')->pluck('q','pid');
      $deals = self::dealBase((float)($s['deal_min_off'] ?? 0))->limit(max(1,(int)($s['deal_max'] ?? 12)))->get()
        ->map(function ($p) use ($sold) { $p->sold = (int)($sold[$p->id] ?? 0); return $p; });
      $top = DB::table('order_items as i')->join('products as p','p.id','=','i.product_id')->where('p.published',true)->select('p.*', DB::raw('sum(i.qty) q'))->groupBy('p.id')->orderByDesc('q')->limit(12)->get();
      $topLabel = 'Top Selling Products';
      if ($top->count() < 6) {
        $more = DB::table('product_views as v')->join('products as p','p.id','=','v.product_id')->where('p.published',true)->whereNotIn('p.id', $top->pluck('id'))->where('v.created_at','>=',now()->subDays(30))->select('p.*', DB::raw('count(*) q'))->groupBy('p.id')->orderByDesc('q')->limit(6 - $top->count())->get();
        if ($more->count()) { $top = $top->concat($more); $topLabel = 'Popular Products'; }
      }
      $sec = []; $i = 0;
      foreach ($this->cats() as $c) { $sec[] = [$c, $pub()->where('category_id',$c->id)->orderByDesc('id')->limit($i < 2 ? 12 : 6)->get()]; $i++; }
      $offers = [];
      foreach ($this->cats() as $c) { $mx = (int)$pub()->where('category_id',$c->id)->whereColumn('compare_price','>','price')->where('compare_price','>',0)->max(DB::raw('round((compare_price - price) / compare_price * 100)')); if ($mx > 0) $offers[] = [$c, $mx]; }
      usort($offers, fn($a, $b) => $b[1] <=> $a[1]);
      return ['new'=>$pub()->orderByDesc('id')->limit(12)->get(), 'deals'=>$deals, 'top'=>$top, 'topLabel'=>$topLabel, 'sec'=>$sec, 'offers'=>array_slice($offers, 0, 2), 'slides'=>DB::table('sliders')->where('active',true)->whereNotNull('image')->where(fn($q)=>$q->whereNull('type')->orWhere('type','main'))->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('position')->get(), 'offerSlides'=>DB::table('sliders')->where('active',true)->whereNotNull('image')->where('type','offer')->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('position')->get(), 'stores'=>DB::table('stores')->where('active',true)->orderBy('id')->get(['id','name','domain','scan_published'])->all(), 'best'=>DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->join('products as p','p.id','=','i.product_id')->where('p.published',true)->where('o.status','!=','Cancel')->where('o.created_at','>=',now()->subDays(60))->select('p.*', DB::raw('sum(i.qty) q'))->groupBy('p.id')->orderByDesc('q')->limit(12)->get(), 'count'=>$pub()->count(), 'maxOff'=>$deals->count() ? self::off($deals[0]) : 0];
    });
    if ($data['count'] === 0) return response()->view('coming', [], 503)->header('Retry-After','3600');
    $live = $s['home_live_orders'] === '1' ? $this->liveOrders() : collect();
    return response()->view('store.home', $this->base($data + ['live'=>$live, 'title'=>$s['site_title'], 'desc'=>$s['meta_description'] ?: 'ডিউটি ফ্রি দামে অরিজিনাল ইমপোর্টেড পারফিউম, আতর, সিগারেট, ভেপ, স্কিন কেয়ার ও ডেইলি নিডস। অর্ডার করুন, সারা বাংলাদেশে হোম ডেলিভারি।', 'canon'=>url('/').'/', 'home'=>true]))->header('Cache-Control','public, max-age=60');
  }
  /** Real recent orders, customer names masked (first letter + ***). */
  private function liveOrders() {
    return self::rem('store.live4', 60, fn() => DB::table('orders as o')->join('order_items as i','i.order_id','=','o.id')->leftJoin('products as p','p.id','=','i.product_id')->whereNotIn('o.status',['Cancel','Incomplete'])->where('o.created_at','>=',now()->subDays(3))
      ->select(DB::raw("'' as customer_name"),DB::raw("'' as district"),'o.shipping','o.created_at','i.name','i.qty','i.price','p.image','p.id as pid','p.ext_id','p.name as pname','p.category_id as pcat','p.sub as psub','p.path_slug as pslug')->orderByDesc('o.id')->limit(6)->get()
      ->map(function ($r) { $r->masked = ''; return $r; }));
  }
  public function category(Request $r, string $slug, ?string $subSlug = null) {
    $c = $this->cats()[$slug] ?? null;
    if (!$c) { if ($subSlug !== null) return $this->notFound(); $page = DB::table('pages')->where('slug',$slug)->where('active',true)->first(); if ($page) return $this->page($page); return $this->notFound(); }
    if ($subSlug === null && $r->filled('sub')) { // old ?sub=LM links -> /category/lm (301)
      $rest = $r->except('sub'); return redirect('/'.$slug.'/'.\App\Support\Slugs::subSlug($r->sub).($rest ? '?'.http_build_query($rest) : ''), 301);
    }
    $s = self::set(); $per = max(12, (int)$s['per_page']);
    $q = $this->q()->where('category_id',$c->id); $subName = null;
    if ($subSlug !== null) {
      $q->whereRaw(\App\Support\Slugs::subSql().' = ?', [$subSlug]);
      $subName = (clone $q)->whereNotNull('sub')->value('sub') ?: ((clone $q)->exists() ? 'Other' : null);
      if ($subName === null) return $this->notFound();
    }
    if (is_numeric($r->min)) $q->where('price','>=',(float)$r->min); if (is_numeric($r->max)) $q->where('price','<=',(float)$r->max);
    $q = $q->orderByRaw('coalesce(sub, \'~\')');
    $items = $this->sorted($q, $r->input('sort', $s['default_sort']))->paginate($per)->withQueryString();
    $canon = url('/'.$slug).($subSlug !== null ? '/'.$subSlug : '/');
    $title = $subName ? $subName.' – '.$c->name.' | DutyFree Shop BD' : ($c->title ?: $c->name.' | DutyFree Shop BD');
    return response()->view('store.category', $this->base(['c'=>$c, 'subName'=>$subName, 'subSlug'=>$subSlug, 'items'=>$items, 'groups'=>collect($items->items())->groupBy(fn($p) => $p->sub ?: $c->name), 'title'=>$title, 'desc'=>$subName ? $subName.' – original imported products in '.$c->name.' at DutyFree Shop BD. Cash on delivery, nationwide delivery.' : $c->meta_description, 'canon'=>$canon]))->header('Cache-Control','public, max-age=120');
  }
  /** /category/secondary-category */
  public function categorySub(Request $r, string $cat, string $sub) { return $this->category($r, $cat, $sub); }
  /** /category/secondary-category/product-name */
  public function productByPath(string $cat, string $sub, string $slug) {
    $c = $this->cats()[$cat] ?? null; if (!$c) return $this->notFound();
    $cands = $this->q()->where('category_id',$c->id)->where('path_slug',$slug)->orderBy('id')->get(); if ($cands->isEmpty()) return $this->notFound();
    $p = $cands->first(fn($x) => \App\Support\Slugs::subSlug($x->sub) === $sub) ?? ($cands->count() === 1 ? $cands->first() : null);
    if (!$p) return $this->notFound();
    if (rtrim(request()->getPathInfo(),'/') !== self::url($p)) return redirect(self::url($p), 301);
    return $this->renderProduct($p);
  }
  /** Legacy /p/ID-name URLs: 301 to the canonical hierarchy URL. */
  public function product(string $key) {
    if (!preg_match('/^(m?)(\d+)(?:-(.*))?$/', $key, $m)) return $this->notFound();
    $p = $m[1] === 'm' ? $this->q()->where('id',(int)$m[2])->first() : $this->q()->where('ext_id',(int)$m[2])->first();
    if (!$p) return $this->notFound();
    if (empty($p->path_slug)) { \App\Support\Slugs::assignAll(); $p = $this->q()->where('id',$p->id)->first(); }
    $u = self::url($p);
    if (!str_starts_with($u, '/p/')) return redirect($u, 301);
    return $this->renderProduct($p);
  }
  public function previewProduct(int $id) {
    $p = DB::table('products')->find($id); abort_unless($p, 404);
    return $this->renderProduct($p, true);
  }
  private function renderProduct($p, bool $preview = false) {
    $c = DB::table('categories')->find($p->category_id);
    $rel = $this->q()->where('category_id',$p->category_id)->where('id','!=',$p->id)->orderByRaw('(sub = ?) desc', [$p->sub ?? ''])->orderByDesc('id')->limit(11)->get();
    $reviews = DB::table('reviews')->where('product_id',$p->id)->where('status','approved')->orderByDesc('id')->limit(20)->get();
    DB::table('product_views')->insert(['product_id'=>$p->id,'day'=>now('Asia/Dhaka')->toDateString(),'created_at'=>now(),'updated_at'=>now()]);
    $price = (float)$p->price;
    $gallery = DB::table('product_images')->where('product_id',$p->id)->orderBy('position')->orderBy('id')->pluck('path')->all();
    return response()->view('store.product', $this->base(['preview'=>$preview, 'gallery'=>$gallery, 'p'=>$p, 'c'=>$c, 'rel'=>$rel, 'reviews'=>$reviews, 'title'=>$p->name.' দাম ৳ '.number_format($price).' | '.($c->name ?? 'Shop').' | DutyFree Shop BD', 'desc'=>$p->name.' – দাম ৳ '.number_format($price).'। অরিজিনাল ইমপোর্টেড পণ্য, অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি। DutyFree Shop BD.', 'canon'=>url(self::url($p)), 'ogimg'=>$p->image ? (preg_match('#^https?://#i', $p->image) ? $p->image : url($p->image)) : url('/og.png')]))->header('Cache-Control','public, max-age=120');
  }
  public function search(Request $r) {
    $t = trim(mb_substr((string)$r->q, 0, 80)); $items = collect();
    if ($r->boolean('deals')) { $items = self::dealBase(0)->paginate(48)->withQueryString(); $t = 'Flash Sale'; }
    elseif ($t !== '') { $items = $this->q()->where(fn($w) => $w->where('name','ilike','%'.$t.'%')->orWhere('sub','ilike','%'.$t.'%'))->orderByDesc('id')->paginate(48)->withQueryString(); DB::table('product_views')->insert(['term'=>$t,'day'=>now('Asia/Dhaka')->toDateString(),'created_at'=>now(),'updated_at'=>now()]); }
    return response()->view('store.search', $this->base(['t'=>$t, 'items'=>$items, 'title'=>($t ? $t.' – ' : '').'Search | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/search'), 'noindex'=>true]));
  }
  /** Live search suggestions: products (with image), brands, categories and stores. */
  public function suggest(Request $r) {
    $t = trim(mb_substr((string)$r->q, 0, 60)); if (mb_strlen($t) < 2) return response()->json([]);
    $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $t).'%';
    $out = [];
    foreach ($this->q()->where(fn($w) => $w->where('name','ilike',$like)->orWhere('sku','ilike',$like)->orWhere('sub','ilike',$like))->orderByDesc('id')->limit(6)->get(['id','ext_id','name','price','image','sub']) as $p) $out[] = ['t'=>'p','n'=>$p->name,'u'=>self::url($p),'i'=>$p->image,'x'=>number_format($p->price).' TK'];
    foreach ($this->cats() as $c) if (stripos($c->name, $t) !== false) $out[] = ['t'=>'c','n'=>$c->name,'u'=>'/'.$c->slug.'/'];
    foreach (DB::table('products')->where('published',true)->where('sub','ilike',$like)->select('category_id','sub')->distinct()->limit(3)->get() as $b) { $c = $this->cats()->firstWhere('id',$b->category_id); if ($c) $out[] = ['t'=>'b','n'=>$b->sub,'u'=>'/'.$c->slug.'/?sub='.rawurlencode($b->sub)]; }
    return response()->json($out)->header('Cache-Control','public, max-age=60');
  }
  public function checkout() {
    return response()->view('store.checkout', $this->base(['pays'=>\App\Http\Controllers\PaymentController::active(),'title'=>'Checkout | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/checkout'), 'noindex'=>true, 'grey'=>true]));
  }
  public function wishlist() {
    return response()->view('store.wishlist', $this->base(['title'=>'Wishlist | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/wishlist'), 'noindex'=>true]));
  }
  /** Parse "12:2,15:1" into [product_id => qty]. */
  private function parseItems($raw): array {
    $o = [];
    if (is_array($raw)) { foreach ($raw as $id => $q) if ((int)$id > 0 && (int)$q > 0) $o[(int)$id] = min(50, (int)$q); return array_slice($o, 0, 30, true); }
    foreach (explode(',', (string)$raw) as $part) { [$id, $q] = array_pad(explode(':', $part), 2, 1); if ((int)$id > 0 && (int)$q > 0) $o[(int)$id] = min(50, (int)$q); }
    return array_slice($o, 0, 30, true);
  }
  /** One price calculation for the cart, the checkout and the saved order. */
  private function quote(array $want, int $area, string $code, ?string $phone = null): array {
    $b = $this->base(); $rules = $b['rules'];
    $rows = $want ? $this->q()->whereIn('id', array_keys($want))->get(['id','ext_id','name','price','compare_price','image','stock']) : collect();
    $items = []; $sub = 0; $qty = 0;
    foreach ($rows as $p) { $n = $want[$p->id]; $items[] = ['id'=>$p->id, 'name'=>$p->name, 'image'=>$p->image, 'url'=>self::url($p), 'price'=>(float)$p->price, 'compare'=>(float)$p->compare_price, 'qty'=>$n, 'line'=>(float)$p->price * $n]; $sub += (float)$p->price * $n; $qty += $n; }
    $ship = isset($b['areas'][$area]) ? $b['areas'][$area]['c'] : (count($b['areas']) ? reset($b['areas'])['c'] : 0);
    if (isset($rules['freedelivery']) && $sub >= (float)($rules['freedelivery']['Free over TK'] ?? PHP_INT_MAX)) $ship = 0;
    $disc = 0; $notes = [];
    if (isset($rules['bundle']) && $qty >= (int)($rules['bundle']['Minimum products'] ?? 2) && (float)($rules['bundle']['Bundle %'] ?? 0) > 0) { $d = round($sub * (float)$rules['bundle']['Bundle %'] / 100); if ($d > $disc) { $disc = $d; $notes = ['Bundle '.(float)$rules['bundle']['Bundle %'].'%']; } }
    if (isset($rules['amount'])) { $pct = 0; foreach (['Over 20000 TK %'=>20000,'Over 10000 TK %'=>10000,'Over 5000 TK %'=>5000] as $k => $min) if ($sub > $min) { $pct = (float)($rules['amount'][$k] ?? 0); break; }
      $d = min(round($sub * $pct / 100), (float)($rules['amount']['Max discount per order TK'] ?? PHP_INT_MAX)); if ($d > $disc) { $disc = $d; $notes = ['Order amount '.$pct.'%']; } }
    $cmsg = ''; $cOk = null;
    if ($code !== '') {
      $c = DB::table('coupons')->whereRaw('upper(code) = ?', [mb_strtoupper($code)])->where('active',true)->first();
      $today = now('Asia/Dhaka')->toDateString();
      if (!$c || ($c->valid_from && $c->valid_from > $today) || ($c->valid_to && $c->valid_to < $today) || ($c->usage_limit && $c->used_count >= $c->usage_limit)) $cmsg = 'Coupon code is not valid';
      elseif ($sub < (float)$c->min_order) $cmsg = 'Minimum order for this coupon: '.self::tk($c->min_order);
      elseif ($phone && $c->per_customer && DB::table('orders')->where('phone',$phone)->whereRaw('upper(coupon) = ?', [mb_strtoupper($c->code)])->where('status','!=','Cancel')->count() >= $c->per_customer) $cmsg = 'You have already used this coupon';
      else { $d = $c->type === 'fixed' ? (float)$c->value : round($sub * (float)$c->value / 100); if ($c->max_discount) $d = min($d, (float)$c->max_discount); $disc += $d; $notes[] = 'Coupon '.$c->code; $cOk = $c->code; $cmsg = 'Coupon applied: '.self::tk($d).' off'; }
    }
    $disc = min($disc, $sub);
    return ['items'=>$items, 'sub'=>$sub, 'ship'=>$items ? $ship : 0, 'disc'=>$disc, 'total'=>$items ? $sub + $ship - $disc : 0, 'coupon'=>$cOk, 'coupon_msg'=>$cmsg, 'notes'=>$notes];
  }
  public function cartItems(Request $r) {
    return response()->json($this->quote($this->parseItems($r->input('ids')), (int)$r->input('area', 1), trim(mb_substr((string)$r->input('code'), 0, 40))))->header('Cache-Control','no-store');
  }
  public function order(Request $r) {
    $ip = $r->ip();
    $want = $r->filled('product') ? [(int)$r->input('product') => max(1, min(50, (int)$r->input('qty', 1)))] : $this->parseItems($r->input('items', []));
    $back = fn($m) => redirect('/checkout?e='.rawurlencode($m));
    if (!$want) return $back('Your cart is empty');
    if (DB::table('blocked_ips')->where('ip',$ip)->exists()) return $back('অনুগ্রহ করে কল করে অর্ডার করুন।');
    $v = \Illuminate\Support\Facades\Validator::make($r->all(), ['name'=>'required|string|max:120','phone'=>['required','regex:/^(\+?88)?01[3-9]\d{8}$/'],'email'=>'nullable|email|max:120','address'=>'required|string|max:500','area'=>'nullable|integer','district'=>'nullable|string|max:60','note'=>'nullable|string|max:500','coupon'=>'nullable|string|max:40','website'=>'max:0','pay'=>'nullable|in:cod,bank,bkash,nagad','pay_account'=>'nullable|string|max:80','pay_txn'=>['nullable','string','max:60','regex:/^[A-Za-z0-9\-_\/ ]+$/'],'pay_proof'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:3072'], ['phone.regex'=>'সঠিক মোবাইল নম্বর লিখুন (01XXXXXXXXX)','required'=>'মোবাইল নম্বর, নাম ও ঠিকানা লিখুন।','email'=>'সঠিক ইমেইল লিখুন অথবা খালি রাখুন।']);
    if ($v->fails()) return $back($v->errors()->first());
    $d = $v->validated();
    $pm = ['label'=>'COD','txn'=>null,'acct'=>null,'proof'=>null,'status'=>null];
    $pk = $d['pay'] ?? 'cod';
    if ($pk !== 'cod') {
      if (!DB::table('payment_methods')->where('kind',$pk)->where('active',true)->exists()) return $back('এই পেমেন্ট মেথডটি এখন চালু নেই।');
      $tx = trim((string)($d['pay_txn'] ?? ''));
      if (strlen($tx) < 4) return $back('Transaction ID / Reference দিন');
      if (DB::table('orders')->where('pay_txn',$tx)->whereIn('pay_status',['Verification Pending','Verified'])->exists()) return $back('এই Transaction ID আগে ব্যবহার হয়েছে। সঠিক ID দিন বা WhatsApp-এ জানান।');
      $pm = ['label'=>PaymentController::KINDS[$pk],'txn'=>$tx,'acct'=>$d['pay_account'] ?? null,'proof'=>$r->hasFile('pay_proof') ? $r->file('pay_proof')->store('pay-proofs','local') : null,'status'=>'Verification Pending'];
    }
    $phone = substr(preg_replace('/\D/','',$d['phone']), -11);
    $b = $this->base(); $area = (int)($d['area'] ?? 1);
    $qt = $this->quote($want, $area, trim((string)($d['coupon'] ?? '')), $phone);
    if (!$qt['items']) return $back('These products are no longer available');
    $where = $d['district'] ?? ($b['areas'][$area]['n'] ?? null);
    $note = trim(($d['note'] ?? '').($qt['notes'] ? ' | Discount: '.implode(', ', $qt['notes']) : ''), ' |');
    $id = DB::transaction(function () use ($d, $qt, $phone, $ip, $where, $note, $pm) {
      $cust = DB::table('customers')->where('phone',$phone)->value('id'); if ($cust) { DB::table('customers')->where('id',$cust)->update(array_filter(['email'=>$d['email'] ?? null],'strlen')+['updated_at'=>now()]); } else $cust = DB::table('customers')->insertGetId(['name'=>$d['name'],'phone'=>$phone,'email'=>($d['email'] ?? null) ?: null,'address'=>$d['address'],'ip'=>$ip,'created_at'=>now(),'updated_at'=>now()]);
      $id = DB::table('orders')->insertGetId(['invoice'=>'TMP'.uniqid(),'store_id'=>DB::table('stores')->value('id'),'customer_id'=>$cust,'customer_name'=>$d['name'],'phone'=>$phone,'email'=>($d['email'] ?? null) ?: null,'address'=>$d['address'],'district'=>$where,'source'=>'Web Site','shipping'=>$qt['ship'],'discount'=>$qt['disc'],'coupon'=>$qt['coupon'],'total'=>$qt['total'],'status'=>'New','note'=>$note ?: null,'payment_method'=>$pm['label'],'pay_txn'=>$pm['txn'],'pay_account'=>$pm['acct'],'pay_proof'=>$pm['proof'],'pay_status'=>$pm['status'],'ip'=>$ip,'created_at'=>now(),'updated_at'=>now()]);
      DB::table('orders')->where('id',$id)->update(['invoice'=>'INV-'.str_pad($id,6,'0',STR_PAD_LEFT)]);
      foreach ($qt['items'] as $it) DB::table('order_items')->insert(['order_id'=>$id,'product_id'=>$it['id'],'name'=>$it['name'],'qty'=>$it['qty'],'price'=>$it['price'],'created_at'=>now(),'updated_at'=>now()]);
      if ($qt['coupon']) DB::table('coupons')->where('code',$qt['coupon'])->increment('used_count');
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>null,'user_name'=>'Website','from_status'=>null,'to_status'=>'New','note'=>$pm['status'] ? 'Order placed on website. Payment Verification Pending ('.$pm['label'].', ref '.$pm['txn'].')' : 'Order placed on website','created_at'=>now(),'updated_at'=>now()]);
      return $id;
    });
    Cache::forget('store.live3'); Cache::forget('store.live4');
    $inv = DB::table('orders')->where('id',$id)->value('invoice');
    try {
      if ((DB::table('settings')->where('key','sms_order_confirm')->value('value') ?? '1') === '1') {
        $tot = DB::table('orders')->where('id',$id)->value('total');
        \App\Support\Sms::queue($phone, "DutyFree Shop BD: আপনার অর্ডার $inv গ্রহণ করা হয়েছে। মোট ৳".number_format((float)$tot).". ট্র্যাক: dutyfreeshopbd.com/track?invoice=$inv", 'order_placed');
      }
    } catch (\Throwable $e) {}
    return redirect(\Illuminate\Support\Facades\URL::temporarySignedRoute('order.done', now()->addDays(3), ['invoice'=>$inv]));
  }
  private function pack(string $invoice): array {
    $o = DB::table('orders')->where('invoice',$invoice)->first(); abort_unless($o, 404);
    $items = DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->where('i.order_id',$o->id)->get(['i.name','i.qty','i.price','p.image']);
    $pay = $o->pay_status ? ($o->pay_status==='Verification Pending' ? 'Payment Verification Pending' : ($o->pay_status==='Verified' ? 'Paid (verified)' : 'Payment '.$o->pay_status)) : 'Cash on delivery. Pay when you receive';
    return ['o'=>$o,'items'=>$items,'sub'=>$items->sum(fn($i)=>$i->price*$i->qty),'payText'=>$pay];
  }
  public function done($invoice) {
    $u = fn($extra = []) => \Illuminate\Support\Facades\URL::temporarySignedRoute('order.invoice', now()->addDays(3), ['invoice'=>$invoice] + $extra);
    return response()->view('store.success', $this->base($this->pack($invoice) + ['viewUrl'=>$u(), 'dlUrl'=>$u(['print'=>1]), 'title'=>'Order Placed | DutyFree Shop BD','desc'=>'','canon'=>url('/'),'noindex'=>true]));
  }
  public function invoice($invoice) { return response()->view('store.invoice', $this->pack($invoice) + ['print'=>request()->boolean('print')]); }
  /** Tracking by invoice number only: shows status steps and products, never the customer's name, phone or address. */
  public function track(Request $r) {
    $o = null; $logs = collect();
    if ($r->filled('invoice')) {
      $inv = strtoupper(trim($r->invoice)); if (ctype_digit($inv)) $inv = 'INV-'.str_pad($inv, 6, '0', STR_PAD_LEFT);
      $o = DB::table('orders')->where('invoice', $inv)->first(['id','invoice','status','shipping','discount','total','created_at','payment_method','pay_status','pay_txn']);
      if ($o) $logs = DB::table('order_status_logs')->where('order_id',$o->id)->orderBy('id')->get(['to_status','created_at']);
    }
    return response()->view('store.track', $this->base(['o'=>$o, 'logs'=>$logs, 'items'=>$o ? DB::table('order_items')->where('order_id',$o->id)->get(['name','qty','price']) : collect(), 'title'=>'Order Tracking | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/track'), 'noindex'=>true]))->header('Cache-Control','no-store');
  }
  private function page($page) {
    return response()->view('store.page', $this->base(['page'=>$page, 'title'=>($page->meta_title ?: $page->title).' | DutyFree Shop BD', 'desc'=>$page->meta_description, 'canon'=>url('/'.$page->slug).'/']));
  }
  public function blog() {
    $posts = DB::table('posts')->where('active',true)->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now()))->orderByDesc('published_at')->orderByDesc('id')->get();
    return response()->view('store.blog', $this->base(['posts'=>$posts, 'title'=>'Blog | DutyFree Shop BD', 'desc'=>'Guides and articles from DutyFree Shop BD', 'canon'=>url('/blog')]));
  }
  public function post($slug) {
    $p = DB::table('posts')->where('slug',$slug)->where('active',true)->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now()))->first();
    if (!$p) return $this->notFound();
    return response()->view('store.post', $this->base(['p'=>$p, 'title'=>($p->meta_title ?: $p->title).' | DutyFree Shop BD', 'desc'=>$p->meta_description ?: $p->excerpt, 'canon'=>url('/blog/'.$p->slug), 'ogimg'=>$p->featured_image ? (str_starts_with($p->featured_image,'http') ? $p->featured_image : url($p->featured_image)) : null]));
  }
  public function cmsExport() {
    return response()->json(['source'=>url('/'), 'pages'=>DB::table('pages')->where('active',true)->get(['slug','title','body','meta_title','meta_description','updated_at']), 'posts'=>DB::table('posts')->where('active',true)->get(['slug','title','featured_image','excerpt','body','meta_title','meta_description','published_at'])]);
  }
  public function notFound() {
    return response()->view('store.missing', $this->base(['title'=>'পেজ পাওয়া যায়নি | DutyFree Shop BD', 'desc'=>'', 'canon'=>url('/'), 'noindex'=>true]), 404);
  }
  public function sitemap() {
    $xml = self::rem('store.sitemap3', 3600, function () {
      $u = fn($loc, $img = null) => '<url><loc>'.e($loc).'</loc><changefreq>daily</changefreq>'.($img ? '<image:image><image:loc>'.e($img).'</image:loc></image:image>' : '').'</url>';
      $o = $u(url('/').'/');
      foreach ($this->cats() as $c) $o .= $u(url('/'.$c->slug).'/');
      foreach ($this->subs() as $cid => $rows) { $cs = $this->cats()->firstWhere('id', $cid); if ($cs) foreach ($rows as $rw) $o .= $u(url('/'.$cs->slug.'/'.\App\Support\Slugs::subSlug($rw->sub))); }
      $o .= $u(url('/blog'));
      foreach (DB::table('posts')->where('active',true)->get(['slug']) as $bp) $o .= $u(url('/blog/'.$bp->slug));
      foreach (DB::table('pages')->where('active',true)->get(['slug']) as $pg) $o .= $u(url('/'.$pg->slug).'/');
      foreach (DB::table('products')->where('published',true)->orderBy('id')->cursor() as $p) $o .= $u(url(self::url($p)), $p->image);
      return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'.$o.'</urlset>';
    });
    return response($xml, 200, ['Content-Type'=>'application/xml; charset=utf-8']);
  }
}
