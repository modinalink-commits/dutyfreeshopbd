<?php
namespace App\Http\Controllers;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Watch category import (owner, 8 Nov 2026): every scanned product of the source shop priced 0-50,000 BDT becomes OUR product (our code, our URL, our image copy, price = source +15%). Source details live only in watch_source (admin only). */
class WatchImportController extends Controller {
  /** Which source feeds which category: watch <- timeaccess-store.com, gadget <- startech.com.bd (owner, 8 Nov 2026; both +5%, prepaid only, no discount). */
  /** Per-store terms (Edit Store template) override the global defaults: sale %, regular %, price window. */
  private function cfg(): array {
    static $memo = []; $k = (string)request('c');
    if (isset($memo[$k])) return $memo[$k];
    $r = $this->cfgBase(); $r['reg'] = self::regPct(); $r['min'] = 0.0;
    try {
      if ($r['domain'] !== '' && \Illuminate\Support\Facades\Schema::hasColumn('stores', 'sale_pct')) {
        $st = DB::table('stores')->whereRaw('lower(domain) = ? or lower(domain) = ?', [$r['domain'], 'www.' . $r['domain']])->orderByRaw('(select count(*) from store_products sp where sp.store_id = stores.id) desc')->first();
        if ($st) { if ($st->sale_pct !== null) $r['pct'] = (float)$st->sale_pct; if ($st->regular_pct !== null) $r['reg'] = (float)$st->regular_pct; if ($st->price_max) $r['max'] = (float)$st->price_max; if ($st->price_min) $r['min'] = (float)$st->price_min; }
      }
    } catch (\Throwable $e) { report($e); }
    return $memo[$k] = $r;
  }
  private function cfgBase(): array {
    $c = in_array(request('c'), ['gadget', 'sunglass', 'amour', 'beauty', 'fashion'], true) ? request('c') : 'watch'; $set = fn($k, $d) => (string)(DB::table('settings')->where('key', $k)->value('value') ?? $d);
    if (preg_match('/^s(\d+)$/', (string)request('c'), $__m) && ($__st = DB::table('stores')->find((int)$__m[1])) && trim((string)$__st->source_category) !== '') { // owner 13 Nov 2026: any store -> the main category picked as its Source category (first one), same rules: prepaid, sale +15%, regular +25%
      $__cn = trim(explode(',', (string)$__st->source_category)[0]); $__cat = DB::table('categories')->whereNull('parent_id')->where('name', $__cn)->first();
      if ($__cat) return ['c' => 's' . $__st->id, 'domain' => strtolower(preg_replace('#^https?://(www\.)?#i', '', trim((string)$__st->domain, ' /'))), 'cat' => $__cat->slug, 'prefix' => 'DX', 'pct' => (float)($set('source_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'store' . $__st->id];
    }
    if ($c === 'fashion') return ['c' => 'fashion', 'domain' => strtolower($set('fashion_source_domain', '')), 'cat' => 'fashion', 'prefix' => 'DFF', 'pct' => (float)($set('fashion_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'fashion']; // owner 13 Nov 2026: Fashion between Gadget & Electronics and Perfume; source domain = setting fashion_source_domain (set when the owner names the shop)
    if ($c === 'beauty') return ['c' => 'beauty', 'domain' => 'sundora.com.bd', 'cat' => 'beauty', 'prefix' => 'DFB', 'pct' => (float)($set('beauty_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'beauty']; // owner 13 Nov 2026: Beauty <- sundora.com.bd (partner), sale price source +15%, regular (crossed-out) price source +25%
    if ($c === 'amour') return ['c' => 'amour', 'domain' => 'amour.com.bd', 'cat' => 'lighter-accessories', 'prefix' => 'DFA', 'pct' => (float)($set('amour_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'amour'];
    if ($c === 'sunglass') return ['c' => 'sunglass', 'domain' => 'luxotix.com', 'cat' => 'sunglass', 'prefix' => 'DFS', 'pct' => (float)($set('sunglass_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'sunglass'];
    return $c === 'gadget'
      ? ['c' => 'gadget', 'domain' => strtolower($set('gadget_source_domain', 'startech.com.bd')), 'cat' => 'gadget-electronics', 'prefix' => 'DFG', 'pct' => (float)($set('mobile_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'gadget']
      : ['c' => 'watch', 'domain' => strtolower($set('watch_source_domain', 'timeaccess-store.com')), 'cat' => 'watch', 'prefix' => 'DFW', 'pct' => (float)($set('watch_markup_pct', '15') ?: 15), 'max' => 0.0, 'dir' => 'watch'];
  }
  /** Owner 12 Nov 2026: selling (sale) price = source +15% (setting *_markup_pct), crossed-out regular price = source +25% (setting source_regular_pct). */
  public static function regPct(): float { return (float)(DB::table('settings')->where('key', 'source_regular_pct')->value('value') ?: 25); }
  /** Owner 13 Nov 2026: vendor products with source price up to low_price_max (default 1000 Tk) are not sold (delivery eats the margin): never imported, and kept unpublished here. */
  public static function lowMax(): float { return (float)(DB::table('settings')->where('key', 'low_price_max')->value('value') ?: 1000); }
  /** The source store row; if the same domain was added twice (migration + admin), use the one that actually holds scanned products. */
  private function src() { $d = $this->cfg()['domain']; return DB::table('stores')->whereRaw('lower(domain) = ? or lower(domain) = ?', [$d, 'www.' . $d])->orderByRaw('(select count(*) from store_products sp where sp.store_id = stores.id) desc')->orderBy('id')->first(); }
  private function cond($q, $sid) {
    $cf = $this->cfg(); $q = $q->where('store_id', $sid)->where('in_stock', true)->where('price', '>', self::lowMax());
    if (($cf['min'] ?? 0) > 0) $q = $q->where('price', '>=', $cf['min']);
    return $cf['max'] > 0 ? $q->where('price', '<=', $cf['max']) : $q;
  }
  private function stat($sid): array {
    $total = $this->cond(DB::table('store_products'), $sid)->count();
    $done = $this->cond(DB::table('store_products'), $sid)->whereIn('id', DB::table('watch_source')->select('store_product_id'))->count();
    return ['total' => $total, 'done' => $done];
  }
  public function page() {
    $s = $this->src(); abort_unless($s, 404); $st = $this->stat($s->id);
    $csrf = csrf_token();
    return view('admin_raw', ['title' => 'Watch Import', 'h' => '<div style="max-width:640px"><h1>⌚ Watch Import</h1><p>Source shop products (যেকোনো দাম, কোনো সীমা নেই) → আমাদের Watch ক্যাটাগরি, দাম +১৫%।</p><p id=s>Imported <b id=d>'.$st['done'].'</b> / <b>'.$st['total'].'</b></p><div style="background:#eee;height:14px;border-radius:7px"><div id=b style="height:14px;border-radius:7px;background:#f97316;width:'.($st['total'] ? round($st['done'] / $st['total'] * 100) : 0).'%"></div></div><p><button id=go style="padding:10px 18px;font-size:15px">Start / Resume</button> <a href="/admin/watch-sources">Source list (admin only)</a></p><pre id=m></pre><script>var T='.$st['total'].',run=false;async function step(){var r=await fetch("/admin/watch-import/step?c='.$this->cfg()['c'].'",{method:"POST",headers:{"X-CSRF-TOKEN":"'.$csrf.'","Accept":"application/json"}});var j=await r.json();document.getElementById("d").textContent=j.done;document.getElementById("b").style.width=(T?j.done/T*100:0)+"%";document.getElementById("m").textContent=j.note||"";return j}document.getElementById("go").onclick=async function(){if(run)return;run=true;try{while(true){var j=await step();if(j.finished)break}document.getElementById("m").textContent="Finished ✔ "+document.getElementById("m").textContent}catch(e){document.getElementById("m").textContent="Stopped: "+e}run=false}</script></div>']);
  }
  public function step(Request $r) {
    @set_time_limit(90);
    $s = $this->src(); abort_unless($s, 404);
    $cat = DB::table('categories')->where('slug', $this->cfg()['cat'])->first(); if (!$cat) return response()->json(['finished' => true, 'done' => 0, 'note' => 'Watch category not found (deploy migration first).']);
    $lock = Cache::lock('watch-import', 80); if (!$lock->get()) return response()->json(['finished' => false] + $this->stat($s->id));
    $cats = $this->storeCats($s); $xr = in_array((string)request('c'), ['fashion'], true) || preg_match('/^s\d+$/', (string)request('c'));
    try {
      $pct = $this->cfg()['pct'];
      $rows = $this->cond(DB::table('store_products'), $s->id)->whereNotIn('id', DB::table('watch_source')->select('store_product_id'))->orderBy('id')->limit(12)->get();
      if ($rows->isEmpty()) { $this->sync($s->id); foreach (['store.cats', 'store.catslugs', 'store.home11', 'stock.modes'] as $k) Cache::forget($k); Cache::forget('store.hsec3.' . $this->cfg()['cat']); return response()->json(['finished' => true, 'note' => 'All products imported.'] + $this->stat($s->id)); }
      $imgs = []; foreach ($rows as $x) if ($x->image && preg_match('#^https?://#i', $x->image)) $imgs[$x->image] = $x->image;
      $got = $imgs ? StoreScanner::pget(array_values($imgs), 12) : [];
      $dir = public_path('uploads/' . $this->cfg()['dir']); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $made = 0;
      foreach ($rows as $x) {
        try {
          $our = (int)ceil((float)$x->price * (1 + $pct / 100));
          $name = trim(preg_replace('/\s+/u', ' ', (string)$x->name)); $name = trim(str_ireplace(['timeaccess-store.com', 'timeaccess store', 'timeaccess'], '', $name)); $name = trim(preg_replace('/\s*(?:\|\s*Star\s*Tech.*|Price in (?:Bangladesh|BD)\b.*)$/iu', '', $name)); $name = trim(preg_replace('/\bStar\s*Tech\b/iu', '', $name)); if ($name === '') $name = 'Watch';
          $name = trim(preg_replace('/\s{2,}/u', ' ', preg_replace(['/\s*[|\-–]\s*Time\s*Access\b.*$/iu', '/\bTime\s*-?\s*Access\b/iu', '/\bLuxotix\b\s*/iu', '/\bSundora\b\s*/iu'], '', $name)), " \t-|–"); // no trace of the source shop in our product names
          $tc = ($xr ? $this->routeCat((string)$x->name, (string)$x->category, $cats) : null) ?: $cat; // a store feeding 2-3 main categories: each product goes to the right one (owner 13 Nov 2026)
          $sub = self::subFor((string)$name, $x->category, $tc->slug, (string)$x->url);
          $base = \App\Support\Slugs::slug($name) ?: 'watch'; $slug = $base; $i = 2; $subSlug = \App\Support\Slugs::subSlug($sub);
          while (DB::table('products')->where('category_id', $tc->id)->whereRaw(\App\Support\Slugs::subSql() . ' = ?', [$subSlug])->where('path_slug', $slug)->exists()) $slug = $base . '-' . $i++;
          $pid = DB::table('products')->insertGetId(['name' => mb_substr($name, 0, 200), 'category_id' => $tc->id, 'price' => $our, 'compare_price' => (int)ceil((float)$x->price * (1 + $this->cfg()['reg'] / 100)), 'cost' => $x->price, 'stock' => 0, 'published' => true, 'sub' => $sub, 'slug' => $base, 'path_slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
          $code = $this->cfg()['prefix'] . str_pad((string)$pid, 6, '0', STR_PAD_LEFT); $upd = ['sku' => $code];
          $g = $x->image ? ($got[$x->image] ?? null) : null;
          if ($g && ($g['code'] ?? 0) == 200 && !empty($g['body']) && @getimagesizefromstring($g['body'])) {
            $ext = strtolower(pathinfo(parse_url($x->image, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)); if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'jpg';
            if (@file_put_contents($dir . '/' . $code . '.' . $ext, $g['body'])) $upd['image'] = '/uploads/' . $this->cfg()['dir'] . '/' . $code . '.' . $ext;
          }
          $upd['ext_id'] = 1000000 + $pid; DB::table('products')->where('id', $pid)->update($upd);
          DB::table('watch_source')->insert(['product_id' => $pid, 'store_product_id' => $x->id, 'store_id' => $s->id, 'source_name' => $x->name, 'source_sku' => $x->sku, 'source_url' => $x->url, 'source_price' => $x->price, 'our_price' => $our, 'markup_pct' => $pct, 'source_in_stock' => $x->in_stock, 'created_at' => now(), 'updated_at' => now()]);
          $made++;
        } catch (\Throwable $e) { report($e); DB::table('watch_source')->insert(['product_id' => null, 'store_product_id' => $x->id, 'store_id' => $s->id, 'source_name' => $x->name, 'source_price' => $x->price, 'note' => mb_substr($e->getMessage(), 0, 200), 'created_at' => now(), 'updated_at' => now()]); }
      }
      return response()->json(['finished' => false, 'note' => "+$made"] + $this->stat($s->id));
    } finally { $lock->release(); }
  }
  /** Clean sub category from the product itself (source shop categories are messy: "Watche", "watch" ...). */
  public static function subFor(string $name, ?string $srcCat, string $cat, string $url = ''): string {
    if ($cat === 'gadget-electronics') return self::gadgetSub($name);
    if ($cat === 'lighter-accessories') return self::amourSub($name, $url);
    if ($cat === 'beauty') return self::beautySub($name, (string)$srcCat);
    $n = mb_strtolower($name); $c = mb_strtolower(trim((string)$srcCat));
    if ($cat === 'watch') { // owner (8 Nov 2026): the Watch category has only two sub categories: Watch and Accessories
      $acc = str_contains($c, 'accessor') || preg_match('/^\d+\s*slots?\b|\bstrap\s+for\b|\bwatch\s+(box|case|organi[sz]er|winder|roll)\b|organi[sz]er|\bwinder\b|\bwatch\s+tool|\bspring\s*bar|\bbuckle\b/i', $n);
      return $acc ? 'Accessories' : 'Watch';
    }
    if ($cat === 'skin-care') return preg_match('/\b(baby|babies|infant|newborn|toddler)\b/', $n.' '.$c) ? 'Baby Care' : (preg_match('/shaving|razor|beard|deodorant|roll\s*on|body\s*spray|talc|hair\s*removal|\bwax\b/', $n.' '.$c) ? 'Personal Care' : (preg_match('/shampoo|conditioner|hair/', $n.' '.$c) ? 'Hair Care' : 'Skin Care'));
    if ($cat === 'others') return self::babySub($name, (string)$srcCat);
    if ($cat === 'wellness') return self::wellnessSub($name, (string)$srcCat);
    if ($cat === 'fashion') return self::fashionSub($name, (string)$srcCat); // one common sub category list, whichever shop the product comes from (owner 13 Nov 2026)
    $t = trim((string)$srcCat); return $t !== '' ? mb_convert_case($t, MB_CASE_TITLE) : ($cat === 'lighter-accessories' ? 'Accessories' : 'Gadgets');
  }
  /** Main categories a store feeds (its Source categories, in order). Only generic stores (c=sNN) can feed more than one. */
  private function storeCats($s): array {
    if (!preg_match('/^s\d+$/', (string)request('c'))) return [];
    $names = array_filter(array_map('trim', explode(',', (string)$s->source_category)));
    return $names ? DB::table('categories')->whereNull('parent_id')->whereIn('name', $names)->get()->sortBy(fn($c) => array_search($c->name, array_values($names)))->values()->all() : [];
  }
  /** Picks the category of one product for a store that feeds several. Beauty only for real skin / hair-care / cosmetic items; everything else to the first non-Beauty category. */
  /** Product that really belongs to another main category (a fashion shop selling watches, earbuds, skincare ...): its slug, else null. */
  public static function crossRoute(string $name, string $type): ?string {
    $t = mb_strtolower($type . ' | ' . $name);
    if (preg_match('/\b(baby|babies|infant|newborn|toddler)\b/', $t) && preg_match('/lotion|wash|\boil\b|powder|shampoo|cream|soap|bath|sunscreen|rash|moisturi|skin\s*care|skincare|massage|wipes?\b|care\b/', $t) && !preg_match('/diaper|nappy|nappies|feeding|bottle|toy/', $t)) return 'skin-care'; // owner 13 Nov: baby care -> Skin & Hair Care
    if (preg_match('/shaving|razor|after\s*shave|beard|deodorant|roll\s*on|body\s*spray|talc|personal\s*care|hair\s*removal|\bwax\b|sanitary/', $t) && !preg_match('/sanitary\s*pad/', $t)) return 'skin-care'; // owner 13 Nov: personal care -> Skin & Hair Care sub category
    if (preg_match('/\b(kids?|child(?:ren)?|baby|babies|infant|toddler|newborn|junior|nappy|nappies|diapers?)\b|feeding\s*bottle|\bsippy\b|\btoys?\b|\blego\b|barbie|hot\s*wheels|\bpuzzles?\b|\bdolls?\b|play\s*set|playset|action\s*figure|board\s*game/', $t)) return 'others'; // owner 13 Nov 2026: child items -> Baby Care and Others
    if (preg_match('/vitamin|supplement|\bprotein\b|herbal|first\s*aid|thermometer|blood\s*pressure|sanitary|feminine|toothpaste|tooth\s*brush|toothbrush|mouth\s*wash|mouthwash|oral\s*care|\bhygiene\b/', $t)) return 'wellness'; // Personal Wellness
    if (preg_match('/earbud|earphone|headphone|headset|\bspeaker|power\s*bank|\bcharger\b|smart\s*watch|smartwatch|smart\s*band|fitness\s*band|\btws\b|neckband|\btrimmer|\bshaver\b|hair\s*(dryer|straightener|remover)|\bipl\b|massager|\bcamera\b|\bgimbal/', $t)) return 'gadget-electronics';
    if (preg_match('/\blighter\b|zippo|ash\s*tray/', $t)) return 'lighter-accessories';
    if (preg_match('/\bwatch(es)?\b/', $t)) return 'watch';
    if (preg_match('/sunglass|eyewear|\bshades\b|polari[sz]ed/', $t)) return 'sunglass';
    if (preg_match('/skin\s*care|skincare|sunscreen|sun\s*block|serum|cleanser|face\s*wash|toner|moisturi[sz]er|\blotion|\bcream\b|shampoo|conditioner|hair\s*(oil|serum|mask|care|treatment|spray)|body\s*(wash|scrub|butter|oil|mist)|lip\s*(balm|care|stick|gloss)|lipstick|mascara|foundation|concealer|eye\s*liner|eyeliner|kajal|makeup|make-up|nail\s*polish|perfume|fragrance|attar|deodorant|facial|scrub|essence|face\s*mask|sheet\s*mask/', $t)) return 'beauty';
    return null;
  }
  private function routeCat(string $name, string $type, array $cats) {
    $xs = self::crossRoute($name, $type);
    if ($xs) { $c = DB::table('categories')->whereNull('parent_id')->where('slug', $xs)->first(); if ($c) return $c; }
    if (count($cats) < 2) return null;
    $beauty = null; $other = null; foreach ($cats as $c) { if ($c->slug === 'beauty') $beauty = $beauty ?: $c; else $other = $other ?: $c; }
    if ($beauty && $other && preg_match('/skin\s*care|skincare|sunscreen|sun\s*block|serum|cleanser|face\s*wash|toner|moisturi[sz]er|\blotion|\bcream\b|\bmask\b|shampoo|conditioner|hair\s*(oil|serum|mask|care|treatment|spray)|body\s*(wash|scrub|butter|oil|mist)|lip\s*(balm|care|stick|gloss)|lipstick|mascara|foundation|concealer|eye\s*liner|eyeliner|kajal|makeup|make-up|nail\s*polish|perfume|deodorant|facial|scrub|essence|\bgel\b|personal\s*care/i', $type . ' | ' . $name)) return $beauty;
    return $other ?: $cats[0];
  }
  /** Baby Care and Others: sub category from type + name (the same list for every shop). */
  public static function babySub(string $name, string $type): string {
    $t = mb_strtolower($type . ' | ' . $name);
    foreach (['Feeding' => '/feeding|bottle|nipple|sippy|breast\s*pump|bib\b|sterili[sz]er|formula/', 'Diapers & Wipes' => '/diaper|nappy|nappies|wipes?\b|pampers/', 'Baby Care' => '/baby\s*(lotion|wash|oil|powder|shampoo|cream|care|soap|bath)|sunscreen|rash|massage\s*oil|skin\s*care|skincare|\blotion|shampoo|\bsoap\b|\bbath\b/', 'Toys & Games' => '/\btoys?\b|doll\b|lego|puzzle|\bgames?\b|plush|teddy/', 'Shoes' => '/\bshoes?\b|sandal|boots?\b|slipper|sneaker|loafer/', 'Clothing' => '/shirt|pant|dress|frock|jacket|panjabi|romper|onesie|sweater|\bcap\b|\bsocks?\b|\bset\b|hoodie|jogger|shorts|winter|clothing|cloth|suit/', 'Accessories' => '/hair\s*(clip|band|pin)|\bhairband|bag\b|backpack|bow\b|headband|accessor/'] as $sub => $re) if (preg_match($re, $t)) return $sub;
    return 'Others';
  }
  /** Personal Wellness: sub category from type + name. */
  public static function wellnessSub(string $name, string $type): string {
    $t = mb_strtolower($type . ' | ' . $name);
    foreach (['Oral Care' => '/tooth|mouth|oral|dental/', 'Vitamins & Supplements' => '/vitamin|supplement|protein|herbal|omega|collagen/', 'Feminine Hygiene' => '/sanitary|feminine|\bpad\b|intimate/', 'Health Devices' => '/thermometer|blood\s*pressure|monitor|first\s*aid|oximeter|nebuli/', 'Hygiene' => '/hygiene|sanitizer|soap|hand\s*wash/'] as $sub => $re) if (preg_match($re, $t)) return $sub;
    return 'Others';
  }
  /** Fashion sub category from product type + name: the same list for every shop, so two shops selling the same kind of item land in one sub category. */
  public static function fashionSub(string $name, string $type): string {
    $t = mb_strtolower($type . ' | ' . $name);
    $g = preg_match('/\b(kids?|child(?:ren)?|baby|infant|junior|toddler)\b/', $t) ? 'Kids' : (preg_match('/\b(women|woman|ladies|lady|female|girls?)\b|women\'?s|ladies/', $t) ? 'Women' : (preg_match('/\b(men|man|gents?|male|boys?)\b|men\'?s/', $t) ? 'Men' : ''));
    $rules = [ // item => [regex, default gender: M men, W women, '' none, '-' never prefixed]
      'Panjabi' => ['/panjabi|punjabi|kabli|fatua/', 'M'], 'Saree' => ['/\bsaree|\bsari\b|\bshari\b/', 'W'],
      'Three Piece & Kameez' => ['/3[\s-]*pcs?\b|3[\s-]*piece|three[\s-]*piece|2[\s-]*pcs?\b|2[\s-]*piece|salwar|shalwar|kameez|unstitched|\blawn\b|bridal|chikankari|festive|embroider|luxe\b|nazir|shakira|rasiya|saadia|asad\b|rizwan|kashmee|shahjahan|hussain\s*rehar|maria\.?\s*b\b|summer\s*(in\s*)?bloom/', 'W'],
      'Tops & Kurti' => ['/kaftan|kurti|kurta|tunic|\btops?\b|\bfrock|\bgown|\bdress\b|\bskirt|palazzo|blouse/', 'W'],
      'Hijab & Scarf' => ['/hijab|scarf|scarves|shawl|orna\b|burk|abaya|niqab|stole/', 'W'], 'Lingerie' => ['/lingerie|\bbra\b|\bbras\b|panty|panties|stocking/', 'W'],
      'Jewelry' => ['/ear\s*ring|earring|necklace|bracelet|bangle|\brings?\b|jewel|pendant|body\s*chain|nose\s*pin|anklet|tiara/', 'W'],
      'Shoes' => ['/\bshoes?\b|sandal|loafer|\bheels?\b|\bflats?\b|\bpumps?\b|slipper|sneaker|\bboots?\b|flip[\s-]*flop|oxford|derby|moccasin|kolhapuri|stiletto|wedge/', ''],
      'Bags & Wallets' => ['/\bbags?\b|handbag|backpack|purse|wallet|clutch|\btote|satchel|pouch/', ''],
      'Blazers & Suits' => ['/blazer|\bsuits?\b|waistcoat|tuxedo/', 'M'], 'Jackets & Winter Wear' => ['/jacket|\bcoat|sweater|sweatshirt|hoodie|cardigan|pullover|outerwear|puffer|beanie|gloves|winter|fleece|thermal|\bcap\b/', ''],
      'Leggings' => ['/legging|flared\s*pants|yoga\s*pants|tights/', 'W'],
      'Jerseys & Sportswear' => ['/jersey|football|\bkit\b|track\s*suit|tracksuit|active\s*wear|activewear|sportswear/', 'M'],
      'Pants & Trousers' => ['/\bpants?\b|bottoms?\b|jeans|trouser|gabardine|chino|jogger|pajama|pyjama|shorts|denim|cargo/', 'M'], 'T-Shirts & Polos' => ['/\bpolo|t-?\s?shirt|\btee\b|\btank\b|singlet/', 'M'], 'Shirts' => ['/shirt/', 'M'],
      'Watches & Gadgets' => ['/watch|earbud|headphone|speaker|charger|power\s*bank|smart\s*(band|watch)|\bgadget/', '-'],
      'Accessories' => ['/\bbelts?\b|\btie\b|\bsocks?\b|boxer|underwear|\bhat\b|sunglass|\bglasses\b|cufflink|handkerchief|keychain|fragrance|perfume|\bhair\b|\bclips?\b|scrunchie|\bcomb\b|\bbrush\b|\bmask\b/', ''],
    ];
    foreach ($rules as $item => [$re, $def]) {
      if (!preg_match($re, $t)) continue;
      $gg = $g !== '' ? $g : ($def === 'M' ? 'Men' : ($def === 'W' ? 'Women' : ''));
      if ($def === '-') return $item;
      return $gg === 'Kids' ? "Kids' $item" : ($gg === 'Men' ? "Men's $item" : ($gg === 'Women' ? "Women's $item" : $item));
    }
    return 'Others';
  }
  /** Gadget & Electronics sub category from the product name (the source shop gives no category). */
  public static function gadgetSub(string $name): string {
    $n = mb_strtolower($name);
    $rules = [
      'Laptop' => '/laptop|notebook|macbook|thinkpad|vivobook|zenbook|ideapad|\bvictus\b|\bnitro\b|\bpavilion\b|chromebook|\binspiron\b|\blatitude\b|\bvostro\b|\bxps\b|\bprobook\b|\belitebook\b|\bomen\b|\blegion\b|\bthinkbook\b|\bloq\b|\baspire\b|\bswift\b|\bpredator\b|\bkatana\b|\bcyborg\b|\bprestige\b|\btuf\s*(gaming\s*)?f\d|\brog\s*(strix|zephyrus|flow)|\bexpertbook\b|\bsurface\s*(laptop|pro|go)|\bhp\s*(14|15|16|17)s?-|\bgram\b/',
      'Appliances' => '/steamer|sewing|dehumidifier|air\s*(purifier|cooler|fryer)|refrigerator|\bfridge\b|microwave|\boven\b|rice\s*cooker|cooker|\bfreezer\b|washing\s*machine|water\s*(heater|purifier|dispenser)|\bgeyser\b|\bheater\b|ceiling\s*fan|pedestal|\bac\b|air\s*conditioner|juicer|\bmixer\b|toaster|\bkettle\b|blender|vacuum|\biron\b/',
      'Office Items' => '/safe\s*box|locker|vault|shredder|bill\s*counter|money\s*counter|laminat|stapler|whiteboard|calculator|paper\s*cutter|binding|fingerprint\s*(attendance|machine)|attendance|\bpos\b|barcode|receipt|cash\s*drawer|trolley/',
      'Security' => '/door\s*lock|smart\s*lock|access\s*control|intercom|video\s*door|metal\s*detector|alarm|\bsiren\b|gps\s*tracker|tracker/',
      'Motherboard' => '/mother\s*board|\b[a-z]\d{3}[a-z]?m?\b.*\b(ddr[45]|am[45]|lga)/', 'Processor' => '/\bprocessor\b|\bryzen\b|\bcore\s*i[3579]\b|\bintel\s+(core|celeron|pentium)|\bcpu\b/',
      'Graphics Card' => '/graphics\s*card|\bgpu\b|geforce|radeon|\brtx\b|\bgtx\b/', 'RAM' => '/\bram\b|\bddr[345]\b.*\b(\d+gb)\b|memory\s*kit|so-?dimm/', 'SSD & Storage' => '/\bssd\b|\bhdd\b|hard\s*disk|nvme|portable\s*(drive|disk)|pen\s*drive|flash\s*drive|memory\s*card|micro\s*sd|\bsd\s*card\b/',
      'Power Supply' => '/power\s*supply|\bpsu\b/', 'Casing & Cooling' => '/\bcasing\b|\bcase\b|cooler|\bfan\b|radiator|thermal\s*paste|\baio\b/',
      'Desktop PC' => '/desktop|all[\s-]*in[\s-]*one|mini\s*pc|\bbrand\s*pc\b|workstation/',
      'Monitor' => '/monitor|\bdisplay\b.*\b(hz|inch)/', 'Printer & Scanner' => '/printer|toner|cartridge|\bink\b|scanner|\bphotocopier\b|label/',
      'Camera & CCTV' => '/\bcamera\b|video\s*light|\bgodox\b|tripod|gimbal|ring\s*light|lens\b|cctv|\bdvr\b|\bnvr\b|webcam|dash\s*cam|\bgopro\b|\bdrone\b/', 'Networking' => '/mikrotik|tp-?link|tenda|ubiquiti|d-?link|\brb\d{3,4}|router|\bswitch\b|access\s*point|\bmodem\b|wi-?fi\s*(adapter|extender|repeater)|lan\s*card|network|\bonu\b|\bsfp\b|\blan\s*cable\b|\bcat\s*[5-7]/',
      'UPS & Power' => '/\bups\b|\bavr\b|stabilizer|power\s*bank|\bcharger\b|adapter|\bbattery\b|power\s*strip|\bsocket\b/', 'Keyboard & Mouse' => '/keyboard|\bmouse\b|mousepad|mouse\s*pad|combo\s*(set|kit)/',
      'Headphone & Speaker' => '/headphone|headset|earphone|earbud|\bairpod|\bneckband|speaker|soundbar|\bmic(rophone)?\b|\bbuds\b|\btws\b/', 'Smart Watch & Wearable' => '/smart\s*watch|smartwatch|\bband\b|\bfitness\b/',
      'Mobile & Tablet' => '/smartphone|\bphone\b|\bipad\b|\btablet\b|\biphone\b|\bgalaxy\b|\bredmi\b|\btab\b/', 'TV & Projector' => '/digital\s*signage|interactive\s*flat|\bifp\b|\btv\b|television|projector|\bandroid\s*box|tv\s*box|streaming/',
      'Gaming' => '/gaming\s*(chair|controller|pad)|gamepad|joystick|controller|console|playstation|\bps[45]\b|xbox|\bsteering\b|\bchair\b|\bdesk\b/', 'Cable & Converter' => '/\bcable\b|converter|\bhub\b|\bdock\b|hdmi|\busb\b|\botg\b|\bvga\b|\bdisplayport\b|adaptor/',
      'Software' => '/software|antivirus|windows\s*\d+|\boffice\b|license|licence|subscription/', 'Smart Home' => '/smart\s*(plug|bulb|lock|home|switch)|\bsensor\b|\balarm\b|doorbell|robot\s*vacuum|\bvacuum\b|trimmer|\bshaver\b|hair\s*dryer|\biron\b|\bkettle\b|\bblender\b/',
    ];
    foreach ($rules as $sub => $re) if (preg_match($re, $n)) return $sub;
    return 'Others';
  }
  /** Lighter & Accessories sub category: from the source shop's own collection (map handle~letter saved in settings), keywords as fallback. */
  /** Beauty sub category from the source product type + name (Fragrance, Makeup, Skincare, Hair & Body, Candles & Home, Toys, Others). */
  public static function beautySub(string $name, string $type): string {
    $t = mb_strtolower($type . ' | ' . $name); $ty = mb_strtolower(trim($type));
    if ($ty !== '') { // the source product type is the strongest signal
      if (preg_match('/gift|set\b|kit\b|coffret/', $ty)) return 'Gift Sets';
      foreach (['Fragrance' => '/perfume|fragrance|parfum|attar|cologne|mist/', 'Skincare' => '/skin|serum|cleanser|mask|cream|toner|sun/', 'Makeup' => '/make|lip|eye|face|nail|brush|cosmetic/', 'Hair & Body' => '/hair|body|bath|shampoo|deodorant/', 'Candles & Home' => '/candle|diffuser|home|car|glass|book|appliance/', 'Toys' => '/toy|lego|barbie/'] as $sub => $re) if (preg_match($re, $ty)) return $sub;
    }
    foreach (['Toys' => '/\btoys?\b|lego|barbie|hot\s*wheels|crayola|puzzle|doll\b/', 'Candles & Home' => '/candle|diffuser|home\s*fragrance|room\s*spray|air\s*freshener|car\s*fresh|glass|book|appliance|incense/', 'Hair & Body' => '/shampoo|conditioner|hair|body\s*(lotion|wash|butter|scrub|oil|cream)|shower|bath|deodorant|deo\b|sun\s*care|suncare|sunscreen\s*body|lotion|hand\s*(cream|wash)/', 'Makeup' => '/make\s*-?up|lip(stick|gloss|\s*liner|\s*balm)?\b|mascara|foundation|concealer|eye\s*(liner|shadow|brow|pencil)|eyeliner|blush|bronzer|highlighter|palette|brush|primer|setting\s*(spray|powder)|powder|nail|contour|kajal|remover/', 'Skincare' => '/skin\s*care|skincare|serum|cleanser|exfoliat|moisturi[sz]er|cream|mask|toner|essence|sunscreen|spf|eye\s*care|face|anti-?aging|treatment|facial|gel\b/', 'Fragrance' => '/perfume|fragrance|parfum|eau\s*de|edp\b|edt\b|edc\b|attar|ittar|cologne|body\s*mist|oud|scent|extrait/'] as $sub => $re) if (preg_match($re, $t)) return $sub;
    return 'Others';
  }
  public static function amourSub(string $name, string $url): string {
    static $L = ['L' => 'Lighter', 'K' => 'Keychains', 'A' => 'Ash Trays', 'B' => 'Bags', 'I' => 'Islamic Items', 'R' => 'Bracelets', 'G' => 'Gadgets', 'C' => 'Cigarette Boxes', 'S' => 'Socks', 'P' => 'Chains & Pendants', 'H' => 'Hindu Items', 'W' => 'Pocket Watches', 'X' => 'Caps', 'D' => 'Home Decor', 'N' => 'Rings', 'O' => 'Others'];
    static $map = null; if ($map === null) { $map = []; try { foreach (explode(',', (string)DB::table('settings')->where('key', 'amour_sub_map')->value('value')) as $e) { $p = explode('~', $e); if (count($p) === 2) $map[$p[0]] = $p[1]; } } catch (\Throwable $e) {} }
    $h = trim((string)preg_replace('#[?\#].*$#', '', $url), '/'); $h = substr($h, (int)strrpos($h, '/') + 1);
    if ($h !== '' && isset($map[$h]) && isset($L[$map[$h]]) && $map[$h] !== 'O') return $L[$map[$h]];
    $n = mb_strtolower($name);
    foreach (['Lighter' => '/lighter|zippo/', 'Keychains' => '/keychain/', 'Socks' => '/socks?\b/', 'Ash Trays' => '/ash\s*tray/', 'Cigarette Boxes' => '/cig(ar|arette)?\s*box/', 'Pocket Watches' => '/pocket\s*watch/', 'Bracelets' => '/bracelet|bangle|cuff/', 'Chains & Pendants' => '/pendant|pendent|locket|chain/', 'Rings' => '/\bring\b/', 'Earrings' => '/earring/', 'Necklaces' => '/necklace/', 'Islamic Items' => '/ayatul|islamic|allah/', 'Hindu Items' => '/hindu|\bom\b|trishul/', 'Bags' => '/bag|backpack/', 'Caps' => '/\bcap\b|bandana/', 'Home Decor' => '/decor|money\s*bank|box\b/'] as $sub => $re) if (preg_match($re, $n)) return $sub;
    return 'Others';
  }
  /** Diagnostic: source category list vs imported sub categories (admin only). */
  public function diag(Request $r) {
    $cf = $this->cfg(); $s = $this->src(); abort_unless($s, 404);
    $src = DB::table('store_products')->where('store_id', $s->id)->selectRaw('coalesce(category,\'-\') c, count(*) n, sum(case when in_stock and price>0 then 1 else 0 end) ok')->groupBy('c')->orderByDesc('n')->limit(80)->get();
    $cat = DB::table('categories')->where('slug', $cf['cat'])->first();
    $imp = $cat ? DB::table('products')->where('category_id', $cat->id)->selectRaw('coalesce(sub,\'-\') s, count(*) n, sum(case when published then 1 else 0 end) pub')->groupBy('s')->orderByDesc('n')->get() : [];
    return response()->json(['domain' => $cf['domain'], 'source_categories' => $src, 'imported_subs' => $imp]);
  }
  /** Re-sort the products of an affiliate category into the right sub categories (after a rule change). POST map=handle~letter,... saves the Amour collection map first. */
  public function resub(Request $r) {
    @set_time_limit(120); $cf = $this->cfg(); $s = $this->src(); abort_unless($s && (in_array($cf['c'], ['gadget', 'amour', 'beauty', 'fashion'], true) || preg_match('/^s\d+$/', $cf['c'])), 404);
    if ($r->filled('map') && $cf['c'] === 'amour') DB::table('settings')->updateOrInsert(['key' => 'amour_sub_map'], ['value' => (string)$r->input('map'), 'updated_at' => now(), 'created_at' => now()]);
    $cat = DB::table('categories')->where('slug', $cf['cat'])->first(); abort_unless($cat, 404);
    $cm = [$cat->id => $cat->slug]; foreach ($this->storeCats($s) as $__c) $cm[$__c->id] = $__c->slug; // a store feeding several categories: re-sort inside each
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->leftJoin('store_products as sp', 'sp.id', '=', 'w.store_product_id')->where('w.store_id', $s->id)->whereIn('p.category_id', array_keys($cm))->get(['p.id', 'p.category_id', 'p.name', 'p.sub', 'p.slug', 'w.source_url', 'w.source_name', 'sp.category as src_cat']); $n = 0;
    $xr = in_array($cf['c'], ['fashion', 'beauty'], true) || preg_match('/^s\d+$/', $cf['c']); $bySlug = [];
    foreach ($rows as $x) {
      $tid = (int)$x->category_id; $tslug = $cm[$x->category_id];
      if ($xr) { $xs = self::crossRoute((string)($x->source_name ?: $x->name), (string)$x->src_cat); if ($xs) { if (!array_key_exists($xs, $bySlug)) $bySlug[$xs] = DB::table('categories')->whereNull('parent_id')->where('slug', $xs)->first(); if ($bySlug[$xs]) { $tid = (int)$bySlug[$xs]->id; $tslug = $xs; } } }
      $sub = self::subFor((string)($x->source_name ?: $x->name), in_array($cf['c'], ['gadget', 'amour'], true) ? null : $x->src_cat, $tslug, (string)$x->source_url);
      if ($sub === $x->sub && $tid === (int)$x->category_id) continue;
      DB::table('products')->where('id', $x->id)->update(['category_id' => $tid, 'sub' => $sub, 'sub_sub' => null, 'path_slug' => $this->slugFor($tid, (string)($x->slug ?: \App\Support\Slugs::slug($x->name)), $sub, (int)$x->id), 'updated_at' => now()]); $n++;
    }
    foreach (['store.cats', 'store.catslugs', 'store.subs', 'store.home11', 'store.sitemap5'] as $k) \Illuminate\Support\Facades\Cache::forget($k); \App\Http\Controllers\StoreController::bust();
    return response()->json(['ok' => true, 'moved' => $n, 'total' => $rows->count()]);
  }
  /** Facts that really are in the product name (capacity, memory type, Wi-Fi, power, size ...). */
  private static function facts(string $name): array {
    $f = []; $n = $name;
    if (preg_match_all('/\b(\d+(?:\.\d+)?\s?(?:TB|GB|MB|MP|mAh|MHz|GHz|Hz|W|Watt|V|inch|"|mm|cm|m|Mbps|Gbps|RPM|L|ml|pcs?|pack))\b/i', $n, $m)) foreach (array_slice(array_unique(array_map(fn($x) => trim(preg_replace('/\s+/', '', $x)), $m[1])), 0, 6) as $x) $f[] = $x;
    foreach (['DDR5', 'DDR4', 'DDR3', 'Wi-Fi 7', 'Wi-Fi 6E', 'Wi-Fi 6', 'Bluetooth', 'USB Type-C', 'USB 3.0', 'HDMI', 'NVMe', 'SSD', 'RGB', 'Gaming', 'Wireless', 'Noise Cancelling', 'ANC', 'Polarized', 'Photochromic', 'Blue Light', 'Stainless Steel', 'Leather', 'Automatic', 'Analog', 'Digital'] as $k) if (stripos($n, $k) !== false) $f[] = $k;
    return array_values(array_unique($f));
  }
  /** Write a product description (HTML) for every product of an affiliate category that has none: only what the name, category and code say, plus the shop's own delivery / payment terms. */
  public function describe(Request $r) {
    @set_time_limit(120); $cf = $this->cfg(); $cat = DB::table('categories')->where('slug', $cf['cat'])->first(); abort_unless($cat, 404);
    $rows = DB::table('products')->where('category_id', $cat->id)->where(fn($w) => $w->whereNull('description')->orWhere('description', ''))->orderBy('id')->limit(1500)->get(['id', 'name', 'sub', 'sku']);
    $pre = !empty(\App\Support\PrepaidOnly::catSet()[$cat->id]); $n = 0;
    foreach ($rows as $p) {
      $nm = e(trim($p->name)); $sub = e((string)($p->sub ?: $cat->name)); $facts = self::facts((string)$p->name);
      $h = '<p><strong>' . $nm . '</strong> — ' . e($cat->name) . ' › ' . $sub . ' বিভাগের পণ্য। DutyFree BD থেকে সহজে অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।</p>';
      $h .= '<h3>পণ্যের তথ্য</h3><ul><li>পণ্য: ' . $nm . '</li><li>ক্যাটাগরি: ' . e($cat->name) . ' › ' . $sub . '</li>' . ($p->sku ? '<li>পণ্য কোড: ' . e($p->sku) . '</li>' : '');
      if ($facts) $h .= '<li>বৈশিষ্ট্য: ' . e(implode(', ', $facts)) . '</li>';
      $h .= '</ul><h3>অর্ডার ও ডেলিভারি</h3><ul><li>সারা বাংলাদেশে কুরিয়ারে ডেলিভারি দেওয়া হয়।</li>' . ($pre ? '<li>এই পণ্যে ক্যাশ অন ডেলিভারি নেই; অনলাইনে সম্পূর্ণ পেমেন্ট করে অর্ডার করতে হবে (bKash, Nagad, কার্ড, ব্যাংক ট্রান্সফার)।</li>' : '') . '<li>অর্ডারের আগে বা পরে প্রয়োজনে হটলাইন ০৯৬১৩৮২৮০৮১ বা WhatsApp ০১৮৭১৬০০৬০০-এ যোগাযোগ করুন।</li></ul>';
      DB::table('products')->where('id', $p->id)->update(['description' => $h, 'updated_at' => now()]); $n++;
    }
    $left = DB::table('products')->where('category_id', $cat->id)->where(fn($w) => $w->whereNull('description')->orWhere('description', ''))->count();
    if (!$left) \App\Http\Controllers\StoreController::bust();
    return response()->json(['ok' => true, 'written' => $n, 'left' => $left]);
  }
  private function slugFor(int $catId, string $base, string $sub, int $selfId) { $subSlug = \App\Support\Slugs::subSlug($sub); $slug = $base; $i = 2; while (DB::table('products')->where('category_id', $catId)->where('id', '!=', $selfId)->whereRaw(\App\Support\Slugs::subSql() . ' = ?', [$subSlug])->where('path_slug', $slug)->exists()) $slug = $base . '-' . $i++; return $slug; }
  private static function cleanHtml(string $h, array $bad): string {
    $h = preg_replace('#<(script|style|iframe|noscript|form|svg)\b.*?</\1>#is', '', $h);
    $h = preg_replace('#<img\b[^>]*>#i', '', $h);
    $h = strip_tags($h, '<p><br><ul><ol><li><strong><b><em><i><h2><h3><h4><table><thead><tbody><tr><th><td><span><div>');
    $h = preg_replace('/\s(?:style|class|id|on\w+|data-[\w-]+)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $h);
    foreach ($bad as $b) if ($b !== '') $h = preg_replace('/' . preg_quote($b, '/') . '/i', '', $h);
    $h = preg_replace('#(<(p|div|span|li)>\s*(&nbsp;|<br\s*/?>)*\s*</\2>)+#i', '', $h);
    return trim($h);
  }
  /** Product details (description, extra photos) from the source shop (Shopify .json), stored as OUR content: no links, no source name, our own image copies. */
  public function details(Request $r) {
    @set_time_limit(100); $cf = $this->cfg(); $s = $this->src(); abort_unless($s, 404);
    DB::table('watch_source')->where('store_id', $s->id)->where('note', 'no details')->update(['details_at' => null, 'note' => null]);
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $s->id)->whereNull('w.details_at')->whereNotNull('w.source_url')->orderBy('w.id')->limit(60)->get(['w.id as wid', 'w.source_url', 'p.id as pid', 'p.sku', 'p.name'])->filter(fn($x) => \Illuminate\Support\Facades\Cache::add('det-' . $x->wid, 1, 150))->take(8)->values(); // claimed, so several workers can run in parallel
    $left = DB::table('watch_source')->where('store_id', $s->id)->whereNull('details_at')->whereNotNull('source_url')->count();
    if ($rows->isEmpty()) return response()->json(['finished' => $left <= 0, 'busy' => $left > 0, 'left' => $left]);
    $urls = []; foreach ($rows as $x) { $u = preg_replace('#[?\#].*$#', '', (string)$x->source_url); $urls[$x->wid] = rtrim($u, '/') . '.json'; }
    try { $resp = \Illuminate\Support\Facades\Http::pool(function ($pool) use ($urls) { $o = []; foreach ($urls as $k => $u) $o[] = $pool->as((string)$k)->timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36', 'Accept' => 'application/json'])->get($u); return $o; }); } catch (\Throwable $e) { return response()->json(['finished' => false, 'err' => mb_substr($e->getMessage(), 0, 150), 'left' => $left]); }
    $dbg = []; $bad = [$cf['domain'], preg_replace('/\..*$/', '', $cf['domain']), 'timeaccess', 'time access', 'ryans', 'luxotix', 'amour', 'sundora']; if (!empty($s->name)) { $bad[] = trim((string)$s->name); $w1 = trim((string)explode(' ', trim((string)$s->name))[0]); if (mb_strlen($w1) >= 4) $bad[] = $w1; } $ok = 0; $imgMap = []; $imgFor = []; // no trace of the source shop in our descriptions
    foreach ($rows as $x) {
      $res = $resp[(string)$x->wid] ?? null; $j = ($res && method_exists($res, 'ok') && $res->ok()) ? $res->json('product') : null;
      $st = ($res && method_exists($res, 'status')) ? $res->status() : 0; $page = null;
      if (!is_array($j)) { // not a Shopify shop (or blocked): read the product page itself (JSON-LD description, meta description, spec tables)
        try { $pu = preg_replace('#[?\#].*$#', '', (string)$x->source_url); $pr = \Illuminate\Support\Facades\Http::timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36', 'Accept' => 'text/html'])->get($pu); $page = $pr->ok() ? (string)$pr->body() : null; $st2 = $pr->status(); } catch (\Throwable $e) { $st2 = 'err'; }
        $ld = ''; $tbl = '';
        if ($page) {
          if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $page, $mm)) foreach ($mm[1] as $jj) { $d = json_decode(trim($jj), true); $q = [$d]; while ($q) { $z = array_shift($q); if (!is_array($z)) continue; if (isset($z['@graph'])) foreach ($z['@graph'] as $g) $q[] = $g; $ty = $z['@type'] ?? ''; if ((is_array($ty) ? in_array('Product', $ty) : $ty === 'Product') && !empty($z['description'])) { $ld = (string)$z['description']; break 2; } if (array_is_list($z)) foreach ($z as $g) $q[] = $g; } }
          if ($ld === '' && preg_match('#<meta[^>]+(?:property|name)=["\'](?:og:)?description["\'][^>]+content=["\']([^"\']+)#i', $page, $m1)) $ld = html_entity_decode($m1[1]);
          if (preg_match_all('#<table\b.*?</table>#is', $page, $tt)) foreach (array_slice($tt[0], 0, 3) as $t1) { $c1 = self::cleanHtml($t1, $bad); if (mb_strlen(strip_tags($c1)) > 60) { $tbl .= $c1; break; } }
        }
        $ld = trim(preg_replace('/\s+/u', ' ', strip_tags($ld)));
        if ($ld === '' && $tbl === '') { DB::table('watch_source')->where('id', $x->wid)->update(['details_at' => now(), 'note' => 'no details http ' . $st . '/' . ($st2 ?? '?')]); $dbg[] = $x->sku . ' ' . $st . '/' . ($st2 ?? '?') . ' len' . strlen((string)$page); continue; }
        $j = ['body_html' => ($ld !== '' ? '<p>' . e($ld) . '</p>' : '') . ($tbl !== '' ? '<h3>Specifications</h3>' . $tbl : ''), 'images' => [], 'options' => []];
      }
      $html = self::cleanHtml((string)($j['body_html'] ?? ''), $bad);
      // option table (strap, size ...) from variants
      $opts = []; foreach ((array)($j['options'] ?? []) as $o) { $vals = array_values(array_unique(array_map('strval', (array)($o['values'] ?? [])))); if (($o['name'] ?? '') !== '' && $o['name'] !== 'Title' && $vals) $opts[] = '<li><strong>' . e($o['name']) . ':</strong> ' . e(implode(', ', array_slice($vals, 0, 12))) . '</li>'; }
      $spec = []; if (!empty($j['vendor'])) { $v = trim((string)$j['vendor']); if (!preg_match('/time\s*-?\s*access|luxotix|amour|ryans|startech|sundora/i', $v)) $spec[] = '<li><strong>Brand:</strong> ' . e($v) . '</li>'; }
      if (!empty($j['product_type']) && stripos((string)$j['product_type'], 'timeaccess') === false) $spec[] = '<li><strong>Type:</strong> ' . e((string)$j['product_type']) . '</li>';
      $desc = ($html !== '' ? $html : '') . ($spec || $opts ? '<h3>Specifications</h3><ul>' . implode('', $spec) . implode('', $opts) . '</ul>' : '');
      $upd = ['updated_at' => now()]; if ($desc !== '') { $upd['description'] = $desc; $txt = trim(preg_replace('/\s+/u', ' ', strip_tags($html))); if ($txt !== '') $upd['meta_description'] = mb_substr($txt, 0, 155); }
      DB::table('products')->where('id', $x->pid)->update($upd);
      $n = 0; foreach (array_slice((array)($j['images'] ?? []), 1, 4) as $im) { $src = $im['src'] ?? null; if (!$src) continue; $n++; $code = $x->sku . '-' . $n; $imgMap[$code] = $src; $imgFor[$code] = $x->pid; }
      DB::table('watch_source')->where('id', $x->wid)->update(['details_at' => now()]); $ok++;
    }
    if ($imgMap) {
      $res = $this->fetchImages($imgMap);
      foreach ($res as $code => $g) if (!empty($g['file'])) { $pid = $imgFor[$code]; if (!DB::table('product_images')->where('product_id', $pid)->where('path', $g['file'])->exists()) DB::table('product_images')->insert(['product_id' => $pid, 'path' => $g['file'], 'position' => (int)substr($code, strrpos($code, '-') + 1), 'created_at' => now(), 'updated_at' => now()]); }
    }
    return response()->json(['finished' => false, 'ok' => $ok, 'left' => $left - count($rows), 'dbg' => $dbg]);
  }
  /** Re-sort existing imported products into clean sub categories and report duplicates. */
  public function tidy(Request $r) {
    @set_time_limit(120); $cf = $this->cfg(); $cat = DB::table('categories')->where('slug', $cf['cat'])->first(); abort_unless($cat, 404); $s = $this->src();
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')->where('w.store_id', $s->id ?? 0)->get(['p.id', 'p.name', 'p.slug', 'p.sub', 'p.path_slug', 'sp.category as scat']);
    $changed = 0; $subs = [];
    foreach ($rows as $x) {
      $sub = self::subFor((string)$x->name, $x->scat, $cf['cat']); $subs[$sub] = ($subs[$sub] ?? 0) + 1;
      if ($sub === $x->sub) continue;
      $slug = $this->slugFor($cat->id, (string)($x->slug ?: \App\Support\Slugs::slug($x->name)), $sub, (int)$x->id);
      DB::table('products')->where('id', $x->id)->update(['sub' => $sub, 'path_slug' => $slug, 'updated_at' => now()]); $changed++;
    }
    $dups = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $s->id ?? 0)->select(DB::raw('lower(p.name) n'), DB::raw('count(*) c'))->groupBy(DB::raw('lower(p.name)'))->havingRaw('count(*) > 1')->limit(30)->get();
    foreach (['store.cats', 'store.catslugs', 'store.home11', 'store.hsec3.' . $cf['cat']] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
    return response()->json(['total' => count($rows), 'changed' => $changed, 'subs' => $subs, 'duplicate_names' => $dups]);
  }
  /** Download images with Laravel's HTTP client (several at once). Returns [url => ['file'=>'/uploads/watch/..']|['err'=>..]]. */
  private function fetchImages(array $map): array {
    $dir = public_path('uploads/' . $this->cfg()['dir']); if (!is_dir($dir)) @mkdir($dir, 0775, true); $res = [];
    try {
      $resp = \Illuminate\Support\Facades\Http::pool(function ($pool) use ($map) { $o = []; foreach ($map as $code => $url) $o[] = $pool->as((string)$code)->timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8'])->get($url); return $o; });
      foreach ($map as $code => $url) {
        $r = $resp[(string)$code] ?? null;
        if (!$r || $r instanceof \Throwable || !method_exists($r, 'ok') || !$r->ok()) { $res[$code] = ['err' => ($r instanceof \Throwable) ? mb_substr($r->getMessage(), 0, 100) : 'http ' . (method_exists($r, 'status') ? $r->status() : '?')]; continue; }
        $b = $r->body(); $i = @getimagesizefromstring($b); $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$i[2] ?? 0] ?? null;
        if (!$ext || strlen($b) < 200) { $res[$code] = ['err' => 'not an image']; continue; }
        if (@file_put_contents($dir . '/' . $code . '.' . $ext, $b) === false) { $res[$code] = ['err' => 'cannot write ' . $dir]; continue; }
        @chmod($dir . '/' . $code . '.' . $ext, 0664); $res[$code] = ['file' => '/uploads/' . $this->cfg()['dir'] . '/' . $code . '.' . $ext];
      }
    } catch (\Throwable $e) { foreach ($map as $code => $url) $res[$code] = ['err' => mb_substr($e->getMessage(), 0, 120)]; }
    return $res;
  }
  /** Repair step: products imported without an image get theirs now. */
  public function images(Request $r) {
    @set_time_limit(90);
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')
      ->where('w.store_id', $this->src()->id ?? 0)->where(fn($q) => $q->whereNull('p.image')->orWhere('p.image', ''))->whereNotNull('sp.image')->orderBy('p.id')->limit(10)->get(['p.id as pid', 'p.sku', 'sp.image as src']);
    $left = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $this->src()->id ?? 0)->where(fn($q) => $q->whereNull('p.image')->orWhere('p.image', ''))->count();
    if ($rows->isEmpty()) return response()->json(['finished' => true, 'left' => $left]);
    $map = []; foreach ($rows as $x) $map[$x->sku] = $x->src; $res = $this->fetchImages($map); $ok = 0; $err = null;
    foreach ($rows as $x) { $g = $res[$x->sku] ?? []; if (!empty($g['file'])) { DB::table('products')->where('id', $x->pid)->update(['image' => $g['file'], 'updated_at' => now()]); $ok++; } else $err = $g['err'] ?? 'unknown'; }
    return response()->json(['finished' => $ok === 0, 'ok' => $ok, 'left' => $left - $ok, 'err' => $err]);
  }
  /** Amour only (owner 8 Nov 2026): every photo is cleaned by AI (watermark, logo, text and background removed, product kept) before the product goes live. One photo per call (many calls can run in parallel, each photo is claimed so none is done twice). */
  public function clean(Request $r) {
    @set_time_limit(170); ignore_user_abort(true); $s = $this->src(); abort_unless($s && $this->cfg()['c'] === 'amour', 404);
    $base = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $s->id)->where('p.image', 'like', '/uploads/amour/%');
    $left = (clone $base)->count();
    $rows = (clone $base)->orderBy('w.id')->limit(60)->get(['p.id as pid', 'p.image']);
    $busy = false;
    foreach ($rows as $x) {
      $h = sha1($x->image); $row = DB::table('ai_images')->where('product_id', $x->pid)->where('source_hash', $h)->first();
      if ($row && $row->ai_status === 'failed' && !$r->boolean('force') && now()->diffInMinutes($row->updated_at) < 360) continue;
      if (!\Illuminate\Support\Facades\Cache::add('amour-clean-' . $x->pid, 1, 170)) { $busy = true; continue; }
      try {
        $id = $row ? $row->id : DB::table('ai_images')->insertGetId(['product_id' => $x->pid, 'source_hash' => $h, 'source_url' => $x->image, 'ai_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $res = \App\Support\AiImage::run((int)$id);
        if (empty($res['ok']) && preg_match('/Unsupported|corrupted|Invalid image/i', (string)($res['error'] ?? ''))) { // the saved photo is damaged: fetch it again from the source shop, then retry once
          $sp = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')->where('p.id', $x->pid)->first(['p.sku', 'sp.image as src']);
          if ($sp && $sp->src) { $g = $this->fetchImages([$sp->sku => $sp->src])[$sp->sku] ?? []; if (!empty($g['file'])) { DB::table('products')->where('id', $x->pid)->update(['image' => $g['file'], 'updated_at' => now()]); $nh = sha1($g['file']); DB::table('ai_images')->where('id', $id)->update(['source_url' => $g['file'], 'source_hash' => $nh, 'original_image' => null, 'ai_status' => 'pending', 'updated_at' => now()]); $res = \App\Support\AiImage::run((int)$id); } else $res['error'] = ($res['error'] ?? '') . ' / refetch: ' . ($g['err'] ?? '?'); }
        }
        if (empty($res['ok'])) { $err = (string)($res['error'] ?? ''); return response()->json(['finished' => (bool)preg_match('/API key|GD is not enabled/i', $err), 'ok' => 0, 'error' => $err, 'left' => $left]); }
        $pi = DB::table('ai_images')->where('id', $id)->value('processed_image');
        if ($pi) {
          $u = ['image' => $pi, 'updated_at' => now()];
          $w = DB::table('watch_source as w')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')->where('w.product_id', $x->pid)->first(['sp.in_stock', 'sp.published']);
          if ($w && $w->in_stock && $w->published) $u['published'] = true;
          DB::table('products')->where('id', $x->pid)->update($u);
          if (\Illuminate\Support\Facades\Cache::add('amour-bust', 1, 15)) { foreach (['store.cats', 'store.catslugs', 'stock.modes'] as $k) \Illuminate\Support\Facades\Cache::forget($k); \App\Http\Controllers\StoreController::bust(); }
        }
        return response()->json(['finished' => false, 'ok' => 1, 'left' => max(0, $left - 1)]);
      } finally { \Illuminate\Support\Facades\Cache::forget('amour-clean-' . $x->pid); }
    }
    return response()->json(['finished' => !$busy, 'ok' => 0, 'busy' => $busy, 'left' => $left]);
  }
  /** Keep live state equal to the source shop: out of stock there = not live here; back in stock = live again; price follows source +markup. */
  private function sync(int $sid, bool $apply = false): void {
    try {
      $stt = DB::table('stores')->where('id', $sid)->first(); $col = \Illuminate\Support\Facades\Schema::hasColumn('stores', 'sale_pct'); $paused = $col && !empty($stt->paused); $jump = $col && $stt->price_jump_pct ? (float)$stt->price_jump_pct : 0.0;
      $lock = \Illuminate\Support\Facades\Schema::hasColumn('products', 'manual_unpublish_lock');
      $rows = DB::table('watch_source as w')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $sid)->get(['w.id as wid', 'w.markup_pct', 'w.our_price', 'sp.price as sprice', 'sp.in_stock', 'sp.published as sppub', 'p.id as pid', 'p.published as ppub', 'p.image as pimg', 'p.compare_price as pcomp', 'w.source_price as prev_sp']); $regPct = ($col && $stt->regular_pct !== null) ? (float)$stt->regular_pct : self::regPct(); $salePct = ($apply && $col && $stt->sale_pct !== null) ? (float)$stt->sale_pct : null;
      $lowMax = self::lowMax(); $needClean = strtolower((string)(DB::table('stores')->where('id', $sid)->value('domain'))) === 'amour.com.bd';
      foreach ($rows as $x) {
        $spike = $jump > 0 && !$apply && (float)$x->prev_sp > 0 && (float)$x->sprice > (float)$x->prev_sp * (1 + $jump / 100); $live = !$paused && !$spike && !((float)$x->sprice <= $lowMax) && (bool)$x->in_stock && (bool)$x->sppub && (!$needClean || str_starts_with((string)$x->pimg, '/uploads/ai/')); $u = ['updated_at' => now()];
        if ($live !== (bool)$x->ppub) { $u['published'] = $live; if ($lock) $u['manual_unpublish_lock'] = !$live; }
        $mp = $salePct ?? (float)$x->markup_pct; $our = (int)ceil((float)$x->sprice * (1 + $mp / 100)); $reg = (int)ceil((float)$x->sprice * (1 + $regPct / 100));
        if ($x->sprice > 0 && !$spike && ($our != (int)$x->our_price || $reg != (int)$x->pcomp)) { $u['price'] = $our; $u['compare_price'] = $reg; $u['cost'] = $x->sprice; DB::table('watch_source')->where('id', $x->wid)->update(['our_price' => $our, 'source_price' => $x->sprice] + ($salePct !== null ? ['markup_pct' => $salePct] : [])); }
        if (count($u) > 1) DB::table('products')->where('id', $x->pid)->update($u);
        DB::table('watch_source')->where('id', $x->wid)->update(['source_in_stock' => $x->in_stock]);
      }
    } catch (\Throwable $e) { report($e); }
  }
  /** Edit Store -> Apply prices now: re-price every product of the store with the store's sale % / regular %, and accept price jumps held by the guard. */
  public function applyStore(Request $r, $id) {
    @set_time_limit(120); abort_unless(DB::table('stores')->where('id', $id)->exists(), 404);
    $this->sync((int)$id, true);
    foreach (['store.cats', 'store.catslugs', 'store.home11', 'store.home13', 'store.catslider3', 'stock.modes'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
    return back()->with('ok', 'Prices and availability re-applied for this store.');
  }
  /** Admin-only history: which product came from where, at what price. */
  public function sources(Request $r) {
    $q = DB::table('watch_source as w')->leftJoin('products as p', 'p.id', '=', 'w.product_id')->select('w.*', 'p.sku', 'p.name', 'p.price')->orderByDesc('w.id');
    if ($r->filled('q')) { $t = '%' . $r->q . '%'; $q->where(fn($w) => $w->where('p.name', 'ilike', $t)->orWhere('p.sku', 'ilike', $t)->orWhere('w.source_sku', 'ilike', $t)); }
    $rows = $q->paginate(100)->withQueryString();
    $h = '<h1>Watch — source history</h1><form><input name=q value="' . e($r->q) . '" placeholder="name / code"> <button>Search</button></form><table border=1 cellpadding=5 style="border-collapse:collapse;font-size:13px;margin-top:10px"><tr><th>Our code</th><th>Our product</th><th>Our price</th><th>Source price</th><th>+%</th><th>Source SKU</th><th>Source link</th><th>Source stock</th></tr>';
    foreach ($rows as $w) $h .= '<tr><td>' . e($w->sku) . '</td><td>' . e($w->name ?: $w->source_name) . '</td><td>' . e(number_format((float)$w->our_price)) . '</td><td>' . e(number_format((float)$w->source_price)) . '</td><td>' . e($w->markup_pct) . '</td><td>' . e($w->source_sku) . '</td><td>' . ($w->source_url ? '<a href="' . e($w->source_url) . '" target=_blank rel=noreferrer>open</a>' : '') . '</td><td>' . ($w->source_in_stock === null ? '' : ($w->source_in_stock ? 'In' : 'Out')) . '</td></tr>';
    return view('admin_raw', ['title' => 'Watch Sources', 'h' => $h . '</table><p>' . $rows->links() . '</p>']);
  }
}
