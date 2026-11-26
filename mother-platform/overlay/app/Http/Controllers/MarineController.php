<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** DutyFree BD Marine Supply admin group (marinedutyfreebd.com): access requests from the marine site, which products the marine channel offers, and the marine desk contact shown on the site.
 *  First step only: quotations, port calls, orders and the crew shop are not built yet. Tobacco and alcohol can only ever be "after login" here (owner rule 26 Nov 2026). */
class MarineController extends Controller
{
  const SITE = 'https://marinedutyfreebd.com';
  const PORTS = ['chattogram' => 'Chattogram', 'mongla' => 'Mongla', 'payra' => 'Payra'];
  const KEYS = ['marine_desk_email', 'marine_desk_phone', 'marine_desk_whatsapp'];

  private static function ready(): bool { return Schema::hasTable('marine_access_requests') && Schema::hasTable('marine_products'); }
  public static function pending(): int { try { return Schema::hasTable('marine_access_requests') ? DB::table('marine_access_requests')->whereIn('status', ['pending', 'docs'])->count() : 0; } catch (\Throwable $e) { return 0; } }
  private static function conf(): array { $c = DB::table('settings')->whereIn('key', self::KEYS)->pluck('value', 'key')->all(); $o = []; foreach (self::KEYS as $k) $o[$k] = trim((string)($c[$k] ?? '')); return $o; }
  /** Bonded goods: never public on the marine site, whatever is posted. */
  private static function bonded($cat): bool { return $cat && (StoreController::isSmokeCat($cat) || preg_match('/alcohol|liquor|whisk|wine|beer|spirit|tobacco|cigar/i', (string)($cat->name ?? '') . ' ' . (string)($cat->slug ?? '')) === 1); }

  public function index() {
    if (!self::ready()) return view('marine.index', ['ready' => false]);
    $n = DB::table('marine_access_requests')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
    $p = ['on' => DB::table('marine_products')->where('enabled', true)->count(), 'public' => DB::table('marine_products')->where('enabled', true)->where('visibility', 'public')->count(), 'noprice' => DB::table('marine_products')->where('enabled', true)->whereNull('usd_price')->count()];
    $last = DB::table('marine_access_requests')->orderByDesc('id')->limit(5)->get();
    return view('marine.index', ['ready' => true, 'n' => $n, 'p' => $p, 'last' => $last, 'conf' => self::conf(), 'site' => self::SITE]);
  }

  public function requests(Request $r) {
    if (\App\Support\MarineAccess::ready()) return redirect('/admin/marine/access/owner'); // replaced by Access Management (three subtabs)
    $st = in_array($r->query('status'), ['pending', 'approved', 'rejected'], true) ? $r->query('status') : 'pending';
    $rows = DB::table('marine_access_requests')->where('status', $st)->orderByDesc('id')->paginate(50)->withQueryString();
    $n = DB::table('marine_access_requests')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
    return view('marine.requests', ['rows' => $rows, 'st' => $st, 'n' => $n]);
  }
  public function decide(Request $r, $id) {
    $q = DB::table('marine_access_requests')->where('id', $id)->first(); if (!$q) return redirect('/admin/marine/requests');
    $ok = $r->input('action') === 'approve'; $note = mb_substr(trim((string)$r->input('note')), 0, 300); $by = (string)(optional(auth()->user())->name ?? 'admin');
    $done = DB::table('marine_access_requests')->where('id', $q->id)->where('status', 'pending')->update(['status' => $ok ? 'approved' : 'rejected', 'staff_note' => $note ?: null, 'decided_by' => $by, 'decided_at' => now(), 'updated_at' => now()]);
    if (!$done) return redirect('/admin/marine/requests')->with('ok', 'Already decided');
    try { \App\Support\Procure::audit('marine.access.' . ($ok ? 'approved' : 'rejected'), 'Marine access request #' . $q->id . ' (' . $q->kind . ': ' . ($q->company ?: $q->name) . ')', 'pending', $ok ? 'approved' : 'rejected'); } catch (\Throwable $e) {}
    return redirect('/admin/marine/requests')->with('ok', $ok ? 'Approved. Account creation is not built yet, so tell the applicant separately.' : 'Rejected');
  }

