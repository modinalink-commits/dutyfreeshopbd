<?php
namespace App\Http\Controllers;

use App\Support\Seo;
use App\Support\SeoMgr as S;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Pages & SEO -> Category / Brand / Product SEO, image alt audit, keyword coverage, exports. */
class SeoItemsController extends Controller
{
  private const T = ['category' => 'categories', 'brand' => 'brands', 'product' => 'products'];
  private function tbl(string $t): string { abort_unless(isset(self::T[$t]), 404); return self::T[$t]; }
  private function clear(): void { foreach (['store.home6', 'store.home4', 'store.home5', 'store.sitemap5'] as $k) Cache::forget($k); }
  /** Map to unified [title,desc,kw] columns per type. */
  private function tcol(string $t): string { return $t === 'category' ? 'title' : 'meta_title'; }
  private function status(object $x, string $t): array {
    $ti = trim((string)($x->{$this->tcol($t)} ?? '')); $de = trim((string)($x->meta_description ?? '')); $kw = trim((string)($x->focus_keyword ?? '')); $iss = [];
    if ($ti === '') $iss[] = 'No SEO title'; elseif (mb_strlen($ti) > 70) $iss[] = 'Title too long';
    if ($de === '') $iss[] = 'No meta description'; elseif (mb_strlen($de) < 70) $iss[] = 'Description short'; elseif (mb_strlen($de) > 165) $iss[] = 'Description too long';
    if ($kw === '') $iss[] = 'No focus keyword'; elseif ($ti !== '' && mb_stripos($ti, $kw) === false && mb_stripos($de, $kw) === false) $iss[] = 'Keyword not in title/description';
    return $iss;
  }
  private function items(string $t, Request $r) {
    $tb = $this->tbl($t); $q = DB::table($tb)->orderBy('id');
    if ($s = trim((string)$r->query('q'))) $q->where('name', 'ilike', '%' . $s . '%');
    return $q;
  }

  public function index(Request $r) {
    $t = in_array($r->query('type'), ['category', 'brand', 'product'], true) ? $r->query('type') : 'category'; $f = (string)$r->query('f', 'all');
    $q = $this->items($t, $r); $all = $q->get();
    $rows = $all->map(function ($x) use ($t) { $x->iss = $this->status($x, $t); return $x; });
    $tot = $rows->count(); $bad = $rows->filter(fn($x) => count($x->iss))->count();
    if ($f === 'bad') $rows = $rows->filter(fn($x) => count($x->iss)); elseif ($f === 'nokw') $rows = $rows->filter(fn($x) => trim((string)$x->focus_keyword) === '');
    $page = max(1, (int)$r->query('page', 1)); $per = 40; $pages = max(1, (int)ceil($rows->count() / $per));
    return view('seo_items', ['t' => $t, 'f' => $f, 'rows' => $rows->slice(($page - 1) * $per, $per)->values(), 'tot' => $tot, 'bad' => $bad, 'page' => $page, 'pages' => $pages, 'qs' => $r->query('q'), 'tc' => $this->tcol($t)]);
  }

  public function save(Request $r, string $t, int $id) {
    $tb = $this->tbl($t); $o = DB::table($tb)->find($id); abort_if(!$o, 404);
    $row = [$this->tcol($t) => mb_substr(trim((string)$r->input('title')), 0, 200) ?: null, 'meta_description' => mb_substr(trim((string)$r->input('desc')), 0, 500) ?: null,
      'focus_keyword' => mb_substr(trim((string)$r->input('kw')), 0, 200) ?: null, 'canonical' => mb_substr(trim((string)$r->input('canonical')), 0, 500) ?: null,
      'og_image' => mb_substr(trim((string)$r->input('og')), 0, 500) ?: null, 'noindex' => $r->boolean('noindex'), 'updated_at' => now()];
    if ($t === 'product') { $row['meta_keywords'] = mb_substr(trim((string)$r->input('kws')), 0, 500) ?: null; $row['image_alt'] = mb_substr(trim((string)$r->input('alt')), 0, 300) ?: null; }
    else $row['meta_keywords'] = mb_substr(trim((string)$r->input('kws')), 0, 500) ?: null;
    DB::table($tb)->where('id', $id)->update($row); $this->clear(); S::audit('item.save', $t . '#' . $id . ' ' . ($o->name ?? ''), null, $row);
    return back()->with('ok', 'Saved: ' . ($o->name ?? ''));
  }

  /** Fill ONLY empty SEO fields (never overwrites editor text). Keyword-rich Bangla + English. */
  public function autofill(Request $r, string $t) {
    $tb = $this->tbl($t); $n = 0;
    if ($t === 'product') { $n = Seo::fillAll(false)['updated']; }
    else {
      foreach (DB::table($tb)->get() as $x) {
        $nm = trim((string)$x->name); $upd = [];
        $title = $nm . ' দাম বাংলাদেশে | অরিজিনাল ' . $nm . ' | ' . S::site();
        $desc = $nm . ' অরিজিনাল ইমপোর্টেড পণ্য ডিউটি ফ্রি দামে। ' . $nm . ' price in Bangladesh, ক্যাশ অন ডেলিভারি ও সারা দেশে হোম ডেলিভারি — ' . S::site() . '।';
        $kws = implode(', ', array_unique([$nm, $nm . ' দাম', $nm . ' price in bangladesh', 'buy ' . $nm . ' online bd', $nm . ' original', $nm . ' duty free', 'ডিউটি ফ্রি', 'dutyfree shop bd']));
        $tcol = $this->tcol($t);
        if (trim((string)($x->$tcol ?? '')) === '') $upd[$tcol] = mb_substr($title, 0, 120);
        if (trim((string)($x->meta_description ?? '')) === '') $upd['meta_description'] = mb_substr($desc, 0, 158);
        if (trim((string)($x->focus_keyword ?? '')) === '') $upd['focus_keyword'] = mb_substr($nm . ' দাম', 0, 120);
        if (trim((string)($x->meta_keywords ?? '')) === '') $upd['meta_keywords'] = mb_substr($kws, 0, 490);
        if ($upd) { DB::table($tb)->where('id', $x->id)->update($upd); $n++; }
      }
    }
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
    $rows = DB::table($tb)->orderBy('id')->get()->map(function ($x) use ($t, $tc) { return [$x->id, $x->name, $x->$tc, $x->meta_description, $x->focus_keyword, $x->meta_keywords ?? '', $x->canonical, !empty($x->noindex) ? 'noindex' : 'index', $t === 'product' ? ($x->image_alt ?? '') : '', implode('; ', $this->status($x, $t))]; });
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
