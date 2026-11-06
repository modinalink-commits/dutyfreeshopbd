<?php
namespace App\Http\Controllers;

use App\Support\Seo;
use App\Support\SeoAutoGenerator as G;
use App\Support\SeoMgr as S;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Pages & SEO -> Category / Brand / Product SEO, image alt audit, keyword coverage, exports. */
class SeoItemsController extends Controller
{
  private const T = ['category' => 'categories', 'sub' => 'category_nodes', 'child' => 'category_nodes', 'brand' => 'brands', 'product' => 'products'];
  /** Admin type -> SEOAutoGenerator type (products are not generated here). */
  private const GT = ['category' => 'main', 'sub' => 'sub', 'child' => 'child', 'brand' => 'brand'];
  private function tbl(string $t): string { abort_unless(isset(self::T[$t]), 404); return self::T[$t]; }
  private function clear(): void { foreach (['store.home6', 'store.home4', 'store.home5', 'store.sitemap5', 'store.sitemap10'] as $k) Cache::forget($k); }
  /** Map to unified [title,desc,kw] columns per type. */
  private function tcol(string $t): string { return $t === 'category' ? 'title' : 'meta_title'; }
  private function status(object $x, string $t): array {
    $ti = trim((string)($x->{$this->tcol($t)} ?? '')); $de = trim((string)($x->meta_description ?? '')); $kw = trim((string)($x->focus_keyword ?? '')); $iss = [];
    if ($ti === '') $iss[] = 'No SEO title'; elseif (mb_strlen($ti) > 78) $iss[] = 'Title too long';
    if ($de === '') $iss[] = 'No meta description'; elseif (mb_strlen($de) < 70) $iss[] = 'Description short'; elseif (mb_strlen($de) > 165) $iss[] = 'Description too long';
    if ($kw === '') $iss[] = 'No focus keyword'; elseif ($ti !== '' && mb_stripos($ti, mb_substr($kw, 0, 24)) === false && mb_stripos($de, mb_substr($kw, 0, 24)) === false) $iss[] = 'Keyword not in title/description';
    return $iss;
  }
  private function items(string $t, Request $r) {
    $tb = $this->tbl($t); $q = DB::table($tb)->orderBy('id');
    if ($t === 'sub') $q->where('level', 2); elseif ($t === 'child') $q->where('level', 3);
    if ($s = trim((string)$r->query('q'))) $q->where('name', 'ilike', '%' . $s . '%');
    return $q;
  }

  public function index(Request $r) {
    $t = in_array($r->query('type'), array_keys(self::T), true) ? $r->query('type') : 'category'; $f = (string)$r->query('f', 'all');
    $q = $this->items($t, $r); $all = $q->get(); $gt = self::GT[$t] ?? null;
    $tc = $this->tcol($t); $dupT = []; $dupD = [];
    foreach ($all as $x) { $a = mb_strtolower(trim((string)($x->$tc ?? ''))); $b = mb_strtolower(trim((string)($x->meta_description ?? ''))); if ($a !== '') $dupT[$a] = ($dupT[$a] ?? 0) + 1; if ($b !== '') $dupD[$b] = ($dupD[$b] ?? 0) + 1; }
    $rows = $all->map(function ($x) use ($t, $gt, $tc, $dupT, $dupD) { $x->iss = $this->status($x, $t);
      $a = mb_strtolower(trim((string)($x->$tc ?? ''))); $b = mb_strtolower(trim((string)($x->meta_description ?? '')));
      if ($a !== '' && ($dupT[$a] ?? 0) > 1) $x->iss[] = 'Duplicate title (' . $dupT[$a] . ')'; if ($b !== '' && ($dupD[$b] ?? 0) > 1) $x->iss[] = 'Duplicate description (' . $dupD[$b] . ')'; $x->seo = $gt ? G::status($gt, $x) : null; $x->modes = $gt ? G::modes($x) : []; return $x; });
    $tot = $rows->count(); $bad = $rows->filter(fn($x) => count($x->iss))->count();
    $miss = $gt ? $rows->filter(fn($x) => $x->seo['state'] === 'missing')->count() : 0;
    if ($f === 'bad') $rows = $rows->filter(fn($x) => count($x->iss)); elseif ($f === 'nokw') $rows = $rows->filter(fn($x) => trim((string)$x->focus_keyword) === '');
    elseif ($gt && $f === 'missing') $rows = $rows->filter(fn($x) => $x->seo['state'] === 'missing'); elseif ($gt && in_array($f, ['auto', 'manual'], true)) $rows = $rows->filter(fn($x) => $x->seo['origin'] === $f);
    $parents = []; if (in_array($t, ['sub', 'child'], true)) { $parents = DB::table('categories')->pluck('name', 'id')->all(); foreach (DB::table('category_nodes')->where('level', 2)->get(['id', 'name']) as $n) $parents['n' . $n->id] = $n->name; }
    $page = max(1, (int)$r->query('page', 1)); $per = 40; $pages = max(1, (int)ceil($rows->count() / $per));
    return view('seo_items', ['t' => $t, 'f' => $f, 'rows' => $rows->slice(($page - 1) * $per, $per)->values(), 'tot' => $tot, 'bad' => $bad, 'miss' => $miss, 'gt' => $gt, 'parents' => $parents, 'page' => $page, 'pages' => $pages, 'qs' => $r->query('q'), 'tc' => $this->tcol($t)]);
  }

