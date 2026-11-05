<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/**
 * The session lives in the cookie (SESSION_DRIVER=cookie). If that cookie ever grows large, every response carries a big Set-Cookie
 * header and the nginx proxy in front answers 502 ("upstream sent too big header") for that one browser, on every page.
 * A session cookie above the safe size is dropped before the session starts, so the person gets a fresh small session
 * (they sign in again once) instead of an endless 502.
 */
class SessionGuard {
  const MAX = 2800;
  public function handle(Request $r, Closure $next) {
    try {
      $n = (string) config('session.cookie');
      if ($n !== '' && strlen((string) $r->cookies->get($n, '')) > self::MAX) $r->cookies->remove($n);
    } catch (\Throwable $e) { /* never block a request because of this */ }
    return $next($r);
  }
}
