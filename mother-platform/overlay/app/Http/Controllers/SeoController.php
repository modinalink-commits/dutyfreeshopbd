<?php
namespace App\Http\Controllers;

use App\Support\SeoMgr as S;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Website & Content -> Pages & SEO */
class SeoController extends Controller
{
  private function clear(): void { Cache::forget('store.sitemap5'); }
  private function rows() {
    return DB::table('pages')->orderByDesc('updated_at')->get()->map(function ($p) { [$sc, $lab, $iss] = S::score($p); $p->score = $sc; $p->label = $lab; $p->issues = $iss; return $p; });
  }

  public function index(Request $r) {
    $tab = in_array($r->query('tab'), ['pages', 'audit', 'redirects', 'settings']) ? $r->query('tab') : 'pages';
    $d = ['tab' => $tab, 'q' => trim((string)$r->query('q')), 'fs' => (string)$r->query('status'), 'ft' => (string)$r->query('type'), 'fx' => (string)$r->query('f')];
    $all = $this->rows(); $d['kpi'] = ['total' => $all->count(), 'pub' => $all->where('status', 'published')->count(), 'draft' => $all->where('status', 'draft')->count(), 'low' => $all->where('score', '<', 65)->count()];
    $list = $all;
    if ($d['q'] !== '') $list = $list->filter(fn($p) => stripos($p->title . ' ' . $p->slug . ' ' . $p->meta_title, $d['q']) !== false);
    if ($d['fs'] !== '') $list = $list->where('status', $d['fs']);
    if ($d['ft'] !== '') $list = $list->where('page_type', $d['ft']);
    if ($tab === 'audit') {
      $f = $d['fx']; $list = $all->filter(function ($p) use ($f) {
        $b = (string)$p->body; $noalt = false; if (preg_match_all('/<img\b[^>]*>/i', $b, $m)) foreach ($m[0] as $im) if (!preg_match('/\balt\s*=\s*"[^"]+"/i', $im)) $noalt = true;
        return match ($f) { 'title' => trim((string)$p->meta_title) === '', 'desc' => trim((string)$p->meta_description) === '', 'kw' => trim((string)$p->focus_keyword) === '', 'alt' => $noalt, 'noindex' => !$p->robots_index, 'low' => $p->score < 65, default => count($p->issues) > 0 };
      });
      $d['prod'] = ['no_title' => DB::table('products')->where('published', true)->where(fn($q) => $q->whereNull('meta_title')->orWhere('meta_title', ''))->count() ?? 0];
    }
    $d['list'] = $list->values();
    $d['redirects'] = $tab === 'redirects' ? DB::table('seo_redirects')->orderByDesc('id')->limit(300)->get() : collect();
    $d['sm'] = S::sitemapStats(); $d['robots'] = S::get('seo_robots', S::ROBOTS);
    $d['set'] = ['site' => S::site(), 'city' => S::get('seo_city', 'Chattogram'), 'index' => S::get('seo_global_index', '1'), 'tpl' => collect(S::TPL)->map(fn($v, $k) => S::get('seo_tpl_' . $k, $v))->all(), 'og' => S::get('seo_default_og'),
      'ver' => ['gsc_verify' => (string)DB::table('settings')->where('key', 'gsc_verify')->value('value'), 'bing_verify' => (string)DB::table('settings')->where('key', 'bing_verify')->value('value'), 'fb_verify' => (string)DB::table('settings')->where('key', 'fb_verify')->value('value')]];
    return view('seo', $d);
  }

