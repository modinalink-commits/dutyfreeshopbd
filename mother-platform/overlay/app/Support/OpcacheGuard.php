<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
/** Frequent deploys leave old release scripts in PHP's code cache; when it fills up every request recompiles and the admin crawls. Checked at most once a minute from the admin's own polling. */
class OpcacheGuard {
  public static function tick(): void {
    try {
      if (!function_exists('opcache_get_status') || !Cache::add('opcache.guard', 1, 60)) return;
      $s = @opcache_get_status(false); if (!$s || empty($s['opcache_enabled'])) return;
      $used = ($s['memory_usage']['used_memory'] ?? 0) + ($s['memory_usage']['wasted_memory'] ?? 0); $free = $s['memory_usage']['free_memory'] ?? 0;
      $pct = $used + $free > 0 ? $used * 100 / ($used + $free) : 0;
      $hits = (int)($s['opcache_statistics']['hits'] ?? 0); $miss = (int)($s['opcache_statistics']['misses'] ?? 0);
      $rate = $hits + $miss > 2000 ? $hits * 100 / ($hits + $miss) : 100;
      if ($pct >= 90 || $rate < 60) {
        @opcache_reset();
        try { Procure::audit('server.opcache_reset', 'OPcache cleared automatically (memory '.round($pct).'%, hit rate '.round($rate).'%)', null, ['by' => 'System']); } catch (\Throwable $e) {}
      }
    } catch (\Throwable $e) {}
  }
}
