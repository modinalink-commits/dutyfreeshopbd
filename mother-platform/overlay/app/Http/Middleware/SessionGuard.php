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
    $res = $next($r);
    // With the cookie driver the session data is a second cookie named after the session id. Every time the id changes (admin sign-in,
    // marine or supplier portal sign-in or sign-out) the old one stays in the browser until it expires, each a copy of the whole session.
    // A few sign-ins in one browser add up past what nginx accepts and that browser gets "400 Request Header Or Cookie Too Large" on
    // every page (owner's browser, 26 Nov 2026, after signing in to the marine demo accounts). Left-over ones are expired here.
    try {
      if (config('session.driver') === 'cookie' && $r->hasSession() && isset($res->headers)) {
        $cur = (string) $r->session()->getId(); $n = 0;
        foreach ($r->cookies->keys() as $k) { $k = (string) $k;
          if ($k === $cur || !preg_match('/^[A-Za-z0-9]{40}$/', $k)) continue;
          $res->headers->setCookie(cookie()->forget($k)); if (++$n >= 12) break; }
      }
    } catch (\Throwable $e) { /* never break a response because of this */ }
    return $res;
  }
}
