<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
/** Dependency-free Sentry reporter: sends server errors (HTTP 5xx / uncaught) to the DSN saved in settings.sentry_dsn. No cookies, no request body, no customer data are sent. */
class SentryReport {
  public static function dsn(): ?string {
    try { $v = Cache::remember('sentry.dsn', 300, fn () => (string) DB::table('settings')->where('key', 'sentry_dsn')->value('value')); } catch (\Throwable $e) { $v = (string) env('SENTRY_DSN', ''); }
    return $v !== '' ? $v : null;
  }
  /** Report a throwable. Ignores client errors (404/403/422/419 ...) and repeats of the same error within 5 minutes. */
  public static function report(\Throwable $e): void {
    try {
      if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() < 500) return;
      if ($e instanceof \Illuminate\Validation\ValidationException || $e instanceof \Illuminate\Auth\AuthenticationException || $e instanceof \Illuminate\Session\TokenMismatchException) return;
      $key = 'sentry.seen.'.md5(get_class($e).$e->getFile().$e->getLine());
      if (!Cache::add($key, 1, 300)) return;
      self::send(self::event($e));
    } catch (\Throwable $x) { /* never break the site because of monitoring */ }
  }
  public static function test(): array {
    try { throw new \RuntimeException('Sentry test event from DutyFreeBD admin (safe to ignore)'); } catch (\Throwable $e) { return self::send(self::event($e, 'info')); }
  }
  private static function event(\Throwable $e, string $level = 'error'): array {
    $base = base_path().'/'; $frames = [];
    foreach (array_reverse($e->getTrace()) as $f) {
      $file = isset($f['file']) ? str_replace($base, '', $f['file']) : '[internal]';
      $frames[] = ['filename' => $file, 'lineno' => $f['line'] ?? 0, 'function' => (isset($f['class']) ? $f['class'].($f['type'] ?? '::') : '').($f['function'] ?? ''), 'in_app' => !str_contains($file, 'vendor/')];
    }
    $frames[] = ['filename' => str_replace($base, '', $e->getFile()), 'lineno' => $e->getLine(), 'function' => '{raised here}', 'in_app' => !str_contains($e->getFile(), 'vendor/')];
    $req = null;
    try { if (!app()->runningInConsole()) { $r = request(); $req = ['url' => $r->url(), 'method' => $r->method()]; } } catch (\Throwable $x) {}
    return array_filter([
      'event_id' => bin2hex(random_bytes(16)), 'timestamp' => gmdate('Y-m-d\TH:i:s\Z'), 'platform' => 'php', 'level' => $level,
      'logger' => 'dutyfreebd', 'environment' => app()->environment(), 'server_name' => 'dutyfreebd.com',
      'exception' => ['values' => [['type' => get_class($e), 'value' => mb_substr($e->getMessage(), 0, 500), 'stacktrace' => ['frames' => array_slice($frames, -40)]]]],
      'request' => $req, 'tags' => ['app' => 'mother-platform'],
    ]);
  }
  /** @return array{0:bool,1:string} */
  private static function send(array $event): array {
    $dsn = self::dsn(); if (!$dsn) return [false, 'No DSN saved (settings.sentry_dsn)'];
    $p = parse_url($dsn); if (empty($p['user']) || empty($p['host']) || empty($p['path'])) return [false, 'Bad DSN'];
    $project = trim($p['path'], '/'); $url = 'https://'.$p['host'].'/api/'.$project.'/envelope/';
    $body = json_encode(['event_id' => $event['event_id'], 'sent_at' => gmdate('Y-m-d\TH:i:s\Z')])."\n".json_encode(['type' => 'event'])."\n".json_encode($event);
    $res = Http::timeout(3)->withHeaders(['X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_client=dutyfreebd-php/1.0, sentry_key='.$p['user'], 'Content-Type' => 'application/x-sentry-envelope'])->withBody($body, 'application/x-sentry-envelope')->post($url);
    return [$res->successful(), 'HTTP '.$res->status()];
  }
}
