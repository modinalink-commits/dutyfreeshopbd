<?php
namespace App\Http\Controllers;
use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class StoreController extends Controller {
  /** Cache helper: stores a serialized string so cached collections/objects come back intact. */
  /** Storefront cache version: bust() makes every cached storefront block (home sections, brands, globe...) stale at once, so a publish/unpublish shows immediately. */
  public static function bust(): void { try { Cache::forever('store.ver', (int)(microtime(true) * 1000)); } catch (\Throwable $e) {} }
  public static function rem(string $k, int $ttl, \Closure $fn) {
    try { $k = 'v' . (Cache::get('store.ver') ?: 0) . '.' . (\App\Support\CleanSite::on() ? 'clean.' : '') . $k; } catch (\Throwable $e) {}
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
      if ($cs) { $ch = trim((string)($p->sub_sub ?? '')) !== '' ? \App\Support\Slugs::slug($p->sub_sub, 60) : ''; return '/'.$cs.'/'.\App\Support\Slugs::subSlug($p->sub ?? null).($ch !== '' ? '/'.$ch : '').'/'.$p->path_slug; }
    }
    return '/p/'.($p->ext_id ?? 'm'.$p->id).'-'.self::slugOf($p->name).'/';
  }
  public static function set(): array {
    $o = self::rem('store.settings2', 300, function () {
      $cur = DB::table('settings')->pluck('value','key'); $o = [];
      foreach (SiteController::schema() as [$k,$l,$t,$d,$g]) $o[$k] = $cur[$k] ?? $d;
      foreach (['hero_offers_slide','hero_member_slide','hero_district_slide','hero_watch_slide','hero_text_slide','hero_lux_slides'] as $k) if (isset($cur[$k])) $o[$k] = $cur[$k]; // hero switches live outside the schema
      return $o;
    });
    // a cached copy from before a deploy may lack newer keys: fill them with their defaults
    foreach (SiteController::schema() as [$k,$l,$t,$d,$g]) if (!array_key_exists($k, $o)) $o[$k] = $d;
    return $o;
  }
  /** Discount percent shown on cards (from the compare price). */
  /** Today's Deal products: the biggest real discounts, with a reserved share of Cigarette & Vape products (setting deal_smoke) mixed in, smoke first. */
  public static function dealList(array $s) {
    $min = (float)($s['deal_min_off'] ?? 0); $max = max(1, (int)($s['deal_max'] ?? 12)); $k = max(0, min($max, (int)($s['deal_smoke'] ?? 4)));
    if ($k === 0) return self::dealBase($min)->limit($max)->get();
    $sm = self::dealBase($min, 'smoke')->limit($k)->get();
    $ot = self::dealBase($min, 'other')->limit(max(1, $max - $sm->count()))->get();
    $out = collect(); $n = max($sm->count(), $ot->count());
    for ($i = 0; $i < $n; $i++) { if (isset($sm[$i])) $out->push($sm[$i]); if (isset($ot[$i])) $out->push($ot[$i]); }
    return $out->take($max)->values();
  }
  /** Flash Sale / Today's Deal on the front page: TWO products from EVERY active category (same order as the homepage sections). Inside a category the two are the most ordered in the LAST 7 DAYS (ties: last 24 h, then 30 days; products with no orders follow by biggest discount, then newest). Pinned products come first, excluded ones never show; refreshed every 10 minutes. Falls back to the biggest discounts when the admin turns "deal_mode" off. Each product carries ->cat_rank (0 = best of its category, 1 = second). */
  public static function todayBest(array $s) {
    if (($s['deal_mode'] ?? '1') !== '1') return self::dealList($s);
    $per = 2;
    $fl = DB::table('deal_flags')->get(['product_id','mode']);
    $ex = $fl->where('mode','exclude')->pluck('product_id')->map(fn($v) => (int)$v)->all();
    $pin = $fl->where('mode','pin')->pluck('product_id')->map(fn($v) => (int)$v)->all();
    $key = 'store.todaybest8.'.md5(implode(',', $ex).'|'.implode(',', $pin).'|'.($s['deal_min_off'] ?? '').'|'.($s['deal_smoke'] ?? ''));
    return self::rem($key, 600, function () use ($per, $ex, $pin) {
      $d1 = now()->subDay(); $d7 = now()->subDays(7); $d30 = now()->subDays(30);
      $cats = DB::table('categories')->whereNotNull('slug')->where('active', true)->orderBy('position')->get(['id','slug','name'])->reject(fn($x) => \App\Support\CleanSite::on() && in_array((int)$x->id, \App\Support\CleanSite::catIds(), true))->values()
        ->sortBy(fn($c) => self::isSmokeCat($c) ? 0 : (($c->slug ?? '') === 'watch' ? 1 : ((($c->slug ?? '') === 'gadget-electronics') ? 2 : (preg_match('/perfume|attar|আতর|পারফিউম/iu', ($c->slug ?? '').' '.($c->name ?? '')) ? 3 : 4))))->values();
      if ($cats->isEmpty()) return collect();
      $agg = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->whereNotIn('o.status',['Cancel','Incomplete'])->where('o.created_at','>=',$d30)->whereNotNull('i.product_id')
        ->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(case when o.created_at >= ? then i.qty else 0 end) as q1, sum(case when o.created_at >= ? then i.qty else 0 end) as q7, sum(i.qty) as q30, sum(case when o.status in (\'Delivered\',\'Received\',\'Completed\') then i.qty else 0 end) as d30', [$d1, $d7]);
      $smokeIds = $cats->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->map(fn($v) => (int)$v)->all();
      $ci = \App\Support\StockGuard::controlledIds();
      // Flash Sale follows what customers actually look at: product page views of the last 7 days (the same numbers as Reports > Top Viewed Products) come first, sales break ties
      $vw = DB::table('product_analytics_events')->where('event_type', 'product_view')->where('created_at', '>=', $d7)->groupBy('product_id')->selectRaw('product_id as vpid, count(*) as v7');
      $inner = DB::table('products as p')->leftJoinSub($agg, 's', 's.pid', '=', 'p.id')->leftJoinSub($vw, 'vv', 'vv.vpid', '=', 'p.id')->where('p.published', true)->whereIn('p.category_id', $cats->pluck('id')->all())
        ->whereNotNull('p.image')->where('p.image', '!=', '')->tap(fn($z) => \App\Support\CleanSite::apply($z, 'p.'))
        ->where(function ($w) use ($ci) { $w->whereNull('p.stock')->orWhere('p.stock', '>', 0); if ($ci) $w->orWhereNotIn('p.category_id', $ci); });
      if ($ex) $inner->whereNotIn('p.id', $ex);
      $inner->selectRaw('p.*, coalesce(s.q1,0) as q1, coalesce(s.q7,0) as q7, coalesce(s.q30,0) as q30, row_number() over (partition by p.category_id order by '.
        // owner (12 Nov 2026): in the Cigarette category the SLOWEST movers (fewest delivered units in the last 30 days) are the ones shown in Flash Sale
        ($smokeIds ? 'case when p.category_id in ('.implode(',', $smokeIds).') then coalesce(s.d30,0) else 0 end asc, ' : '').
        'coalesce(vv.v7,0) desc, coalesce(s.q7,0) desc, coalesce(s.q1,0) desc, coalesce(s.q30,0) desc, case when coalesce(p.compare_price,0) > coalesce(p.price,0) and p.compare_price > 0 then (p.compare_price - p.price) / p.compare_price else 0 end desc, p.id desc) as rn');
      $rows = DB::query()->fromSub($inner, 'x')->where('rn', '<=', $per)->orderBy('rn')->get()->groupBy('category_id');
      $out = collect();
      foreach ($cats as $c) {
        foreach (($rows[$c->id] ?? collect())->values() as $i => $p) {
          $p->cat_rank = $i; $p->bs_q1 = (int)$p->q1; $p->bs_q7 = (int)$p->q7; $p->bs = ((int)$p->q30 > 0);
          $out->push($p);
        }
      }
      // pinned products (admin) go first and take their category's slot
      if ($pin) {
        $pp = $out->filter(fn($p) => in_array((int)$p->id, $pin, true));
        $out = $pp->merge($out->reject(fn($p) => in_array((int)$p->id, $pin, true)))->values();
      }
      return $out->values();
    });
  }
  /** Front-page globe banner: every published product of the Cigarette & Vape category (in-stock first), cached 5 minutes. */
  public static function globeProducts() {
    return self::rem('store.globe_products2', 300, function () {
      if (\App\Support\CleanSite::on()) return collect();
      $sc = DB::table('categories')->get(['id','slug','name'])->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->all();
      $q = \App\Support\StockGuard::listable(DB::table('products')->where('published', true))->whereNotNull('image')->where('image', '!=', '')->where(function ($w) use ($sc) { $w->where('group_key', 'smoke'); if ($sc) $w->orWhereIn('category_id', $sc); });
      return $q->orderByRaw('coalesce(stock,0) > 0 desc')->orderByDesc('id')->limit(60)->get();
    });
  }
  public static function dealBase(float $min = 0, ?string $scope = null) {
    $ex = DB::table('deal_flags')->where('mode','exclude')->pluck('product_id')->all();
    $pin = array_map('intval', DB::table('deal_flags')->where('mode','pin')->pluck('product_id')->all());
    $q = \App\Support\StockGuard::listable(DB::table('products')->where('published',true))->whereColumn('compare_price','>','price')->where('compare_price','>',0);
    if ($scope !== null) {
      $sc = DB::table('categories')->get(['id','slug','name'])->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->all();
      if ($scope === 'smoke') $q->where(function ($w) use ($sc) { $w->where('group_key','smoke'); if ($sc) $w->orWhereIn('category_id', $sc); });
      else $q->where(function ($w) { $w->whereNull('group_key')->orWhere('group_key','!=','smoke'); })->where(function ($w) use ($sc) { $w->whereNull('category_id'); if ($sc) $w->orWhereNotIn('category_id', $sc); });
    }
    if ($ex) $q->whereNotIn('id', $ex);
    $q->where(function ($w) use ($min, $pin) { $w->whereRaw('(compare_price - price) / compare_price * 100 >= ?', [$min]); if ($pin) $w->orWhereIn('id', $pin); });
    if ($pin) $q->orderByRaw('case when id in ('.implode(',', $pin).') then 0 else 1 end');
    return $q->orderByRaw('(compare_price - price) / compare_price desc')->orderByDesc('id');
  }
  public static function off($p): int { return $p->compare_price && $p->compare_price > $p->price ? (int)round(($p->compare_price - $p->price) / $p->compare_price * 100) : 0; }
  public static function tk($v): string { return number_format((float)$v).' TK'; }
  private function cats() {
    return self::rem('store.cats', 300, fn() => DB::table('categories')->whereNotNull('slug')->where('active',true)->orderBy('position')->get()->when(\App\Support\CleanSite::on(), fn($c) => $c->reject(fn($x) => in_array((int)$x->id, \App\Support\CleanSite::catIds(), true)))->keyBy('slug'));
  }
  /** Top brands (sub groups) per category, used by the menu dropdowns, brand tabs and brand chips. */
  private function subs() {
    return self::rem('store.subs', 300, function () {
      $o = [];
      $nodes = \Illuminate\Support\Facades\Schema::hasTable('category_nodes') ? DB::table('category_nodes')->where('level', 2)->get()->keyBy(fn($n) => $n->category_id.'|'.$n->name) : collect();
      foreach (DB::table('products')->where('published',true)->whereNotNull('sub')->select('category_id','sub', DB::raw('count(*) n'))->groupBy('category_id','sub')->orderByDesc('n')->get() as $r) {
        $nd = $nodes[$r->category_id.'|'.$r->sub] ?? null; if ($nd && !$nd->active) continue; if (\App\Support\CleanSite::on() && \App\Support\CleanSite::bad((string)$r->sub)) continue; $r->pos = $nd->position ?? 100000; $o[$r->category_id][] = $r;
      }
      foreach ($o as $k => $rows) { usort($rows, fn($x, $y) => [$x->pos, -$x->n] <=> [$y->pos, -$y->n]); $o[$k] = $rows; }
      return $o;
    });
  }
  private function rules(): array {
    return self::rem('store.rules', 300, fn() => DB::table('discount_rules')->where('enabled',true)->get()->mapWithKeys(fn($r) => [$r->key => json_decode($r->config, true) ?: []])->all());
  }
  protected function base(array $x = []): array {
    $s = self::set(); $wa = \App\Support\Biz::waIntl($s['whatsapp_number'] ?: null);
    $areas = \App\Support\Delivery::options();
    return $x + ['s'=>$s, 'cats'=>$this->cats(), 'subs'=>$this->subs(), 'wa'=>$wa, 'areas'=>$areas, 'rules'=>$this->rules()];
  }
  private function q() { return \App\Support\StockGuard::listable(DB::table('products')->where('published',true)); }
  private function sorted($q, $sort) {
    return match ($sort) { 'price_asc'=>$q->orderBy('price'), 'price_desc'=>$q->orderByDesc('price'), 'popular'=>$q->orderByDesc('stock'), default=>$q->orderByDesc('id') };
  }
  /** Tobacco/vape is kept off Google: these pages are noindex and absent from the sitemap, and public titles/descriptions never mention it. */
  /** Product belongs to the Cigarette & Vape category (used to show the Free Delivery badge only there). */
  public static function isSmokeProduct($p): bool {
    static $ids = null;
    if ($ids === null) $ids = self::rem('store.smokecatids', 300, fn() => DB::table('categories')->get(['id','slug','name'])->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->map(fn($v) => (int)$v)->all());
    return ($p->group_key ?? null) === 'smoke' || in_array((int)($p->category_id ?? 0), $ids, true);
  }
  public static function isSmokeCat($c): bool { return $c && (($c->slug ?? '') === 'cigarette-vape' || preg_match('/cigarette|vape|সিগারেট|ভেপ/iu', (string)($c->name ?? '')) === 1); }
  public static function cleanSeo(?string $v, string $fallback): string { $v = trim((string)$v); return $v === '' ? $fallback : $v; } // owner (12 Nov 2026): cigarette wording is allowed in the title and description
  /** Keep <title> within ~60 chars so Google does not truncate it: cut the name part at a word boundary. */
  public static function fitTitle(string $name, string $suffix, int $max = 62): string {
    $name = trim(preg_replace('/\s+/u', ' ', $name)); $room = $max - mb_strlen($suffix);
    if (mb_strlen($name) > $room) { $cut = mb_substr($name, 0, max(10, $room)); $sp = mb_strrpos($cut, ' '); $name = (string)preg_replace('/[\s,\-–|]+$/u', '', $sp !== false && $sp > 12 ? mb_substr($cut, 0, $sp) : $cut); $name = (string)preg_replace('/\s+(for|and|of|in|with|by|the|a|made|from)$/iu', '', $name); }
    return $name.$suffix;
  }
  const HOME_TITLE = 'DutyFree BD | অরিজিনাল ইমপোর্টেড পারফিউম ও স্কিন কেয়ার – দুবাই থেকে সারা বাংলাদেশে';
  const HOME_DESC = 'আন্তর্জাতিক মানের সকল অরিজিনাল ইমপোর্টেড পণ্য এক জায়গায়: পারফিউম, আতর, স্কিন কেয়ার, হেয়ার কেয়ার, বেবি প্রোডাক্ট ও ডেইলি নিডস। ক্যাশ অন ডেলিভারি, সারা বাংলাদেশে হোম ডেলিভারি।';
  public function home() {
    $s = self::set();
    $data = self::rem('store.home13', 300, function () use ($s) {
      $pub = fn() => \App\Support\StockGuard::listable(DB::table('products')->where('published',true));
      $sold = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->whereNotIn('o.status',['Cancel','Incomplete'])->where('o.created_at','>=',now()->subDays(7))->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q')->pluck('q','pid');
      $deals = self::todayBest($s)
        ->map(function ($p) use ($sold) { $p->sold = (int)($sold[$p->id] ?? 0); return $p; });
      $sec = []; $i = 0;
      $ordC = $this->cats()->values(); // same order as the top menu (categories.position)
      foreach ($ordC as $c) { $sec[] = [$c, $pub()->where('category_id',$c->id)->orderByDesc('id')->limit(80)->get()]; $i++; }
      $offers = [];
      foreach ($this->cats() as $c) { $mx = (int)$pub()->where('category_id',$c->id)->whereColumn('compare_price','>','price')->where('compare_price','>',0)->max(DB::raw('round((compare_price - price) / compare_price * 100)')); if ($mx > 0) $offers[] = [$c, $mx]; }
      usort($offers, fn($a, $b) => $b[1] <=> $a[1]);
      return ['new'=>$pub()->where(fn($q)=>$q->whereNull('group_key')->orWhere('group_key','!=','smoke'))->orderByDesc('id')->limit(36)->get(), 'deals'=>$deals, 'top'=>collect(), 'topLabel'=>'Top Selling Products', 'sec'=>$sec, 'offers'=>array_slice($offers, 0, 2), 'slides'=>DB::table('sliders')->where('active',true)->where(fn($q)=>$q->whereNotNull('image')->orWhereNotNull('video_id'))->where(fn($q)=>$q->whereNull('type')->orWhere('type','main'))->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('position')->get(), 'offerSlides'=>DB::table('sliders')->where('active',true)->whereNotNull('image')->where('type','offer')->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('position')->get(), 'stores'=>DB::table('stores')->where('active',true)->orderBy('id')->get(['id','name','domain','scan_published'])->all(), 'best'=>DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->join('products as p','p.id','=','i.product_id')->where('p.published',true)->where('o.status','!=','Cancel')->where('o.created_at','>=',now()->subDays(60))->select('p.*', DB::raw('sum(i.qty) q'))->groupBy('p.id')->orderByDesc('q')->limit(12)->get(), 'count'=>$pub()->count(), 'maxOff'=>$deals->count() ? self::off($deals[0]) : 0];
    });
    // daily ranking is read outside the 5-minute page cache so a new Dhaka day starts at midnight
    try { [$data['top'], $data['topSold']] = \App\Support\TopSellers::products($s); } catch (\Throwable $e) { report($e); $data['top'] = collect(); $data['topSold'] = []; } // a ranking problem must never take the homepage down
    $data['topLabel'] = 'Top Selling Products';
    try { $data['slider'] = ($s['home_cat_slider'] ?? '1') !== '0' ? self::catSlider() : []; } catch (\Throwable $e) { report($e); $data['slider'] = []; } // category slider is optional: any problem falls back to the old category sections
    if (\App\Support\CleanSite::on()) { // tobacco-free storefront: ranking / best-seller lists are not built through StockGuard, so strip tobacco here
      $cf = fn($c) => $c instanceof \Illuminate\Support\Collection ? $c->filter(fn($p) => \App\Support\CleanSite::keep($p))->values() : $c;
      foreach (['new', 'deals', 'top', 'best'] as $kk) if (isset($data[$kk])) $data[$kk] = $cf($data[$kk]);
      $data['sec'] = array_map(function ($row) use ($cf) { $row[1] = $cf($row[1]); return $row; }, $data['sec']);
    }
    // Dynamic Product Shuffle: the eligible pool is cached, the ORDER is a fresh seed on every full page load (never ORDER BY RANDOM()). Eligibility, stock, price and publish rules are untouched; category / brand / search pages are not shuffled.
    try {
      if (\App\Support\Procure::set('shuffle_home', '1') !== '0' && \App\Support\Procure::set('shuffle_general', '1') !== '0') {
        $seed = random_int(1, 2147483646);
        $data['new'] = $data['new']->shuffle($seed)->take(12)->values();
        $data['deals'] = $data['deals']->shuffle($seed + 1)->take(16)->values();
        $data['top'] = $data['top']->shuffle($seed + 2)->take(12)->values();
        $k = 0; $data['sec'] = array_map(function ($row) use (&$k, $seed) { $lim = $k < 2 ? 8 : 6; $row[1] = $row[1]->shuffle($seed + 10 + $k)->take($lim)->values(); $k++; return $row; }, $data['sec']);
      } else { $data['new'] = $data['new']->take(12)->values(); $k = 0; $data['sec'] = array_map(function ($row) use (&$k) { $row[1] = $row[1]->take($k < 2 ? 8 : 6)->values(); $k++; return $row; }, $data['sec']); }
    } catch (\Throwable $e) { report($e); }
    if ($data['count'] === 0) return response()->view('coming', [], 503)->header('Retry-After','3600');
    $live = $s['home_live_orders'] === '1' ? $this->liveOrders() : collect();
    return response()->view('store.home', $this->base($data + ['showcase'=>\App\Support\Showcase::slots(), 'live'=>$live, 'title'=>self::cleanSeo($s['site_title'] ?? '', self::HOME_TITLE), 'desc'=>self::cleanSeo($s['meta_description'] ?? '', self::HOME_DESC), 'canon'=>url('/').'/', 'home'=>true]))->header('Cache-Control','private, no-cache, max-age=0');
  }
  /** Category Slider (home): one banner per active category with up to 6 customer-visible products, best sellers (last 30 days) first, then newest. Cached 5 minutes; categories without products are skipped. */
  /** Flash Sale, Cigarette & Vape part: the sellable products of that category that sold the LEAST in the last 30 days (delivered quantity, then all non-cancelled orders; never-sold first). No price is changed here: only products that already carry a real compare price show a SAVE badge. */
  /** Product ids on the Flash Sale shelf (the slowest-selling Cigarette & Vape products). The Flash Sale discount applies to these only. */
  public static function flashIds(): array { return array_values(array_unique(array_merge(self::flashSlowSmoke(0)->pluck('id')->map(fn($v) => (int)$v)->all(), self::flashSlowSmoke(0, -1)->pluck('id')->map(fn($v) => (int)$v)->all()))); } // current + previous rotation, so a cart item does not lose its discount the moment the shelf rotates
  /** Flash Sale (owner, 13 Nov 2026): ALL slow-selling Cigarette & Vape products (no fixed count): sellable products whose delivered units in the last 30 days are at or below the category median. The order is reshuffled every 30 minutes so no product stays in front. $n = 0 means unlimited. $back = -1 gives the previous rotation. */
  public static function flashSlowSmoke(int $n = 0, int $back = 0) {
    $slot = intdiv(time(), 1800) + $back;
    return self::rem('store.flashslow2.' . $slot, 1800, function () use ($n, $slot) {
      $ids = DB::table('categories')->get(['id','slug','name'])->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->map(fn($v) => (int)$v)->all();
      if (!$ids) return collect();
      $d30 = now()->subDays(30);
      $agg = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotIn('o.status', ['Cancel', 'Incomplete'])->where('o.created_at', '>=', $d30)->whereNotNull('i.product_id')->groupBy('i.product_id')
        ->selectRaw("i.product_id as pid, sum(case when o.status in ('Delivered','Received','Completed') then i.qty else 0 end) as d30")->get()->keyBy('pid');
      $pool = \App\Support\StockGuard::listable(DB::table('products')->where('published', true)->whereIn('category_id', $ids))->whereNotNull('image')->where('image', '!=', '')->get()
        ->filter(fn($p) => \App\Support\StockGuard::sellable($p))->values();
      if ($pool->isEmpty()) return collect();
      $q = $pool->map(fn($p) => (int)($agg[$p->id]->d30 ?? 0))->sort()->values();
      $med = $q[intdiv($q->count() - 1, 2)];
      $slow = $pool->filter(fn($p) => (int)($agg[$p->id]->d30 ?? 0) <= $med)->values();
      mt_srand($slot); $arr = $slow->all(); for ($i = count($arr) - 1; $i > 0; $i--) { $j = mt_rand(0, $i); [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]]; } mt_srand();
      $out = collect($arr);
      return $n > 0 ? $out->take($n)->values() : $out;
    });
  }
  public static function catSlider(): array {
    $rows = self::rem('store.catslider4', 300, function () {
      $cats = DB::table('categories')->whereNotNull('slug')->where('active', true)->orderBy('position')->get();
      $sold = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotIn('o.status', ['Cancel', 'Incomplete'])->where('o.created_at', '>=', now()->subDays(30))->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q')->pluck('q', 'pid');
      $out = [];
      foreach ($cats as $c) {
        $pool = \App\Support\StockGuard::listable(DB::table('products')->where('published', true)->where('category_id', $c->id))->whereNotNull('image')->where('image', '!=', '')->orderByDesc('id')->limit(800)->get()
          ->filter(fn($p) => \App\Support\StockGuard::sellable($p))->take(60);
        $pick = $pool->sortByDesc(fn($p) => [(int)($sold[$p->id] ?? 0), (int)$p->id])->values(); // whole sellable pool (best sellers first); the 6 shown are picked per request below
        if ($pick->count()) $out[] = [$c, $pick, $pick->take(5)->values()]; // [2] = best sellers (hero slider), never shuffled
      }
      // Owner (13 Nov 2026): the home shelves follow the 8 menu groups. Members of a group are merged into one shelf (up to 60 products from each member, best sellers first); the group takes the lead category's link and slug. A category outside every group keeps its own shelf.
      $bySlug = []; foreach ($out as $r) $bySlug[$r[0]->slug] = $r;
      $grouped = []; $used = [];
      foreach (\App\Support\MenuGroups::GROUPS as [$gname, $lead, $slugs]) {
        $mem = []; foreach ($slugs as $sl) if (isset($bySlug[$sl])) { $mem[] = $bySlug[$sl]; $used[$sl] = true; }
        if (!$mem) continue;
        if (count($mem) === 1) { $grouped[] = $mem[0]; continue; }
        $base = $bySlug[$lead] ?? $mem[0]; $cat = clone $base[0]; $cat->name = $gname;
        $pool = collect($mem)->flatMap(fn($r) => $r[1]->all())->sortByDesc(fn($p) => [(int)($sold[$p->id] ?? 0), (int)$p->id])->values();
        $grouped[] = [$cat, $pool, $pool->take(5)->values()];
      }
      foreach ($out as $r) if (!isset($used[$r[0]->slug])) $grouped[] = $r;
      return $grouped;
    });
    // Dynamic Product Shuffle for the category shelves: the cached pool is every sellable product of the category; each page load shows 6 random ones so products that sit lower still get seen and sold. Shuffle off (settings) = the old best-sellers-first 6.
    $shuf = \App\Support\Procure::set('shuffle_home', '1') !== '0' && \App\Support\Procure::set('shuffle_general', '1') !== '0';
    $rows = array_map(function ($r) use ($shuf) { $r[1] = ($shuf ? $r[1]->shuffle(random_int(1, 2147483646)) : $r[1])->take(6)->values(); return $r; }, $rows);
    if (\App\Support\CleanSite::on()) { $rows = array_values(array_filter(array_map(function ($r) { $r[1] = $r[1]->filter(fn($p) => \App\Support\CleanSite::keep($p))->values(); $r[2] = ($r[2] ?? collect())->filter(fn($p) => \App\Support\CleanSite::keep($p))->values(); return $r[1]->count() ? $r : null; }, $rows))); }
    return $rows;
  }
  /** Animated Brand Slider data: active brands (admin order) that have at least one customer-visible product; cached 5 min. A problem here never breaks the homepage. */
  /** Homepage promo cards: every customer offer that is switched ON right now (Discount Center rules, bundle, app reward). Nothing is invented; text comes from the saved values. */
  public static function promoOffers(int $maxOff = 0): array {
    try {
      $R = self::rem('store.promorules1', 300, function () {
        $o = []; foreach (DB::table('discount_rules')->where('enabled', true)->get(['key', 'config']) as $r) $o[$r->key] = json_decode((string)$r->config, true) ?: [];
        return $o;
      });
      $n = fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
      $tk = fn($v) => '৳' . number_format((float)$v);
      $I = ['truck' => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>', 'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M8.5 12l2.5 2.5L15.5 10"/>', 'tag' => '<path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/>', 'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v8h14v-8M12 8c-2 0-4-1-4-3s3-2 4 3c1-5 4-5 4-3s-2 3-4 3z"/>', 'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/>', 'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/>', 'box' => '<path d="M4 8l8-4 8 4v8l-8 4-8-4z"/><path d="M4 8l8 4 8-4M12 12v8"/>', 'bolt' => '<path d="M13 3L5 13h6l-1 8 8-10h-6z"/>'];
      $L = [['ic' => $I['shield'], 'h' => '100% Original', 's' => 'Imported and authentic', 'u' => '/about-us/']];
      if (isset($R['freedelivery']) && (float)($R['freedelivery']['Free over TK'] ?? 0) > 0) $L[] = ['ic' => $I['truck'], 'h' => 'Free Delivery', 's' => 'On orders over ' . $tk($R['freedelivery']['Free over TK']), 'u' => '/delivery-policy/'];
      if (isset($R['flash']) && $maxOff > 0) $L[] = ['ic' => $I['bolt'], 'h' => 'Flash Sale', 's' => 'Up to ' . $maxOff . '% off today', 'u' => '#flash'];
      if (isset($R['first']) && (float)($R['first']['Discount %'] ?? 0) > 0) $L[] = ['ic' => $I['gift'], 'h' => 'First Order Offer', 's' => $n($R['first']['Discount %']) . '% off your first order' . ((float)($R['first']['Max TK'] ?? 0) > 0 ? ' (up to ' . $tk($R['first']['Max TK']) . ')' : ''), 'u' => '/search?deals=1'];
      if (isset($R['membership'])) { $m = max(array_map('floatval', array_filter($R['membership'], 'is_numeric')) ?: [0]); if ($m > 0) $L[] = ['ic' => $I['user'], 'h' => 'Member Discount', 's' => 'Silver to Diamond: up to ' . $n($m) . '% on every order', 'u' => '/account']; }
      if (isset($R['amount'])) { $m = max(array_map('floatval', array_filter([$R['amount']['Over 5000 TK %'] ?? 0, $R['amount']['Over 10000 TK %'] ?? 0, $R['amount']['Over 20000 TK %'] ?? 0], 'is_numeric')) ?: [0]); if ($m > 0) $L[] = ['ic' => $I['tag'], 'h' => 'Spend More, Save More', 's' => 'Up to ' . $n($m) . '% off on big orders', 'u' => '/search?deals=1']; }
      if (isset($R['payment'])) { $m = max([(float)($R['payment']['Card %'] ?? 0), (float)($R['payment']['bKash / Nagad %'] ?? 0), (float)($R['payment']['ShurjoPay / Amarpay %'] ?? 0)]); if ($m > 0) $L[] = ['ic' => $I['card'], 'h' => 'Pay Online, Save More', 's' => 'Up to ' . $n($m) . '% off on online payment', 'u' => '/search?deals=1']; }
      try { $bc = \App\Support\BundlePricing::cfg(); if (!empty($bc['on'])) { $bm = max($bc['pct'] ?: [0]); if ($bm > 0) $L[] = ['ic' => $I['box'], 'h' => 'Buy 2–5 & Save', 's' => 'Bigger pack, bigger saving: up to ' . $n($bm) . '% off', 'u' => '/search?deals=1']; } } catch (\Throwable $e) {}
      if (isset($R['bulk'])) { $m = max(array_map('floatval', array_filter($R['bulk'], 'is_numeric')) ?: [0]); if ($m > 0) $L[] = ['ic' => $I['box'], 'h' => 'Carton Discount', 's' => 'Buy more cartons, up to ' . $n($m) . '% off', 'u' => '/search?deals=1']; }
      if (isset($R['bogo']) && (float)($R['bogo']['Get quantity free'] ?? 0) > 0) $L[] = ['ic' => $I['gift'], 'h' => 'Buy ' . (int)($R['bogo']['Buy quantity'] ?? 0) . ' Get ' . (int)$R['bogo']['Get quantity free'] . ' Free', 's' => 'On selected products', 'u' => '/search?deals=1'];
      if (isset($R['referral']) && (float)($R['referral']['Friend discount TK'] ?? 0) > 0) $L[] = ['ic' => $I['gift'], 'h' => 'Refer a Friend', 's' => 'Your friend gets ' . $tk($R['referral']['Friend discount TK']) . ' off', 'u' => '/account'];
      if (isset($R['birthday']) && (float)($R['birthday']['Birthday %'] ?? 0) > 0) $L[] = ['ic' => $I['gift'], 'h' => 'Birthday Offer', 's' => $n($R['birthday']['Birthday %']) . '% off around your birthday', 'u' => '/account'];
      try { $ar = \App\Support\AppReward::cfg(); if (!empty($ar['on']) && ($ar['amt'] ?? 0) > 0) $L[] = ['ic' => $I['gift'], 'h' => 'App Install Reward', 's' => 'Get ' . $tk($ar['amt']) . ' reward on the DutyFree app', 'u' => '/app']; } catch (\Throwable $e) {}
      return $L;
    } catch (\Throwable $e) { report($e); return []; }
  }
  public static function brandSlider(): array {
    try {
      if (\App\Support\Procure::set('brand_slider_enabled', '1') === '0') return [];
      return self::rem('store.brandslider2', 300, function () {
        $ids = \App\Support\StockGuard::listable(DB::table('products')->where('published', true))->whereNotNull('brand_id')->distinct()->pluck('brand_id')->all();
        if (!$ids) return [];
        return DB::table('brands')->where('active', true)->whereIn('id', $ids)->orderBy('position')->orderBy('name')->get(['name', 'slug'])->filter(fn($b) => trim((string)$b->slug) !== '')->map(fn($b) => ['name' => (string)$b->name, 'slug' => (string)$b->slug])->values()->all();
      });
    } catch (\Throwable $e) { report($e); return []; }
  }
  /** Below-the-fold home category section, lazy-loaded by the home page so the first HTML stays small. */
  /** Private preview of the globe section before it goes on the front page. */
  public function dealPreview() {
    $deals = self::todayBest(self::set())->take(12);
    return response()->view('store.deal_preview', $this->base(['deals'=>$deals, 'title'=>'Deal boarding pass preview | DutyFree BD', 'desc'=>'', 'canon'=>url('/deal-preview'), 'noindex'=>true]))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
  }
  public function globePreview() {
    $deals = self::dealList(self::set())->take(6);
    return response()->view('store.globe_preview', $this->base(['deals'=>$deals, 'title'=>'Globe preview | DutyFree BD', 'desc'=>'', 'canon'=>url('/globe-preview'), 'noindex'=>true]))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
  }
  public function globePreview2() {
    $deals = self::dealList(self::set())->take(6);
    return response()->view('store.globe_preview2', $this->base(['deals'=>$deals, 'title'=>'Globe preview 2 | DutyFree BD', 'desc'=>'', 'canon'=>url('/globe-preview-2'), 'noindex'=>true]))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
  }
  public function watchLuxPreview() {
    return response()->view('store.watch_lux_preview', $this->base(['title'=>'Luxury watch banner preview | DutyFree BD', 'desc'=>'', 'canon'=>url('/watch-banner-preview'), 'noindex'=>true]))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
  }
  public function airportPreview() {
    $deals = self::dealList(self::set())->take(6);
    return response()->view('store.airport_preview', $this->base(['deals'=>$deals, 'title'=>'Airport preview | DutyFree BD', 'desc'=>'', 'canon'=>url('/airport-preview'), 'noindex'=>true]))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
  }
  public function homeSec(string $slug) {
    $c = $this->cats()[$slug] ?? null; $s = self::set();
    if (!$c || $s['home_cat_sections'] !== '1') abort(404);
    // pool of up to 80 listable products is cached; the 6 shown are re-drawn on EVERY load, so each homepage reload shows different products from inside the category
    $pool = self::rem('store.hsec3.'.$slug, 300, fn() => \App\Support\StockGuard::listable(DB::table('products')->where('published', true))->where('category_id', $c->id)->orderByDesc('id')->limit(80)->get());
    if (!$pool->count()) abort(404);
    $items = (\App\Support\Procure::set('shuffle_home', '1') !== '0' && \App\Support\Procure::set('shuffle_general', '1') !== '0') ? $pool->shuffle(random_int(1, 2147483646))->take(6)->values() : $pool->take(6)->values();
    return response()->view('store.home_sec', $this->base(['c'=>$c, 'items'=>$items, 'lazy'=>false]))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'private, no-store, max-age=0');
  }
  /** Real recent orders, customer names masked (first letter + ***). */
  public function liveFragment() {
    $live = $this->liveOrders();
    return response()->json(['n'=>$live->count(), 'sig'=>md5(json_encode($live->map(fn($l) => [$l->pid, $l->created_at, $l->qty, $l->price])->all())), 'html'=>view('store.live_cards', ['live'=>$live])->render()])->header('Cache-Control','public, max-age=30');
  }
  private function liveOrders() {
    $L = $this->liveOrdersRaw();
    return \App\Support\CleanSite::on() ? $L->filter(fn($l) => \App\Support\CleanSite::keep((object)['category_id' => $l->pcat ?? null, 'name' => trim(($l->name ?? '').' '.($l->pname ?? '')), 'sub' => $l->psub ?? '', 'sub_sub' => $l->psubsub ?? '']))->values()->take(6) : $L->take(6)->values();
  }
  private function liveOrdersRaw() {
    return self::rem('store.live7', 60, function () {
      $q = DB::table('orders as o')->join('order_items as i','i.order_id','=','o.id')->leftJoin('products as p','p.id','=','i.product_id')->whereIn('o.status',['Approved','Packaging','Shipment','Partial Delivered','Delivered'])->where('o.created_at','>=',now()->subDays(7));
      if (\App\Support\CleanSite::on()) { \App\Support\CleanSite::apply($q, 'p.'); $q->whereRaw("coalesce(i.name,'') !~* ?", [\App\Support\CleanSite::RX]); }
      return $q->select(DB::raw("'' as customer_name"),DB::raw("'' as district"),'o.shipping','o.created_at','i.name','i.qty','i.price','p.image','p.id as pid','p.ext_id','p.name as pname','p.category_id as pcat','p.sub as psub','p.sub_sub as psubsub','p.path_slug as pslug')->orderByDesc('o.id')->limit(12)->get()
        ->map(function ($r) { $r->masked = ''; return $r; });
    });
  }
  /** Category / sub / child page SEO written by the auto generator (fields tracked in seo_modes). Legacy rows without tracking keep the old logic. */
  private function autoSeo(?object $n, string $tcol): array {
    if (!$n) return []; $m = json_decode((string)($n->seo_modes ?? ''), true); if (!is_array($m) || !$m) return [];
    $v = fn($col, $k) => in_array($m[$k] ?? null, ['auto', 'manual'], true) && trim((string)($n->$col ?? '')) !== '' ? trim((string)$n->$col) : null; $o = [];
    if ($x = $v($tcol, 'title')) $o['title'] = $x; if ($x = $v('meta_description', 'desc')) $o['desc'] = $x; if ($x = $v('canonical', 'canonical')) $o['canon'] = $x; if ($x = $v('focus_keyword', 'focus')) $o['keywords'] = $x;
    if ($x = $v('og_title', 'og_title')) $o['ogtitle'] = $x; if ($x = $v('og_description', 'og_desc')) $o['ogdesc'] = $x; if ($x = $v('og_image', 'og_image')) $o['ogimg'] = $x; if ($x = $v('schema_type', 'schema')) $o['schemaType'] = $x; if ($x = $v('breadcrumb_title', 'crumb')) $o['crumb'] = $x;
    return $o;
  }
  public function category(Request $r, string $slug, ?string $subSlug = null, ?string $subSubName = null) {
    $c = $this->cats()[$slug] ?? null;
    if (!$c) { if ($subSlug !== null) return $this->notFound(); $page = DB::table('pages')->where('slug',$slug)->where('active',true)->first(); if (!$page && ($pt = request()->query('preview'))) { $pp = DB::table('pages')->where('slug',$slug)->first(); if ($pp && \App\Support\SeoMgr::previewOk($pt, $pp)) { $page = $pp; $GLOBALS['seo_preview'] = true; } } if ($page) return $this->page($page); return $this->notFound(); }
    if ($subSlug === null && $r->filled('sub')) { // old ?sub=LM links -> /category/lm (301)
      $rest = $r->except('sub'); return redirect('/'.$slug.'/'.\App\Support\Slugs::subSlug($r->sub).($rest ? '?'.http_build_query($rest) : ''), 301);
    }
    $s = self::set(); $per = max(12, (int)$s['per_page']);
    $q = $this->q()->where('products.category_id',$c->id); $subName = null;
    if ($subSlug !== null) {
      $q->whereRaw(\App\Support\Slugs::subSql().' = ?', [$subSlug]);
      $subName = (clone $q)->whereNotNull('sub')->value('sub') ?: ((clone $q)->exists() ? 'Other' : null);
      if ($subName === null) return $this->notFound();
      if ($subSubName !== null) { $q->where('sub_sub', $subSubName); if (!(clone $q)->exists()) return $this->notFound(); }
    }
    if (is_numeric($r->min)) $q->where('price','>=',(float)$r->min); if (is_numeric($r->max)) $q->where('price','<=',(float)$r->max);
    $scope = 'm:'.$c->id; $hasPos = false;
    $seoNode = null;
    if ($subSlug !== null && \Illuminate\Support\Facades\Schema::hasTable('category_nodes')) {
      $sn = DB::table('category_nodes')->where('category_id', $c->id)->where('level', 2)->where('slug', $subSlug)->first();
      if ($sn) { $scope = 'n:'.$sn->id; $seoNode = $sn; if ($subSubName !== null && ($cn = DB::table('category_nodes')->where('level', 3)->where('parent_id', $sn->id)->where('name', $subSubName)->first())) { $scope = 'n:'.$cn->id; $seoNode = $cn; } }
    }
    if (\Illuminate\Support\Facades\Schema::hasTable('product_positions')) $hasPos = DB::table('product_positions')->where('pp_scope', $scope)->exists();
    $sort = (string)$r->input('sort', '');
    if ($sort === '' || $sort === 'position') { // category position, then product position inside it
      $q = $q->leftJoin('category_nodes as sn', fn($j) => $j->on('sn.category_id', '=', 'products.category_id')->where('sn.level', '=', 2)->on('sn.name', '=', 'products.sub'))
        ->leftJoin('category_nodes as cn', fn($j) => $j->where('cn.level', '=', 3)->on('cn.parent_id', '=', 'sn.id')->on('cn.name', '=', 'products.sub_sub'))
        ->leftJoin('product_positions as pp', fn($j) => $j->on('pp.pp_product', '=', 'products.id')->where('pp.pp_scope', '=', $scope))->select('products.*')
        ->orderByRaw('sn.position asc nulls last')->orderByRaw('cn.position asc nulls last')->orderByRaw('pp.pp_pos asc nulls last')->orderByDesc('products.id');
      $items = $q->paginate($per)->withQueryString();
    } else {
      $q = $q->orderByRaw('coalesce(sub, \'~\')');
      $items = $this->sorted($q, $sort === 'new' ? 'new' : $sort)->paginate($per)->withQueryString();
    }
    $canon = url('/'.$slug).($subSlug !== null ? '/'.$subSlug.($subSubName !== null ? '/'.\App\Support\Slugs::slug($subSubName, 60) : '') : '/'); if ($subSubName !== null) { $subName = $subSubName.' · '.$subName; }
    $baseT = trim((string)($c->title ?: $c->name));
    if ($subName) { $tt = $subName.' – '.$c->name; if (mb_strlen($tt) < 26) $tt .= ' – Original Imported Products'; $title = self::fitTitle($tt, ' | DutyFree BD'); }
    else {
      $baseT = trim(preg_replace('/\s*\|.*$/su', '', $baseT)); // drop any old "| brand" suffix, the brand is added once below
      if (mb_strlen($baseT) < 10) $baseT = trim((string)$c->name);
      if (mb_strlen($baseT) < 30) $baseT .= ' – Original Imported Products in Bangladesh';
      $title = self::fitTitle($baseT, ' | DutyFree BD');
    }
    $ov = $this->autoSeo($subSlug === null ? $c : $seoNode, $subSlug === null ? 'title' : 'meta_title');
    return response()->view('store.category', $this->base(array_replace(['c'=>$c, 'subName'=>$subName, 'subSlug'=>$subSlug, 'items'=>$items, 'hasPos'=>$hasPos, 'groups'=>collect($items->items())->groupBy(fn($p) => $p->sub ?: $c->name), 'title'=>$title, 'desc'=>$subName ? $subName.' – original imported products in '.$c->name.' at DutyFree BD. Cash on delivery, nationwide delivery.' : $c->meta_description, 'canon'=>(!$subName && !empty($c->canonical)) ? $c->canonical : $canon, 'noindex'=>(!empty($c->noindex) && !$subName) || self::isSmokeCat($c), 'keywords'=>$c->focus_keyword ?? null, 'ogimg'=>(!empty($c->og_image) ? $c->og_image : null)], $ov)))->header('Cache-Control','public, max-age=120');
  }
  /** Public brand page /brand/{slug}: published products of one brand + the brand's own SEO (auto or manual). */
  public function brand(Request $r, string $slug) {
    $b = DB::table('brands')->where('slug', $slug)->where('active', true)->first(); if (!$b) return $this->notFound();
    $s = self::set(); $per = max(12, (int)$s['per_page']);
    $q = $this->q()->where('products.brand_id', $b->id);
    if (is_numeric($r->min)) $q->where('price','>=',(float)$r->min); if (is_numeric($r->max)) $q->where('price','<=',(float)$r->max);
    $sort = (string)$r->input('sort', '');
    $items = ($sort === '' || $sort === 'position') ? (clone $q)->orderByDesc('products.id')->paginate($per)->withQueryString() : $this->sorted($q, $sort === 'new' ? 'new' : $sort)->paginate($per)->withQueryString();
    $catIds = $this->q()->where('brand_id', $b->id)->distinct()->pluck('category_id')->all();
    $smoke = DB::table('categories')->get(['id','slug','name'])->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->all();
    $onlySmoke = $catIds && !array_diff($catIds, $smoke);
    $noindex = !empty($b->noindex) || $onlySmoke || $items->total() === 0;
    $name = trim($b->name); $site = 'DutyFreeBD';
    $title = trim((string)($b->meta_title ?? '')) ?: "$name Products in Bangladesh | $site";
    $desc = trim((string)($b->meta_description ?? '')) ?: "Explore $name products at $site with product details, current pricing and convenient ordering in Bangladesh.";
    $canon = trim((string)($b->canonical ?? '')) ?: url('/brand/'.$b->slug);
    $img = trim((string)($b->og_image ?? '')) ?: (trim((string)($b->logo ?? '')) ?: null);
    $robots = ($noindex ? 'noindex' : 'index').','.(!empty($b->nofollow) ? 'nofollow' : 'follow').($noindex ? '' : ',max-image-preview:large');
    return response()->view('store.brand', $this->base(['b'=>$b, 'items'=>$items, 'title'=>$title, 'desc'=>$desc, 'canon'=>$canon, 'noindex'=>$noindex, 'robots'=>$robots, 'keywords'=>trim((string)($b->meta_keywords ?? '')) ?: null, 'ogimg'=>$img, 'ogtitle'=>trim((string)($b->og_title ?? '')) ?: $title, 'ogdesc'=>trim((string)($b->og_description ?? '')) ?: $desc, 'crumb'=>trim((string)($b->breadcrumb_title ?? '')) ?: $name, 'alt'=>trim((string)($b->image_alt ?? '')) ?: "$name brand products"]))->header('Cache-Control','public, max-age=120');
  }
  /** /category/secondary-category */
  public function categorySub(Request $r, string $cat, string $sub) { return $this->category($r, $cat, $sub); }
  /** /category/secondary-category/product-name */
  public function productByChild(string $cat, string $sub, string $child, string $slug) { return $this->productByPath($cat, $sub, $slug, $child); }
  public function productByPath(string $cat, string $sub, string $slug, ?string $child = null) {
    $c = $this->cats()[$cat] ?? null; if (!$c) return $this->notFound();
    $cands = $this->q()->where('category_id',$c->id)->where('path_slug',$slug)->orderBy('id')->get();
    if ($child !== null) $cands = $cands->filter(fn($x) => \App\Support\Slugs::slug($x->sub_sub, 60) === $child)->values();
    $p = $cands->isEmpty() ? null : ($cands->first(fn($x) => \App\Support\Slugs::subSlug($x->sub) === $sub) ?? ($cands->count() === 1 ? $cands->first() : null));
    if (!$p && $child !== null) return $this->notFound();
    if (!$p) { // /category/sub/child category listing
      $t = \Illuminate\Support\Facades\Schema::hasTable('category_nodes') ? DB::table('category_nodes as t')->join('category_nodes as s', 's.id', '=', 't.parent_id')->where('t.level', 3)->where('t.active', true)->where('t.category_id', $c->id)->where('t.slug', $slug)->get(['t.name as tn', 's.name as sn'])->first(fn($x) => \App\Support\Slugs::subSlug($x->sn) === $sub) : null;
      return $t ? $this->category(request(), $cat, $sub, $t->tn) : $this->notFound();
    }
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
    $rst = DB::table('reviews')->whereIn('product_id', $rel->pluck('id')->all())->where('status','approved')->groupBy('product_id')->selectRaw('product_id, avg(rating) as av, count(*) as n')->get()->keyBy('product_id');
    foreach ($rel as $r0) { $x = $rst->get($r0->id); $r0->rv_avg = $x ? round((float)$x->av, 1) : 0; $r0->rv_n = $x ? (int)$x->n : 0; }
    DB::table('product_views')->insert(['product_id'=>$p->id,'day'=>now('Asia/Dhaka')->toDateString(),'created_at'=>now(),'updated_at'=>now()]);
    if (!$preview) \App\Support\ProductTrack::view(request(), $p);
    $price = (float)$p->price;
    $gallery = DB::table('product_images')->where('product_id',$p->id)->orderBy('position')->orderBy('id')->pluck('path')->all(); if (str_contains((string)$p->image, '/uploads/ai/')) $gallery = []; // owner rule: a product with a clean AI image shows that ONE image only (no old photos with the old background)
    return response()->view('store.product', $this->base(['preview'=>$preview, 'gallery'=>$gallery, 'p'=>$p, 'c'=>$c, 'rel'=>$rel, 'reviews'=>$reviews, 'title'=>(mb_strlen((string)$p->meta_title) >= 10 && mb_strlen((string)$p->meta_title) <= 66 ? $p->meta_title : self::fitTitle($p->name, ' দাম ৳'.number_format($price).' | DutyFree BD')), 'keywords'=>$p->meta_keywords ?? null, 'desc'=>($p->meta_description ?: $p->name.' – দাম ৳ '.number_format($price).'। অরিজিনাল ইমপোর্টেড পণ্য, অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি। DutyFree BD.'), 'canon'=>($p->canonical ?? null) ?: url(self::url($p)), 'noindex'=>!empty($p->noindex) || ($p->group_key ?? null) === 'smoke', 'ogimg'=>($p->og_image ?? null) ?: ($p->image ? (preg_match('#^https?://#i', $p->image) ? $p->image : url($p->image)) : url('/og.png'))]))->header('Cache-Control','public, max-age=120');
  }
  public function search(Request $r) {
    $t = trim(mb_substr((string)$r->q, 0, 80)); $items = collect();
    if ($r->boolean('deals')) { $l = self::todayBest(array_merge(self::set(), ['deal_max' => 24])); $items = new \Illuminate\Pagination\LengthAwarePaginator($l, $l->count(), 48, 1, ['path' => url('/search')]); $t = 'Flash Sale'; }
    elseif ($t === '' && in_array((string)$r->sort, ['new', 'popular'], true)) { $qq = $this->q(); $qq = (string)$r->sort === 'new' ? $qq->orderByDesc('id') : $qq->orderByRaw("(select coalesce(sum(i.qty),0) from order_items i join orders o on o.id = i.order_id where i.product_id = products.id and o.status not in ('Cancel','Incomplete')) desc")->orderByDesc('id'); try { $items = $qq->paginate(48)->withQueryString(); } catch (\Throwable $e) { $items = $this->q()->orderByDesc('id')->paginate(48)->withQueryString(); } $t = (string)$r->sort === 'new' ? 'New Arrivals' : 'Top Selling'; }
    elseif ($t !== '') { $items = $this->searchRank($this->searchWhere($this->q(), $t), $t)->orderByDesc('id')->paginate(48)->withQueryString(); DB::table('product_views')->insert(['term'=>$t,'day'=>now('Asia/Dhaka')->toDateString(),'created_at'=>now(),'updated_at'=>now()]); }
    return response()->view('store.search', $this->base(['t'=>$t, 'items'=>$items, 'title'=>($t ? $t.' – ' : '').'Search | DutyFree BD', 'desc'=>'', 'canon'=>url('/search'), 'noindex'=>true]));
  }
  /** Customer search: every word must match somewhere - name, product code (DF-1106, df1106 or just 1106), keywords, sub category, URL slug or brand name. */
  private function searchWhere($qb, string $t) {
    foreach (array_slice(preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $tk) {
      $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tk).'%'; $norm = preg_replace('/[^a-z0-9]/', '', strtolower($tk));
      $qb->where(function ($w) use ($like, $norm) {
        $w->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like)->orWhere('sub', 'ilike', $like)->orWhere('meta_keywords', 'ilike', $like)->orWhere('slug', 'ilike', $like)
          ->orWhereIn('brand_id', fn($s) => $s->from('brands')->select('id')->where('name', 'ilike', $like));
        if (strlen($norm) >= 2) $w->orWhereRaw("regexp_replace(lower(coalesce(sku,'')),'[^a-z0-9]','','g') like ?", ['%'.$norm.'%']);
      });
    }
    return $qb;
  }
  /** Best matches first: exact product code, code starts with, name starts with, word starts with, anything else. */
  private function searchRank($qb, string $t) {
    $lt = mb_strtolower(trim($t)); $norm = preg_replace('/[^a-z0-9]/', '', $lt); $e = str_replace(['%', '_'], ['\\%', '\\_'], $lt);
    return $qb->orderByRaw("case when lower(coalesce(sku,'')) = ? or (?::text <> '' and regexp_replace(lower(coalesce(sku,'')),'[^a-z0-9]','','g') = ?) then 0 when lower(coalesce(sku,'')) like ? or (?::text <> '' and regexp_replace(lower(coalesce(sku,'')),'[^a-z0-9]','','g') like ?) then 1 when lower(name) like ? then 2 when lower(name) like ? then 3 else 4 end", [$lt, $norm, $norm, $e.'%', $norm, '%'.$norm.'%', $e.'%', '% '.$e.'%']);
  }
  /** Live search suggestions: products (with image), brands, categories and stores. */
  public function suggest(Request $r) {
    $t = trim(mb_substr((string)$r->q, 0, 60)); if (mb_strlen($t) < 2) return response()->json([]);
    $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $t).'%';
    $out = [];
    foreach ($this->searchRank($this->searchWhere($this->q(), $t), $t)->orderByDesc('id')->limit(8)->get(['id','ext_id','sku','name','price','image','sub','stock','category_id']) as $p) $out[] = ['t'=>'p','n'=>$p->name,'u'=>self::url($p),'i'=>$p->image,'x'=>number_format($p->price).' TK','s'=>(string)($p->sku ?: '')];
    foreach ($this->cats() as $c) if (stripos($c->name, $t) !== false) $out[] = ['t'=>'c','n'=>$c->name,'u'=>'/'.$c->slug.'/'];
    foreach (\App\Support\StockGuard::listable(DB::table('products')->where('published',true))->where('sub','ilike',$like)->select('category_id','sub')->distinct()->limit(3)->get() as $b) { $c = $this->cats()->firstWhere('id',$b->category_id); if ($c) $out[] = ['t'=>'b','n'=>$b->sub,'u'=>'/'.$c->slug.'/?sub='.rawurlencode($b->sub)]; }
    return response()->json($out)->header('Cache-Control','public, max-age=60');
  }
  public function checkout() {
    return response()->view('store.checkout', $this->base(['pays'=>\App\Http\Controllers\PaymentController::active(),'title'=>'Checkout | DutyFree BD', 'desc'=>'', 'canon'=>url('/checkout'), 'noindex'=>true, 'grey'=>true]));
  }
  public function wishlist() {
    return response()->view('store.wishlist', $this->base(['title'=>'Wishlist | DutyFree BD', 'desc'=>'', 'canon'=>url('/wishlist'), 'noindex'=>true]));
  }
  /** Parse "12:2,15:1" into [product_id => qty]. */
  private function parseItems($raw): array {
    $o = [];
    if (is_array($raw)) { foreach ($raw as $id => $q) if ((int)$id > 0 && (int)$q > 0) $o[(int)$id] = min(50, (int)$q); return array_slice($o, 0, 30, true); }
    foreach (explode(',', (string)$raw) as $part) { [$id, $q] = array_pad(explode(':', $part), 2, 1); if ((int)$id > 0 && (int)$q > 0) $o[(int)$id] = min(50, (int)$q); }
    return array_slice($o, 0, 30, true);
  }
  /** Total for $n units of a product using the admin "Quantity prices" (Buy 2..5 = total price of the pack). Packs of 5 first, then the remainder; never more than unit price x qty. */
  public static function bundleLine(object $p, int $n): float {
    $u = (float)$p->price; $plain = $u * $n; if ($n < 2) return round($plain, 2);
    if (isset(\App\Support\PrepaidOnly::catSet()[(int)($p->category_id ?? 0)])) return round($plain, 2); // Watch: no bundle price
    $id = (int)($p->id ?? 0); // Global bundle pricing: always calculated live from the sale price and the FIFO cost (never a stale stored price)
    $tier = function (int $k) use ($p, $u, $id) { $t = $id > 0 ? \App\Support\BundlePricing::total($id, $k) : null; if ($t === null && $id <= 0) { $t = (float)($p->{'buy'.$k.'_price'} ?? 0); } return ($t !== null && $t > 0 && $t < $u * $k) ? $t : $u * $k; };
    $left = $n; $tot = 0.0;
    while ($left >= 5) { $tot += $tier(5); $left -= 5; }
    $tot += $left >= 2 ? $tier($left) : $u * $left;
    return round(min($tot, $plain), 2);
  }
  /** Policy snapshot saved with the order so later benefit changes never rewrite history (spec: checkout pricing snapshot + discount breakdown). */
  private static function benefitSnap(?string $phone, array $app, $profile, array $cand = []): array {
    try {
      if (!$phone) return [];
      $cols = self::rem('store.orders.cols', 600, fn() => array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('orders')));
      $b = \App\Support\CustomerBenefit::check($phone);
      $row = ['benefit_status_snapshot'=>$b['status'], 'benefit_reason_snapshot'=>mb_substr(implode(',', $b['reasons']), 0, 190), 'membership_snapshot'=>mb_substr((string)($profile['membership_name'] ?? ''), 0, 60) ?: null, 'discount_type_snapshot'=>mb_substr(implode(', ', array_map(fn($l) => (string)($l['type'] ?? $l['key'] ?? ''), $app)), 0, 190) ?: null,
        'successful_received_count_snapshot'=>(int)$b['received'],
        'applied_discounts_snapshot'=>json_encode(array_map(fn($l) => ['type'=>$l['type'] ?? null, 'source'=>$l['source'] ?? null, 'name'=>$l['label'] ?? null, 'value'=>$l['pct'] ?? null, 'amount'=>$l['amount'] ?? null, 'rule'=>$l['rule'] ?? null, 'reason'=>'Eligible: '.$b['status'].', received '.$b['received']], $app), JSON_UNESCAPED_UNICODE),
        'available_discounts_snapshot'=>json_encode(array_map(fn($l) => ['type'=>$l['type'] ?? null, 'amount'=>$l['amount'] ?? null, 'applied'=>!empty($l['applied'])], $cand), JSON_UNESCAPED_UNICODE)];
      return array_intersect_key($row, $cols);
    } catch (\Throwable $e) { return []; }
  }
  /** Customer-friendly benefits block for the checkout panel. Never carries fraud / risk / staff details. */
  private static function benefitsView(string $phone, array $q, \Illuminate\Http\Request $r): array {
    try {
      $items = [];
      foreach (($q['cand'] ?? []) as $l) { $ap = !empty($l['applied']); if (!$ap && (($l['key'] ?? '') === 'coupon' || !empty($l['floor_hidden']))) continue; $items[] = ['key'=>(string)($l['key'] ?? ''), 'label'=>(string)($l['label'] ?? $l['type'] ?? ''), 'state'=>$ap ? 'applied' : 'available', 'amount'=>(float)($l['amount'] ?? 0)]; }
      $mx = 0; foreach ($items as $it) $mx = max($mx, $it['amount']); foreach ($items as &$it) $it['best'] = $mx > 0 && $it['amount'] >= $mx; unset($it);
      if (!empty($q['ship_free']) && (float)($q['ship_orig'] ?? 0) > 0) array_unshift($items, ['label'=>'Free Delivery', 'state'=>'applied', 'amount'=>0]);
      $c = \App\Support\OrderDiscount::customer($phone);
      if (!$c) return ['mode'=>$q['mode'] ?? null, 'state'=>'new', 'msg'=>'New Customer — loyalty benefits begin after your first successfully received order.', 'items'=>$items];
      $k = 'co.bn.'.md5((string)$r->ip()); \Illuminate\Support\Facades\Cache::add($k, 0, 3600); $show = (int)\Illuminate\Support\Facades\Cache::get($k, 0) < 60; \Illuminate\Support\Facades\Cache::increment($k);
      $b = \App\Support\CustomerBenefit::check($phone, $c); $held = in_array($b['status'], ['Review Required', 'Suspended'], true);
      $tier = \App\Support\OrderDiscount::tier($phone, $c);
      $out = ['mode'=>$q['mode'] ?? null, 'state'=>'known', 'status'=>$held ? 'Not Eligible' : $b['status'], 'items'=>$held ? [] : $items, 'msg'=>$held ? 'Some customer benefits are currently unavailable for this order.' : ($b['received'] < 1 ? 'Membership and loyalty offers start after your first successfully received order.' : '✓ Welcome back. Your saved information and eligible benefits have been loaded.')];
      if ($show) { $out['membership'] = $tier['name'] ?? null; $out['received'] = (int)$b['received']; $out['rate'] = $b['rate'] !== null ? (int)round($b['rate']) : null; }
      if ($held) $out['items'] = array_values(array_filter($items, fn($i) => !preg_match('/Membership|VIP|Returning|Birthday|Welcome|Referral/i', $i['label'])));
      return $out;
    } catch (\Throwable $e) { return ['state'=>'error', 'msg'=>'Unable to load customer benefits right now.', 'items'=>[]]; }
  }
  /** AI chat quotation: the very same calculation the website checkout uses (customer known by phone). */
  public static function aiQuote(array $want, int $area, string $phone, string $addr = ''): array { return app(self::class)->quote($want, $area, '', $phone, false, $addr); }
  /** One price calculation for the cart, the checkout and the saved order. */
  private function quote(array $want, int $area, string $code, ?string $phone = null, bool $verified = false, string $addr = ''): array {
    $b = $this->base(); $rules = $b['rules'];
    $rows = $want ? $this->q()->whereIn('id', array_keys($want))->get(['id','ext_id','name','price','compare_price','image','stock','category_id','buy2_price','buy3_price','buy4_price','buy5_price']) : collect();
    $items = []; $sub = 0; $qty = 0; $bsave = 0.0;
    foreach ($rows as $p) { $n = $want[$p->id]; $lst = (float)$p->price * $n; $ln = self::bundleLine($p, $n); $bs = round($lst - $ln, 2); $bsave += $bs; $items[] = ['id'=>$p->id, 'ext'=>(string)(($p->ext_id ?? null) ?: $p->id), 'name'=>$p->name, 'image'=>$p->image, 'url'=>self::url($p), 'price'=>(float)$p->price, 'compare'=>(float)$p->compare_price, 'qty'=>$n, 'line'=>$ln, 'list'=>$lst, 'bsave'=>$bs, 'oos'=>!\App\Support\StockGuard::sellable($p)]; $sub += $lst; $qty += $n; }
    $ndSet = \App\Support\PrepaidOnly::catSet(); $ndIds = []; $ndSub = 0.0; if ($ndSet) foreach ($rows as $p) if (isset($ndSet[(int)$p->category_id])) $ndIds[(int)$p->id] = true; foreach ($items as $i) if (isset($ndIds[(int)$i['id']])) $ndSub += (float)$i['line'];
    $dv = \App\Support\Delivery::compute((float)$sub, $area, $addr, '', array_column($items, 'id')); $ship = $dv['charge'];
    $eng = \App\Support\OrderDiscount::compute($phone, (float)($sub - $bsave - $ndSub), $code !== '' ? $code : null, ['web'=>true, 'verified'=>$verified, 'known'=>($phone && ($kc = \App\Support\OrderDiscount::customer($phone)) && !$kc->blocked), 'app'=>$verified ? \App\Support\AppReward::installId() : null, 'qty'=>$qty, 'qprice'=>$bsave > 0, 'bsave'=>$bsave, 'items'=>array_map(fn($i) => ['product_id'=>$i['id'], 'price'=>$i['price'], 'qty'=>$i['qty']], array_values(array_filter($items, fn($i) => !isset($ndIds[(int)$i['id']]))))]);
    $app = array_values(array_filter($eng['lines'], fn($l) => $l['applied'])); $disc = (float)$eng['total'] + $bsave;
    if ($bsave > 0) array_unshift($app, ['key'=>'qprice', 'type'=>'Bundle Price', 'rule'=>'Quantity price (Buy 2-5)', 'source'=>'Product bundle price', 'pct'=>null, 'amount'=>round($bsave, 2), 'label'=>'Bundle price – saved '.self::tk($bsave), 'applied'=>true]); $notes = array_map(fn($l) => in_array($l['key'] ?? '', ['special', 'vip', 'membership', 'returning', 'birthday'], true) ? (string)($l['type'] ?? $l['label']) : $l['label'], $app); // customers never see the % of their own membership / profile discounts
    if ($eng['free_ship'] && $ship > 0 && empty($dv['paid'])) { $dv['free_discount'] = $dv['original']; $dv['free'] = true; $dv['reason'] = 'Free delivery coupon'; $ship = 0; }
    $cOk = null; $cmsg = $eng['coupon_error'] ?? ''; foreach ($app as $l) if (($l['key'] ?? '') === 'coupon') { $cOk = $l['code']; $cmsg = 'Coupon applied: '.self::tk($l['amount']).' off'; }
    if ($code !== '' && !$cOk && !$cmsg) $cmsg = ($eng['free_ship'] && empty($dv['paid'])) ? 'Free delivery applied' : 'Coupon is valid but another discount is already better for this order';
    $disc = min($disc, $sub);
    return ['prepaid_only'=>\App\Support\PrepaidOnly::any(array_column($items, 'id')), 'items'=>$items, 'sub'=>$sub, 'ship'=>$items ? $ship : 0, 'disc'=>$disc, 'total'=>$items ? $sub + $ship - $disc : 0, 'coupon'=>$cOk, 'coupon_msg'=>$cmsg, 'notes'=>$notes, 'applied'=>$app, 'profile'=>$eng['profile'], 'zone'=>$dv['zone'], 'ship_orig'=>$dv['original'], 'ship_free'=>(bool)$dv['free'], 'ship_unlocked'=>(bool)$dv['unlocked'], 'dv'=>$dv, 'mode'=>$eng['mode'] ?? null, 'cand'=>$eng['lines'] ?? [], 'bsnap'=>self::benefitSnap($phone, $app, $eng['profile'] ?? null, $eng['lines'] ?? [])];
  }
  public function cartItems(Request $r) {
    \App\Support\OrderDiscount::$pick = preg_match('/^[a-z]{3,20}$/', (string)$r->input('pick')) ? (string)$r->input('pick') : null;
    $sp = \App\Support\OrderDiscount::sessionPhone($r); $ph = null;
    // The preview must equal what the order will save. Order submit uses the TYPED number and counts the customer profile only when that number is the logged-in one; so the preview does the same (a typed number that is not the logged-in number gets only the non-profile discounts, and the profile is not revealed).
    $t = substr(preg_replace('/\D/', '', (string)$r->input('ph')), -11); if (!preg_match('/^01\d{9}$/', $t)) $t = null;
    if ($sp && $t && $t !== \App\Support\Chat::phone($sp)) { $ph = $t; $sp = null; }
    elseif (!$sp && $t) $ph = $t;
    $q = $this->quote($this->parseItems($r->input('ids')), (int)$r->input('area', 1), trim(mb_substr((string)$r->input('code'), 0, 40)), $sp ?: $ph, (bool)$sp, mb_substr((string)$r->input('addr'), 0, 300));
    if ($ph) $q['profile'] = null;
    $q['benefits'] = $t ? self::benefitsView($t, $q, $r) : null; unset($q['cand'], $q['bsnap']);
    // customers see which benefits apply and the TOTAL discount, never the percentage of each benefit
    $np = fn($x) => trim(preg_replace('/\s{2,}/u', ' ', preg_replace(['/\s*[–—-]\s*\d+(?:\.\d+)?\s*%/u', '/\(?\s*\d+(?:\.\d+)?\s*%\s*\)?/u'], ['', ' '], (string)$x)));
    foreach (($q['applied'] ?? []) as $i => $l) { $q['applied'][$i]['pct'] = null; foreach (['label', 'type', 'rule'] as $f) if (isset($l[$f])) $q['applied'][$i][$f] = $np($l[$f]); }
    if (!empty($q['notes'])) $q['notes'] = array_map($np, $q['notes']);
    if (!empty($q['benefits']['items'])) foreach ($q['benefits']['items'] as $i => $it) $q['benefits']['items'][$i]['label'] = $np($it['label'] ?? '');
    return response()->json($q)->header('Cache-Control','no-store');
  }
  /** Checkout progress tracking: one "incomplete order" per browser session, updated as the customer types. Never confirms, never sends SMS, never touches stock or courier. */
  public function trackEvent(Request $r) { \App\Support\ProductTrack::event($r); return response()->noContent(); }
  public function trackCheckout(Request $r) {
    $in = json_decode((string)$r->getContent(), true); if (!is_array($in)) $in = $r->all();
    $tok = (string)($in['token'] ?? ''); if (!preg_match('/^[A-Za-z0-9]{16,40}$/', $tok)) return response()->json(['ok'=>false]);
    $phone = substr(preg_replace('/\D/', '', (string)($in['phone'] ?? '')), -11);
    $row = DB::table('incomplete_orders')->where('token', $tok)->orderByDesc('id')->first();
    if ($row && $row->status === 'converted') return response()->json(['ok'=>true, 'done'=>true]);
    if (!preg_match('/^01\d{9}$/', $phone)) return response()->json(['ok'=>false]);
    if (DB::table('customers')->where('phone', $phone)->where('blocked', true)->exists()) return response()->json(['ok'=>false]); // blocked by phone, never by shared IP
    $want = $this->parseItems($in['items'] ?? ''); if (!$want) return response()->json(['ok'=>false]);
    $area = (int)($in['area'] ?? 1); $sp = \App\Support\OrderDiscount::sessionPhone($r);
    $qt = $this->quote($want, $area, trim(mb_substr((string)($in['coupon'] ?? ''), 0, 40)), $phone, $sp !== null && $sp === \App\Support\Chat::phone($phone), (string)($in['address'] ?? ''));
    if (!$qt['items']) return response()->json(['ok'=>false]);
    $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 120)); $addr = trim(mb_substr((string)($in['address'] ?? ''), 0, 500)); $pay = !empty($in['pay_touched']) && in_array($in['pay'] ?? '', ['cod','bank','bkash','nagad'], true) ? $in['pay'] : null;
    $prog = 40 + ($name !== '' ? 20 : 0) + (mb_strlen($addr) >= 5 ? 20 : 0) + ($pay ? 20 : 0);
    $step = $pay ? 'Payment selected' : (mb_strlen($addr) >= 5 ? 'Address entered' : ($name !== '' ? 'Name entered' : 'Phone entered'));
    $pr = \App\Support\OrderDiscount::profile($phone); $b = $this->base();
    $data = ['phone'=>$phone, 'name'=>$name ?: null, 'email'=>trim(mb_substr((string)($in['email'] ?? ''), 0, 120)) ?: null, 'address'=>$addr ?: null, 'district'=>($b['areas'][$area]['n'] ?? null),
      'items'=>json_encode(array_map(fn($i) => ['id'=>$i['id'], 'name'=>$i['name'], 'qty'=>$i['qty'], 'price'=>$i['price']], $qt['items']), JSON_UNESCAPED_UNICODE), 'qty'=>array_sum(array_column($qt['items'], 'qty')),
      'subtotal'=>$qt['sub'], 'discount'=>$qt['disc'], 'shipping'=>$qt['ship'], 'total'=>$qt['total'], 'discount_note'=>$qt['notes'] ? mb_substr(implode(', ', $qt['notes']), 0, 250) : null, 'pay_method'=>$pay,
      'customer_category'=>$pr['category'], 'membership'=>$pr['membership'].($pr['membership_name'] ? ' – '.$pr['membership_name'] : ''), 'note'=>trim(mb_substr((string)($in['note'] ?? ''), 0, 200)) ?: null,
      'last_step'=>$step, 'progress'=>$prog, 'last_activity_at'=>now(), 'updated_at'=>now()];
    if ($row && $row->status !== 'converted') { if ($row->status !== 'open') $data['status'] = 'open'; DB::table('incomplete_orders')->where('id', $row->id)->update($data); }
    else DB::table('incomplete_orders')->insert($data + ['invoice'=>\App\Support\InvoiceNumber::next(), 'token'=>$tok, 'status'=>'open', 'ip'=>$r->ip(), 'created_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function order(Request $r) {
    $ip = $r->ip();
    $want = $r->filled('product') ? [(int)$r->input('product') => max(1, min(50, (int)$r->input('qty', 1)))] : $this->parseItems($r->input('items', []));
    $back = fn($m) => redirect('/checkout?e='.rawurlencode($m));
    if (!$want) return $back('Your cart is empty');
    \App\Support\PhoneRule::trim($r);
    $v = \Illuminate\Support\Facades\Validator::make($r->all(), ['name'=>'required|string|max:120','phone'=>['required','string',new \App\Support\PhoneRule],'email'=>'nullable|email|max:120','address'=>'required|string|max:500','area'=>'nullable|integer','district'=>'nullable|string|max:60','note'=>'nullable|string|max:500','coupon'=>'nullable|string|max:40','website'=>'max:0','pay'=>'nullable|in:cod,bank,bkash,nagad,shurjopay','pay_account'=>'nullable|string|max:80','pay_txn'=>['nullable','string','max:60','regex:/^[A-Za-z0-9\-_\/ ]+$/'],'pay_proof'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:3072'], ['phone.regex'=>'সঠিক মোবাইল নম্বর লিখুন (01XXXXXXXXX)','required'=>'মোবাইল নম্বর, নাম ও ঠিকানা লিখুন।','email'=>'সঠিক ইমেইল লিখুন অথবা খালি রাখুন।']);
    if ($v->fails()) return $back($v->errors()->first());
    $d = $v->validated();
    $pm = ['label'=>'COD','txn'=>null,'acct'=>null,'proof'=>null,'status'=>null];
    $pk = $d['pay'] ?? 'cod';
    if ($pk === 'cod' && \App\Support\PrepaidOnly::any(array_keys($want))) return $back('এই ক্যাটাগরির পণ্যে ক্যাশ অন ডেলিভারি নেই। সম্পূর্ণ পেমেন্ট করে অর্ডার করুন।');
    if (in_array($pk, ['cod', 'shurjopay'], true) && DB::table('payment_methods')->where('kind', $pk)->where('active', false)->exists()) return $back('এই পেমেন্ট মেথডটি এখন চালু নেই।');
    if ($pk === 'shurjopay') {
      if (!\App\Support\ShurjoPay::enabled()) return $back('অনলাইন পেমেন্ট এখন চালু নেই।');
      $pm = ['label'=>'ShurjoPay (Online)','txn'=>null,'acct'=>null,'proof'=>null,'status'=>'Awaiting Payment'];
    } elseif ($pk !== 'cod') {
      if (!DB::table('payment_methods')->where('kind',$pk)->where('active',true)->exists()) return $back('এই পেমেন্ট মেথডটি এখন চালু নেই।');
      $tx = trim((string)($d['pay_txn'] ?? ''));
      if (strlen($tx) < 4) return $back('Transaction ID / Reference দিন');
      if (DB::table('orders')->where('pay_txn',$tx)->whereIn('pay_status',['Verification Pending','Verified'])->exists()) return $back('এই Transaction ID আগে ব্যবহার হয়েছে। সঠিক ID দিন বা WhatsApp-এ জানান।');
      $pm = ['label'=>PaymentController::KINDS[$pk],'txn'=>$tx,'acct'=>$d['pay_account'] ?? null,'proof'=>$r->hasFile('pay_proof') ? $r->file('pay_proof')->store('pay-proofs','local') : null,'status'=>'Verification Pending'];
    }
    $phone = substr(preg_replace('/\D/','',$d['phone']), -11);
    // Protection is by customer phone, never by IP: many genuine customers share one WiFi / ISP NAT address.
    if (DB::table('customers')->where('phone',$phone)->where('blocked',true)->exists()) return $back('অনুগ্রহ করে কল করে অর্ডার করুন।');
    if (DB::table('orders')->where('phone',$phone)->where('created_at','>',now()->subMinutes(10))->count() >= 10) return $back('এই নম্বর থেকে অল্প সময়ে অনেকগুলো অর্ডার এসেছে। দয়া করে কল করে অর্ডার করুন।');
    $b = $this->base(); $area = (int)($d['area'] ?? 1);
    \App\Support\OrderDiscount::$pick = preg_match('/^[a-z]{3,20}$/', (string)$r->input('pick')) ? (string)$r->input('pick') : null;
    $sp = \App\Support\OrderDiscount::sessionPhone($r); $qt = $this->quote($want, $area, trim((string)($d['coupon'] ?? '')), $phone, $sp !== null && $sp === \App\Support\Chat::phone($phone), (string)$d['address']);
    if (!$qt['items']) return $back('These products are no longer available');
    if ($oos = array_values(array_filter($qt['items'], fn($i) => !empty($i['oos'])))) return $back(implode(', ', array_map(fn($i) => $i['name'], array_slice($oos, 0, 3))).' — Out of Stock right now. Remove it from the cart or call us for the next arrival. / এই পণ্যটি এখন স্টক আউট।');
    $where = $d['district'] ?? ($b['areas'][$area]['n'] ?? null);
    $note = trim((string)($d['note'] ?? ''));
    $meta = \App\Support\OrderDiscount::meta($qt['applied'], $qt['profile'], 'Automatic', (float)$qt['disc']);
    $incTok = (string)$r->input('inc');
    // Duplicate protection: one checkout token = one order; identical content within 90s returns the order that already exists (no 2nd order, stock, SMS, courier or CAPI event).
    $fp = \App\Support\OrderDedupe::fingerprint($phone, $qt['items'], $qt['total'], (string)$d['address'], $pk); $dupId = null;
    try {
    $id = DB::transaction(function () use ($d, $qt, $phone, $ip, $where, $note, $pm, $meta, $incTok, $fp, &$dupId) {
      \App\Support\OrderDedupe::lock($phone);
      [$exOrder, $idemKey] = \App\Support\OrderDedupe::resolve($incTok, $fp);
      if ($exOrder) { $dupId = (int)$exOrder->id; return $dupId; }
      if (!empty($pm['txn'])) { // same Transaction ID submitted twice at once: serialise on it, then re-check
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['paytxn:'.$pm['txn']]);
        if (DB::table('orders')->where('pay_txn',$pm['txn'])->whereIn('pay_status',['Verification Pending','Verified'])->exists()) throw new \RuntimeException('txn_used');
      }
      $cust = DB::table('customers')->where('phone',$phone)->value('id'); if ($cust) { DB::table('customers')->where('id',$cust)->update(array_filter(['email'=>$d['email'] ?? null],'strlen')+['updated_at'=>now()]); } else $cust = DB::table('customers')->insertGetId(['name'=>$d['name'],'phone'=>$phone,'email'=>($d['email'] ?? null) ?: null,'address'=>$d['address'],'ip'=>$ip,'created_at'=>now(),'updated_at'=>now()]);
      $invNo = \App\Support\InvoiceNumber::reserved($incTok, null, $phone) ?: \App\Support\InvoiceNumber::next();
      $id = DB::table('orders')->insertGetId(['invoice'=>$invNo,'idempotency_key'=>$idemKey,'order_fingerprint'=>$fp,'store_id'=>(DB::table('stores')->whereNotIn('id', DB::table('watch_source')->whereNotNull('store_id')->select('store_id'))->orderBy('id')->value('id') ?: DB::table('stores')->orderBy('id')->value('id')),'customer_id'=>$cust,'customer_name'=>$d['name'],'phone'=>$phone,'email'=>($d['email'] ?? null) ?: null,'address'=>$d['address'],'district'=>$where,'source'=>'Web Site','shipping'=>$qt['ship'],'delivery_zone'=>mb_substr((string)$qt['zone'],0,160),'delivery_original'=>$qt['ship_orig'],'delivery_free_discount'=>$qt['dv']['free_discount'],'delivery_note'=>$qt['dv']['reason'] ? mb_substr($qt['dv']['reason'],0,200) : null,'discount'=>$qt['disc'],'coupon'=>$qt['coupon'],'total'=>$qt['total'],'status'=>'New','note'=>$note ?: null,'payment_method'=>$pm['label'],'pay_txn'=>$pm['txn'],'pay_account'=>$pm['acct'],'pay_proof'=>$pm['proof'],'pay_status'=>$pm['status'],'ip'=>$ip,'courier'=>\App\Support\CourierSync::defaultName(),'created_at'=>now(),'updated_at'=>now()] + $meta + ($qt['bsnap'] ?? []));
      foreach ($qt['items'] as $it) DB::table('order_items')->insert(['order_id'=>$id,'product_id'=>$it['id'],'name'=>$it['name'],'qty'=>$it['qty'],'price'=>$it['price'],'created_at'=>now(),'updated_at'=>now()] + \App\Support\BundlePricing::snapshot((int)$it['id'], (int)$it['qty'], (float)$it['price'], (float)$it['line']));
      if ($qt['coupon'] && !DB::table('coupons')->where('code',$qt['coupon'])->where(fn($q) => $q->whereNull('usage_limit')->orWhere('usage_limit', 0)->orWhereColumn('used_count', '<', 'usage_limit'))->increment('used_count')) throw new \RuntimeException('coupon_used_up'); // atomic: the last use cannot be taken twice
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>null,'user_name'=>'Website','from_status'=>null,'to_status'=>'New','note'=>$pm['status'] === 'Awaiting Payment' ? 'Order placed on website. Awaiting online payment (ShurjoPay)' : ($pm['status'] ? 'Order placed on website. Payment Verification Pending ('.$pm['label'].', ref '.$pm['txn'].')' : 'Order placed on website'),'created_at'=>now(),'updated_at'=>now()]);
      try { \App\Support\OrderActivity::log((int)$id, 'checkout_discounts', null, null, 'Benefits checked at checkout · applied: '.(implode(', ', array_map(fn($l) => ($l['type'] ?? '').' ৳'.number_format((float)($l['amount'] ?? 0)), $qt['applied'])) ?: 'none').' · total discount ৳'.number_format((float)$qt['disc']).' · payable ৳'.number_format((float)$qt['total'])); } catch (\Throwable $e) {}
      return $id;
    });
    } catch (\Illuminate\Database\QueryException $e) {
      if (($e->errorInfo[0] ?? '') !== '23505' || !($tk = \App\Support\OrderDedupe::token($incTok)) || !($ex2 = DB::table('orders')->where('idempotency_key', $tk)->first())) throw $e;
      $dupId = (int)$ex2->id; $id = $dupId; // lost a same-millisecond race: the other request already created the order
    } catch (\RuntimeException $e) {
      if ($e->getMessage() === 'coupon_used_up') return $back('এই কুপনের ব্যবহারের সীমা শেষ হয়ে গেছে। কুপন ছাড়া অর্ডার করুন বা অন্য কুপন দিন।');
      if ($e->getMessage() === 'txn_used') return $back('এই Transaction ID আগে ব্যবহার হয়েছে। সঠিক ID দিন।');
      throw $e;
    }
    if ($dupId) { // the same submit again (double click / refresh / retry): show the order that already exists
      $ex = DB::table('orders')->where('id', $dupId)->first(['invoice','pay_status','payment_method']);
      if ($pk === 'shurjopay' && $ex->pay_status === 'Awaiting Payment') return redirect(\Illuminate\Support\Facades\URL::temporarySignedRoute('pay.shurjopay.start', now()->addHours(2), ['invoice'=>$ex->invoice]));
      return redirect(\Illuminate\Support\Facades\URL::temporarySignedRoute('order.done', now()->addDays(3), ['invoice'=>$ex->invoice, 'dup'=>1]));
    }
    Cache::forget('store.live3'); Cache::forget('store.live4'); Cache::forget('store.live5');
    try { $__avId = (int)$id; dispatch(function () use ($__avId) { try { \App\Support\AiVerify::run($__avId, 'order_created', true, false); } catch (\Throwable $e) {} })->afterResponse(); } catch (\Throwable $e) {} // AI Verify with the live courier fraud check, run after the customer already got the response (never slows checkout)
    try { \App\Support\Referral::onOrder((int)$id, $phone, $qt); \App\Support\Winback::onOrder($phone, (int)$id, $qt); \App\Support\Voice::onOrder($phone, (int)$id, $qt['applied'] ?? []); } catch (\Throwable $e) {}
    try { \App\Support\AppReward::onOrder($phone, (int)$id, $qt['applied'] ?? [], \App\Support\AppReward::installId()); } catch (\Throwable $e) {}
    $inv = DB::table('orders')->where('id',$id)->value('invoice');
    if ($pk !== 'shurjopay') { \App\Support\OrderSms::placed((int)$id); try { \App\Support\Capi::purchase((int)$id, $r); } catch (\Throwable $e) {} }
    try { DB::table('incomplete_orders')->where('status', 'open')->where(fn($q) => $q->where('token', (string)$r->input('inc'))->orWhere('phone', $phone))->update(['status'=>'converted', 'order_id'=>$id, 'updated_at'=>now()]); } catch (\Throwable $e) {}
    if ($pk === 'shurjopay') return redirect(\Illuminate\Support\Facades\URL::temporarySignedRoute('pay.shurjopay.start', now()->addHours(2), ['invoice'=>$inv]));
    return redirect(\Illuminate\Support\Facades\URL::temporarySignedRoute('order.done', now()->addDays(3), ['invoice'=>$inv]));
  }
  private function pack(string $invoice): array {
    $o = DB::table('orders')->where('invoice',$invoice)->first(); abort_unless($o, 404);
    $items = DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->where('i.order_id',$o->id)->get(['i.product_id','p.ext_id','i.name','i.qty','i.price','p.image']);
    $pay = $o->pay_status ? ($o->pay_status==='Verification Pending' ? 'Payment Verification Pending' : ($o->pay_status==='Verified' ? 'Paid (verified)' : ($o->pay_status==='Awaiting Payment' ? 'Online payment not completed' : 'Payment '.$o->pay_status))) : 'Cash on delivery. Pay when you receive';
    return ['o'=>$o,'items'=>$items,'sub'=>$items->sum(fn($i)=>$i->price*$i->qty),'payText'=>$pay];
  }
  public function done($invoice) {
    $u = fn($extra = []) => \Illuminate\Support\Facades\URL::temporarySignedRoute('order.invoice', now()->addDays(3), ['invoice'=>$invoice] + $extra);
    try {
      return response()->view('store.success', $this->base($this->pack($invoice) + ['viewUrl'=>$u(), 'dlUrl'=>$u(['print'=>1]), 'title'=>'Order Placed | DutyFree BD','desc'=>'','canon'=>url('/'),'noindex'=>true]));
    } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { throw $e;
    } catch (\Throwable $e) {
      report($e);
      $inv = e((string)$invoice);
      return response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Order Placed | DutyFree BD</title></head><body style="font-family:Inter,Bornomala,sans-serif;background:#f8fafc;margin:0;padding:24px"><div style="max-width:440px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;text-align:center"><div style="font-size:42px">✅</div><h1 style="font-size:20px;margin:8px 0">Order Placed Successfully</h1><p style="color:#374151;font-size:15px">Your order number: <b>'.$inv.'</b></p><p style="color:#6b7280;font-size:13px;line-height:1.6">Thank you! Our team will call you shortly to confirm your order. / ধন্যবাদ! আমাদের প্রতিনিধি শীঘ্রই ফোন করে অর্ডার কনফার্ম করবেন।</p><a href="/" style="display:inline-block;margin-top:12px;background:#FF5A00;color:#fff;text-decoration:none;padding:10px 18px;border-radius:10px;font-weight:700">Continue shopping</a></div></body></html>', 200, ['Content-Type'=>'text/html; charset=utf-8']);
    }
  }
  public function invoice($invoice) { return response()->view('store.invoice', $this->pack($invoice) + ['print'=>request()->boolean('print')]); }
  /** Tracking by invoice number only: shows status steps and products, never the customer's name, phone or address. */
  public function track(Request $r) {
    // Either the invoice number or the mobile number is enough. With both, they must belong to the same order.
    $o = null; $logs = collect(); $others = collect(); $askPhone = false; $bad = false; $nf = false;
    $cols = ['id','invoice','status','shipping','discount','total','created_at','payment_method','pay_status','pay_txn','phone'];
    $inv = trim((string)$r->input('invoice')); $rawPh = (string)$r->input('phone');
    $ph = substr(preg_replace('/\D/', '', $rawPh), -10); $hasPh = strlen($ph) === 10; $hasKey = $r->filled('k');
    if ($inv !== '') {
      $row = DB::table('orders')->whereIn('invoice', \App\Support\Inv::candidates($inv) ?: ['-'])->first($cols);
      if (!$row) { if ($hasPh || $hasKey) $bad = true; else $nf = true; }
      elseif (!$hasPh && !$hasKey) { $o = $row; unset($o->phone); }   // invoice number alone
      else {
        $okKey = $hasKey && hash_equals(\App\Support\Inv::trackKey($row->invoice), (string)$r->input('k'));
        $okPh = $hasPh && substr(preg_replace('/\D/', '', (string)$row->phone), -10) === $ph;
        if ($okKey || $okPh) { $o = $row; unset($o->phone); } else $bad = true;
      }
    } elseif (trim($rawPh) !== '') {
      if (!$hasPh) $bad = true;
      else {
        $rows = DB::table('orders')->whereRaw("right(regexp_replace(phone, '\\D', '', 'g'), 10) = ?", [$ph])->orderByDesc('id')->limit(6)->get($cols);
        if ($rows->isEmpty()) $nf = true;
        else { $o = $rows->first(); $others = $rows->slice(1)->values(); unset($o->phone); }   // mobile number alone: the latest order, plus a short list of the others
      }
    }
    if ($o) $logs = DB::table('order_status_logs')->where('order_id',$o->id)->orderBy('id')->get(['to_status','created_at']);
    return response()->view('store.track', $this->base(['o'=>$o, 'logs'=>$logs, 'others'=>$others, 'askPhone'=>$askPhone, 'badTrack'=>$bad, 'notFound'=>$nf, 'items'=>$o ? DB::table('order_items as i')->leftJoin('products as p','p.id','=','i.product_id')->where('i.order_id',$o->id)->get(['i.product_id','p.ext_id','i.name','i.qty','i.price']) : collect(), 'title'=>'Order Tracking | DutyFree BD', 'desc'=>'', 'canon'=>url('/track'), 'noindex'=>true]))->header('Cache-Control','no-store');
  }
  private function page($page) {
    if (empty($page->canonical) && request()->isMethod('get') && !str_ends_with(request()->getPathInfo(), '/') && empty($GLOBALS['seo_preview']) && !request()->query('preview')) return redirect(url('/'.$page->slug).'/'.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301);
    $S = \App\Support\SeoMgr::class; $img = fn($u) => $u ? (str_starts_with($u,'http') ? $u : url($u)) : null;
    $noidx = !empty($GLOBALS['seo_preview']) || (isset($page->robots_index) && !$page->robots_index) || $S::get('seo_global_index','1') === '0';
    return response()->view('store.page', $this->base(['page'=>$page, 'title'=>isset($page->status) ? $S::effTitle($page) : (($page->meta_title ?: $page->title).' | DutyFree BD'), 'desc'=>(isset($page->status) ? $S::effDesc($page) : $page->meta_description) ?: \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($page->body ?? $page->content ?? '')))), 155, '…') ?: ($page->title.' – DutyFree BD. Original imported products, cash on delivery and nationwide home delivery in Bangladesh.'),
      'canon'=>!empty($page->canonical) ? $page->canonical : url('/'.$page->slug).'/', 'noindex'=>$noidx, 'keywords'=>$page->keywords ?? null, 'ogimg'=>$img($page->og_image ?? $page->featured_image ?? null)]));
  }
  public function blog() {
    $posts = DB::table('posts')->where('active',true)->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now()))->orderByDesc('published_at')->orderByDesc('id')->get();
    return response()->view('store.blog', $this->base(['posts'=>$posts, 'title'=>'Blog | DutyFree BD', 'desc'=>'Guides and articles from DutyFree BD', 'canon'=>url('/blog')]));
  }
  public function post($slug) {
    $p = DB::table('posts')->where('slug',$slug)->where('active',true)->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now()))->first();
    if (!$p) return $this->notFound();
    return response()->view('store.post', $this->base(['p'=>$p, 'title'=>($p->meta_title ?: $p->title).' | DutyFree BD', 'desc'=>$p->meta_description ?: $p->excerpt, 'canon'=>url('/blog/'.$p->slug), 'ogimg'=>$p->featured_image ? (str_starts_with($p->featured_image,'http') ? $p->featured_image : url($p->featured_image)) : null]));
  }
  public function cmsExport() {
    return response()->json(['source'=>url('/'), 'pages'=>DB::table('pages')->where('active',true)->get(['slug','title','body','meta_title','meta_description','updated_at']), 'posts'=>DB::table('posts')->where('active',true)->get(['slug','title','featured_image','excerpt','body','meta_title','meta_description','published_at'])]);
  }
  /** /ref/CODE: remember the friend's code for 60 days, then go to the shop. */
  public function referral(string $code) {
    $c = strtoupper($code); $ok = \App\Support\Referral::on() && preg_match('/^[A-Z0-9]{4,20}$/', $c) && \App\Support\Referral::referrer($c);
    $r = redirect('/'); return $ok ? $r->withCookie(cookie('dfref', $c, 60 * 24 * 60, '/', null, null, false)) : $r;
  }
  public function notFound() {
    $pp = request()->path();
    if (preg_match('#^(?:public/)?storage/images/(?:products|product_thumbnail_img|product_gallery|gallery)/([^/]+)$#i', $pp, $m)) {
      $fn = basename(urldecode($m[1]));
      $to = Cache::remember('oldimg.'.md5($fn), 3600, function () use ($fn) {
        $l = DB::table('legacy_products')->whereNotNull('new_product_id')
          ->where(fn($q) => $q->where('thumbnail_img', 'like', '%'.$fn)->orWhereRaw('raw::text like ?', ['%'.str_replace('/', '\\/', $fn).'%'])->orWhereRaw('raw::text like ?', ['%'.$fn.'%']))
          ->first(['new_product_id']);
        if (!$l) return '';
        $p = DB::table('products')->where('id', $l->new_product_id)->value('image');
        if (!$p) $p = DB::table('product_images')->where('product_id', $l->new_product_id)->orderBy('position')->value('path');
        return (string)$p;
      });
      if ($to) return redirect($to, 301);
      return response('', 410);
    }
    if ($rd = \App\Support\SeoMgr::redirect(request()->path())) return redirect($rd->to_url, (int)$rd->type);
    if (preg_match('#^product/([a-z0-9\-]+)$#i', $pp, $lm) && preg_match('/-(\d{3,6})$/', $lm[1], $lid)) { // old site /product/name-EXTID -> current canonical URL
      $lp = $this->q()->where('ext_id', (int)$lid[1])->first() ?: DB::table('products')->where('ext_id', (int)$lid[1])->where('published', true)->first();
      if ($lp) { if (empty($lp->path_slug)) { \App\Support\Slugs::assignAll(); $lp = DB::table('products')->where('id', $lp->id)->first(); } return redirect(self::url($lp), 301); }
      return response()->view('store.missing', $this->base(['title'=>'পেজ পাওয়া যায়নি | DutyFree BD', 'desc'=>'', 'canon'=>url('/'), 'noindex'=>true]), 410); // product no longer exists: tell Google it is gone for good
    }
    if (preg_match('#^public/index\.php(/.*)?$#i', $pp, $pm)) { $qs = request()->getQueryString(); return redirect(($pm[1] ?? '') !== '' ? $pm[1] : '/', 301); } // old /public/index.php/... duplicates -> clean URL (query string dropped)
    if (preg_match('#^custom/page/([a-z0-9\-]+)$#i', $pp, $cm)) return redirect('/'.$cm[1].($cm[1] === 'blog' ? '' : '/'), 301);
    if (preg_match('#^(vape|exclusive-deals|premium-cigarette|nano-cigarette|cigarettes?|cigars?)(/.*)?$#i', $pp)) return redirect('/cigarette-vape/', 301); // old tobacco category URLs -> tobacco hub (noindex)
    return response()->view('store.missing', $this->base(['title'=>'পেজ পাওয়া যায়নি | DutyFree BD', 'desc'=>'', 'canon'=>url('/'), 'noindex'=>true]), 404);
  }
  /**
   * /sitemap.xml is a sitemap index; /sitemap-pages.xml lists home, categories, brands, blog and pages; /sitemap-<category>.xml lists that category's products.
   * All parts are built together once an hour (same rules as before: only pages that really open, tobacco/vape never listed).
   */
  public function sitemap(?string $part = null) {
    $B = \App\Support\Domain::main(); $pre = 'smap16.' . (Cache::get('store.ver') ?: 0) . (\App\Support\CleanSite::on() ? '.c' : '') . '.';
    $idx = Cache::get($pre . 'idx');
    if (!is_array($idx) || ($part !== null && isset($idx[$part]) && !Cache::has($pre . 'p.' . $part))) {
      $parts = $this->sitemapParts(); $idx = [];
      foreach ($parts as $k => $body) { Cache::put($pre . 'p.' . $k, $body, 4200); $idx[$k] = substr_count($body, '<url>'); }
      Cache::put($pre . 'idx', $idx, 3600);
      try { Cache::put('store.sitemap.total', array_sum($idx), 86400); } catch (\Throwable $e) {}
    }
    $h = ['Content-Type' => 'application/xml; charset=utf-8'];
    if ($part === null) {
      $o = ''; $lm = date('c');
      foreach ($idx as $k => $n) if ($n > 0) $o .= '<sitemap><loc>' . e($B . '/sitemap-' . $k . '.xml') . '</loc><lastmod>' . $lm . '</lastmod></sitemap>';
      return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $o . '</sitemapindex>', 200, $h);
    }
    abort_unless(isset($idx[$part]), 404);
    return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . (string)Cache::get($pre . 'p.' . $part, '') . '</urlset>', 200, $h);
  }
  /** @return array<string,string> part name => <url> entries ('pages' first, then one part per main category slug). */
  private function sitemapParts(): array {
    $parts = [];
    $xml = (function () use (&$parts) {
      $B = \App\Support\Domain::main(); $ul = fn($p) => $B.'/'.ltrim($p, '/'); /* sitemap always lists the main domain, whichever domain asked */
      $u = fn($loc, $img = null, $lm = null) => '<url><loc>'.e($loc).'</loc>'.($lm ? '<lastmod>'.date('c', strtotime((string)$lm)).'</lastmod>' : '').'<changefreq>daily</changefreq>'.($img ? '<image:image><image:loc>'.e(preg_match('#^https?://#i', (string)$img) ? $img : $B.'/'.ltrim((string)$img, '/')).'</image:loc></image:image>' : '').'</url>';
      $o = $u($ul('/'));
      foreach ($this->cats() as $c) { if (self::isSmokeCat($c)) continue; $o .= $u($ul($c->slug.'/')); }
      foreach ($this->subs() as $cid => $rows) { $cs = $this->cats()->firstWhere('id', $cid); if ($cs && !self::isSmokeCat($cs)) foreach ($rows as $rw) $o .= $u($ul($cs->slug.'/'.\App\Support\Slugs::subSlug($rw->sub))); }
      if (\Illuminate\Support\Facades\Schema::hasTable('category_nodes')) foreach (DB::table('category_nodes as t')->join('category_nodes as s', 's.id', '=', 't.parent_id')->join('categories as c', 'c.id', '=', 't.category_id')->where('t.level', 3)->where('c.slug', '!=', 'cigarette-vape')->when(\App\Support\CleanSite::on() && \App\Support\CleanSite::catIds(), fn($qq) => $qq->whereNotIn('c.id', \App\Support\CleanSite::catIds()))->where('t.active', true)->where('s.active', true)->whereExists(fn($q) => $q->select(DB::raw(1))->from('products as p')->whereColumn('p.category_id', 't.category_id')->whereColumn('p.sub', 's.name')->whereColumn('p.sub_sub', 't.name')->where('p.published', true))->select('c.slug as cs', 's.name as sn', 't.name as tn')->get() as $ch) $o .= $u($ul($ch->cs.'/'.\App\Support\Slugs::subSlug($ch->sn).'/'.\App\Support\Slugs::slug($ch->tn, 60)));
      try { $smk = $this->cats()->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->all(); $bq = DB::table('products')->where('published', true)->whereNotNull('brand_id')->where(fn($w) => $w->whereNull('group_key')->orWhere('group_key', '!=', 'smoke')); if ($smk) $bq->whereNotIn('category_id', $smk); \App\Support\CleanSite::apply($bq, 'products.'); $bids = $bq->distinct()->pluck('brand_id')->all(); if ($bids) foreach (DB::table('brands')->whereIn('id', $bids)->where('active', true)->get() as $br) { if (!empty($br->noindex) || trim((string)$br->slug) === '') continue; $o .= $u($ul('/brand/' . $br->slug), $br->logo ?: null, $br->updated_at ?? null); } } catch (\Throwable $e) { report($e); }
      $o .= $u($ul('/blog'));
      foreach (DB::table('posts')->where('active',true)->get(['slug']) as $bp) $o .= $u($ul('/blog/'.$bp->slug));
      foreach (DB::table('pages')->where('active',true)->where(fn($q)=>$q->whereNull('robots_index')->orWhere('robots_index',true))->get(['slug']) as $pg) $o .= $u($ul($pg->slug.'/'));
      // only pages that really open: tobacco/vape stays off Google (also when its group_key is empty), and a stock-controlled product at zero stock has no public page (404), so it must not be in the sitemap
      $smkIds = $this->cats()->filter(fn($c) => self::isSmokeCat($c))->pluck('id')->all();
      $pq = \App\Support\StockGuard::listable(DB::table('products')->where('published',true))->where(fn($q)=>$q->whereNull('group_key')->orWhere('group_key','!=','smoke'));
      if ($smkIds) $pq->where(fn($q) => $q->whereNull('category_id')->orWhereNotIn('category_id', $smkIds));
      foreach ($pq->orderBy('id')->cursor() as $p) { $pu = self::url($p); $k = explode('/', ltrim($pu, '/'))[0]; if ($k === 'p' || !preg_match('/^[a-z0-9-]{1,60}$/', $k) || $k === 'pages') $k = 'other-products'; $parts[$k] = ($parts[$k] ?? '') . $u($ul($pu), $p->image, $p->updated_at ?? null); }
      return $o;
    })();
    ksort($parts);
    return ['pages' => $xml] + $parts;
  }
}
