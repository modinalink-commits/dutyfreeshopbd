<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PwaController extends Controller
{
  public static function ver(): string {
    $f = base_path('RELEASE'); if (is_file($f)) { $v = trim((string)@file_get_contents($f)); if ($v !== '') return $v; }
    $v = getenv('REF') ?: ($_SERVER['REF'] ?? '');
    return $v !== '' ? substr($v, 0, 10) : (string)@filemtime(base_path('routes/web.php'));
  }

  public function manifest() {
    $m = [
      'id' => '/',
      'name' => 'DutyFreeBD',
      'short_name' => 'DutyFreeBD',
      'description' => 'Shop faster, track orders and receive exclusive updates.',
      'lang' => 'bn',
      'dir' => 'ltr',
      'start_url' => '/?source=pwa',
      'scope' => '/',
      'display' => 'standalone',
      'orientation' => 'portrait-primary',
      'background_color' => '#FFFFFF',
      'theme_color' => '#0B1220',
      'categories' => ['shopping'],
      'icons' => [
        ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/pwa/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
      ],
      'shortcuts' => [
        ['name' => 'My Orders', 'short_name' => 'Orders', 'url' => '/account/orders', 'icons' => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Track Order', 'short_name' => 'Track', 'url' => '/track', 'icons' => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']]],
      ],
    ];
    return response(json_encode($m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
  }

  public function sw() {
    $js = str_replace('__VER__', self::ver(), file_get_contents(resource_path('pwa/sw.js')));
    return response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache, max-age=0', 'Service-Worker-Allowed' => '/']);
  }

  public function offline() {
    return response()->view('store.offline', [], 200)->header('Cache-Control', 'no-cache');
  }
}
