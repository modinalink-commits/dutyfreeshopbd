<?php
namespace App\Http\Controllers;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Watch category import (owner, 8 Nov 2026): every scanned product of the source shop priced 0-50,000 BDT becomes OUR product (our code, our URL, our image copy, price = source +15%). Source details live only in watch_source (admin only). */
class WatchImportController extends Controller {
  /** Which source feeds which category: watch <- timeaccess-store.com, gadget <- startech.com.bd (owner, 8 Nov 2026; both +5%, prepaid only, no discount). */
  private function cfg(): array {
    $c = in_array(request('c'), ['gadget', 'sunglass', 'amour'], true) ? request('c') : 'watch'; $set = fn($k, $d) => (string)(DB::table('settings')->where('key', $k)->value('value') ?? $d);
    if ($c === 'amour') return ['c' => 'amour', 'domain' => 'amour.com.bd', 'cat' => 'lighter-accessories', 'prefix' => 'DFA', 'pct' => (float)($set('amour_markup_pct', '10') ?: 10), 'max' => 0.0, 'dir' => 'amour'];
    if ($c === 'sunglass') return ['c' => 'sunglass', 'domain' => 'luxotix.com', 'cat' => 'sunglass', 'prefix' => 'DFS', 'pct' => (float)($set('sunglass_markup_pct', '5') ?: 5), 'max' => 0.0, 'dir' => 'sunglass'];
    return $c === 'gadget'
      ? ['c' => 'gadget', 'domain' => strtolower($set('gadget_source_domain', 'startech.com.bd')), 'cat' => 'gadget-electronics', 'prefix' => 'DFG', 'pct' => (float)($set('mobile_markup_pct', '5') ?: 5), 'max' => 0.0, 'dir' => 'gadget']
      : ['c' => 'watch', 'domain' => strtolower($set('watch_source_domain', 'timeaccess-store.com')), 'cat' => 'watch', 'prefix' => 'DFW', 'pct' => (float)($set('watch_markup_pct', '5') ?: 5), 'max' => (float)($set('watch_max_price', '50000') ?: 50000), 'dir' => 'watch'];
  }
  private function src() { return DB::table('stores')->whereRaw('lower(domain) = ? or lower(domain) = ?', [$this->cfg()['domain'], 'www.' . $this->cfg()['domain']])->first(); }
  private function cond($q, $sid) {
    $cf = $this->cfg(); $q = $q->where('store_id', $sid)->where('in_stock', true)->where('price', '>', 0);
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
    return response('<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>Watch Import</title><body style="font-family:system-ui;max-width:640px;margin:30px auto;padding:0 16px"><h2>⌚ Watch Import</h2><p>Source shop products (০–৫০,০০০ টাকা) → আমাদের Watch ক্যাটাগরি, দাম +১৫%।</p><p id=s>Imported <b id=d>'.$st['done'].'</b> / <b>'.$st['total'].'</b></p><div style="background:#eee;height:14px;border-radius:7px"><div id=b style="height:14px;border-radius:7px;background:#f97316;width:'.($st['total'] ? round($st['done'] / $st['total'] * 100) : 0).'%"></div></div><p><button id=go style="padding:10px 18px;font-size:15px">Start / Resume</button> <a href="/admin/watch-sources">Source list (admin only)</a></p><pre id=m></pre><script>var T='.$st['total'].',run=false;async function step(){var r=await fetch("/admin/watch-import/step?c='.$this->cfg()['c'].'",{method:"POST",headers:{"X-CSRF-TOKEN":"'.$csrf.'","Accept":"application/json"}});var j=await r.json();document.getElementById("d").textContent=j.done;document.getElementById("b").style.width=(T?j.done/T*100:0)+"%";document.getElementById("m").textContent=j.note||"";return j}document.getElementById("go").onclick=async function(){if(run)return;run=true;try{while(true){var j=await step();if(j.finished)break}document.getElementById("m").textContent="Finished ✔ "+document.getElementById("m").textContent}catch(e){document.getElementById("m").textContent="Stopped: "+e}run=false}</script>');
  }
  public function step(Request $r) {
    @set_time_limit(90);
    $s = $this->src(); abort_unless($s, 404);
    $cat = DB::table('categories')->where('slug', $this->cfg()['cat'])->first(); if (!$cat) return response()->json(['finished' => true, 'done' => 0, 'note' => 'Watch category not found (deploy migration first).']);
    $lock = Cache::lock('watch-import', 80); if (!$lock->get()) return response()->json(['finished' => false] + $this->stat($s->id));
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
          $name = trim(preg_replace('/\s{2,}/u', ' ', preg_replace(['/\s*[|\-–]\s*Time\s*Access\b.*$/iu', '/\bTime\s*-?\s*Access\b/iu', '/\bLuxotix\b\s*/iu'], '', $name)), " \t-|–"); // no trace of the source shop in our product names
          $sub = self::subFor((string)$name, $x->category, $this->cfg()['cat'], (string)$x->url);
          $base = \App\Support\Slugs::slug($name) ?: 'watch'; $slug = $base; $i = 2; $subSlug = \App\Support\Slugs::subSlug($sub);
          while (DB::table('products')->where('category_id', $cat->id)->whereRaw(\App\Support\Slugs::subSql() . ' = ?', [$subSlug])->where('path_slug', $slug)->exists()) $slug = $base . '-' . $i++;
          $pid = DB::table('products')->insertGetId(['name' => mb_substr($name, 0, 200), 'category_id' => $cat->id, 'price' => $our, 'cost' => $x->price, 'stock' => 0, 'published' => true, 'sub' => $sub, 'slug' => $base, 'path_slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
          $code = $this->cfg()['prefix'] . str_pad((string)$pid, 6, '0', STR_PAD_LEFT); $upd = ['sku' => $code];
          $g = $x->image ? ($got[$x->image] ?? null) : null;
          if ($g && ($g['code'] ?? 0) == 200 && !empty($g['body']) && @getimagesizefromstring($g['body'])) {
            $ext = strtolower(pathinfo(parse_url($x->image, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)); if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'jpg';
            if (@file_put_contents($dir . '/' . $code . '.' . $ext, $g['body'])) $upd['image'] = '/uploads/' . $this->cfg()['dir'] . '/' . $code . '.' . $ext;
          }
          DB::table('products')->where('id', $pid)->update($upd);
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
    $n = mb_strtolower($name); $c = mb_strtolower(trim((string)$srcCat));
    if ($cat === 'watch') { // owner (8 Nov 2026): the Watch category has only two sub categories: Watch and Accessories
      $acc = str_contains($c, 'accessor') || preg_match('/^\d+\s*slots?\b|\bstrap\s+for\b|\bwatch\s+(box|case|organi[sz]er|winder|roll)\b|organi[sz]er|\bwinder\b|\bwatch\s+tool|\bspring\s*bar|\bbuckle\b/i', $n);
      return $acc ? 'Accessories' : 'Watch';
    }
    $t = trim((string)$srcCat); return $t !== '' ? mb_convert_case($t, MB_CASE_TITLE) : ($cat === 'lighter-accessories' ? 'Accessories' : 'Gadgets');
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
    @set_time_limit(120); $cf = $this->cfg(); $s = $this->src(); abort_unless($s && in_array($cf['c'], ['gadget', 'amour'], true), 404);
    if ($r->filled('map') && $cf['c'] === 'amour') DB::table('settings')->updateOrInsert(['key' => 'amour_sub_map'], ['value' => (string)$r->input('map'), 'updated_at' => now(), 'created_at' => now()]);
    $cat = DB::table('categories')->where('slug', $cf['cat'])->first(); abort_unless($cat, 404);
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $s->id)->where('p.category_id', $cat->id)->get(['p.id', 'p.name', 'p.sub', 'p.slug', 'w.source_url', 'w.source_name']); $n = 0;
    foreach ($rows as $x) {
      $sub = self::subFor((string)($x->source_name ?: $x->name), null, $cf['cat'], (string)$x->source_url);
      if ($sub === $x->sub) continue;
      DB::table('products')->where('id', $x->id)->update(['sub' => $sub, 'sub_sub' => null, 'path_slug' => $this->slugFor($cat->id, (string)($x->slug ?: \App\Support\Slugs::slug($x->name)), $sub, (int)$x->id), 'updated_at' => now()]); $n++;
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
    $dbg = []; $bad = [$cf['domain'], preg_replace('/\..*$/', '', $cf['domain']), 'timeaccess', 'time access', 'ryans', 'luxotix', 'amour']; $ok = 0; $imgMap = []; $imgFor = [];
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
      $spec = []; if (!empty($j['vendor'])) { $v = trim((string)$j['vendor']); if (!preg_match('/time\s*-?\s*access|luxotix|amour|ryans|startech/i', $v)) $spec[] = '<li><strong>Brand:</strong> ' . e($v) . '</li>'; }
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
  private function sync(int $sid): void {
    try {
      $lock = \Illuminate\Support\Facades\Schema::hasColumn('products', 'manual_unpublish_lock');
      $rows = DB::table('watch_source as w')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $sid)->get(['w.id as wid', 'w.markup_pct', 'w.our_price', 'sp.price as sprice', 'sp.in_stock', 'sp.published as sppub', 'p.id as pid', 'p.published as ppub', 'p.image as pimg']);
      $needClean = strtolower((string)(DB::table('stores')->where('id', $sid)->value('domain'))) === 'amour.com.bd';
      foreach ($rows as $x) {
        $live = (bool)$x->in_stock && (bool)$x->sppub && (!$needClean || str_starts_with((string)$x->pimg, '/uploads/ai/')); $u = ['updated_at' => now()];
        if ($live !== (bool)$x->ppub) { $u['published'] = $live; if ($lock) $u['manual_unpublish_lock'] = !$live; }
        $our = (int)ceil((float)$x->sprice * (1 + (float)$x->markup_pct / 100));
        if ($x->sprice > 0 && $our != (int)$x->our_price) { $u['price'] = $our; $u['cost'] = $x->sprice; DB::table('watch_source')->where('id', $x->wid)->update(['our_price' => $our, 'source_price' => $x->sprice]); }
        if (count($u) > 1) DB::table('products')->where('id', $x->pid)->update($u);
        DB::table('watch_source')->where('id', $x->wid)->update(['source_in_stock' => $x->in_stock]);
      }
    } catch (\Throwable $e) { report($e); }
  }
  /** Admin-only history: which product came from where, at what price. */
  public function sources(Request $r) {
    $q = DB::table('watch_source as w')->leftJoin('products as p', 'p.id', '=', 'w.product_id')->select('w.*', 'p.sku', 'p.name', 'p.price')->orderByDesc('w.id');
    if ($r->filled('q')) { $t = '%' . $r->q . '%'; $q->where(fn($w) => $w->where('p.name', 'ilike', $t)->orWhere('p.sku', 'ilike', $t)->orWhere('w.source_sku', 'ilike', $t)); }
    $rows = $q->paginate(100)->withQueryString();
    $h = '<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>Watch Sources</title><body style="font-family:system-ui;padding:16px"><h3>Watch — source history (admin only)</h3><form><input name=q value="' . e($r->q) . '" placeholder="name / code"> <button>Search</button></form><table border=1 cellpadding=5 style="border-collapse:collapse;font-size:13px;margin-top:10px"><tr><th>Our code</th><th>Our product</th><th>Our price</th><th>Source price</th><th>+%</th><th>Source SKU</th><th>Source link</th><th>Source stock</th></tr>';
    foreach ($rows as $w) $h .= '<tr><td>' . e($w->sku) . '</td><td>' . e($w->name ?: $w->source_name) . '</td><td>' . e(number_format((float)$w->our_price)) . '</td><td>' . e(number_format((float)$w->source_price)) . '</td><td>' . e($w->markup_pct) . '</td><td>' . e($w->source_sku) . '</td><td>' . ($w->source_url ? '<a href="' . e($w->source_url) . '" target=_blank rel=noreferrer>open</a>' : '') . '</td><td>' . ($w->source_in_stock === null ? '' : ($w->source_in_stock ? 'In' : 'Out')) . '</td></tr>';
    return response($h . '</table><p>' . $rows->links() . '</p>');
  }
}
