<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Deployment Center: health probes (app-side), worker heartbeat and the Issues Center. Read-only except for issue rows. */
class DeployHealth
{
  const HOST = 'dutyfreeshopbd.com';
  private static ?bool $hasIssues = null;

  // ---------- worker heartbeat (the background loop that also acts as the scheduler) ----------
  public static function beat(): void { try { Deployer::put('worker_beat', (string)time()); } catch (\Throwable $e) {} }
  public static function beatAge(): ?int { $v = DB::table('settings')->where('key', 'worker_beat')->value('value'); return $v ? max(0, time() - (int)$v) : null; }

  // ---------- probes ----------
  /** One internal HTTP request through nginx (same way deploy.sh checks a release). */
  public static function probe(string $path, int $timeout = 6): array {
    $t0 = microtime(true);
    try {
      $r = Http::withHeaders(['Host' => self::HOST, 'User-Agent' => 'deploy-check', 'X-Forwarded-Proto' => 'https'])->withOptions(['allow_redirects' => false])->timeout($timeout)->get('http://web' . $path);
      $c = $r->status(); $ok = $c >= 200 && $c < 400 && !preg_match('/Server Error|Whoops|Stack trace|ErrorException/i', (string)$r->body());
      return ['ok' => $ok, 'code' => $c, 'ms' => (int)((microtime(true) - $t0) * 1000)];
    } catch (\Throwable $e) { return ['ok' => false, 'code' => 0, 'ms' => (int)((microtime(true) - $t0) * 1000), 'err' => mb_substr($e->getMessage(), 0, 120)]; }
  }

  public static function pendingMigrations(): array {
    try {
      $ran = DB::table('migrations')->pluck('migration')->all(); $out = [];
      foreach (glob(database_path('migrations/*.php')) ?: [] as $f) { $n = basename($f, '.php'); if (!in_array($n, $ran, true)) $out[] = $n; }
      sort($out); return $out;
    } catch (\Throwable $e) { return []; }
  }

  public static function queueInfo(): array {
    $drv = (string)config('queue.default'); $o = ['driver' => $drv, 'pending' => null, 'failed' => 0, 'oldest' => null];
    try { if (Schema::hasTable('failed_jobs')) $o['failed'] = (int)DB::table('failed_jobs')->count(); } catch (\Throwable $e) {}
    try { if ($drv === 'database' && Schema::hasTable('jobs')) { $o['pending'] = (int)DB::table('jobs')->count(); $m = DB::table('jobs')->min('created_at'); $o['oldest'] = $m ? max(0, time() - (int)$m) : null; } } catch (\Throwable $e) {}
    return $o;
  }

  public static function cacheInfo(): array {
    $views = glob(storage_path('framework/views/*.php')) ?: [];
    return ['driver' => (string)config('cache.default'), 'config' => app()->configurationIsCached(), 'route' => app()->routesAreCached(), 'views' => count($views)];
  }

