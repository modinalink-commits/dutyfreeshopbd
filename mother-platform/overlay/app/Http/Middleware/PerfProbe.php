<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only measurement: super users can add ?_perf=1 to an admin page to get query count, DB time, memory and time as response headers. */
class PerfProbe {
  public function handle(Request $r, Closure $next) {
    if (!$r->query('_perf') || !auth()->check() || !in_array(auth()->user()->role, ['super','admin'], true)) return $next($r);
    $t0 = microtime(true); DB::enableQueryLog();
    $resp = $next($r);
    $log = DB::getQueryLog(); $ms = 0; $pat = [];
    foreach ($log as $q) { $ms += $q['time']; $k = substr(preg_replace(['/\s+/', '/\b\d+\b/', "/'[^']*'/"], [' ', '?', '?'], $q['query']), 0, 90); $pat[$k] = ($pat[$k] ?? 0) + 1; }
    arsort($pat); $top = []; foreach (array_slice($pat, 0, 3, true) as $k => $n) $top[] = $n . 'x ' . $k;
    $resp->headers->set('X-Perf', sprintf('time_ms=%d; queries=%d; db_ms=%.1f; peak_mb=%.1f; bytes=%d', (microtime(true) - $t0) * 1000, count($log), $ms, memory_get_peak_usage(true) / 1048576, strlen((string)$resp->getContent())));
    $resp->headers->set('X-Perf-Top', preg_replace('/[^\x20-\x7E]/', '', implode(' || ', $top)));
    return $resp;
  }
}
