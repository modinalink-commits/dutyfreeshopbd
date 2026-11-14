<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/** Storefront forms carry no CSRF token (pages stay cacheable), so a browser POST must come from this same site. Server-to-server webhooks send no Origin header and pass untouched. Also adds a Permissions-Policy. */
class StorefrontGuard {
  /** Report-only for now: violations are logged via /csp-report so the policy can be tightened without breaking GTM / Pixel / chat. */
  const CSP = "default-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net https://static.cloudflareinsights.com https://*.google.com https://*.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self' https:; frame-src https:; media-src 'self' blob: https:; object-src 'none'; base-uri 'self'; form-action 'self' https:; report-uri /csp-report";
  public function handle(Request $r, Closure $next) {
    if (!$r->isMethodSafe() && !$r->is('webhooks/*', 'api/*')) {
      $src = (string)($r->headers->get('Origin') ?: $r->headers->get('Referer'));
      if ($src !== '' && $src !== 'null') {
        $h = strtolower((string)parse_url($src, PHP_URL_HOST)); $me = strtolower($r->getHost());
        $n = fn($x) => preg_replace('/^www\./', '', $x);
        if ($h !== '' && $n($h) !== $n($me)) return response('Forbidden', 403);
      }
    }
    /* Stored copies of public pages. Facebook / Meta crawlers get a 5-minute copy, anonymous visitors (no account / referral cookie) a 90-second copy, so a campaign rush does not run the whole page for every visitor. Only page routes, only GET, only 200 HTML. */
    $pgKey = null; $pgTtl = 0; $resp = null;
    try {
      if ($r->isMethod('GET') && !$r->ajax() && $r->route() && in_array($r->route()->uri(), ['/', 'p/{key}', '{cat}/{sub}', '{cat}/{sub}/{child}/{slug}', '{cat}/{sub}/{slug}', '{slug}', 'brand/{slug}', 'blog', 'blog/{slug}'], true)) {
        $qs = $r->query(); foreach (array_keys($qs) as $k) if (preg_match('/^(fbclid|utm_.*|_gl|gclid|media_type|ttclid|msclkid)$/i', (string)$k)) unset($qs[$k]); ksort($qs);
        $isFb = (bool)preg_match('/facebookexternalhit|facebot|meta-externalagent|meta-externalfetcher/i', (string)$r->userAgent());
        $skip = (bool)array_intersect_key($qs, array_flip(['preview', 'q', 's', 'search', 'token', 'code', 'coupon', 'ref', 'key', '_perf', 'debug', 'nocache']));
        $anon = !$r->cookies->has('dfa') && !$r->cookies->has('dfref');
        if (!$skip && ($isFb || $anon)) { $pgTtl = $isFb ? 300 : 90; $pgKey = 'pgc.' . ($isFb ? 'f.' : 'a.') . md5(strtolower($r->getHost()) . '|' . $r->getPathInfo() . '|' . http_build_query($qs)); }
      }
      if ($pgKey) { $hit = \Illuminate\Support\Facades\Cache::get($pgKey); if (is_array($hit) && isset($hit['b'])) $resp = response($hit['b'], 200, ['Content-Type' => $hit['t'], 'X-Page-Cache' => 'HIT']); }
    } catch (\Throwable $e) { $pgKey = null; $resp = null; }
    if (!$resp) {
      $resp = $next($r);
      if ($pgKey && method_exists($resp, 'getStatusCode') && $resp->getStatusCode() === 200 && !$resp instanceof \Symfony\Component\HttpFoundation\StreamedResponse && !$resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
        try { $bd = (string)$resp->getContent(); $ty = (string)$resp->headers->get('Content-Type', 'text/html; charset=UTF-8'); if ($bd !== '' && strlen($bd) < 2000000 && stripos($ty, 'html') !== false) { \Illuminate\Support\Facades\Cache::put($pgKey, ['b' => $bd, 't' => $ty], $pgTtl); $resp->headers->set('X-Page-Cache', 'MISS'); } } catch (\Throwable $e) {}
      }
    }
    if (method_exists($resp, 'header') || isset($resp->headers)) $resp->headers->set('Permissions-Policy', 'camera='.($r->is('admin/orders', 'admin/orders/*', 'admin/packing-scan', 'admin/packing-scan/*') ? '(self)' : '()').', microphone=(self), geolocation=(self), payment=(self), usb=()'); /* camera only where staff scan parcel labels with a phone (Packaging Scan) */
    if ($r->isMethodSafe() && !$r->is('webhooks/*', 'api/*', 'p/*') && mt_rand(1, 20) === 1) $resp->headers->set('Content-Security-Policy-Report-Only', self::CSP); /* sampled 1 in 20 so visitors' browsers do not post a report on every page */
    return $resp;
  }
}
