<?php
namespace App\Support;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Sales Channels (owner 28 Nov 2026): one central catalogue, several storefront domains. A channel (table sales_channels) is opened by its domain and shows ONLY the categories ticked for it,
 * each with its own price rule (percent up or down on the main sale price). Everything is decided on the server, in the same places CleanSite already hooks:
 * listings, menu, search, product pages (404 when the category is not allowed), cart, checkout, order. The main domain and every domain that is not a channel are never touched (cur() is null = every method is a no-op).
 * Staff can look at any channel (even Draft) on any storefront host with ?channelpreview=slug (needs a logged-in staff session); ?channelpreview=0 leaves.
 * A channel that is not Live is private (404 unless staff or the private preview link ?pv=KEY was used) and cannot take orders.
 */
class ChannelScope {
  private static $ch = false;
  public static function host(): string { return preg_replace('/^www\./', '', strtolower((string)request()->getHost())); }
  /** The channel this request belongs to, or null (main site). */
  public static function cur(): ?object {
    if (self::$ch !== false) return self::$ch;
    try {
      if (app()->runningInConsole()) return self::$ch = null;
      $r = request();
      if ($r->is('admin', 'admin/*', 'webhook/*')) return self::$ch = null;
      $map = Cache::remember('chan.hosts', 60, fn() => DB::table('sales_channels')->whereNotNull('domain')->whereIn('status', ['preview', 'live'])->pluck('id', 'domain')->mapWithKeys(fn($id, $d) => [preg_replace('/^www\./', '', strtolower($d)) => (int)$id])->all());
      $id = $map[self::host()] ?? null;
      if (!$id && ($slug = $r->query('channelpreview', $r->cookie('channelpreview'))) && $slug !== '0') {
        if (!$r->hasSession() || !$r->session()->isStarted()) return null; // staff session not started yet (early middleware): ask again later, do not remember "no channel"
        if (Auth::check()) $id = (int)DB::table('sales_channels')->where('slug', (string)$slug)->value('id') ?: null;
      }
      if ($r->query('channelpreview') !== null) \Illuminate\Support\Facades\Cookie::queue('channelpreview', (string)$r->query('channelpreview'), 30);
      if (!$id) return self::$ch = null;
      return self::$ch = self::load($id);
    } catch (\Throwable $e) { return self::$ch = null; }
  }
  public static function load(int $id): ?object {
    $a = Cache::remember('chan.row.' . $id, 60, fn() => (array)DB::table('sales_channels')->where('id', $id)->first()); // plain array: Laravel's cache does not unserialize objects
    if (!$a) return null; $c = (object)$a;
    $s = json_decode((string)$c->settings, true) ?: [];
    $c->cfg = $s; $c->adj = []; $c->mode = []; foreach (($s['cats'] ?? []) as $x) { $c->adj[(int)$x['id']] = (float)($x['adj'] ?? 0); $c->mode[(int)$x['id']] = ($x['mode'] ?? 'pct') === 'tk' ? 'tk' : 'pct'; }
    return $c;
  }
  public static function forget(?int $id = null): void { Cache::forget('chan.hosts'); if ($id) Cache::forget('chan.row.' . $id); }
  /** Category ids this channel may show. Nothing ticked = nothing shown. */
  public static function ids(?object $c = null): array { $c = $c ?: self::cur(); return $c ? array_keys($c->adj) : []; }
  public static function allows($cid, ?object $c = null): bool { $c = $c ?: self::cur(); return !$c || isset($c->adj[(int)$cid]); }
  /** Cache-key part: a different channel, or a changed channel, never shares cached lists with the main site. */
  public static function key(): string { $c = self::cur(); return $c ? 'ch' . $c->id . '.' . strtotime((string)$c->updated_at) . '.' : ''; }
  /** Only the ticked categories (products query; $a = alias prefix). No-op on the main site. */
  public static function apply($qb, string $a = 'products.') {
    $c = self::cur(); if (!$c) return $qb;
    return $qb->whereIn($a . 'category_id', self::ids($c) ?: [0]);
  }
  public static function keep($p): bool { return self::allows($p->category_id ?? 0); }
  public static function price($v, $cid, ?object $c = null) {
    $c = $c ?: self::cur(); if (!$c || $v === null) return $v;
    $a = $c->adj[(int)$cid] ?? 0.0; if ($a == 0.0 || (float)$v <= 0) return $v; // 0 = main price; price 0 stays "call for price"
    return (($c->mode[(int)$cid] ?? 'pct') === 'tk') ? max(1, round((float)$v + $a)) : round((float)$v * (1 + $a / 100));
  }
  /** Apply the channel price rule to ONE product row (once; the row is marked). */
  public static function adjust($p) {
    if (!is_object($p) || isset($p->_ch) || !isset($p->price)) return $p;
    $c = self::cur(); if (!$c) return $p;
    $cid = $p->category_id ?? 0;
    $p->price = self::price($p->price, $cid, $c);
    if (isset($p->compare_price) && $p->compare_price !== null) $p->compare_price = self::price($p->compare_price, $cid, $c);
    $p->_ch = 1; return $p;
  }
  public static function adjustRows($rows) { if (self::cur()) foreach ($rows as $r) self::adjust($r); return $rows; }
  /** View data: every product row in the page data gets the price rule (depth and size limited). */
  public static function adjustData($d, int $depth = 0, int &$n = 0): void {
    if ($depth > 4 || $n > 3000) return;
    if (is_object($d)) {
      if ($d instanceof \Illuminate\Pagination\AbstractPaginator) { self::adjustData($d->getCollection(), $depth + 1, $n); return; }
      if ($d instanceof \Illuminate\Support\Collection) { foreach ($d as $x) { $n++; self::adjustData($x, $depth + 1, $n); } return; }
      if ($d instanceof \stdClass) { if (isset($d->price) && isset($d->category_id)) self::adjust($d); return; }
      return;
    }
    if (is_array($d)) foreach ($d as $x) { $n++; if ($n > 3000) return; if (is_object($x) || is_array($x)) self::adjustData($x, $depth + 1, $n); }
  }
  /** Not live = private. Returns a 404 response when this visitor may not see the channel, else null (and remembers the private link in a cookie). */
  public static function gate(\Illuminate\Http\Request $r): ?\Illuminate\Http\Response {
    $c = self::cur(); if (!$c || $c->status === 'live') return null;
    $key = (string)($c->cfg['access_key'] ?? ''); $tok = $key !== '' ? hash('sha256', 'chpv' . $c->id . $key) : null;
    try { if (Auth::check()) return null; } catch (\Throwable $e) {}
    if ($tok && ($r->cookie('chpv') === $tok || hash_equals($key, (string)$r->query('pv')))) return null;
    return response('Not found', 404)->header('X-Robots-Tag', 'noindex, nofollow');
  }
  public static function pvCookie(): ?\Symfony\Component\HttpFoundation\Cookie {
    $c = self::cur(); if (!$c || $c->status === 'live') return null;
    $key = (string)($c->cfg['access_key'] ?? ''); if ($key === '' || !hash_equals($key, (string)request()->query('pv'))) return null;
    return cookie('chpv', hash('sha256', 'chpv' . $c->id . $key), 60 * 24 * 14, '/', null, true, true, false, 'lax');
  }
  /** Orders open only on a Live channel. */
  public static function ordersOpen(): bool { $c = self::cur(); return !$c || $c->status === 'live'; }
}
