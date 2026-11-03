<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Behind Cloudflare the hop Traefik sees is a Cloudflare edge IP, so every visitor would share a handful of IPs
 * (rate limits, blocks, order/session IPs). The real visitor IP is in CF-Connecting-IP, and it is honoured ONLY when the
 * request really arrived from a Cloudflare range - a direct hit on the origin with a forged header is ignored.
 * Without Cloudflare in front (current state) this does nothing.
 */
class CloudflareRealIp {
  const RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
    '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
  ];
  public function handle(Request $r, Closure $next) {
    try {
      $cf = trim((string) $r->headers->get('CF-Connecting-IP'));
      if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        $edge = (string) $r->getClientIp();
        if ($edge !== '' && IpUtils::checkIp($edge, self::RANGES)) $r->server->set('REMOTE_ADDR', $cf);
      }
    } catch (\Throwable $e) { /* never block a request because of this */ }
    return $next($r);
  }
}
