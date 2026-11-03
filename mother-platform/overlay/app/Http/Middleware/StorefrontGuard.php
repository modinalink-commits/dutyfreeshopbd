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
    $resp = $next($r);
    if (method_exists($resp, 'header') || isset($resp->headers)) $resp->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(self), payment=(self), usb=()');
    if ($r->isMethodSafe() && !$r->is('webhooks/*', 'api/*', 'p/*') ) $resp->headers->set('Content-Security-Policy-Report-Only', self::CSP);
    return $resp;
  }
}