  public function catalog(Request $r) {
    $q = trim((string)$r->query('q')); $show = in_array($r->query('show'), ['on', 'all', 'featured', 'essential'], true) ? $r->query('show') : 'all'; $cat = (int)$r->query('category');
    $pq = DB::table('products as p')->leftJoin('marine_products as m', 'm.product_id', '=', 'p.id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
      ->select('p.id', 'p.sku', 'p.name', 'p.price', 'p.stock', 'p.image', 'c.name as cat', 'c.slug as cslug', 'c.id as cid', 'm.enabled', 'm.visibility', 'm.ports', 'm.usd_price', 'm.featured', 'm.essential');
    $MC = \App\Support\MarineCats::class; $mcr = $MC::ready(); $mf = (string)$r->query('mcat'); if ($mcr) { $pq->addSelect('m.mcat', 'm.msub', 'm.mcat_by'); if ($MC::ok($mf)) $pq->where('m.mcat', $mf); elseif ($mf === 'none') $pq->where('m.enabled', true)->whereNull('m.mcat'); else $mf = ''; } else $mf = '';
    if ($q !== '') $pq->where(fn($w) => $w->where('p.name', 'ilike', '%' . $q . '%')->orWhere('p.sku', 'ilike', '%' . $q . '%'));
    if ($cat) $pq->where('p.category_id', $cat);
    if ($show === 'on') $pq->where('m.enabled', true);
    if ($show === 'featured') $pq->whereNotNull('m.featured');
    if ($show === 'essential') $pq->where('m.essential', true);
    $rows = $pq->orderByRaw('m.featured asc nulls last')->orderByRaw('coalesce(m.enabled, false) desc')->orderBy('p.name')->paginate(50)->withQueryString();
    $cats = DB::table('categories')->orderBy('name')->get(['id', 'name', 'slug']); $bond = [];
    foreach ($cats as $c) if (self::bonded($c)) $bond[$c->id] = true;
    return view('marine.catalog', ['mcr' => $mcr, 'mf' => $mf, 'mcats' => $MC::CATS, 'mcn' => $mcr ? DB::table('marine_products')->where('enabled', true)->selectRaw("coalesce(mcat, 'none') k, count(*) n")->groupBy('k')->pluck('n', 'k')->all() : [], 'rate' => self::rate(), 'adm' => \App\Support\ProfitFloor::admin(), 'rows' => $rows, 'q' => $q, 'show' => $show, 'cat' => $cat, 'cats' => $cats, 'bond' => $bond, 'ports' => self::PORTS, 'featN' => (int)DB::table('marine_products')->whereNotNull('featured')->count(), 'featMax' => self::FEATURED, 'essN' => (int)DB::table('marine_products')->where('essential', true)->count()]);
  }
  /** Categories that do not belong on a ship-supply site at all (baby goods): never listed on the marine site, with or without login (owner rule 26 Nov 2026). */
  public static function hiddenIds(): array { $o = []; foreach (DB::table('categories')->get(['id', 'name', 'slug']) as $c) if (preg_match('/\bbab(y|ies)\b|infant|diaper|nappy/i', (string)$c->name . ' ' . str_replace('-', ' ', (string)$c->slug)) === 1) $o[] = (int)$c->id; return $o; }
  public static function bondedIds(): array { $o = []; foreach (DB::table('categories')->get(['id', 'name', 'slug']) as $c) if (self::bonded($c)) $o[] = (int)$c->id; return $o; }
  /** New version number for the cached public product pages (never a full cache flush: the cache is shared with the workers). */
  private static function bump(): void { try { \Illuminate\Support\Facades\Cache::forever('marine.ppv', time()); } catch (\Throwable $e) {} }
  public static function rate(): float { try { $v = (float)DB::table('settings')->where('key', 'marine_usd_rate')->value('value'); return $v > 0 ? $v : 0.0; } catch (\Throwable $e) { return 0.0; } }
  /** Owner instruction 26 Nov 2026: put every published store product on the marine channel with a USD price = store price / rate (BDT per 1 USD).
   *  Bonded categories stay "after login"; other products keep a visibility already chosen by hand, new ones become public. The store's own prices are not touched. */
  public function bulkPublish(Request $r) {
    if (!\App\Support\ProfitFloor::admin()) return back()->with('ok', 'Only an admin can run this.');
    $rate = (float)$r->input('rate'); if ($rate < 20 || $rate > 1000 || !$r->input('sure')) return back()->with('ok', 'Enter the BDT per USD rate and tick the box first.');
    $bond = array_flip(self::bondedIds()); $by = (string)(optional(auth()->user())->name ?? 'admin'); $n = 0; $pub = 0; $now = now();
    $have = DB::table('marine_products')->get(['id', 'product_id', 'visibility'])->keyBy('product_id'); $all = implode(',', array_keys(self::PORTS)); // owner 26 Nov 2026: every product can be delivered at every port
    DB::table('products')->where('published', true)->where('price', '>', 0)->orderBy('id')->select('id', 'price', 'category_id')->chunk(500, function ($rows) use (&$n, &$pub, $bond, $have, $rate, $by, $now, $all) {
      $ins = [];
      foreach ($rows as $p) {
        $usd = max(0.01, round((float)$p->price / $rate, 2)); $b = isset($bond[(int)$p->category_id]); $old = $have[$p->id] ?? null;
        $vis = $b ? 'login' : ($old ? $old->visibility : 'public'); if ($vis === 'public') $pub++;
        if ($old) DB::table('marine_products')->where('id', $old->id)->update(['enabled' => true, 'visibility' => $vis, 'ports' => $all, 'usd_price' => $usd, 'updated_by' => $by, 'updated_at' => $now]);
        else $ins[] = ['product_id' => $p->id, 'enabled' => true, 'visibility' => $vis, 'ports' => $all, 'usd_price' => $usd, 'updated_by' => $by, 'created_at' => $now, 'updated_at' => $now];
        $n++;
      }
      if ($ins) DB::table('marine_products')->insert($ins);
    });
    DB::table('settings')->updateOrInsert(['key' => 'marine_usd_rate'], ['value' => (string)$rate, 'updated_at' => $now]);
    self::bump();
    try { \App\Support\Procure::audit('marine.bulk_publish', "Marine channel: $n published store products switched on at $rate BDT per USD ($pub public, " . ($n - $pub) . " after login), all ports", null, ['count' => $n, 'rate' => $rate, 'public' => $pub]); } catch (\Throwable $e) {}
    return redirect('/admin/marine/catalog?show=on')->with('ok', "$n products are now on the marine channel at $rate BDT per USD: $pub public, " . ($n - $pub) . " after login only.");
  }
  /** Ship essentials in bulk: mark (or unmark) every marine product of one category, optionally narrowed by a search word. Admin only. */
  public function bulkEssential(Request $r) {
    if (!\App\Support\ProfitFloor::admin()) return back()->with('ok', 'Only an admin can run this.');
    $cat = (int)$r->input('category'); $q = mb_substr(trim((string)$r->input('q')), 0, 60); $set = $r->input('set') === '1';
    if (!$cat && mb_strlen($q) < 3) return back()->with('ok', 'Choose a category or type a search word (3 letters or more) first.');
    $ids = DB::table('products as p')->join('marine_products as m', 'm.product_id', '=', 'p.id')->where('m.enabled', true)
      ->when($cat, fn($w) => $w->where('p.category_id', $cat))->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('p.name', 'ilike', '%' . $q . '%')->orWhere('p.sku', 'ilike', '%' . $q . '%')))->pluck('m.id');
    $n = 0; foreach ($ids->chunk(1000) as $c) $n += DB::table('marine_products')->whereIn('id', $c->all())->update(['essential' => $set, 'updated_at' => now()]);
    self::bump();
    try { \App\Support\Procure::audit('marine.essential', 'Marine ship essentials: ' . ($set ? 'marked ' : 'unmarked ') . $n . ' products (category ' . ($cat ?: 'any') . ', search "' . $q . '")', null, ['count' => $n, 'set' => $set, 'category' => $cat, 'q' => $q]); } catch (\Throwable $e) {}
    return back()->with('ok', $n . ' products ' . ($set ? 'marked as' : 'removed from') . ' Ship essentials.');
  }
  /** Public product list for the marine site: enabled + public + published only, and never a bonded category (checked again here, whatever the stored flag says). */
  public function publicProducts(Request $r) {
    $q = mb_substr(trim((string)$r->query('q')), 0, 60); $cat = (int)$r->query('category'); $page = max(1, min(700, (int)$r->query('page', 1))); $home = (bool)$r->query('home'); $per = $home ? self::FEATURED : 48; $port = (string)$r->query('port'); if ($home || !isset(self::PORTS[$port])) $port = ''; $ess = !$home && (bool)$r->query('essential'); if ($home) { $q = ''; $cat = 0; $page = 1; }
    $MC = \App\Support\MarineCats::class; $mc = (!$home && $MC::ready() && $MC::ok($r->query('mcat'))) ? (string)$r->query('mcat') : ''; $ms = $mc !== '' ? (string)$MC::sub($mc, $r->query('sub')) : '';
    // Bonded Stores: never listed here, whoever asks. The category card exists; its products open only in a signed-in account with bonded approval.
    if ($mc === 'bonded') return response()->json(['locked' => true, 'total' => 0, 'page' => 1, 'per' => $per, 'port' => $port, 'categories' => [], 'items' => []])->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=120');
    $key = 'marine.pp.' . (int)\Illuminate\Support\Facades\Cache::get('marine.ppv', 0) . '.' . md5($q . '|' . $cat . '|' . $page . '|' . (int)$home . '|' . $port . '|' . (int)$ess . '|' . $mc . '|' . $ms);
    $d = \Illuminate\Support\Facades\Cache::remember($key, 120, function () use ($q, $cat, $page, $per, $home, $port, $ess, $mc, $ms) {
      $bond = array_values(array_unique(array_merge(self::bondedIds(), self::hiddenIds())));
      $b = DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
        ->where('m.enabled', true)->where('m.visibility', 'public')->where('p.published', true)->whereNotNull('m.usd_price');
      if ($bond) $b->where(fn($w) => $w->whereNull('p.category_id')->orWhereNotIn('p.category_id', $bond));
      if (\App\Support\MarineCats::ready()) { $b->where(fn($w) => $w->whereNull('m.mcat')->orWhere('m.mcat', '!=', 'bonded')); if ($mc !== '') $b->where('m.mcat', $mc); if ($ms !== '') $b->where('m.msub', $ms); }
      $cats = (clone $b)->whereNotNull('c.id')->groupBy('c.id', 'c.name')->orderBy('c.name')->get(['c.id', 'c.name'])->map(fn($c) => ['id' => (int)$c->id, 'name' => (string)$c->name])->all();
      if ($q !== '') $b->where(fn($w) => $w->where('p.name', 'ilike', '%' . $q . '%')->orWhere('p.sku', 'ilike', '%' . $q . '%'));
      if ($cat) $b->where('p.category_id', $cat);
      if ($ess) $b->where('m.essential', true);
      if ($port !== '') $b->whereRaw("(',' || coalesce(m.ports, '') || ',') like ?", ['%,' . $port . ',%']);
      $total = (clone $b)->count();
      $cols = ['p.id', 'p.sku', 'p.name', 'p.image', 'p.size', 'p.category_id', 'c.name as cat', 'm.usd_price', 'm.ports', 'm.featured']; $premium = []; $essentials = [];
      if ($home) { // home page: the team's picks first (position 1-8), then in-stock items with a photo to fill up to 8
        $rows = (clone $b)->whereNotNull('m.featured')->orderBy('m.featured')->orderBy('p.id')->limit($per)->get($cols);
        if ($rows->count() < $per) $rows = $rows->concat((clone $b)->whereNull('m.featured')->whereNotNull('p.image')->where('p.image', '!=', '')->orderByDesc('p.stock')->orderBy('p.id')->limit($per - $rows->count())->get($cols));
        // Premium slider (owner 26 Nov 2026): higher-priced consumer goods of the kinds seafarers buy (phones, watches, sunglasses, perfume), a few of each.
        // Product kinds and the price ceiling come from settings marine_premium_words / marine_premium_max; the ceiling keeps servers and other non-crew items out.
        $ids = $rows->pluck('id')->all(); $prem = collect(); $ps = self::js('marine_premium');
        $words = array_slice(array_filter(array_map('trim', (array)($ps['words'] ?? ['smartphone', 'watch', 'sunglasses', 'eau de parfum']))), 0, 8); $max = (float)($ps['max'] ?? 2500) ?: 2500;
        foreach ($words as $wd) $prem = $prem->concat((clone $b)->where('p.name', 'ilike', '%' . $wd . '%')->where('m.usd_price', '<=', $max)->whereNotIn('p.id', $ids)->whereNotNull('p.image')->where('p.image', '!=', '')->orderByDesc('m.usd_price')->orderBy('p.id')->limit(4)->get($cols));
        $prem = $prem->unique('id');
        $premium = $prem->sortByDesc(fn($x) => (float)$x->usd_price)->take(16)->map(fn($x) => self::item($x))->values()->all();
        // Ship essentials row: products the team marked as needed on board, in-stock first.
        $essentials = (clone $b)->where('m.essential', true)->whereNotNull('p.image')->where('p.image', '!=', '')->orderByDesc('p.stock')->orderBy('p.name')->limit(16)->get($cols)->map(fn($x) => self::item($x))->all();
      } else $rows = $b->orderBy('c.name')->orderBy('p.name')->offset(($page - 1) * $per)->limit($per)->get($cols);
      $items = []; foreach ($rows as $x) $items[] = self::item($x);
      return ['total' => $total, 'page' => $page, 'per' => $per, 'port' => $port, 'categories' => $cats, 'items' => $items] + ($home ? ['premium' => $premium, 'essentials' => $essentials, 'essential_total' => (int)(clone $b)->where('m.essential', true)->count()] : []);
    });
    return response()->json($d)->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=120');
  }

  private static function item($x): array {
    $im = trim((string)$x->image); if ($im !== '' && !preg_match('#^https?://#i', $im)) $im = 'https://dutyfreebd.com/' . ltrim($im, '/');
    return ['id' => (int)$x->id, 'sku' => (string)$x->sku, 'name' => (string)$x->name, 'size' => (string)($x->size ?? ''), 'category' => (string)($x->cat ?? ''), 'image' => $im, 'usd' => number_format((float)$x->usd_price, 2, '.', ''),
      'ports' => array_values(array_intersect(array_keys(self::PORTS), explode(',', (string)$x->ports))), 'featured' => isset($x->featured) && $x->featured !== null];
  }
  /** One public product for the marine site's product page. Same rules as the list: on, public, published, priced, never bonded. */
  public function publicProduct($id) {
    $id = (int)$id; $key = 'marine.p1.' . (int)\Illuminate\Support\Facades\Cache::get('marine.ppv', 0) . '.' . $id;
    $d = \Illuminate\Support\Facades\Cache::remember($key, 120, function () use ($id) {
      $x = DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->leftJoin('brands as br', 'br.id', '=', 'p.brand_id')
        ->where('p.id', $id)->where('m.enabled', true)->where('m.visibility', 'public')->where('p.published', true)->whereNotNull('m.usd_price')
        ->when(\App\Support\MarineCats::ready(), fn($w) => $w->where(fn($x) => $x->whereNull('m.mcat')->orWhere('m.mcat', '!=', 'bonded')))
        ->first(['p.id', 'p.sku', 'p.name', 'p.image', 'p.size', 'p.description', 'p.category_id', 'c.name as cat', 'br.name as brand', 'm.usd_price', 'm.ports', 'm.featured']);
      if (!$x || ($x->category_id && in_array((int)$x->category_id, array_merge(self::bondedIds(), self::hiddenIds()), true))) return ['found' => false];
      $it = self::item($x); $it['brand'] = (string)($x->brand ?? '');
      // English-only site: pass the store description on only when it has no Bengali text.
      $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', (string)$x->description)), ENT_QUOTES, 'UTF-8')));
      $it['description'] = ($t !== '' && !preg_match('/[\x{0980}-\x{09FF}]/u', $t)) ? mb_substr($t, 0, 700) : '';
      $ports = []; foreach (self::portConf() as $k => $p) if (($p['status'] ?? '') !== 'hidden') $ports[] = ['key' => $k, 'name' => $p['name'] ?? ucfirst($k), 'available' => in_array($k, $it['ports'], true), 'charge' => $p['charge']];
      $it['port_list'] = $ports;
      return ['found' => true, 'item' => $it];
    });
    return response()->json($d, empty($d['found']) ? 404 : 200)->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=120');
  }

  /** The six marine categories for "Shop by Category", with how many public products each has. Bonded Stores is shown as a card but never counted or listed here. */
  public function publicCategories() {
    $d = \Illuminate\Support\Facades\Cache::remember('marine.mc.' . (int)\Illuminate\Support\Facades\Cache::get('marine.ppv', 0), 300, function () {
      $MC = \App\Support\MarineCats::class; $n = [];
      if ($MC::ready()) { $hid = array_values(array_unique(array_merge(self::bondedIds(), self::hiddenIds())));
        $n = DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->where('m.enabled', true)->where('m.visibility', 'public')->where('p.published', true)->whereNotNull('m.usd_price')->whereNotNull('m.mcat')->where('m.mcat', '!=', 'bonded')
          ->when($hid, fn($w) => $w->where(fn($x) => $x->whereNull('p.category_id')->orWhereNotIn('p.category_id', $hid)))->selectRaw('m.mcat, count(*) n')->groupBy('m.mcat')->pluck('n', 'mcat')->all(); }
      $o = []; foreach ($MC::CATS as $k => $c) $o[] = ['key' => $k, 'name' => $c['name'], 'text' => $c['text'], 'locked' => $k === 'bonded', 'count' => $k === 'bonded' ? null : (int)($n[$k] ?? 0), 'subs' => array_map(fn($sk, $sn) => ['key' => $sk, 'name' => $sn], array_keys($c['subs']), $c['subs'])];
      return ['categories' => $o];
    });
    return response()->json($d)->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=300');
  }
  /** Marine categories in bulk (admin). "auto" = first placement of every product not yet placed by hand; otherwise every product of one mother category goes to the chosen marine category. */
  public function mapCategories(Request $r) {
    $MC = \App\Support\MarineCats::class; if (!\App\Support\ProfitFloor::admin() || !$MC::ready()) return back()->with('ok', 'Only an admin can do this.');
    $by = (string)(optional(auth()->user())->name ?? 'admin'); $bond = array_flip(self::bondedIds()); $n = 0; @set_time_limit(180);
    if ($r->input('mode') === 'auto') {
      $tally = [];
      DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->where(fn($w) => $w->whereNull('m.mcat_by')->orWhere('m.mcat_by', 'auto'))->orderBy('m.id')->select('m.id', 'p.name', 'p.category_id', 'c.name as cat')
        ->chunk(500, function ($rows) use (&$n, &$tally, $bond, $MC) { $set = []; foreach ($rows as $x) { [$k, $sub] = $MC::guess((string)$x->name, (string)($x->cat ?? ''), isset($bond[(int)$x->category_id])); $set[$k . '|' . $sub][] = $x->id; $tally[$k] = ($tally[$k] ?? 0) + 1; $n++; }
          foreach ($set as $key => $ids) { [$k, $sub] = explode('|', $key); DB::table('marine_products')->whereIn('id', $ids)->update(['mcat' => $k, 'msub' => $sub ?: null, 'mcat_by' => 'auto']); } });
      // bonded mother categories are always Bonded Stores, even when a line was placed by hand before
      if ($bond) DB::table('marine_products')->whereIn('product_id', DB::table('products')->whereIn('category_id', array_keys($bond))->select('id'))->where(fn($w) => $w->whereNull('mcat')->orWhere('mcat', '!=', 'bonded'))->update(['mcat' => 'bonded', 'msub' => null]);
      self::bump(); \App\Support\Procure::audit('marine.categories', 'Marine categories: automatic first placement of ' . $n . ' products', null, $tally);
      $t = []; foreach ($tally as $k => $v) $t[] = $MC::CATS[$k]['name'] . ' ' . number_format($v); return back()->with('ok', number_format($n) . ' products placed: ' . implode(', ', $t) . '. Products placed by hand were not changed.');
    }
    $cat = (int)$r->input('category'); $k = (string)$r->input('mcat'); if (!$cat || !$MC::ok($k)) return back()->with('ok', 'Choose a store category and a marine category.');
    if ($k !== 'bonded' && isset($bond[$cat])) return back()->with('ok', 'Tobacco and alcohol categories always stay in Bonded Stores.');
    $n = DB::table('marine_products')->whereIn('product_id', DB::table('products')->where('category_id', $cat)->select('id'))->update(['mcat' => $k, 'msub' => $MC::sub($k, $r->input('msub')), 'mcat_by' => $by, 'updated_at' => now()]);
    self::bump(); \App\Support\Procure::audit('marine.categories', 'Marine categories: store category #' . $cat . ' -> ' . $MC::label($k, $r->input('msub')) . ' (' . $n . ' products)', null, ['category' => $cat, 'mcat' => $k, 'count' => $n]);
    return back()->with('ok', number_format($n) . ' products moved to ' . $MC::label($k, $r->input('msub')) . '.');
  }
  public function saveProduct(Request $r, $id) {
    $p = DB::table('products')->where('id', $id)->first(['id', 'sku', 'name', 'category_id']); if (!$p) return back();
    $c = $p->category_id ? DB::table('categories')->where('id', $p->category_id)->first(['id', 'slug', 'name']) : null;
    $on = (bool)$r->input('enabled'); $vis = $r->input('visibility') === 'public' ? 'public' : 'login'; if (self::bonded($c)) $vis = 'login';
    $ports = implode(',', array_values(array_intersect(array_keys(self::PORTS), (array)$r->input('ports', []))));
    $usd = trim((string)$r->input('usd_price')); $usd = ($usd !== '' && is_numeric($usd) && (float)$usd > 0 && (float)$usd < 1000000) ? round((float)$usd, 2) : null;
    $old = DB::table('marine_products')->where('product_id', $p->id)->first();
    // Home page pick: position 1-8, only for a product that is on, public and priced. At most 8 picks in total.
    $feat = max(0, min(self::FEATURED, (int)$r->input('featured'))) ?: null; $warn = '';
    if ($feat && (!$on || $vis !== 'public' || $usd === null)) { $feat = null; $warn = ' Home page pick removed: the product must be On, Public and have a USD price.'; }
    if ($feat && DB::table('marine_products')->whereNotNull('featured')->where('product_id', '!=', $p->id)->count() >= self::FEATURED) { $feat = null; $warn = ' Not added to the home page: ' . self::FEATURED . ' products are already picked. Remove one first.'; }
    $new = ['enabled' => $on, 'visibility' => $vis, 'ports' => $ports ?: null, 'usd_price' => $usd, 'featured' => $feat, 'essential' => $on && (bool)$r->input('essential'), 'updated_by' => (string)(optional(auth()->user())->name ?? 'admin'), 'updated_at' => now()];
    if (\App\Support\MarineCats::ready() && $r->has('mcat')) { $MC = \App\Support\MarineCats::class; $k = self::bonded($c) ? 'bonded' : ($MC::ok($r->input('mcat')) ? (string)$r->input('mcat') : null); $sub = $MC::sub($k, $r->input('msub')); if (!$old || $old->mcat !== $k || $old->msub !== $sub) $new += ['mcat' => $k, 'msub' => $sub, 'mcat_by' => $k ? (string)(optional(auth()->user())->name ?? 'admin') : null]; }
    if ($old) DB::table('marine_products')->where('id', $old->id)->update($new); else DB::table('marine_products')->insert($new + ['product_id' => $p->id, 'created_at' => now()]);
    try { \App\Support\Procure::audit('marine.product', 'Marine channel: ' . ($p->sku ?: $p->id) . ' ' . mb_substr((string)$p->name, 0, 80), $old ? ['enabled' => (bool)$old->enabled, 'visibility' => $old->visibility, 'ports' => $old->ports, 'usd_price' => $old->usd_price] : null, ['enabled' => $on, 'visibility' => $vis, 'ports' => $ports, 'usd_price' => $usd]); } catch (\Throwable $e) {}
    self::bump();
    return back()->with('ok', ($p->sku ?: 'Product') . ' saved.' . $warn);
  }

  const FEATURED = 8;
  const STATUS = ['live' => 'Now serving', 'soon' => 'Launching soon', 'next' => 'Opening next', 'hidden' => 'Hidden'];
  private static function js(string $k): array { try { $v = DB::table('settings')->where('key', $k)->value('value'); $a = $v ? json_decode($v, true) : null; return is_array($a) ? $a : []; } catch (\Throwable $e) { return []; } }
  private static function put(string $k, array $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => json_encode($v, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]); try { \Illuminate\Support\Facades\Cache::forget('marine.public'); } catch (\Throwable $e) {} }
  private static function txt(Request $r, string $k, int $n): string { return mb_substr(trim(strip_tags((string)$r->input($k))), 0, $n); }
  /** Only images uploaded through this admin (/uploads/...) may be shown on the marine site. */
  private static function img($v): string { $v = trim((string)$v); return preg_match('#^/uploads/[A-Za-z0-9._-]+$#', $v) ? $v : ''; }
  private static function link($v): string { $v = trim((string)$v); return ($v !== '' && preg_match('#^(https://[^\s"<>]+|/[A-Za-z0-9._/-]*)$#', $v)) ? mb_substr($v, 0, 300) : ''; }
  public static function portConf(): array { $c = self::js('marine_ports'); $o = []; foreach (self::PORTS as $k => $n) { $x = is_array($c[$k] ?? null) ? $c[$k] : []; $o[$k] = ['name' => $n, 'status' => isset(self::STATUS[$x['status'] ?? '']) ? $x['status'] : 'live', 'note' => (string)($x['note'] ?? ''), 'shop' => (string)($x['shop'] ?? ''), 'photo' => self::img($x['photo'] ?? ''), 'charge' => self::usd($x['charge'] ?? null)]; } return $o; }
  /** Delivery charge in USD for a port, as text with 2 decimals; '' when not set (the site then says it is confirmed on the quotation). */
  private static function usd($v): string { $v = trim((string)$v); return ($v !== '' && is_numeric($v) && (float)$v >= 0 && (float)$v < 100000) ? number_format((float)$v, 2, '.', '') : ''; }

  public function site() { $b = self::js('marine_banners'); $rows = []; for ($i = 0; $i < 4; $i++) $rows[] = ($b[$i] ?? []) + ['image' => '', 'title' => '', 'text' => '', 'link' => '', 'on' => false]; return view('marine.site', ['c' => self::js('marine_site') + ['strip' => '', 'hero_title' => '', 'hero_sub' => ''], 'banners' => $rows, 'site' => self::SITE]); }
  public function saveSite(Request $r) {
    $old = ['site' => self::js('marine_site'), 'banners' => self::js('marine_banners')];
    $c = ['strip' => self::txt($r, 'strip', 160), 'hero_title' => self::txt($r, 'hero_title', 90), 'hero_sub' => self::txt($r, 'hero_sub', 300)]; $b = [];
    for ($i = 0; $i < 4; $i++) { $x = ['image' => self::img($r->input("b.$i.image")), 'title' => mb_substr(trim(strip_tags((string)$r->input("b.$i.title"))), 0, 80), 'text' => mb_substr(trim(strip_tags((string)$r->input("b.$i.text"))), 0, 160), 'link' => self::link($r->input("b.$i.link")), 'on' => (bool)$r->input("b.$i.on")]; if ($x['image'] === '' && $x['title'] === '') $x['on'] = false; $b[] = $x; }
    self::put('marine_site', $c); self::put('marine_banners', $b);
    try { \App\Support\Procure::audit('marine.site', 'Marine site content and banners', $old, ['site' => $c, 'banners' => $b]); } catch (\Throwable $e) {}
    return redirect('/admin/marine/site')->with('ok', 'Saved. The marine site shows it within a few minutes.');
  }
  public function ports() { return view('marine.ports', ['ports' => self::portConf(), 'status' => self::STATUS, 'site' => self::SITE]); }
  public function savePorts(Request $r) {
    $old = self::js('marine_ports'); $o = [];
    foreach (self::PORTS as $k => $n) { $st = (string)$r->input("p.$k.status"); $o[$k] = ['status' => isset(self::STATUS[$st]) ? $st : 'next', 'note' => mb_substr(trim(strip_tags((string)$r->input("p.$k.note"))), 0, 200), 'shop' => mb_substr(trim(strip_tags((string)$r->input("p.$k.shop"))), 0, 120), 'photo' => self::img($r->input("p.$k.photo")), 'charge' => self::usd($r->input("p.$k.charge"))]; }
    self::put('marine_ports', $o); self::bump();
    try { \App\Support\Procure::audit('marine.ports', 'Marine ports (status, note, partner shop, photo, delivery charge)', $old, $o); } catch (\Throwable $e) {}
    return redirect('/admin/marine/ports')->with('ok', 'Saved. The marine site shows it within a few minutes.');
  }

  private static function vessel($v, bool $full = false): array {
    $o = ['imo' => $v->imo ? (int)$v->imo : null, 'mmsi' => (int)$v->mmsi, 'name' => (string)($v->name ?? ''), 'type' => \App\Support\MarineAis::typeName($v->ship_type ? (int)$v->ship_type : null), 'flag' => \App\Support\MarineAis::flag((int)$v->mmsi), 'seen' => $v->seen_at ? \Carbon\Carbon::parse($v->seen_at)->toIso8601String() : null];
    if (!$full) return $o;
    return $o + ['callsign' => (string)($v->callsign ?? ''), 'length' => $v->length ? (int)$v->length : null, 'beam' => $v->beam ? (int)$v->beam : null, 'draught' => $v->draught !== null ? (float)$v->draught : null,
      'destination' => (string)($v->destination ?? ''), 'eta' => (string)($v->eta ?? ''), 'status' => \App\Support\MarineAis::navName($v->nav_status !== null ? (int)$v->nav_status : null),
      'lat' => $v->lat !== null ? (float)$v->lat : null, 'lon' => $v->lon !== null ? (float)$v->lon : null, 'sog' => $v->sog !== null ? (float)$v->sog : null, 'cog' => $v->cog !== null ? (float)$v->cog : null,
      'position_at' => $v->pos_at ? \Carbon\Carbon::parse($v->pos_at)->toIso8601String() : null];
  }
  private static function vjson(array $d, int $code = 200) { return response()->json($d, $code)->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=30'); }
  /** Public vessel search for the marine site's free tracking: by name (2+ letters) or IMO / MMSI digits. Public AIS data only. */
  public function publicVessels(Request $r) {
    $q = mb_substr(trim((string)$r->query('q')), 0, 40); if (mb_strlen($q) < 2 || !\Illuminate\Support\Facades\Schema::hasTable('marine_vessels')) return self::vjson(['items' => []]);
    $d = \Illuminate\Support\Facades\Cache::remember('marine.vs.' . md5(mb_strtolower($q)), 30, function () use ($q) {
      $b = DB::table('marine_vessels')->whereNotNull('name');
      if (ctype_digit($q)) $b->where(fn($w) => $w->whereRaw('cast(imo as text) like ?', [$q . '%'])->orWhereRaw('cast(mmsi as text) like ?', [$q . '%']));
      else { $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q); $b->where('name', 'ilike', '%' . $like . '%')->orderByRaw('case when name ilike ? then 0 else 1 end', [$like . '%']); }
      return ['items' => $b->orderByRaw('imo is null')->orderByDesc('seen_at')->limit(10)->get()->map(fn($v) => self::vessel($v))->all()];
    });
    return self::vjson($d);
  }
  /** One vessel by IMO number (7 digits) or MMSI (9 digits): full details and last position. */
  public function publicVessel($id) {
    $id = (string)$id; if (!\Illuminate\Support\Facades\Schema::hasTable('marine_vessels')) return self::vjson(['found' => false], 404);
    $d = \Illuminate\Support\Facades\Cache::remember('marine.v1.' . $id, 30, function () use ($id) {
      $v = DB::table('marine_vessels')->where(strlen($id) === 9 ? 'mmsi' : 'imo', (int)$id)->orderByDesc('seen_at')->first();
      return $v ? ['found' => true, 'vessel' => self::vessel($v, true)] : ['found' => false];
    });
    return self::vjson($d, empty($d['found']) ? 404 : 200);
  }
  /** Vessels seen lately near a port (for "ships at this port now" lists). */
  public function publicVesselsNear(Request $r) {
    $P = ['chattogram' => [22.25, 91.75], 'mongla' => [22.35, 89.6], 'payra' => [21.95, 90.3]]; $k = (string)$r->query('port'); if (!isset($P[$k]) || !\Illuminate\Support\Facades\Schema::hasTable('marine_vessels')) return self::vjson(['items' => []]);
    $d = \Illuminate\Support\Facades\Cache::remember('marine.vn.' . $k, 60, function () use ($P, $k) {
      [$la, $lo] = $P[$k];
      return ['items' => DB::table('marine_vessels')->whereNotNull('name')->whereBetween('lat', [$la - 0.45, $la + 0.45])->whereBetween('lon', [$lo - 0.5, $lo + 0.5])->where('pos_at', '>=', now()->subHours(6))->orderByRaw('imo is null')->orderByDesc('length')->limit(40)->get()->map(fn($v) => self::vessel($v, true))->all()];
    });
    return self::vjson($d);
  }

  /** Vessel Tracking: the AIS data source for the marine site. The key is stored encrypted and never shown again. */
  public static function aisOn(): bool { try { return (string)DB::table('settings')->where('key', 'marine_ais_on')->value('value') === '1' && (string)\App\Support\Sms::secret('marine_aisstream_key') !== ''; } catch (\Throwable $e) { return false; } }
  public function tracking() {
    $has = false; try { $has = (string)\App\Support\Sms::secret('marine_aisstream_key') !== ''; } catch (\Throwable $e) {}
    $st = ['vessels' => 0, 'named' => 0, 'last' => null]; try { if (\Illuminate\Support\Facades\Schema::hasTable('marine_vessels')) { $st['vessels'] = (int)DB::table('marine_vessels')->count(); $st['named'] = (int)DB::table('marine_vessels')->whereNotNull('imo')->count(); $st['last'] = DB::table('marine_vessels')->max('seen_at'); } } catch (\Throwable $e) {}
    return view('marine.tracking', ['has' => $has, 'on' => (string)DB::table('settings')->where('key', 'marine_ais_on')->value('value') === '1', 'st' => $st, 'hb' => \Illuminate\Support\Facades\Cache::get('marine_ais_hb'), 'stat' => \Illuminate\Support\Facades\Cache::get('marine_ais_stat'), 'statw' => \Illuminate\Support\Facades\Cache::get('marine_ais_statw'), 'err' => \Illuminate\Support\Facades\Cache::get('marine_ais_err'), 'site' => self::SITE]);
  }
  /** Admin connection test: a few seconds on a busy strait (proves the key works) and on our own area (shows whether AISStream has receivers near Bangladesh). */
  public function testTracking() {
    if (!\App\Support\ProfitFloor::admin()) return back()->with('ok', 'Only an admin can run this.');
    @set_time_limit(60);
    $a = \App\Support\MarineAis::probe([[[1.0, 103.4], [1.5, 104.2]]], 8); $b = \App\Support\MarineAis::probe(\App\Support\MarineAis::BOX, 14);
    $f = fn($x) => ($x['ok'] ? $x['msgs'] . ' vessel messages' . (!empty($x['names']) ? ' (e.g. ' . implode(', ', $x['names']) . ')' : '') : 'failed') . ($x['note'] !== '' ? ' - ' . $x['note'] : '');
    return redirect('/admin/marine/tracking')->with('ok', 'Test result. Singapore Strait, 8 seconds: ' . $f($a) . '. Bay of Bengal (our area), 14 seconds: ' . $f($b) . '.');
  }
  public function saveTracking(Request $r) {
    if (!\App\Support\ProfitFloor::admin()) return back()->with('ok', 'Only an admin can change this.');
    $k = trim((string)$r->input('key')); $msg = [];
    if ($r->input('remove')) { \App\Support\Sms::setSecret('marine_aisstream_key', null); $msg[] = 'Key removed.'; }
    elseif ($k !== '') { if (!preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $k)) return back()->with('ok', 'That does not look like an AISStream key. Copy it again from your AISStream account page.'); \App\Support\Sms::setSecret('marine_aisstream_key', $k); $msg[] = 'Key saved (encrypted).'; }
    DB::table('settings')->updateOrInsert(['key' => 'marine_ais_on'], ['value' => $r->input('on') ? '1' : '0', 'updated_at' => now()]);
    try { \App\Support\Procure::audit('marine.tracking', 'Marine vessel tracking source: ' . ($r->input('on') ? 'on' : 'off') . ($k !== '' ? ', key replaced' : '') . ($r->input('remove') ? ', key removed' : ''), null, null); } catch (\Throwable $e) {}
    return redirect('/admin/marine/tracking')->with('ok', trim(implode(' ', $msg) . ' Saved.'));
  }

  public function settings() { return view('marine.settings', ['conf' => self::conf(), 'site' => self::SITE]); }
  public function saveSettings(Request $r) {
    $d = $r->validate(['marine_desk_email' => 'nullable|email|max:160', 'marine_desk_phone' => ['nullable', 'max:30', 'regex:/^[0-9+ ()-]*$/'], 'marine_desk_whatsapp' => ['nullable', 'max:30', 'regex:/^[0-9+ ()-]*$/']]);
    $old = self::conf();
    foreach (self::KEYS as $k) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => trim((string)($d[$k] ?? '')), 'updated_at' => now()]);
    try { \Illuminate\Support\Facades\Cache::forget('marine.public'); \App\Support\Procure::audit('marine.settings', 'Marine desk contact', $old, self::conf()); } catch (\Throwable $e) {}
    return redirect('/admin/marine/settings')->with('ok', 'Saved. The marine site shows it within a few minutes.');
  }

  /** Public, read-only: marine desk contact for the static marine site (no private data). */
  public function publicInfo() {
    $d = \Illuminate\Support\Facades\Cache::remember('marine.public', 300, function () {
      $c = self::conf(); $abs = fn($u) => $u !== '' ? 'https://dutyfreebd.com' . $u : ''; $ports = [];
      foreach (self::portConf() as $k => $p) $ports[$k] = ['name' => $p['name'], 'status' => $p['status'], 'label' => self::STATUS[$p['status']], 'note' => $p['note'], 'photo' => $abs($p['photo']), 'charge' => $p['charge']]; // partner shop name stays internal
      $bn = []; foreach (self::js('marine_banners') as $b) if (!empty($b['on'])) $bn[] = ['image' => $abs(self::img($b['image'] ?? '')), 'title' => (string)($b['title'] ?? ''), 'text' => (string)($b['text'] ?? ''), 'link' => self::link($b['link'] ?? '')];
      return ['email' => $c['marine_desk_email'], 'phone' => $c['marine_desk_phone'], 'whatsapp' => $c['marine_desk_whatsapp'], 'site' => self::js('marine_site') + ['strip' => '', 'hero_title' => '', 'hero_sub' => ''], 'ports' => $ports, 'banners' => $bn];
    });
    return response()->json($d)->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=300');
  }
  /** Public: the "Request Access" form on the marine site posts here (plain form post, then back to the marine site). Stores the request only; nothing is created or sent. */
  public function accessRequest(Request $r) {
    $back = self::SITE . '/request-access.html';
    if (!self::ready() || trim((string)$r->input('website')) !== '') return redirect()->away($back . '?sent=1'); // honeypot: bots get the same answer
    $kind = in_array($r->input('kind'), ['crew', 'agent'], true) ? $r->input('kind') : 'company'; // agent = local agent or supplier, no vessel needed
    $f = fn($k, $n) => mb_substr(trim(strip_tags((string)$r->input($k))), 0, $n) ?: null;
    $email = $f('email', 160); $imo = preg_replace('/\D/', '', (string)$r->input('imo'));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($imo !== '' && strlen($imo) !== 7)) return redirect()->away($back . '?sent=0');
    if (DB::table('marine_access_requests')->where('email', $email)->where('status', 'pending')->where('created_at', '>', now()->subDay())->exists()) return redirect()->away($back . '?sent=1'); // same request again: no second row
    $vrole = $kind === 'crew' ? ($r->input('vessel_role') === 'captain' ? 'captain' : 'crew') : null; $hasV = \Illuminate\Support\Facades\Schema::hasColumn('marine_access_requests', 'vessel_role');
    if ($vrole === 'captain' && $imo === '') return redirect()->away($back . '?sent=0'); // a captain or authorized officer applies for a named vessel
    $rid = DB::table('marine_access_requests')->insertGetId(($hasV ? ['vessel_role' => $vrole] : []) + ['kind' => $kind, 'company' => $f('company', 160), 'role' => $f('role', 80), 'name' => $f('name', 120), 'crew_rank' => $f('crew_rank', 80), 'email' => $email, 'phone' => $f('phone', 40),
      'imo' => $imo ?: null, 'vessel' => $f('vessel', 120), 'port' => $f('port', 40), 'message' => $f('message', 1000), 'status' => 'pending', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    // documents sent with the application: checked one by one, kept on the private disk; a refused file never blocks the application itself
    try { if (\App\Support\MarineAccess::ready()) foreach (array_slice(array_filter((array)$r->file('docs', [])), 0, \App\Support\MarineAccess::DOC_COUNT) as $f) \App\Support\MarineAccess::storeDoc($f, (int)$rid, null, 'applicant'); } catch (\Throwable $e) { report($e); }
    try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
    return redirect()->away($back . '?sent=1');
  }
}
