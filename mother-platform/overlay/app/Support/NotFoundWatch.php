<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 404 watch: counts public addresses that answered "not found" and, when the last part of the address is the exact slug of one current product or category, redirects it there (301) and remembers the redirect. Anything unclear stays a 404 and is listed on Pages & SEO > Redirects. */
class NotFoundWatch {
  /** Scanner noise that is not a broken link of ours. */
  private static function junk(string $p): bool { return (bool)preg_match('#(^|/)(wp-|xmlrpc|\.env|\.git|phpmyadmin|admin/|vendor/|cgi-bin|boaform|actuator|\.well-known)|\.(php|asp|aspx|jsp|env|sql|zip|bak|ini)$#i', $p) || strlen($p) > 300; }

  public static function seen(string $path): void {
    try {
      if (!Schema::hasTable('not_found_log')) return;
      $p = '/' . trim(strtolower(rawurldecode($path)), '/'); if ($p === '/' || self::junk($p)) return;
      $ref = mb_substr((string)request()->headers->get('referer', ''), 0, 300);
      $n = DB::table('not_found_log')->where('path', $p)->update(['hits' => DB::raw('hits + 1'), 'last_seen' => now(), 'last_ref' => $ref ?: DB::raw('last_ref')]);
      if (!$n) DB::table('not_found_log')->insertOrIgnore(['path' => $p, 'hits' => 1, 'last_ref' => $ref ?: null, 'first_seen' => now(), 'last_seen' => now()]);
    } catch (\Throwable $e) {}
  }

  /** Current address for an old one, or null. Only an exact, unique slug match counts. */
  public static function guess(string $path): ?string {
    try {
      $segs = array_values(array_filter(explode('/', trim(strtolower(rawurldecode($path)), '/')), 'strlen')); if (count($segs) < 2 || self::junk($path)) return null;
      $last = end($segs); if (!preg_match('/^[a-z0-9][a-z0-9\-]{2,120}$/', $last)) return null;
      $prod = DB::table('products')->where('published', true)->where('path_slug', $last)->limit(2)->get();
      if ($prod->count() === 1) return \App\Http\Controllers\StoreController::url($prod->first());
      if ($prod->count() > 1) return null;
      if (count($segs) === 2) { $c = DB::table('categories')->where('slug', $last)->limit(2)->get(); if ($c->count() === 1) return '/' . $last . '/'; }
    } catch (\Throwable $e) {}
    return null;
  }

  /** Called from the 404 page. Returns the target address to redirect to, or null. */
  public static function handle(string $path): ?string {
    $to = self::guess($path);
    if ($to) {
      try { if (Schema::hasTable('seo_redirects')) SeoMgr::addRedirect('/' . trim($path, '/'), $to, 301, 'auto-404'); if (Schema::hasTable('not_found_log')) DB::table('not_found_log')->where('path', '/' . trim(strtolower(rawurldecode($path)), '/'))->update(['fixed' => true]); } catch (\Throwable $e) {}
      return $to;
    }
    self::seen($path); return null;
  }

  public static function top(int $n = 40): \Illuminate\Support\Collection {
    try { return Schema::hasTable('not_found_log') ? DB::table('not_found_log')->where('fixed', false)->where('last_seen', '>=', now()->subDays(30))->orderByDesc('hits')->limit($n)->get() : collect(); } catch (\Throwable $e) { return collect(); }
  }
}
