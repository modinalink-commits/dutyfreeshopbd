<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/** Storefront forms carry no CSRF token (pages stay cacheable), so a browser POST must come from this same site. Server-to-server webhooks send no Origin header and pass untouched. Also adds a Permissions-Policy. */
class StorefrontGuard {
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
    return $resp;
  }
}