  /** Generated (not saved) values for the form: Regenerate SEO / Reset to Auto fill the form from this, staff reviews and saves. */
  public function preview(Request $r, string $t, int $id) {
    $this->tbl($t); $gt = self::GT[$t] ?? null; abort_unless($gt, 404); $o = G::row($gt, $id); abort_unless($o, 404);
    return response()->json(['gen' => G::generate($gt, $o), 'modes' => G::modes($o)])->header('Cache-Control', 'no-store');
  }
  /** Admin action: Generate Missing SEO for Main / Sub / Child categories and Brands (only empty fields; existing text untouched). */
  public function generateMissing(Request $r) {
    $res = G::bulk($r->filled('type') && isset(self::GT[$r->input('type')]) ? self::GT[$r->input('type')] : null, true);
    $this->clear(); S::audit('item.generate_missing', 'taxonomy', null, $res);
    $m = []; foreach ($res as $k => $v) $m[] = G::TYPES[$k] . ': ' . $v['records'] . ' record(s)';
    return back()->with('ok', 'Missing SEO generated — ' . implode(' · ', $m) . '. Existing and manually edited text was not changed.');
  }

  public function save(Request $r, string $t, int $id) {
    $tb = $this->tbl($t); $o = DB::table($tb)->find($id); abort_if(!$o, 404);
    if ($gt = (self::GT[$t] ?? null)) return $this->saveAuto($r, $t, $gt, $id, $o);
    $row = [$this->tcol($t) => mb_substr(trim((string)$r->input('title')), 0, 200) ?: null, 'meta_description' => mb_substr(trim((string)$r->input('desc')), 0, 500) ?: null,
      'focus_keyword' => mb_substr(trim((string)$r->input('kw')), 0, 200) ?: null, 'canonical' => mb_substr(trim((string)$r->input('canonical')), 0, 500) ?: null,
      'og_image' => mb_substr(trim((string)$r->input('og')), 0, 500) ?: null, 'noindex' => $r->boolean('noindex'), 'updated_at' => now()];
    $row['meta_keywords'] = mb_substr(trim((string)$r->input('kws')), 0, 500) ?: null; $row['image_alt'] = mb_substr(trim((string)$r->input('alt')), 0, 300) ?: null;
    DB::table($tb)->where('id', $id)->update($row); $this->clear(); S::audit('item.save', $t . '#' . $id . ' ' . ($o->name ?? ''), null, $row);
    return back()->with('ok', 'Saved: ' . ($o->name ?? ''));
  }
  /** Category / brand save with Auto / Manual tracking: a field the staff changed becomes Manual (never silently overwritten later); a field emptied is filled automatically; "Reset to Auto" returns it to Auto. */
  private function saveAuto(Request $r, string $t, string $gt, int $id, object $o) {
    $in = ['title' => 'title', 'desc' => 'desc', 'focus' => 'kw', 'keywords' => 'kws', 'canonical' => 'canonical', 'og_title' => 'og_title', 'og_desc' => 'og_desc', 'og_image' => 'og', 'crumb' => 'crumb', 'alt' => 'alt', 'schema' => 'schema'];
    $max = ['title' => 200, 'desc' => 500, 'focus' => 200, 'keywords' => 500, 'canonical' => 500, 'og_title' => 200, 'og_desc' => 500, 'og_image' => 500, 'crumb' => 120, 'alt' => 300, 'schema' => 30];
    $gen = G::generate($gt, $o); $modes = G::modes($o); $row = ['noindex' => $r->boolean('noindex'), 'updated_at' => now()];
    foreach (G::FIELDS as $f) {
      $col = G::col($gt, $f); $cur = trim((string)($o->$col ?? '')); $v = mb_substr(trim((string)$r->input($in[$f])), 0, $max[$f]);
      if ($r->boolean('auto_' . $f)) { $row[$col] = (string)$gen[$f] !== '' ? $gen[$f] : ($v ?: null); $modes[$f] = 'auto'; continue; }
      if ($v === '') { $row[$col] = (string)$gen[$f] !== '' ? $gen[$f] : null; if ((string)$gen[$f] !== '') $modes[$f] = 'auto'; continue; }
      $row[$col] = $v;
      if ($v === $cur) { $modes[$f] = $modes[$f] ?? 'manual'; continue; }
      $modes[$f] = $v === (string)$gen[$f] ? 'auto' : 'manual';
    }
    if ($t === 'brand') { $row['short_description'] = mb_substr(trim((string)$r->input('short')), 0, 300) ?: null; $row['seo_content'] = mb_substr(trim((string)$r->input('content')), 0, 4000) ?: null; $row['nofollow'] = $r->boolean('nofollow'); }
    $row['seo_modes'] = json_encode($modes); $row['seo_auto_at'] = now();
    DB::table(G::table($gt))->where('id', $id)->update($row); $this->clear(); S::audit('item.save', $t . '#' . $id . ' ' . ($o->name ?? ''), null, $row);
    return back()->with('ok', 'Saved: ' . ($o->name ?? ''));
  }