  public function edit(Request $r, $id = null) {
    $pg = $id ? DB::table('pages')->find($id) : (object)['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'page_type' => 'static', 'status' => 'draft', 'robots_index' => 1, 'robots_follow' => 1, 'updated_at' => now()];
    abort_if(!$pg, 404);
    $rev = $id ? DB::table('page_revisions')->where('page_id', $id)->orderByDesc('id')->limit(15)->get() : collect();
    return view('seo_edit', ['pg' => $pg, 'rev' => $rev, 'site' => S::site(), 'sc' => $id ? S::score($pg) : [0, 'Poor', []], 'prev' => $id ? S::previewSig($pg) : null]);
  }

  public function save(Request $r, $id = null) {
    $v = $r->validate(['title' => 'required|string|max:160', 'slug' => 'nullable|string|max:120', 'body' => 'nullable|string', 'page_type' => 'required|in:' . implode(',', array_keys(S::TYPES)), 'status' => 'required|in:' . implode(',', array_keys(S::STATUSES)),
      'short_desc' => 'nullable|string|max:500', 'featured_image' => 'nullable|string|max:500', 'meta_title' => 'nullable|string|max:200', 'meta_description' => 'nullable|string|max:400', 'keywords' => 'nullable|string|max:500', 'focus_keyword' => 'nullable|string|max:120',
      'canonical' => 'nullable|url|max:500', 'og_title' => 'nullable|string|max:200', 'og_description' => 'nullable|string|max:400', 'og_image' => 'nullable|string|max:500', 'twitter_image' => 'nullable|string|max:500', 'publish_at' => 'nullable|date', 'unpublish_at' => 'nullable|date']);
    $old = $id ? DB::table('pages')->find($id) : null; abort_if($id && !$old, 404);
    $slug = S::slug($v['slug'] ?: $v['title']); $slug = S::uniqueSlug($slug ?: 'page', $id ? (int)$id : null);
    $pub = fn($x) => $x ? \Carbon\Carbon::parse($x, 'Asia/Dhaka')->utc()->toDateTimeString() : null;
    $d = ['title' => $v['title'], 'slug' => $slug, 'body' => $v['body'] ?? '', 'page_type' => $v['page_type'], 'status' => $v['status'], 'short_desc' => $v['short_desc'] ?? null, 'featured_image' => $v['featured_image'] ?? null,
      'meta_title' => $v['meta_title'] ?? null, 'meta_description' => $v['meta_description'] ?? null, 'keywords' => $v['keywords'] ?? null, 'focus_keyword' => $v['focus_keyword'] ?? null, 'canonical' => $v['canonical'] ?? null,
      'robots_index' => $r->boolean('robots_index'), 'robots_follow' => $r->boolean('robots_follow'), 'og_title' => $v['og_title'] ?? null, 'og_description' => $v['og_description'] ?? null, 'og_image' => $v['og_image'] ?? null, 'twitter_image' => $v['twitter_image'] ?? null,
      'publish_at' => $pub($v['publish_at'] ?? null), 'unpublish_at' => $pub($v['unpublish_at'] ?? null), 'updated_by' => auth()->user()->name ?? 'admin', 'updated_at' => now()];
    // auto defaults
    if (trim((string)$d['meta_title']) === '') $d['meta_title'] = S::apply(S::get('seo_tpl_page', S::TPL['page']), ['{page_title}' => $d['title']]);
    if (trim((string)$d['meta_description']) === '') $d['meta_description'] = trim((string)$d['short_desc']) !== '' ? S::excerpt($d['short_desc']) : S::excerpt($d['body']);
    $d['active'] = S::liveFlag($d['status'], $d['publish_at'], $d['unpublish_at']);
    if ($old) {
      DB::table('page_revisions')->insert(['page_id' => $old->id, 'title' => $old->title, 'slug' => $old->slug, 'body' => $old->body, 'meta' => json_encode(['t' => $old->meta_title, 'd' => $old->meta_description]), 'edited_by' => $old->updated_by, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('pages')->where('id', $old->id)->update($d);
      if ($old->slug !== $slug) { S::addRedirect('/' . $old->slug, '/' . $slug . '/', 301, 'auto'); }
      S::audit('page.update', $d['title'], ['slug' => $old->slug, 'status' => $old->status], ['slug' => $slug, 'status' => $d['status']]); $pid = $old->id;
    } else { $d['created_at'] = now(); $pid = DB::table('pages')->insertGetId($d); S::audit('page.create', $d['title'], null, ['slug' => $slug]); }
    $this->clear();
    return redirect('/admin/seo/page/' . $pid)->with('ok', 'Saved' . ($slug !== S::slug($v['slug'] ?: $v['title']) ? ' — slug adjusted to /' . $slug : ''));
  }

  public function restore($id, $rid) {
    $rv = DB::table('page_revisions')->where('page_id', $id)->find($rid); abort_if(!$rv, 404);
    DB::table('pages')->where('id', $id)->update(['title' => $rv->title, 'body' => $rv->body, 'updated_at' => now(), 'updated_by' => auth()->user()->name ?? 'admin']);
    S::audit('page.restore', $rv->title, null, ['revision' => $rid]); $this->clear();
    return redirect('/admin/seo/page/' . $id)->with('ok', 'Revision restored');
  }
  public function duplicate($id) {
    $p = DB::table('pages')->find($id); abort_if(!$p, 404); $a = (array)$p; unset($a['id']);
    $a['title'] .= ' (Copy)'; $a['slug'] = S::uniqueSlug($p->slug . '-copy'); $a['status'] = 'draft'; $a['active'] = false; $a['created_at'] = $a['updated_at'] = now();
    $n = DB::table('pages')->insertGetId($a); S::audit('page.duplicate', $p->title, null, ['new' => $n]);
    return redirect('/admin/seo/page/' . $n)->with('ok', 'Duplicated as draft');
  }
  public function destroy(Request $r, $id) {
    $p = DB::table('pages')->find($id); if ($p) { DB::table('pages')->where('id', $id)->delete(); S::addRedirect('/' . $p->slug, '/', 301, 'auto'); S::audit('page.delete', $p->title); $this->clear(); }
    return redirect('/admin/seo')->with('ok', 'Page deleted (old URL now redirects to the home page)');
  }

  public function bulk(Request $r) {
    $ids = array_map('intval', (array)$r->input('ids', [])); $act = (string)$r->input('act'); if (!$ids || !$act) return back()->with('ok', 'Select pages first');
    if ($r->input('confirm') !== '1') return back()->with('ok', 'Not confirmed');
    $q = DB::table('pages')->whereIn('id', $ids); $n = count($ids);
    if (in_array($act, ['published', 'draft', 'inactive', 'private'], true)) $q->update(['status' => $act, 'active' => $act === 'published', 'updated_at' => now()]);
    elseif ($act === 'noindex') $q->update(['robots_index' => false, 'updated_at' => now()]);
    elseif ($act === 'index') $q->update(['robots_index' => true, 'updated_at' => now()]);
    elseif ($act === 'autofill') foreach ($q->get() as $p) {
      $u = []; if (trim((string)$p->meta_title) === '') $u['meta_title'] = S::apply(S::get('seo_tpl_page', S::TPL['page']), ['{page_title}' => $p->title]);
      if (trim((string)$p->meta_description) === '') $u['meta_description'] = trim((string)$p->short_desc) !== '' ? S::excerpt($p->short_desc) : S::excerpt($p->body);
      if ($u) DB::table('pages')->where('id', $p->id)->update($u + ['updated_at' => now()]);
    } else return back();
    S::audit('bulk.' . $act, $n . ' page(s)', null, $ids); $this->clear();
    return back()->with('ok', 'Done for ' . $n . ' page(s)');
  }

  public function redirectSave(Request $r) {
    $f = trim((string)$r->input('from')); $t = trim((string)$r->input('to')); $ty = (int)$r->input('type', 301);
    if (!str_starts_with($f, '/') || $t === '') return back()->with('ok', 'From must start with / and To is required');
    if (!S::addRedirect($f, $t, $ty)) return back()->with('ok', 'Invalid redirect (same URL or empty)');
    S::audit('redirect.add', $f, null, $t); return back()->with('ok', 'Redirect saved');
  }
  public function redirectAct(Request $r, $id) {
    if ($r->input('do') === 'delete') DB::table('seo_redirects')->where('id', $id)->delete();
    else DB::table('seo_redirects')->where('id', $id)->update(['active' => DB::raw('not active'), 'updated_at' => now()]);
    S::audit('redirect.' . $r->input('do', 'toggle'), '#' . $id); return back()->with('ok', 'Updated');
  }

  public function settings(Request $r) {
    S::put('seo_site_name', trim((string)$r->input('site')) ?: 'DutyFree BD'); S::put('seo_city', trim((string)$r->input('city')));
    S::put('seo_global_index', $r->boolean('index') ? '1' : '0'); S::put('seo_default_og', trim((string)$r->input('og')));
    foreach (array_keys(S::TPL) as $k) S::put('seo_tpl_' . $k, trim((string)$r->input('tpl_' . $k)) ?: S::TPL[$k]);
    $rb = str_replace("\r", '', trim((string)$r->input('robots'))); S::put('seo_robots', mb_substr($rb, 0, 4000));
    foreach (['gsc_verify', 'bing_verify', 'fb_verify'] as $k) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$r->input($k)), 'updated_at' => now(), 'created_at' => now()]);
    Cache::flush(); S::audit('settings', 'SEO settings', null, $r->except(['_token']));
    return redirect('/admin/seo?tab=settings')->with('ok', 'Saved');
  }
  public function sitemap() { S::sitemapStats(true); S::audit('sitemap.rebuild', 'sitemap.xml'); return redirect('/admin/seo?tab=settings')->with('ok', 'Sitemap rebuilt'); }

  public function export() {
    $h = ['Title', 'Slug', 'Type', 'Status', 'SEO Title', 'Meta Description', 'Focus Keyword', 'Indexed', 'Score', 'Updated'];
    $out = fopen('php://temp', 'w+'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, $h);
    foreach ($this->rows() as $p) fputcsv($out, [$p->title, '/' . $p->slug, $p->page_type, $p->status, $p->meta_title, $p->meta_description, $p->focus_keyword, $p->robots_index ? 'yes' : 'no', $p->score, $p->updated_at]);
    rewind($out); return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="pages-seo.csv"']);
  }
}
