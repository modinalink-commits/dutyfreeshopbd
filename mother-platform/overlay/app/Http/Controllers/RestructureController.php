<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\Slugs;
use App\Support\SeoMgr;

/** Moves products between main categories/subs safely: backup log, unique URL slug, 301 from the old product URL, revert by batch. */
class RestructureController extends Controller {
  private function cat(string $slug) { return DB::table('categories')->where('slug', $slug)->first(); }
  private function uniq(int $catId, string $base, string $sub, int $self): string {
    $ss = Slugs::subSlug($sub); $s = $base; $i = 2;
    while (DB::table('products')->where('category_id', $catId)->where('id', '!=', $self)->whereRaw(Slugs::subSql() . ' = ?', [$ss])->where('path_slug', $s)->exists()) $s = $base . '-' . $i++;
    return $s;
  }
  private function bust(): void {
    foreach (['store.cats', 'store.catslugs', 'store.subs', 'store.home11', 'store.sitemap5', 'store.sitemap15', 'store.catslider4'] as $k) Cache::forget($k);
    StoreController::bust();
  }
  /** POST from (cat slug), to (cat slug), fs (old sub, optional; '*' = any), ts (new sub; empty = keep old sub), re (case-insensitive name regex, optional), ids (optional list), commit (1 = do it), limit */
  public function move(Request $r) {
    @set_time_limit(300);
    $from = $this->cat((string)$r->input('from')); $to = $this->cat((string)$r->input('to')); abort_unless($from && $to, 404);
    $q = DB::table('products')->where('category_id', $from->id);
    $fs = $r->input('fs'); if ($fs !== null && $fs !== '*') $q->where('sub', (string)$fs);
    if ($re = (string)$r->input('re')) $q->whereRaw('name ~* ?', [$re]);
    if ($nre = (string)$r->input('nre')) $q->whereRaw('name !~* ?', [$nre]);
    if ($dre = (string)$r->input('dre')) $q->whereRaw("(name || ' ' || coalesce(description,'') || ' ' || coalesce(meta_keywords,'')) ~* ?", [$dre]); // name + description + keywords
    if ($ndre = (string)$r->input('ndre')) $q->whereRaw("(name || ' ' || coalesce(description,'') || ' ' || coalesce(meta_keywords,'')) !~* ?", [$ndre]);
    if ($r->filled('pub')) $q->where('published', $r->boolean('pub'));
    if ($ids = $r->input('ids')) $q->whereIn('id', array_map('intval', (array)$ids));
    $total = (clone $q)->count(); $ts = trim((string)$r->input('ts'));
    if (!$r->boolean('commit')) return response()->json(['dry' => true, 'matches' => $total, 'sample' => (clone $q)->limit(min(60, (int)($r->input('sn') ?: 15)))->pluck('name')]);
    $batch = 'b' . now()->format('mdHis') . substr(md5(random_bytes(4)), 0, 4); $n = 0;
    $errs = [];
    foreach ($q->orderBy('id')->limit((int)($r->input('limit') ?: 1500))->get() as $p) { try {
      $old = StoreController::url($p); $sub = $ts !== '' ? $ts : (string)$p->sub;
      $base = Slugs::slug((string)($p->path_slug ?: ($p->slug ?: $p->name)), 80); if ($base === '') $base = 'product';
      $slug = $this->uniq((int)$to->id, $base, $sub, (int)$p->id);
      DB::table('product_move_log')->insert(['batch' => $batch, 'product_id' => $p->id, 'old_category_id' => $p->category_id, 'old_sub' => $p->sub, 'old_sub_sub' => $p->sub_sub, 'old_path_slug' => $p->path_slug, 'old_url' => $old, 'new_category_id' => $to->id, 'new_sub' => $sub, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('products')->where('id', $p->id)->update(['category_id' => $to->id, 'sub' => $sub !== '' ? $sub : null, 'sub_sub' => null, 'path_slug' => $slug, 'updated_at' => now()]);
      $np = DB::table('products')->where('id', $p->id)->first(); $new = StoreController::url($np);
      if (str_starts_with($old, '/') && !str_starts_with($old, '/p/') && $old !== $new) SeoMgr::addRedirect($old, $new, 301, 'restructure');
      $n++;
    } catch (\Throwable $e) { $errs[] = $p->id . ': ' . substr($e->getMessage(), 0, 160); } }
    $this->bust();
    return response()->json(['ok' => true, 'moved' => $n, 'left' => max(0, $total - $n), 'batch' => $batch, 'errors' => array_slice($errs, 0, 5)]);
  }
  /** POST cat (slug), fs (sub, '*' = all): re-sort with the same sub rules the importers use (WatchImportController::subFor). commit=1 to apply. */
  public function resub(Request $r) {
    @set_time_limit(300); $c = $this->cat((string)$r->input('cat')); abort_unless($c, 404); $fs = (string)($r->input('fs') ?: '*');
    $q = DB::table('products')->where('category_id', $c->id); if ($fs !== '*') $q->where('sub', $fs);
    $groups = []; foreach ($q->orderBy('id')->limit((int)($r->input('limit') ?: 1500))->get(['id', 'name', 'sub']) as $p) {
      $ns = WatchImportController::subFor((string)$p->name, null, $c->slug); if ($ns !== '' && $ns !== (string)$p->sub) $groups[$ns][] = (int)$p->id;
    }
    if (!$r->boolean('commit')) return response()->json(['dry' => true, 'groups' => array_map(fn($g) => count($g), $groups)]);
    $done = [];
    foreach ($groups as $ns => $ids) { $sub = new Request(['from' => $c->slug, 'to' => $c->slug, 'fs' => '*', 'ts' => $ns, 'ids' => $ids, 'commit' => 1, 'limit' => 2000]); $res = $this->move($sub)->getData(true); $done[$ns] = $res['moved'] ?? 0; }
    return response()->json(['ok' => true, 'moved' => $done]);
  }
  /** POST batch: put every product of the batch back where it was (old URL restored, redirect removed). */
  public function revert(Request $r) {
    $b = (string)$r->input('batch'); $n = 0;
    foreach (DB::table('product_move_log')->where('batch', $b)->get() as $l) {
      $cur = DB::table('products')->where('id', $l->product_id)->first(); if (!$cur) continue;
      DB::table('products')->where('id', $l->product_id)->update(['category_id' => $l->old_category_id, 'sub' => $l->old_sub, 'sub_sub' => $l->old_sub_sub, 'path_slug' => $l->old_path_slug, 'updated_at' => now()]);
      if ($l->old_url) DB::table('seo_redirects')->where('from_path', rtrim(strtolower($l->old_url), '/'))->where('source', 'restructure')->delete();
      $n++;
    }
    $this->bust(); return response()->json(['ok' => true, 'reverted' => $n]);
  }
  /** GET n, offset: old URL / new URL pairs of moved products and whether the 301 row exists (the browser then fetches both). */
  public function verify(Request $r) {
    $rows = DB::table('product_move_log as l')->join('products as p', 'p.id', '=', 'l.product_id')->orderBy('l.id')->offset((int)$r->query('offset', 0))->limit(min(300, (int)$r->query('n', 20)))->get(['l.old_url', 'l.product_id', 'p.category_id', 'p.sub', 'p.sub_sub', 'p.path_slug', 'p.ext_id', 'p.name', 'p.published']);
    $out = [];
    foreach ($rows as $x) { $new = StoreController::url($x); $f = rtrim(strtolower((string)$x->old_url), '/'); $out[] = ['id' => $x->product_id, 'old' => $x->old_url, 'new' => $new, 'pub' => (bool)$x->published, 'redir' => $f === rtrim(strtolower($new), '/') ? 'same' : (DB::table('seo_redirects')->where('from_path', $f)->where('active', true)->exists() ? 'yes' : 'NO')]; }
    return response()->json(['total' => DB::table('product_move_log')->count(), 'rows' => $out]);
  }
  /** GET: product counts per main category and sub (the result table). */
  public function counts() {
    $o = [];
    foreach (DB::table('categories')->whereNull('parent_id')->orderBy('position')->get(['id', 'name', 'slug']) as $c) {
      $subs = DB::table('products')->where('category_id', $c->id)->where('published', true)->selectRaw("coalesce(nullif(sub,''),'(no sub)') as sub, count(*) n")->groupBy('sub')->orderByDesc('n')->get();
      $o[] = ['cat' => $c->name, 'slug' => $c->slug, 'published' => (int)$subs->sum('n'), 'all' => DB::table('products')->where('category_id', $c->id)->count(), 'subs' => $subs];
    }
    return response()->json($o);
  }
}
