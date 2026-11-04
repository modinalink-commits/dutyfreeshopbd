<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/** Storefront only: shrinks the inline <style> blocks (comments and extra spaces) so every page is lighter to download and parse. Never touches scripts, admin pages or non-HTML responses; any problem returns the original page. */
class MinifyHtml {
  public function handle(Request $r, Closure $next) {
    $resp = $next($r);
    try {
      if (!$r->isMethod('GET') || $r->is('admin', 'admin/*', 'api/*') || $resp->getStatusCode() !== 200) return $resp;
      if (stripos((string)$resp->headers->get('Content-Type'), 'text/html') === false || $resp instanceof \Symfony\Component\HttpFoundation\StreamedResponse || $resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) return $resp;
      $html = $resp->getContent();
      if (!is_string($html) || strlen($html) < 2000 || stripos($html, '<style') === false) return $resp;
      $out = preg_replace_callback('~(<style\b[^>]*>)(.*?)(</style>)~is', fn($m) => $m[1].self::css($m[2]).$m[3], $html);
      if (is_string($out) && $out !== '' && strlen($out) < strlen($html)) { $resp->setContent($out); if ($resp->headers->has('Content-Length')) $resp->headers->set('Content-Length', (string)strlen($out)); }
    } catch (\Throwable $e) {}
    return $resp;
  }
  public static function css(string $c): string {
    if (stripos($c, 'data:') !== false || strpos($c, '@import') !== false) return $c; // keep anything with embedded data untouched
    $o = preg_replace('~/\*.*?\*/~s', '', $c); if ($o === null) return $c;
    $o = preg_replace('~\s+~', ' ', $o); if ($o === null) return $c;
    $o = preg_replace('~\s*([{};,>])\s*~', '$1', $o); if ($o === null) return $c;
    $o = preg_replace('~:\s+~', ':', $o); if ($o === null) return $c;
    $o = str_replace(';}', '}', $o);
    return trim($o);
  }
}
