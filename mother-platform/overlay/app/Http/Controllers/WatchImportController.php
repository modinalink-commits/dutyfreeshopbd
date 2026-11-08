<?php
namespace App\Http\Controllers;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Watch category import (owner, 8 Nov 2026): every scanned product of the source shop priced 0-50,000 BDT becomes OUR product (our code, our URL, our image copy, price = source +15%). Source details live only in watch_source (admin only). */
class WatchImportController extends Controller {
  private function src() { return DB::table('stores')->whereRaw('lower(domain) = ?', [strtolower((string)(DB::table('settings')->where('key', 'watch_source_domain')->value('value') ?: 'timeaccess-store.com'))])->first(); }
  private function cond($q, $sid) {
    $max = (float)(DB::table('settings')->where('key', 'watch_max_price')->value('value') ?: 50000); $min = (float)(DB::table('settings')->where('key', 'watch_min_price')->value('value') ?: 0);
    return $q->where('store_id', $sid)->where('in_stock', true)->where('price', '>', max(0, $min))->where('price', '<=', $max);
  }
  private function stat($sid): array {
    $total = $this->cond(DB::table('store_products'), $sid)->count();
    $done = $this->cond(DB::table('store_products'), $sid)->whereIn('id', DB::table('watch_source')->select('store_product_id'))->count();
    return ['total' => $total, 'done' => $done];
  }
  public function page() {
    $s = $this->src(); abort_unless($s, 404); $st = $this->stat($s->id);
    $csrf = csrf_token();
    return response('<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>Watch Import</title><body style="font-family:system-ui;max-width:640px;margin:30px auto;padding:0 16px"><h2>⌚ Watch Import</h2><p>Source shop products (০–৫০,০০০ টাকা) → আমাদের Watch ক্যাটাগরি, দাম +১৫%।</p><p id=s>Imported <b id=d>'.$st['done'].'</b> / <b>'.$st['total'].'</b></p><div style="background:#eee;height:14px;border-radius:7px"><div id=b style="height:14px;border-radius:7px;background:#f97316;width:'.($st['total'] ? round($st['done'] / $st['total'] * 100) : 0).'%"></div></div><p><button id=go style="padding:10px 18px;font-size:15px">Start / Resume</button> <a href="/admin/watch-sources">Source list (admin only)</a></p><pre id=m></pre><script>var T='.$st['total'].',run=false;async function step(){var r=await fetch("/admin/watch-import/step",{method:"POST",headers:{"X-CSRF-TOKEN":"'.$csrf.'","Accept":"application/json"}});var j=await r.json();document.getElementById("d").textContent=j.done;document.getElementById("b").style.width=(T?j.done/T*100:0)+"%";document.getElementById("m").textContent=j.note||"";return j}document.getElementById("go").onclick=async function(){if(run)return;run=true;try{while(true){var j=await step();if(j.finished)break}document.getElementById("m").textContent="Finished ✔ "+document.getElementById("m").textContent}catch(e){document.getElementById("m").textContent="Stopped: "+e}run=false}</script>');
  }
  public function step(Request $r) {
    @set_time_limit(90);
    $s = $this->src(); abort_unless($s, 404);
    $cat = DB::table('categories')->where('slug', 'watch')->first(); if (!$cat) return response()->json(['finished' => true, 'done' => 0, 'note' => 'Watch category not found (deploy migration first).']);
    $lock = Cache::lock('watch-import', 80); if (!$lock->get()) return response()->json(['finished' => false] + $this->stat($s->id));
    try {
      $pct = (float)(DB::table('settings')->where('key', 'watch_markup_pct')->value('value') ?: 15);
      $rows = $this->cond(DB::table('store_products'), $s->id)->whereNotIn('id', DB::table('watch_source')->select('store_product_id'))->orderBy('id')->limit(12)->get();
      if ($rows->isEmpty()) { $this->sync($s->id); foreach (['store.cats', 'store.catslugs', 'store.home11', 'stock.modes'] as $k) Cache::forget($k); Cache::forget('store.hsec3.watch'); return response()->json(['finished' => true, 'note' => 'All products imported.'] + $this->stat($s->id)); }
      $imgs = []; foreach ($rows as $x) if ($x->image && preg_match('#^https?://#i', $x->image)) $imgs[$x->image] = $x->image;
      $got = $imgs ? StoreScanner::pget(array_values($imgs), 12) : [];
      $dir = public_path('uploads/watch'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $made = 0;
      foreach ($rows as $x) {
        try {
          $our = (int)ceil((float)$x->price * (1 + $pct / 100));
          $name = trim(preg_replace('/\s+/u', ' ', (string)$x->name)); $name = trim(str_ireplace(['timeaccess-store.com', 'timeaccess store', 'timeaccess'], '', $name)); if ($name === '') $name = 'Watch';
          $sub = $x->category ? mb_convert_case(trim((string)$x->category), MB_CASE_TITLE) : 'Watch'; if (mb_strtolower($sub) === 'timeaccess' || $sub === '') $sub = 'Watch';
          $base = \App\Support\Slugs::slug($name) ?: 'watch'; $slug = $base; $i = 2; $subSlug = \App\Support\Slugs::subSlug($sub);
          while (DB::table('products')->where('category_id', $cat->id)->whereRaw(\App\Support\Slugs::subSql() . ' = ?', [$subSlug])->where('path_slug', $slug)->exists()) $slug = $base . '-' . $i++;
          $pid = DB::table('products')->insertGetId(['name' => mb_substr($name, 0, 200), 'category_id' => $cat->id, 'price' => $our, 'cost' => $x->price, 'stock' => 0, 'published' => true, 'sub' => $sub, 'slug' => $base, 'path_slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
          $code = 'DFW' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT); $upd = ['sku' => $code];
          $g = $x->image ? ($got[$x->image] ?? null) : null;
          if ($g && ($g['code'] ?? 0) == 200 && !empty($g['body']) && @getimagesizefromstring($g['body'])) {
            $ext = strtolower(pathinfo(parse_url($x->image, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)); if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'jpg';
            if (@file_put_contents($dir . '/' . $code . '.' . $ext, $g['body'])) $upd['image'] = '/uploads/watch/' . $code . '.' . $ext;
          }
          DB::table('products')->where('id', $pid)->update($upd);
          DB::table('watch_source')->insert(['product_id' => $pid, 'store_product_id' => $x->id, 'store_id' => $s->id, 'source_name' => $x->name, 'source_sku' => $x->sku, 'source_url' => $x->url, 'source_price' => $x->price, 'our_price' => $our, 'markup_pct' => $pct, 'source_in_stock' => $x->in_stock, 'created_at' => now(), 'updated_at' => now()]);
          $made++;
        } catch (\Throwable $e) { report($e); DB::table('watch_source')->insert(['product_id' => null, 'store_product_id' => $x->id, 'store_id' => $s->id, 'source_name' => $x->name, 'source_price' => $x->price, 'note' => mb_substr($e->getMessage(), 0, 200), 'created_at' => now(), 'updated_at' => now()]); }
      }
      return response()->json(['finished' => false, 'note' => "+$made"] + $this->stat($s->id));
    } finally { $lock->release(); }
  }
  /** Download images with Laravel's HTTP client (several at once). Returns [url => ['file'=>'/uploads/watch/..']|['err'=>..]]. */
  private function fetchImages(array $map): array {
    $dir = public_path('uploads/watch'); if (!is_dir($dir)) @mkdir($dir, 0775, true); $res = [];
    try {
      $resp = \Illuminate\Support\Facades\Http::pool(function ($pool) use ($map) { $o = []; foreach ($map as $code => $url) $o[] = $pool->as((string)$code)->timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8'])->get($url); return $o; });
      foreach ($map as $code => $url) {
        $r = $resp[(string)$code] ?? null;
        if (!$r || $r instanceof \Throwable || !method_exists($r, 'ok') || !$r->ok()) { $res[$code] = ['err' => ($r instanceof \Throwable) ? mb_substr($r->getMessage(), 0, 100) : 'http ' . (method_exists($r, 'status') ? $r->status() : '?')]; continue; }
        $b = $r->body(); $i = @getimagesizefromstring($b); $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$i[2] ?? 0] ?? null;
        if (!$ext || strlen($b) < 200) { $res[$code] = ['err' => 'not an image']; continue; }
        if (@file_put_contents($dir . '/' . $code . '.' . $ext, $b) === false) { $res[$code] = ['err' => 'cannot write ' . $dir]; continue; }
        @chmod($dir . '/' . $code . '.' . $ext, 0664); $res[$code] = ['file' => '/uploads/watch/' . $code . '.' . $ext];
      }
    } catch (\Throwable $e) { foreach ($map as $code => $url) $res[$code] = ['err' => mb_substr($e->getMessage(), 0, 120)]; }
    return $res;
  }
  /** Repair step: products imported without an image get theirs now. */
  public function images(Request $r) {
    @set_time_limit(90);
    $rows = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')
      ->where(fn($q) => $q->whereNull('p.image')->orWhere('p.image', ''))->whereNotNull('sp.image')->orderBy('p.id')->limit(10)->get(['p.id as pid', 'p.sku', 'sp.image as src']);
    $left = DB::table('watch_source as w')->join('products as p', 'p.id', '=', 'w.product_id')->where(fn($q) => $q->whereNull('p.image')->orWhere('p.image', ''))->count();
    if ($rows->isEmpty()) return response()->json(['finished' => true, 'left' => $left]);
    $map = []; foreach ($rows as $x) $map[$x->sku] = $x->src; $res = $this->fetchImages($map); $ok = 0; $err = null;
    foreach ($rows as $x) { $g = $res[$x->sku] ?? []; if (!empty($g['file'])) { DB::table('products')->where('id', $x->pid)->update(['image' => $g['file'], 'updated_at' => now()]); $ok++; } else $err = $g['err'] ?? 'unknown'; }
    return response()->json(['finished' => $ok === 0, 'ok' => $ok, 'left' => $left - $ok, 'err' => $err]);
  }
  /** Keep live state equal to the source shop: out of stock there = not live here; back in stock = live again; price follows source +markup. */
  private function sync(int $sid): void {
    try {
      $lock = \Illuminate\Support\Facades\Schema::hasColumn('products', 'manual_unpublish_lock');
      $rows = DB::table('watch_source as w')->join('store_products as sp', 'sp.id', '=', 'w.store_product_id')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.store_id', $sid)->get(['w.id as wid', 'w.markup_pct', 'w.our_price', 'sp.price as sprice', 'sp.in_stock', 'sp.published as sppub', 'p.id as pid', 'p.published as ppub']);
      foreach ($rows as $x) {
        $live = (bool)$x->in_stock && (bool)$x->sppub; $u = ['updated_at' => now()];
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
