<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pages & SEO helpers: settings, scoring, redirects, scheduling, sitemap stats, robots.txt. */
class SeoMgr
{
  const TYPES = ['static' => 'Static Page', 'policy' => 'Policy Page', 'landing' => 'Landing Page', 'about' => 'About Page', 'contact' => 'Contact Page', 'faq' => 'FAQ Page', 'promo' => 'Promotional Page', 'custom' => 'Custom Page'];
  const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'private' => 'Private', 'inactive' => 'Inactive'];
  const TPL = ['product' => '{product_name} | Buy Online at {site_name}', 'category' => '{category_name} Products | {site_name}', 'brand' => '{brand_name} Products | {site_name}', 'blog' => '{page_title} | {site_name}', 'page' => '{page_title} | {site_name}'];
  const ROBOTS = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /checkout\nDisallow: /account\nDisallow: /search\nDisallow: /track";

  public static function get(string $k, string $def = ''): string {
    $all = Cache::remember('seo.settings', 120, fn() => DB::table('settings')->where('key', 'like', 'seo\_%')->pluck('value', 'key')->all());
    return isset($all[$k]) && $all[$k] !== null ? (string)$all[$k] : $def;
  }
  public static function put(string $k, ?string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => (string)$v, 'updated_at' => now(), 'created_at' => now()]); Cache::forget('seo.settings'); }
  public static function site(): string { return self::get('seo_site_name', 'DutyFree BD'); }

  public static function slug(string $s): string {
    $s = strtolower(trim($s)); $s = preg_replace('/[^a-z0-9\-\s_]+/', '', $s); $s = preg_replace('/[\s_]+/', '-', $s); return trim(preg_replace('/-+/', '-', $s), '-');
  }
  public static function uniqueSlug(string $slug, ?int $ignore = null): string {
    $base = $slug ?: 'page'; $s = $base; $i = 2;
    $reserved = ['admin', 'blog', 'search', 'checkout', 'account', 'track', 'cart', 'p', 'sitemap', 'robots', 'api', 'login'];
    while (in_array($s, $reserved, true) || DB::table('pages')->where('slug', $s)->when($ignore, fn($q) => $q->where('id', '<>', $ignore))->exists() || ((Schema::hasTable('categories')) && DB::table('categories')->where('slug', $s)->exists())) $s = $base . '-' . $i++;
    return $s;
  }
  public static function audit(string $act, string $subject, $prev = null, $new = null): void { Procure::audit('seo.' . $act, $subject, $prev, $new); }

  public static function excerpt(?string $html, int $n = 155): string {
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strlen($t) > $n ? rtrim(mb_substr($t, 0, $n - 1)) . '…' : $t;
  }
  public static function effTitle(object $pg): string { $t = trim((string)($pg->meta_title ?? '')); return $t !== '' ? $t : trim($pg->title . ' | ' . self::site()); }
  public static function effDesc(object $pg): string { $d = trim((string)($pg->meta_description ?? '')); if ($d !== '') return $d; $d = trim((string)($pg->short_desc ?? '')); return $d !== '' ? $d : self::excerpt($pg->body ?? ''); }

  /** Simple 0-100 score: [points, label, [issues]] */
  public static function score(object $pg): array {
    $pts = 0; $iss = []; $t = trim((string)($pg->meta_title ?? '')); $d = trim((string)($pg->meta_description ?? '')); $fk = mb_strtolower(trim((string)($pg->focus_keyword ?? ''))); $body = (string)($pg->body ?? '');
    $tl = mb_strlen($t); if ($tl >= 30 && $tl <= 65) $pts += 15; elseif ($tl > 0) { $pts += 7; $iss[] = 'SEO title length should be 50–60 characters'; } else $iss[] = 'SEO title missing';
    $dl = mb_strlen($d); if ($dl >= 110 && $dl <= 165) $pts += 15; elseif ($dl > 0) { $pts += 7; $iss[] = 'Meta description should be 140–160 characters'; } else $iss[] = 'Meta description missing';
    if ($fk !== '') { $pts += 10; if (str_contains(mb_strtolower($t . ' ' . $pg->title), $fk)) $pts += 10; else $iss[] = 'Focus keyword not in title'; } else $iss[] = 'Focus keyword missing';
    $imgs = preg_match_all('/<img\b[^>]*>/i', $body, $m); $noalt = 0; foreach ($m[0] as $im) if (!preg_match('/\balt\s*=\s*"[^"]+"/i', $im)) $noalt++;
    if ($noalt === 0) $pts += 10; else $iss[] = $noalt . ' image(s) missing alt text';
    if (preg_match('/<h2\b/i', $body)) $pts += 10; else $iss[] = 'No H2 headings in content';
    if (($pg->robots_index ?? 1)) $pts += 10; else $iss[] = 'Page is set to noindex';
    $sl = (string)$pg->slug; if (strlen($sl) <= 60 && preg_match('/^[a-z0-9\-]+$/', $sl)) $pts += 10; else $iss[] = 'URL is long or has special characters';
    $pts += trim((string)($pg->canonical ?? '')) === '' || str_starts_with((string)$pg->canonical, 'http') ? 10 : 0;
    $lab = $pts >= 85 ? 'Excellent' : ($pts >= 65 ? 'Good' : ($pts >= 40 ? 'Needs Improvement' : 'Poor'));
    return [$pts, $lab, $iss];
  }

  public static function previewSig(object $pg): string { return $pg->id . '.' . substr(hash_hmac('sha256', $pg->id . '|' . $pg->updated_at, (string)config('app.key')), 0, 20); }
  public static function previewOk(?string $tok, object $pg): bool { return $tok && hash_equals(self::previewSig($pg), $tok); }

  /** Redirect lookup for a request path. */
  public static function redirect(string $path): ?object {
    try {
      if (!Schema::hasTable('seo_redirects')) return null;
      $p = '/' . trim(strtolower(rawurldecode($path)), '/'); $r = DB::table('seo_redirects')->where('active', true)->whereIn('from_path', [$p, $p . '/'])->first();
      if ($r) DB::table('seo_redirects')->where('id', $r->id)->increment('hits');
      return $r;
    } catch (\Throwable $e) { return null; }
  }
  public static function addRedirect(string $from, string $to, int $type = 301, string $src = 'manual'): bool {
    $f = '/' . trim(strtolower($from), '/'); $to = trim($to); if ($f === '/' || $to === '' || rtrim($to, '/') === $f || $f === '/' . trim(strtolower($to), '/')) return false;
    $ex = DB::table('seo_redirects')->where('from_path', $f)->first();
    $d = ['to_url' => $to, 'type' => $type === 302 ? 302 : 301, 'active' => true, 'source' => $src, 'created_by' => auth()->user()->name ?? 'system', 'updated_at' => now()];
    if ($ex) DB::table('seo_redirects')->where('id', $ex->id)->update($d); else DB::table('seo_redirects')->insert($d + ['from_path' => $f, 'created_at' => now()]);
    // collapse chains: anything pointing at $f now points at $to
    DB::table('seo_redirects')->where('to_url', $f)->orWhere('to_url', $f . '/')->update(['to_url' => $to]);
    return true;
  }

  /** Publish / unpublish scheduled pages (called by the background worker). */
  public static function tick(): void {
    try {
      if (!Schema::hasColumn('pages', 'status')) return;
      $n = DB::table('pages')->where('status', 'scheduled')->whereNotNull('publish_at')->where('publish_at', '<=', now())->update(['active' => true]);
      DB::table('pages')->where('status', 'scheduled')->whereNotNull('unpublish_at')->where('unpublish_at', '<=', now())->update(['active' => false, 'status' => 'inactive']);
      if ($n) { Cache::forget('store.sitemap5'); self::audit('page.scheduled_publish', $n . ' page(s)'); }
    } catch (\Throwable $e) {}
  }
  /** is this scheduled page live right now */
  public static function liveFlag(string $status, $publishAt, $unpublishAt): bool {
    if ($status === 'published') return true;
    if ($status === 'scheduled') return $publishAt && strtotime($publishAt . ' UTC') <= time() && (!$unpublishAt || strtotime($unpublishAt . ' UTC') > time());
    return false;
  }

  public static function sitemapStats(bool $rebuild = false): array {
    if ($rebuild) { try { \App\Http\Controllers\StoreController::bust(); app(\App\Http\Controllers\StoreController::class)->sitemap(); } catch (\Throwable $e) {} self::put('seo_sitemap_at', now()->toDateTimeString()); }
    $n = (int)Cache::get('store.sitemap.total', 0);
    return ['at' => self::get('seo_sitemap_at'), 'total' => $n, 'ok' => true];
  }
  public static function robotsText(): string {
    $t = trim(self::get('seo_robots', self::ROBOTS)); if (self::get('seo_global_index', '1') === '0') $t = "User-agent: *\nDisallow: /";
    if (!preg_match('/^\s*sitemap:/im', $t)) $t .= "\nSitemap: " . \App\Support\Domain::abs('/sitemap.xml');
    return $t . "\n";
  }
  public static function apply(string $tpl, array $v): string {
    $m = ['{site_name}' => self::site(), '{city}' => self::get('seo_city', 'Chattogram')] + $v; return trim(preg_replace('/\s+/', ' ', strtr($tpl, $m)));
  }
}
