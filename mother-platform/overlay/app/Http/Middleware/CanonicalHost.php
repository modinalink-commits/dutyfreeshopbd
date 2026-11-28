<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\Domain;

/** Storefront pages opened on a secondary domain (dutyfreeshopbd.com, dutyfreebd.shop ...) still tell search engines the main domain is the real address: canonical, og:url, og:image and structured data point to www.dutyfreebd.com.
 *  The visitor stays on the domain they opened; only those head tags change. Admin, API, non-HTML and any error are left alone. */
class CanonicalHost {
  public function handle(Request $r, Closure $next) {
    // Google was crawling store pages on mail.dutyfreebd.com (not a real storefront address; many returned 5xx) -> send them to the same path on the main domain.
    try { if ($r->isMethod('GET') && strtolower($r->getHost()) === 'mail.' . Domain::HOST && !$r->is('admin', 'admin/*', 'api/*')) return redirect()->to(Domain::main() . $r->getRequestUri(), 301); } catch (\Throwable $e) {}
    try { if ($g = \App\Support\ChannelScope::gate($r)) return $g; } catch (\Throwable $e) {} // sales channel that is not Live: private
    $resp = $next($r);
    try { if (\App\Support\ChannelScope::cur()) { if ($ck = \App\Support\ChannelScope::pvCookie()) $resp->headers->setCookie($ck); if (\App\Support\ChannelScope::cur()->status !== 'live') $resp->headers->set('X-Robots-Tag', 'noindex, nofollow'); return $resp; } } catch (\Throwable $e) {}
    try {
      $h = strtolower($r->getHost());
      if ($h === Domain::HOST || \App\Support\CleanSite::on() || !str_contains($h, '.') || !$r->isMethod('GET') || $r->is('admin', 'admin/*', 'api/*') || $resp->getStatusCode() !== 200) return $resp;
      if (stripos((string)$resp->headers->get('Content-Type'), 'text/html') === false || $resp instanceof \Symfony\Component\HttpFoundation\StreamedResponse || $resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) return $resp;
      $html = $resp->getContent(); if (!is_string($html) || $html === '') return $resp;
      $from = ['https://' . $h, 'http://' . $h]; $to = Domain::main();
      $fix = fn($s) => str_replace($from, $to, $s);
      $out = preg_replace_callback('~<link\b[^>]*rel="canonical"[^>]*>|<meta\b[^>]*(?:property="og:(?:url|image)"|name="twitter:image")[^>]*>|<script\b[^>]*application/ld\+json[^>]*>.*?</script>~is', fn($m) => $fix($m[0]), $html);
      if (is_string($out) && $out !== '' && $out !== $html) { $resp->setContent($out); if ($resp->headers->has('Content-Length')) $resp->headers->set('Content-Length', (string)strlen($out)); }
    } catch (\Throwable $e) {}
    return $resp;
  }
}