  /** Fill ONLY empty SEO fields (never overwrites editor text). Keyword-rich Bangla + English. */
  public function autofill(Request $r, string $t) {
    $tb = $this->tbl($t); $n = 0;
    if ($t === 'product') { $n = Seo::fillAll(false)['updated']; }
    else { $res = G::bulk(self::GT[$t], true)[self::GT[$t]]; $n = $res['records']; }
    $this->clear(); S::audit('item.autofill', $t, null, ['updated' => $n]);
    return back()->with('ok', "Auto-filled empty SEO fields on $n " . $t . " record(s). Existing text was not touched.");
  }

  /** Image ALT audit: products whose main image has no custom alt (storefront falls back to the product name). */
  public function alt(Request $r) {
    $f = (string)$r->query('f', 'missing'); $q = DB::table('products')->whereNotNull('image')->where('image', '!=', '')->orderBy('id');
    $tot = (clone $q)->count(); $miss = (clone $q)->where(fn($w) => $w->whereNull('image_alt')->orWhere('image_alt', ''))->count();
    if ($f === 'missing') $q->where(fn($w) => $w->whereNull('image_alt')->orWhere('image_alt', ''));
    if ($s = trim((string)$r->query('q'))) $q->where('name', 'ilike', '%' . $s . '%');
    $noimg = DB::table('products')->where(fn($w) => $w->whereNull('image')->orWhere('image', ''))->count();
    $gal = DB::table('product_images')->count(); $galMiss = DB::table('product_images')->where(fn($w) => $w->whereNull('alt')->orWhere('alt', ''))->count();
    return view('seo_alt', ['rows' => $q->paginate(40)->withQueryString(), 'tot' => $tot, 'miss' => $miss, 'noimg' => $noimg, 'gal' => $gal, 'galMiss' => $galMiss, 'f' => $f, 'qs' => $r->query('q')]);
  }
  public function altSave(Request $r, int $id) { DB::table('products')->where('id', $id)->update(['image_alt' => mb_substr(trim((string)$r->input('alt')), 0, 300) ?: null, 'updated_at' => now()]); $this->clear(); return back()->with('ok', 'Alt text saved'); }
  public function altFill() {
    $n = 0; $cats = DB::table('categories')->pluck('name', 'id')->all();
    DB::table('products')->whereNotNull('image')->where('image', '!=', '')->where(fn($w) => $w->whereNull('image_alt')->orWhere('image_alt', ''))->orderBy('id')->chunkById(300, function ($rs) use (&$n, $cats) {
      foreach ($rs as $p) { $a = trim($p->name . ' — অরিজিনাল ' . ($cats[$p->category_id] ?? 'পণ্য') . ' | DutyFree Shop BD'); DB::table('products')->where('id', $p->id)->update(['image_alt' => mb_substr($a, 0, 290)]); $n++; }
    });
    DB::statement("update product_images set alt = left(p.name || ' – ছবি', 290) from products p where p.id = product_images.product_id and (product_images.alt is null or product_images.alt = '')");
    $this->clear(); S::audit('alt.fill', 'products', null, ['n' => $n]);
    return back()->with('ok', "Image alt text generated for $n product(s) (existing alt text kept).");
  }

  /** CSV / Word (.doc) / printable PDF export of Category, Brand or Product SEO. */
  public function export(Request $r, string $t) {
    $tb = $this->tbl($t); $fmt = (string)$r->query('fmt', 'csv'); $tc = $this->tcol($t);
    $eq = DB::table($tb)->orderBy('id'); if ($t === 'sub') $eq->where('level', 2); elseif ($t === 'child') $eq->where('level', 3);
    $rows = $eq->get()->map(function ($x) use ($t, $tc) { return [$x->id, $x->name, $x->$tc, $x->meta_description, $x->focus_keyword, $x->meta_keywords ?? '', $x->canonical, !empty($x->noindex) ? 'noindex' : 'index', $t === 'product' ? ($x->image_alt ?? '') : '', implode('; ', $this->status($x, $t))]; });
    $h = ['ID', 'Name', 'SEO Title', 'Meta Description', 'Focus Keyword', 'Keywords', 'Canonical', 'Indexing', 'Image Alt', 'Issues'];
    if ($fmt === 'csv') {
      $out = fopen('php://temp', 'w+'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, $h); foreach ($rows as $x) fputcsv($out, $x); rewind($out);
      return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="seo-' . $t . '.csv"']);
    }
    $html = view('seo_export', ['h' => $h, 'rows' => $rows, 't' => $t, 'fmt' => $fmt])->render();
    if ($fmt === 'doc') return response($html, 200, ['Content-Type' => 'application/msword; charset=utf-8', 'Content-Disposition' => 'attachment; filename="seo-' . $t . '.doc"']);
    return response($html);
  }
}