  private static function ssl(): array {
    return Cache::remember('dh.ssl', 3600, function () {
      $host = parse_url((string)config('app.url'), PHP_URL_HOST) ?: 'dutyfreebd.com';
      try {
        $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false]]);
        $s = @stream_socket_client('ssl://' . $host . ':443', $en, $es, 5, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) return ['state' => 'na', 'text' => 'UNKNOWN', 'detail' => 'Could not reach ' . $host . ':443 from the server'];
        $p = stream_context_get_params($s); fclose($s); $c = openssl_x509_parse($p['options']['ssl']['peer_certificate'] ?? '');
        $to = (int)($c['validTo_time_t'] ?? 0); if (!$to) return ['state' => 'na', 'text' => 'UNKNOWN', 'detail' => ''];
        $d = (int)floor(($to - time()) / 86400);
        return ['state' => $d < 0 ? 'fail' : ($d < 14 ? 'warn' : 'ok'), 'text' => $d < 0 ? 'EXPIRED' : 'ACTIVE', 'detail' => 'Valid until ' . date('d M Y', $to) . ' (' . $d . ' days)', 'days' => $d];
      } catch (\Throwable $e) { return ['state' => 'na', 'text' => 'UNKNOWN', 'detail' => '']; }
    });
  }

  private static function cell(string $key, string $label, string $state, string $text, string $detail = ''): array { return compact('key', 'label', 'state', 'text', 'detail'); }

  /** Compact status bar, cached 20 s so the page can poll it. */
  public static function light(bool $fresh = false): array {
    if (!$fresh && ($c = Cache::get('dh.light'))) return $c;
    $cells = [];
    $web = self::probe('/_health', 4);
    $cells['website'] = self::cell('website', 'Website', $web['ok'] ? 'ok' : 'fail', $web['ok'] ? 'LIVE' : 'DOWN', $web['ok'] ? $web['ms'] . ' ms' : ('HTTP ' . $web['code'] . ' ' . ($web['err'] ?? '')));
    $rel = Deployer::liveId(); $relOk = Deployer::enabled() && is_file(base_path('artisan')) && is_file(base_path('vendor/autoload.php'));
    $cells['application'] = self::cell('application', 'Application', $relOk ? 'ok' : 'fail', $relOk ? 'HEALTHY' : 'INVALID', 'Release ' . $rel);
    try { $t = microtime(true); DB::select('select 1'); $ms = (int)((microtime(true) - $t) * 1000); $cells['database'] = self::cell('database', 'Database', 'ok', 'CONNECTED', 'PostgreSQL · ' . $ms . ' ms'); }
    catch (\Throwable $e) { $cells['database'] = self::cell('database', 'Database', 'fail', 'DOWN', 'Connection failed'); }
    $uses = array_filter([config('cache.default'), config('queue.default'), config('session.driver')], fn($d) => $d === 'redis');
    if ($uses) { try { \Illuminate\Support\Facades\Redis::connection()->ping(); $cells['redis'] = self::cell('redis', 'Redis', 'ok', 'HEALTHY', 'Connected'); } catch (\Throwable $e) { $cells['redis'] = self::cell('redis', 'Redis', 'fail', 'DOWN', 'Connection failed'); } }
    else $cells['redis'] = self::cell('redis', 'Redis', 'na', 'NOT USED', 'This site uses file/database cache');
    $q = self::queueInfo();
    if ($q['driver'] === 'sync') $cells['queue'] = self::cell('queue', 'Queue', $q['failed'] > 0 ? 'warn' : 'ok', 'SYNC', 'Jobs run instantly, no separate worker' . ($q['failed'] ? ' · ' . $q['failed'] . ' failed' : ''));
    else $cells['queue'] = self::cell('queue', 'Queue', $q['failed'] > 0 ? 'warn' : 'ok', 'RUNNING', ($q['pending'] ?? 0) . ' pending · ' . $q['failed'] . ' failed');
    $age = self::beatAge();
    if ($age === null) $cells['scheduler'] = self::cell('scheduler', 'Scheduler', 'na', 'UNKNOWN', 'No heartbeat recorded yet');
    else $cells['scheduler'] = self::cell('scheduler', 'Scheduler', $age <= 120 ? 'ok' : ($age <= 600 ? 'warn' : 'fail'), $age <= 120 ? 'RUNNING' : ($age <= 600 ? 'DELAYED' : 'STOPPED'), 'Last heartbeat ' . \Carbon\Carbon::now('Asia/Dhaka')->subSeconds($age)->format('H:i') . ' (' . trim(self::ago($age)) . ')');
    $allOk = $cells['website']['state'] === 'ok' && $cells['application']['state'] === 'ok' && $cells['database']['state'] === 'ok' && in_array($cells['scheduler']['state'], ['ok', 'na'], true);
    $cells['containers'] = self::cell('containers', 'Containers', $allOk ? 'ok' : (($cells['website']['state'] === 'fail' || $cells['database']['state'] === 'fail') ? 'fail' : 'warn'), $allOk ? 'HEALTHY' : 'CHECK', 'App · Web · Database · Worker (inferred from probes)');
    $s = self::ssl(); $cells['ssl'] = self::cell('ssl', 'SSL', $s['state'], $s['text'], $s['detail']);
    $states = array_column($cells, 'state'); $overall = in_array('fail', $states, true) ? 'ISSUE' : (in_array('warn', $states, true) ? 'WARNING' : 'LIVE');
    $out = ['cells' => array_values($cells), 'overall' => $overall, 'at' => time()];
    Cache::put('dh.light', $out, 20); return $out;
  }
  private static function ago(int $s): string { return $s < 90 ? ' ' . $s . ' s ago' : ' ' . floor($s / 60) . ' min ago'; }

  /** Full Health Check (button): every item of the spec, including critical routes. */
  public static function full(): array {
    $c = []; $add = function (string $n, string $st, string $t, string $d = '') use (&$c) { $c[] = ['name' => $n, 'state' => $st, 'text' => $t, 'detail' => $d]; };
    $w = self::probe('/', 10); $add('Website response', $w['ok'] ? 'ok' : 'fail', $w['ok'] ? 'Healthy' : 'Failed', 'HTTP ' . $w['code'] . ' · ' . $w['ms'] . ' ms' . (isset($w['err']) ? ' · ' . $w['err'] : ''));
    $add('Laravel boot', 'ok', 'Healthy', 'Framework ' . app()->version() . ' · PHP ' . PHP_VERSION);
    try { $t = microtime(true); DB::select('select 1'); $add('Database', 'ok', 'Connected', 'PostgreSQL · ' . (int)((microtime(true) - $t) * 1000) . ' ms'); } catch (\Throwable $e) { $add('Database', 'fail', 'Failed', 'Connection failed'); }
    $l = self::light(true); foreach ($l['cells'] as $x) if ($x['key'] === 'redis') $add('Redis', $x['state'] === 'na' ? 'na' : $x['state'], $x['state'] === 'na' ? 'Not used' : $x['text'], $x['detail']);
    foreach ($l['cells'] as $x) if ($x['key'] === 'queue') $add('Queue', $x['state'], $x['text'] === 'SYNC' ? 'Sync mode' : 'Running', $x['detail']);
    foreach ($l['cells'] as $x) if ($x['key'] === 'scheduler') $add('Scheduler', $x['state'], ucfirst(strtolower($x['text'])), $x['detail']);
    $f = storage_path('framework/health.tmp'); $wr = @file_put_contents($f, '1') !== false; @unlink($f); $add('Storage', $wr ? 'ok' : 'fail', $wr ? 'Writable' : 'Not writable', 'storage/framework');
    $dirs = [storage_path('logs'), storage_path('framework/views'), storage_path('framework/cache'), base_path('bootstrap/cache')]; $bad = array_filter($dirs, fn($d) => !is_dir($d) || !is_writable($d));
    $add('Required directories', $bad ? 'fail' : 'ok', $bad ? 'Problem' : 'Present', $bad ? implode(', ', array_map(fn($d) => str_replace(base_path() . '/', '', $d), $bad)) : 'logs · views · cache · bootstrap/cache');
    $rel = Deployer::liveId(); $okr = Deployer::enabled() && is_file(base_path('artisan')) && is_file(base_path('vendor/autoload.php')) && is_file(base_path('RELEASE'));
    $add('Current release', $okr ? 'ok' : 'warn', $okr ? 'Valid' : 'Check', $rel);
    $bad = []; foreach (['/offline', '/_health', '/track', '/admin/login', '/account/login', '/manifest.webmanifest'] as $p) { $r = self::probe($p, 8); if (!$r['ok']) $bad[] = $p . ' (' . $r['code'] . ')'; }
    $add('Critical routes', $bad ? 'fail' : 'ok', $bad ? 'Failed' : 'Healthy', $bad ? implode(', ', $bad) : '6 routes responded correctly');
    $st = array_column($c, 'state'); $overall = in_array('fail', $st, true) ? 'ISSUE' : (in_array('warn', $st, true) ? 'WARNING' : 'HEALTHY');
    $r = ['checks' => $c, 'overall' => $overall, 'at' => time()]; Cache::put('dh.full', $r, 3600); return $r;
  }

  // ---------- Issues Center ----------
  private static function has(): bool { return self::$hasIssues ??= Schema::hasTable('deploy_issues'); }
  public static function raise(string $fp, string $sev, string $service, string $title, string $desc, string $cause, string $action, ?int $dep = null): void {
    try {
      if (!self::has()) return;
      $ex = DB::table('deploy_issues')->where('fingerprint', $fp)->whereIn('status', ['open', 'investigating'])->first();
      if ($ex) { DB::table('deploy_issues')->where('id', $ex->id)->update(['severity' => $sev, 'description' => mb_substr($desc, 0, 1500), 'updated_at' => now()]); return; }
      if (DB::table('deploy_issues')->where('fingerprint', $fp)->where('status', 'ignored')->where('updated_at', '>', now()->subDay())->exists()) return;
      DB::table('deploy_issues')->insert(['fingerprint' => $fp, 'title' => mb_substr($title, 0, 200), 'severity' => $sev, 'service' => $service, 'description' => mb_substr($desc, 0, 1500), 'cause' => mb_substr($cause, 0, 800), 'action' => mb_substr($action, 0, 800),
        'status' => 'open', 'deployment_id' => $dep, 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {}
  }
  public static function resolve(string $fp, string $by = 'System (auto)', bool $like = false): void {
    try {
      if (!self::has()) return;
      $q = DB::table('deploy_issues')->whereIn('status', ['open', 'investigating']); $like ? $q->where('fingerprint', 'like', $fp . '%') : $q->where('fingerprint', $fp);
      $q->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $by, 'updated_at' => now()]);
    } catch (\Throwable $e) {}
  }
  public static function active(): array {
    if (!self::has()) return [];
    $rank = ['critical' => 0, 'high' => 1, 'warning' => 2, 'info' => 3];
    $rows = DB::table('deploy_issues')->whereIn('status', ['open', 'investigating'])->orderByDesc('id')->limit(40)->get()->map(fn($r) => self::shape($r))->all();
    usort($rows, fn($a, $b) => [$rank[$a['severity']] ?? 9, -$a['id']] <=> [$rank[$b['severity']] ?? 9, -$b['id']]); return $rows;
  }
  public static function history(int $limit = 80): array {
    if (!self::has()) return [];
    return DB::table('deploy_issues')->whereIn('status', ['resolved', 'ignored'])->orderByDesc('id')->limit($limit)->get()->map(fn($r) => self::shape($r))->all();
  }
  public static function shape(object $r): array {
    $tz = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka')->format('d M Y H:i') : '';
    return ['id' => (int)$r->id, 'title' => $r->title, 'severity' => $r->severity, 'service' => $r->service, 'description' => $r->description, 'cause' => $r->cause, 'action' => $r->action, 'status' => $r->status,
      'deployment_id' => $r->deployment_id, 'detected' => $tz($r->detected_at), 'resolved' => $tz($r->resolved_at), 'by' => $r->resolved_by, 'note' => $r->note, 'fp' => $r->fingerprint];
  }

  /** Called from Deployer::tick (every ~2 min): raise issues for real problems, close them automatically when they clear. */
  public static function scan(): void {
    if (!self::has() || !Cache::add('dh.scan.lock', 1, 100)) return;
    if (DB::table('deployments')->whereIn('status', Deployer::ACTIVE)->exists()) return; // a release is being built: do not judge it half-way
    $l = self::light(true); $by = []; foreach ($l['cells'] as $c) $by[$c['key']] = $c;
    $w = $by['website']; $fails = (int)Cache::get('dh.web_fail', 0);
    if ($w['state'] === 'fail') { Cache::put('dh.web_fail', $fails + 1, 900); if ($fails + 1 >= 2) self::raise('website_down', 'critical', 'Website', 'Website is not responding', 'The internal website check failed twice in a row (' . $w['detail'] . ').', 'Application error after a change, web server restart or database trouble.', 'Run Health Check, review logs, and Rollback if it started after a deployment.'); }
    else { Cache::forget('dh.web_fail'); self::resolve('website_down'); }
    if ($by['database']['state'] === 'fail') self::raise('db_down', 'critical', 'Database', 'Database connection failed', 'The application could not run a simple query.', 'Database container stopped or restarting.', 'Check the database container and run Health Check.'); else self::resolve('db_down');
    if ($by['application']['state'] === 'fail') self::raise('release_invalid', 'critical', 'Application', 'Current release looks incomplete', 'The live release is missing core files.', 'An interrupted deployment or a manual file change.', 'Run Recovery Check, then Rollback if it stays.'); else self::resolve('release_invalid');
    $age = self::beatAge();
    if ($age !== null && $age > 180) self::raise('worker_stale', $age > 600 ? 'high' : 'warning', 'Scheduler', $age > 600 ? 'Background worker has stopped' : 'Background worker is delayed', 'No heartbeat for ' . floor($age / 60) . ' minutes. Order pushes, scheduled SMS, AI Brief and auto-deployments depend on it.', 'The worker loop crashed or the container was restarted.', 'Run Recovery Check; if it does not return, ask the server owner to restart the app container.');
    else self::resolve('worker_stale');
    $pm = self::pendingMigrations();
    if ($pm) self::raise('migrations_pending', 'warning', 'Database', count($pm) . ' pending migration(s) detected', 'Pending: ' . implode(', ', array_slice($pm, 0, 4)) . (count($pm) > 4 ? '…' : ''), 'A deployment added migrations that have not run on this release.', 'Run a Standard Deploy (it runs safe migrations) or review the list with View Migrations.'); else self::resolve('migrations_pending');
    $q = self::queueInfo();
    if ($q['failed'] > 0) self::raise('failed_jobs', 'info', 'Queue', $q['failed'] . ' failed job(s) in the queue', 'Failed jobs are stored and can be reviewed.', 'A job threw an error (for example a temporary network problem).', 'Open View Failed Jobs and review the first lines of the error.'); else self::resolve('failed_jobs');
    $free = @disk_free_space(storage_path()); $tot = @disk_total_space(storage_path());
    if ($free && $tot) { $p = $free / $tot * 100; if ($p < 12) self::raise('disk_low', $p < 5 ? 'high' : 'warning', 'Server', 'Disk space is running low', sprintf('%.1f%% free (%.1f GB).', $p, $free / 1073741824), 'Logs, old releases or uploads are using the disk.', 'Remove old logs/uploads or enlarge the disk.'); else self::resolve('disk_low'); }
    $s = $by['ssl']; if ($s['state'] === 'warn' || $s['state'] === 'fail') self::raise('ssl_expiring', $s['state'] === 'fail' ? 'critical' : 'warning', 'SSL', $s['state'] === 'fail' ? 'SSL certificate expired' : 'SSL certificate expires soon', $s['detail'], 'Automatic renewal did not run.', 'Check certificate renewal in the proxy (Traefik) or Cloudflare.'); else self::resolve('ssl_expiring');
  }
}
