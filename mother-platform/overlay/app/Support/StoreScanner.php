<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Scans a store/domain and imports its products into store_products (central inventory). Runs in short steps so the browser can show progress. */
class StoreScanner {
  const UA = 'Mozilla/5.0 (compatible; MotherCommerceBot/1.0; +https://dutyfreeshopbd.com) Chrome/120 Safari/537.36';
  public static function ph(): string {
    return 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" rx="10" fill="#FFF1E8"/><path d="M22 54l11-14 8 9 6-7 11 12z" fill="#FDC2A5"/><circle cx="53" cy="29" r="6" fill="#FDC2A5"/></svg>');
  }
  public static function cleanDomain(string $d): string {
    $d = strtolower(trim($d)); $d = preg_replace('#^https?://#', '', $d); $d = preg_replace('#[/?\#].*$#', '', $d);
    return preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $d) ? $d : '';
  }
  public static function safeHost(string $h): bool {
    if ($h === '' || $h === 'localhost') return false;
    $ips = filter_var($h, FILTER_VALIDATE_IP) ? [$h] : (@gethostbynamel($h) ?: []);
    if (!$ips) return false;
    foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    return true;
  }
  public static function get(string $url, int $timeout = 10, int $max = 8000000): ?array {
    for ($i = 0; $i < 4; $i++) {
      $p = parse_url($url); if (!$p || !in_array($p['scheme'] ?? '', ['http','https'], true) || !self::safeHost($p['host'] ?? '')) return null;
      $ctx = stream_context_create(['http'=>['timeout'=>$timeout,'follow_location'=>0,'ignore_errors'=>true,'header'=>"User-Agent: ".self::UA."\r\nAccept: */*\r\nAccept-Language: en\r\n"],'ssl'=>['verify_peer'=>true]]);
      $fp = @fopen($url, 'r', false, $ctx); if (!$fp) return null;
      stream_set_timeout($fp, $timeout);
      $meta = stream_get_meta_data($fp); $body = (string) stream_get_contents($fp, $max); fclose($fp);
      $hd = $meta['wrapper_data'] ?? []; $code = 0; $loc = null; $h = [];
      foreach ($hd as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) { $code = (int)$m[1]; continue; }
        if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $h[strtolower(trim($k))] = trim($v); }
      }
      if (in_array($code, [301,302,303,307,308], true) && !empty($h['location'])) {
        $l = $h['location'];
        if (str_starts_with($l, '//')) $l = $p['scheme'].':'.$l; elseif (str_starts_with($l, '/')) $l = $p['scheme'].'://'.$p['host'].$l; elseif (!preg_match('#^https?://#', $l)) $l = $p['scheme'].'://'.$p['host'].'/'.$l;
        $url = $l; continue;
      }
      return ['code'=>$code, 'body'=>$body, 'h'=>$h, 'url'=>$url];
    }
    return null;
  }
  private static function json(?array $r) { return ($r && $r['code'] === 200) ? json_decode($r['body'], true) : null; }
  private static function row(int $sid, string $key, string $name, ?string $sku, ?string $img, $price, $stock, $inStock, ?string $cat, ?string $url, bool $pub): array {
    $img = $img && preg_match('#^https?://#', $img) ? mb_substr($img, 0, 600) : null;
    return ['store_id'=>$sid, 'ext_key'=>mb_substr($key,0,400), 'name'=>mb_substr(trim(html_entity_decode(strip_tags($name), ENT_QUOTES|ENT_HTML5, 'UTF-8')),0,300), 'sku'=>$sku !== null && $sku !== '' ? mb_substr($sku,0,120) : null, 'image'=>$img, 'price'=>is_numeric($price) ? round((float)$price,2) : null, 'stock'=>$stock !== null && is_numeric($stock) ? (int)$stock : null, 'in_stock'=>$inStock, 'category'=>$cat ? mb_substr(html_entity_decode($cat, ENT_QUOTES|ENT_HTML5, 'UTF-8'),0,200) : null, 'url'=>$url ? mb_substr($url,0,600) : null, 'published'=>$pub, 'seen_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()];
  }
  private static function save(array $rows): void {
    foreach (array_chunk($rows, 200) as $ch) DB::table('store_products')->upsert($ch, ['store_id','ext_key'], ['name','sku','image','price','stock','in_stock','category','url','published','seen_at','updated_at']);
  }
  public static function begin(object $s): void {
    $st = ['mode'=>null, 'started'=>now()->toDateTimeString(), 'page'=>1, 'pos'=>0, 'sm'=>[], 'base'=>null, 'seen'=>0];
    DB::table('stores')->where('id',$s->id)->update(['scan_status'=>'running','scan_total'=>0,'scan_published'=>0,'scan_draft'=>0,'scan_imported'=>0,'scan_failed'=>0,'scan_percent'=>1,'scan_note'=>'Detecting store type…','scan_state'=>json_encode($st),'platform'=>null,'updated_at'=>now()]);
    @unlink(self::qfile($s->id));
  }
  private static function qfile(int $id): string { return storage_path('app/scan-'.$id.'.json'); }
  /** One bounded step (~18s). Returns the store row after the step. */
  public static function step(int $id): object {
    $s = DB::table('stores')->find($id); if (!$s || $s->scan_status !== 'running') return $s;
    $st = json_decode($s->scan_state ?: '{}', true) ?: []; $deadline = microtime(true) + 18; $c = ['total'=>$s->scan_total,'imp'=>$s->scan_imported,'pub'=>$s->scan_published,'fail'=>$s->scan_failed]; $note = $s->scan_note; $done = false; $platform = $s->platform; $pct = $s->scan_percent;
    try {
      if (empty($st['mode'])) {
        $base = null;
        foreach (['https','http'] as $sch) { $r = self::get("$sch://{$s->domain}/", 12); if ($r && $r['code'] > 0 && $r['code'] < 500) { $p = parse_url($r['url']); $base = ($p['scheme'] ?? $sch).'://'.($p['host'] ?? $s->domain); break; } }
        if (!$base) { $done = true; $note = 'Could not open the website (blocked, offline or DNS error).'; $st['mode'] = 'none'; }
        else {
          $st['base'] = $base;
          $j = self::json(self::get($base.'/products.json?limit=1'));
          if (is_array($j) && array_key_exists('products', $j)) { $st['mode'] = 'shopify'; $platform = 'Shopify'; }
          else {
            $r = self::get($base.'/wp-json/wc/store/v1/products?per_page=1'); $j = self::json($r);
            if (is_array($j) && isset($j[0]['name'])) { $st['mode'] = 'woo'; $platform = 'WooCommerce'; $c['total'] = (int)($r['h']['x-wp-total'] ?? 0); }
            else {
              foreach (['/products.xml','/feed.xml','/google-feed.xml','/product-feed.xml','/merchant.xml','/feeds/products.xml'] as $fp) {
                $r = self::get($base.$fp, 20); if ($r && $r['code'] === 200 && str_contains($r['body'], '<item') && str_contains($r['body'], 'base.google.com')) { $st['mode'] = 'feed'; $st['feed'] = $base.$fp; $platform = 'Google product feed'; break; }
              }
              if (empty($st['mode'])) {
                $sm = []; $rb = self::get($base.'/robots.txt'); if ($rb && $rb['code'] === 200 && preg_match_all('/^\s*sitemap:\s*(\S+)/im', $rb['body'], $m)) $sm = $m[1];
                if (!$sm) foreach (['/sitemap.xml','/sitemap_index.xml','/wp-sitemap.xml','/product-sitemap.xml'] as $d) $sm[] = $base.$d;
                $st['mode'] = 'sitemap'; $st['sm'] = array_slice(array_values(array_unique($sm)), 0, 40); $st['phase'] = 'expand'; $platform = 'Website (auto-detected pages)'; file_put_contents(self::qfile($id), '[]');
              }
            }
          }
        }
        $note = $done ? $note : "Found: $platform";
      } elseif ($st['mode'] === 'shopify') {
        $page = $st['page']; $rows = [];
        while (microtime(true) < $deadline) {
          $j = self::json(self::get($st['base']."/products.json?limit=250&page=$page", 20)); $list = is_array($j) ? ($j['products'] ?? []) : [];
          if (!$list) { $done = true; break; }
          foreach ($list as $p) {
            $v = $p['variants'][0] ?? []; $avail = false; foreach ($p['variants'] ?? [] as $vv) if (!empty($vv['available'])) $avail = true;
            $qty = null; foreach ($p['variants'] ?? [] as $vv) if (isset($vv['inventory_quantity'])) $qty = ($qty ?? 0) + (int)$vv['inventory_quantity'];
            $rows[] = self::row($id, 'sh:'.$p['id'], $p['title'] ?? '', $v['sku'] ?? null, $p['images'][0]['src'] ?? ($p['image']['src'] ?? null), $v['price'] ?? null, $qty, $avail, $p['product_type'] ?? null, $st['base'].'/products/'.($p['handle'] ?? ''), true);
          }
          self::save($rows); $c['imp'] += count($rows); $c['pub'] += count($rows); $c['total'] += count($rows); $rows = [];
          $page++; if (count($list) < 250) { $done = true; break; }
        }
        $st['page'] = $page; $note = "Reading products page $page…"; $pct = min(95, 5 + $page * 8);
      } elseif ($st['mode'] === 'woo') {
        $page = $st['page']; $rows = [];
        while (microtime(true) < $deadline) {
          $r = self::get($st['base']."/wp-json/wc/store/v1/products?per_page=100&page=$page", 25); $list = self::json($r);
          if (!is_array($list) || !$list) { $done = true; break; }
          if (!$c['total']) $c['total'] = (int)($r['h']['x-wp-total'] ?? 0);
          foreach ($list as $p) {
            $minor = (int)($p['prices']['currency_minor_unit'] ?? 2); $price = isset($p['prices']['price']) && $p['prices']['price'] !== '' ? ((float)$p['prices']['price']) / pow(10, $minor) : null;
            $stock = $p['low_stock_remaining'] ?? null; $in = array_key_exists('is_in_stock', $p) ? (bool)$p['is_in_stock'] : null;
            $rows[] = self::row($id, 'wc:'.$p['id'], $p['name'] ?? '', $p['sku'] ?? null, $p['images'][0]['src'] ?? null, $price, $in === false ? 0 : $stock, $in, $p['categories'][0]['name'] ?? null, $p['permalink'] ?? null, true);
          }
          self::save($rows); $c['imp'] += count($rows); $c['pub'] += count($rows); $rows = [];
          $page++; if (count($list) < 100) { $done = true; break; }
        }
        $st['page'] = $page; $note = "Reading products page $page…"; $pct = $c['total'] ? min(95, (int)round($c['imp'] / $c['total'] * 100)) : min(95, 5 + $page * 8);
      } elseif ($st['mode'] === 'feed') {
        $r = self::get($st['feed'], 40, 60000000); $doc = $r ? @simplexml_load_string($r['body']) : null; $rows = [];
        if (!$doc || !isset($doc->channel->item)) { $done = true; $note = 'Feed could not be read.'; }
        else {
          foreach ($doc->channel->item as $i) {
            $g = $i->children('http://base.google.com/ns/1.0'); $pid = (string)$g->id; if ($pid === '') { $c['fail']++; continue; }
            $pr = preg_replace('/[^0-9.]/', '', (string)($g->sale_price ?: $g->price)); $av = strtolower((string)$g->availability); $cat = trim(explode('>', (string)$g->product_type)[0] ?: (string)$g->google_product_category);
            $rows[] = self::row($id, 'fd:'.$pid, (string)($g->title ?: $i->title), (string)($g->mpn ?: $g->id), (string)$g->image_link, $pr, null, $av === '' ? null : !str_contains($av, 'out'), $cat, (string)($g->link ?: $i->link), true);
          }
          $c['total'] = count($rows) + $c['fail']; self::save($rows); $c['imp'] = count($rows); $c['pub'] = count($rows); $done = true; $note = 'Feed imported.';
        }
      } else { // sitemap
        $q = json_decode((string)@file_get_contents(self::qfile($id)), true) ?: [];
        if (($st['phase'] ?? '') === 'expand') {
          while ($st['sm'] && microtime(true) < $deadline && count($q) < 30000) {
            $u = array_shift($st['sm']); $r = self::get($u, 15, 12000000); if (!$r || $r['code'] !== 200) continue;
            $b = $r['body']; if (str_starts_with($b, "\x1f\x8b")) $b = @gzdecode($b) ?: '';
            if (!preg_match_all('#<loc>\s*(?:<!\[CDATA\[)?\s*([^<\]\s]+)#i', $b, $m)) continue;
            if (stripos($b, '<sitemapindex') !== false) {
              $kids = $m[1]; $prod = array_values(array_filter($kids, fn($k) => stripos($k, 'product') !== false)); if ($prod) $kids = $prod;
              foreach (array_slice($kids, 0, 80) as $k) $st['sm'][] = html_entity_decode($k);
            } else foreach ($m[1] as $l) { $l = html_entity_decode($l); if (!preg_match('#\.(jpg|jpeg|png|webp|gif|pdf|xml)(\?|$)#i', $l) && !preg_match('#/(cart|checkout|account|login|blog|contact|about|terms|privacy|tag|category|categories|collections?)(/|$)#i', $l)) $q[] = $l; }
          }
          if (!$st['sm']) { $q = array_values(array_unique($q)); $st['phase'] = 'pages'; $c['total'] = count($q); if (!$q) { $done = true; $note = 'No sitemap or product pages found.'; } else $note = 'Found '.count($q).' pages, reading products…'; }
          else $note = 'Reading sitemaps… ('.count($q).' pages so far)';
          file_put_contents(self::qfile($id), json_encode($q)); $pct = 3;
        } else {
          $rows = []; $n = 0; $pos = $st['pos'];
          while ($pos < count($q) && microtime(true) < $deadline && $n < 40) {
            $u = $q[$pos++]; $n++; $r = self::get($u, 8, 1500000);
            if (!$r || $r['code'] !== 200) { $c['fail']++; continue; }
            $row = self::parsePage($id, $r['url'], $r['body']);
            if ($row) { $rows[] = $row; $c['pub']++; } else $c['fail']++;
          }
          self::save($rows); $c['imp'] += count($rows); $st['pos'] = $pos; $done = $pos >= count($q);
          $pct = count($q) ? min(99, (int)round($pos / count($q) * 100)) : 99; $note = "Reading product pages $pos / ".count($q);
        }
      }
    } catch (\Throwable $e) { $c['fail']++; $note = 'Error: '.mb_substr($e->getMessage(), 0, 160); }
    $upd = ['scan_state'=>json_encode($st), 'scan_total'=>$c['total'] ?: ($c['imp'] + $c['fail']), 'scan_imported'=>$c['imp'], 'scan_published'=>$c['pub'], 'scan_failed'=>$c['fail'], 'scan_percent'=>$pct, 'scan_note'=>$note, 'platform'=>$platform, 'updated_at'=>now()];
    if ($done) {
      $started = $st['started'] ?? now()->toDateTimeString();
      if ($c['imp'] > 0) DB::table('store_products')->where('store_id',$id)->where(fn($q) => $q->whereNull('seen_at')->orWhere('seen_at','<',$started))->update(['published'=>false]);
      $upd['scan_draft'] = DB::table('store_products')->where('store_id',$id)->where('published',false)->count();
      $upd['scan_published'] = DB::table('store_products')->where('store_id',$id)->where('published',true)->count();
      $upd['scan_total'] = DB::table('store_products')->where('store_id',$id)->count() + $c['fail'];
      $upd['scan_percent'] = 100; $upd['last_scan_at'] = now(); $upd['scan_status'] = $c['imp'] > 0 ? 'completed' : 'failed';
      if ($c['imp'] === 0 && !$upd['scan_note']) $upd['scan_note'] = 'No products found.';
      if ($c['imp'] > 0) $upd['scan_note'] = 'Scan finished: '.$c['imp'].' products imported'.($c['fail'] ? ', '.$c['fail'].' skipped' : '').'.';
      @unlink(self::qfile($id));
    }
    DB::table('stores')->where('id',$id)->update($upd);
    return DB::table('stores')->find($id);
  }
  /** Product data from a single page: JSON-LD Product first, Open Graph as fallback. */
  public static function parsePage(int $sid, string $url, string $html): ?array {
    $prod = null;
    if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m)) {
      $crumbs = null;
      $walk = function ($n) use (&$walk, &$prod, &$crumbs) {
        if (!is_array($n)) return;
        $t = $n['@type'] ?? null; $t = is_array($t) ? $t : [$t];
        if (!$prod && in_array('Product', $t, true)) $prod = $n;
        if (in_array('BreadcrumbList', $t, true)) $crumbs = $n;
        foreach ($n as $v) if (is_array($v)) $walk($v);
      };
      foreach ($m[1] as $js) { $d = json_decode(trim(html_entity_decode($js)), true); if ($d) $walk($d); }
    }
    $og = function ($p) use ($html) { return preg_match('#<meta[^>]+(?:property|name)=["\']'.preg_quote($p,'#').'["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $x) || preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']'.preg_quote($p,'#').'["\']#i', $html, $x) ? html_entity_decode($x[1], ENT_QUOTES|ENT_HTML5, 'UTF-8') : null; };
    if ($prod) {
      $img = $prod['image'] ?? null; if (is_array($img)) $img = $img['url'] ?? ($img[0]['url'] ?? ($img[0] ?? null)); if (!is_string($img)) $img = $og('og:image');
      $off = $prod['offers'] ?? []; if (isset($off['@type']) || isset($off['price']) || isset($off['lowPrice'])) $off = [$off]; $o = $off[0] ?? [];
      $price = $o['price'] ?? ($o['lowPrice'] ?? ($o['priceSpecification']['price'] ?? null)); $av = strtolower(is_string($o['availability'] ?? null) ? $o['availability'] : '');
      $in = $av === '' ? null : !str_contains($av, 'outofstock') && !str_contains($av, 'soldout') && !str_contains($av, 'discontinued');
      $cat = $prod['category'] ?? null; if (is_array($cat)) $cat = $cat['name'] ?? null;
      if (!$cat && !empty($crumbs['itemListElement'])) { $it = $crumbs['itemListElement']; $k = count($it) - 2; if ($k >= 0) $cat = $it[$k]['name'] ?? ($it[$k]['item']['name'] ?? null); }
      $sku = $prod['sku'] ?? ($prod['mpn'] ?? ($prod['gtin13'] ?? null)); if (is_array($sku)) $sku = $sku[0] ?? null;
      $name = $prod['name'] ?? $og('og:title');
      if (!is_string($name) || $name === '') return null;
      return self::row($sid, 'u:'.$url, $name, is_scalar($sku) ? (string)$sku : null, is_string($img) ? $img : null, is_scalar($price) ? preg_replace('/[^0-9.]/', '', (string)$price) : null, null, $in, is_string($cat) ? $cat : null, $url, true);
    }
    $price = $og('product:price:amount') ?? $og('og:price:amount');
    if (($og('og:type') === 'product' || $price) && ($t = $og('og:title'))) return self::row($sid, 'u:'.$url, $t, null, $og('og:image'), $price ? preg_replace('/[^0-9.]/', '', $price) : null, null, null, null, $url, true);
    return null;
  }
}
