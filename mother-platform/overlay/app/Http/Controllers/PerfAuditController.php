<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

/** Read-only server / database / Laravel health report for the performance audit (super user only). Nothing here writes. */
class PerfAuditController extends Controller {
  private function safe(callable $f, $d = null) { try { return $f(); } catch (\Throwable $e) { return $d ?? ('n/a: ' . substr($e->getMessage(), 0, 120)); } }
  private function cpu(): array { $r = fn() => preg_split('/\s+/', trim(explode("\n", (string)@file_get_contents('/proc/stat'))[0])); $a = $r(); usleep(400000); $b = $r(); $d = []; for ($i = 1; $i <= 8; $i++) $d[$i] = (int)($b[$i] ?? 0) - (int)($a[$i] ?? 0); $t = max(1, array_sum($d)); return ['busy' => round(100 - ($d[4] + $d[5]) / $t * 100, 1), 'iowait' => round($d[5] / $t * 100, 1)]; }
  public function show() {
    abort_unless(auth()->user() && auth()->user()->type === 'super', 403);
    $S = []; $mem = []; foreach (explode("\n", (string)@file_get_contents('/proc/meminfo')) as $l) if (preg_match('/^(\w+):\s+(\d+)/', $l, $m)) $mem[$m[1]] = (int)$m[2] / 1024;
    $cores = max(1, substr_count((string)@file_get_contents('/proc/cpuinfo'), 'processor'));
    $S['Server'] = [
      ['CPU cores', $cores], ['CPU busy / iowait now', ($c = $this->cpu())['busy'] . '% / ' . $c['iowait'] . '%'], ['Load average (1/5/15 min)', implode(' / ', array_map(fn($x) => round($x, 2), sys_getloadavg() ?: []))],
      ['RAM total / available (MB)', round($mem['MemTotal'] ?? 0) . ' / ' . round($mem['MemAvailable'] ?? 0)], ['Swap total / free (MB)', round($mem['SwapTotal'] ?? 0) . ' / ' . round($mem['SwapFree'] ?? 0)],
      ['Disk free / total (GB)', round(disk_free_space(base_path()) / 1073741824, 1) . ' / ' . round(disk_total_space(base_path()) / 1073741824, 1)], ['PHP version', PHP_VERSION], ['PHP memory_limit', ini_get('memory_limit')],
      ['OPcache', $this->safe(fn() => function_exists('opcache_get_status') && ($o = @opcache_get_status(false)) ? 'on, hit rate ' . round($o['opcache_statistics']['opcache_hit_rate'] ?? 0, 1) . '%, memory used ' . round(($o['memory_usage']['used_memory'] ?? 0) / 1048576) . ' MB' : 'OFF or not visible')],
      ['PHP-FPM processes seen', $this->safe(function () { $n = 0; foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $f) if (str_contains((string)@file_get_contents($f), 'php-fpm')) $n++; return $n; })],
      ['queue:work processes seen', $this->safe(function () { $n = 0; foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $f) if (str_contains((string)@file_get_contents($f), 'queue:work')) $n++; return $n; })],
      ['FPM pool config', $this->safe(function () { foreach (['/usr/local/etc/php-fpm.d/www.conf', '/usr/local/etc/php-fpm.d/zz-docker.conf', '/etc/php/8.3/fpm/pool.d/www.conf'] as $p) if (is_readable($p)) { preg_match_all('/^\s*(pm(?:\.[a-z_]+)?)\s*=\s*(\S+)/m', (string)file_get_contents($p), $m, PREG_SET_ORDER); return basename($p) . ': ' . implode(', ', array_map(fn($x) => $x[1] . '=' . $x[2], $m)); } return 'config not readable here'; })],
    ];
    $S['Laravel'] = [
      ['APP_ENV / APP_DEBUG', config('app.env') . ' / ' . (config('app.debug') ? 'TRUE (bad for production)' : 'false')],
      ['config cached', file_exists(base_path('bootstrap/cache/config.php')) ? 'yes' : 'NO'], ['routes cached', glob(base_path('bootstrap/cache/routes-*.php')) ? 'yes' : 'NO'], ['events cached', file_exists(base_path('bootstrap/cache/events.php')) ? 'yes' : 'NO'],
      ['compiled views', count(glob(storage_path('framework/views/*.php')) ?: [])], ['cache / session / queue driver', config('cache.default') . ' / ' . config('session.driver') . ' / ' . config('queue.default')],
      ['Redis extension / host set', (extension_loaded('redis') ? 'ext yes' : 'ext no') . ' / ' . (config('database.redis.default.host') ?: 'none')],
      ['Redis ping', $this->safe(fn() => extension_loaded('redis') && config('database.redis.default.host') ? (string)\Illuminate\Support\Facades\Redis::connection()->ping() : 'not configured')],
      ['jobs waiting / failed', $this->safe(fn() => DB::table('jobs')->count() . ' / ' . DB::table('failed_jobs')->count())], ['oldest waiting job (min)', $this->safe(fn() => ($t = DB::table('jobs')->min('created_at')) ? round((time() - $t) / 60) : 0)],
      ['env() calls outside config/', $this->safe(function () { $hits = []; $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) if ($f->getExtension() === 'php' && preg_match('/\benv\(/', (string)file_get_contents($f->getPathname()))) $hits[] = str_replace(base_path() . '/', '', $f->getPathname()); return count($hits) ? count($hits) . ': ' . implode(', ', array_slice($hits, 0, 6)) : 'none'; })],
    ];
    $pg = fn($sql) => $this->safe(fn() => DB::selectOne($sql), null);
    $set = fn($n) => $this->safe(fn() => DB::selectOne("select setting || coalesce(unit,'') v from pg_settings where name = ?", [$n])->v);
    $S['PostgreSQL'] = [['Version', $this->safe(fn() => explode(',', DB::selectOne('select version() v')->v)[0])], ['shared_buffers', $set('shared_buffers')], ['work_mem', $set('work_mem')], ['effective_cache_size', $set('effective_cache_size')], ['max_connections', $set('max_connections')],
      ['Connections now', $this->safe(fn() => DB::selectOne('select count(*) n from pg_stat_activity')->n)], ['Database size', $this->safe(fn() => DB::selectOne('select pg_size_pretty(pg_database_size(current_database())) s')->s)],
      ['Cache hit ratio', $this->safe(fn() => round(DB::selectOne('select sum(blks_hit)*100.0/nullif(sum(blks_hit)+sum(blks_read),0) r from pg_stat_database where datname = current_database()')->r, 2) . '%')],
      ['pg_stat_statements', $this->safe(fn() => DB::selectOne("select count(*) n from pg_extension where extname='pg_stat_statements'")->n ? 'installed' : 'not installed')]];
    $tables = $this->safe(fn() => DB::select("select relname, n_live_tup rows, seq_scan, idx_scan, pg_size_pretty(pg_total_relation_size(relid)) size from pg_stat_user_tables order by seq_scan desc limit 15"), []);
    $key = ['orders', 'order_items', 'customers', 'products', 'suppliers', 'purchases', 'purchase_items', 'supplier_payments', 'account_entries', 'stock_logs', 'legacy_orders'];
    $idx = $this->safe(fn() => DB::select("select tablename, indexname, indexdef from pg_indexes where schemaname='public' and tablename = any(?) order by tablename, indexname", ['{' . implode(',', $key) . '}']), []);
    $explain = [];
    foreach ([
      'Order status counts (all time)' => "select status, count(*) from orders group by status",
      'Orders page: newest 50 by id' => "select * from orders order by id desc limit 50",
      'Orders page: status filter + newest 50' => "select * from orders where status = 'Delivered' order by id desc limit 50",
      'Customer stats by phone (50 phones)' => "select phone, count(*) from orders where phone in (select phone from orders order by id desc limit 50) group by phone",
      'Orders by date range (30 days)' => "select count(*) from orders where created_at >= now() - interval '30 days'",
      'Customers by phone' => "select * from customers where phone = (select phone from customers limit 1)",
      'Product search by name' => "select id from products where name ilike '%perfume%' limit 50",
      'Profit report: order_items join products (500 orders)' => "select i.order_id, p.cost from order_items i left join products p on p.id = i.product_id where i.order_id in (select id from orders order by id desc limit 500)",
    ] as $label => $sql) $explain[$label] = $this->safe(function () use ($sql) { $rows = DB::select('explain (analyze, buffers) ' . $sql); $txt = implode("\n", array_map(fn($x) => current((array)$x), $rows)); return $txt; });
    return view('perf_audit', compact('S', 'tables', 'idx', 'explain'));
  }
}
