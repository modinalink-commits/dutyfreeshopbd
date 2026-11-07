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
    /* Facebook / Meta link-preview crawlers hit the same ad URL hundreds of times; give them a 5-minute stored copy instead of running the whole page each time. */
    $fbKey = null;
    if ($r->isMethod('GET') && !$r->is('admin', 'admin/*', 'account*', 'checkout*', 'cart*', 'pay/*', 'webhooks/*', 'api/*') && preg_match('/facebookexternalhit|facebot|meta-externalagent|meta-externalfetcher/i', (string)$r->userAgent())) {
      $qs = $r->query(); foreach (array_keys($qs) as $k) if (preg_match('/^(fbclid|utm_.*|_gl|gclid|media_type)$/i', (string)$k)) unset($qs[$k]); ksort($qs);
      $fbKey = 'fbc.' . md5(strtolower($r->getHost()) . '|' . $r->getPathInfo() . '|' . http_build_query($qs));
      try { $hit = \Illuminate\Support\Facades\Cache::get($fbKey); if (is_array($hit) && isset($hit['b'])) return response($hit['b'], 200, ['Content-Type' => $hit['t'], 'X-FB-Cache' => 'HIT', 'Cache-Control' => 'public, max-age=60']); } catch (\Throwable $e) {}
    }
    $resp = $next($r);
    if ($fbKey && method_exists($resp, 'getStatusCode') && $resp->getStatusCode() === 200 && !$resp instanceof \Symfony\Component\HttpFoundation\StreamedResponse && !$resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
      try { $b = (string)$resp->getContent(); $t = (string)$resp->headers->get('Content-Type', 'text/html; charset=UTF-8'); if ($b !== '' && strlen($b) < 2000000 && stripos($t, 'html') !== false) \Illuminate\Support\Facades\Cache::put($fbKey, ['b' => $b, 't' => $t], 300); } catch (\Throwable $e) {}
    }
    if (method_exists($resp, 'header') || isset($resp->headers)) $resp->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(self), payment=(self), usb=()');
    if ($r->isMethodSafe() && !$r->is('webhooks/*', 'api/*', 'p/*') && mt_rand(1, 20) === 1) $resp->headers->set('Content-Security-Policy-Report-Only', self::CSP); /* sampled 1 in 20 so visitors' browsers do not post a report on every page */
    return $resp;
  }
}
