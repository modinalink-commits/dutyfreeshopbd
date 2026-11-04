<?php
namespace App\Support;
/** One main domain for everything public (canonical, sitemap, robots, structured data, footer, links in messages). Other domains the site answers on (dutyfreeshopbd.com, dutyfreebd.shop, dutyfreebd.store ...) are secondary. */
class Domain {
  const HOST = 'www.dutyfreebd.com';
  public static function main(): string { return 'https://' . self::HOST; }
  public static function abs(string $path = '/'): string { return self::main() . '/' . ltrim($path, '/'); }
  /** Same URL on the main domain (scheme, host and port replaced; path and query kept). */
  public static function canon(string $url): string {
    $p = parse_url($url); if (!$p || empty($p['host'])) return $url;
    return self::main() . ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
  }
  public static function isMain(?string $host = null): bool { $h = strtolower($host ?? (string)request()->getHost()); return $h === self::HOST; }
}
