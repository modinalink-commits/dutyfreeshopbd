<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owner 17 Nov 2026: the official hotline changed to 09644999000. This finds every place in the database where an OLD hotline is still written
 * (any spacing, hyphens, +880, English or Bengali digits) and replaces it only in current content (settings, pages, templates, product text).
 * History (sent messages, call logs, orders, audit and activity logs) is never touched.
 */
class HotlineAudit {
  const NEW = '09644999000';
  /** old official hotlines: the real one and the mistyped variant that was once in circulation */
  const OLD = ['9613828081', '9631828081'];
  /** tables that hold what already happened: reported, never changed */
  const HISTORY = ['sms_logs', 'chat_messages', 'chat_threads', 'chat_comments', 'call_logs', 'call_listens', 'wa_calls', 'wa_call_recordings', 'wa_call_recording_logs', 'orders', 'order_items', 'order_status_logs', 'order_activity_logs', 'audit_logs', 'activity_logs', 'staff_activity', 'mail_logs', 'email_logs', 'push_logs', 'notifications', 'customer_notifications', 'jobs', 'failed_jobs', 'sessions', 'cache', 'cache_locks', 'migrations', 'voice_calls', 'voice_logs', 'winback_events', 'payments', 'manual_payments', 'deploy_runs', 'deploy_logs', 'ai_logs', 'ai_chat_messages', 'ai_chat_sessions', 'webhook_logs', 'courier_logs', 'customers', 'users', 'suppliers', 'riders', 'employees'];
  private static function bn(string $d): string { return strtr($d, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']); }
  /** POSIX regex for one old number in any writing: optional +880 / 880 / 0, digits split by spaces, hyphens or dots, English or Bengali numerals. */
  public static function rx(string $core): string {
    $sep = '[ .\\-]{0,2}'; $out = [];
    foreach ([$core, self::bn($core)] as $v) { $z = $v === $core ? '0' : '০'; $p880 = $v === $core ? '880' : '৮৮০'; $chars = preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY); $out[] = '(\\+?' . $p880 . $sep . '|' . $z . ')?' . implode($sep, $chars); }
    return '(' . implode('|', $out) . ')';
  }
  public static function pattern(): string { return implode('|', array_map([self::class, 'rx'], self::OLD)); }
  private static function cols(): array {
    return DB::select("select c.table_name t, c.column_name c from information_schema.columns c join information_schema.tables tb on tb.table_schema = c.table_schema and tb.table_name = c.table_name and tb.table_type = 'BASE TABLE' where c.table_schema = current_schema() and c.data_type in ('text', 'character varying', 'json', 'jsonb') order by 1, 2");
  }
  /** Where the old (or, with $new, the new) hotline is written right now: [[table, column, rows, history, sample keys]]. Read-only. */
  public static function scan(bool $new = false): array {
    $rx = $new ? self::rx(ltrim(self::NEW, '0')) : self::pattern(); $out = []; $skipped = [];
    foreach (self::cols() as $x) {
      $q = '"' . str_replace('"', '', $x->t) . '"'; $col = '"' . str_replace('"', '', $x->c) . '"';
      try {
        $n = DB::transaction(function () use ($q, $col, $rx) { DB::statement("set local statement_timeout = '15s'"); return (int)DB::selectOne("select count(*) n from $q where $col::text ~ ?", [$rx])->n; });
      } catch (\Throwable $e) { $skipped[] = $x->t . '.' . $x->c; continue; }
      if ($n <= 0) continue; $hist = in_array($x->t, self::HISTORY, true); $keys = [];
      if (!$hist) { try { $kc = collect(['key', 'slug', 'name', 'title', 'code', 'id'])->first(fn($k) => \Illuminate\Support\Facades\Schema::hasColumn($x->t, $k)); if ($kc) $keys = DB::table($x->t)->whereRaw("$col::text ~ ?", [$rx])->limit(8)->pluck($kc)->map(fn($v) => mb_substr((string)$v, 0, 50))->all(); } catch (\Throwable $e) {} }
      $out[] = ['table' => $x->t, 'column' => $x->c, 'rows' => $n, 'history' => $hist, 'keys' => $keys];
    }
    return ['hits' => $out, 'skipped' => $skipped];
  }
  /** Replace the old hotline in current content only. Bengali-digit text gets Bengali digits; +880 writing becomes +8809644999000. Returns what changed. */
  public static function apply(): array {
    $done = []; $newBn = self::bn(self::NEW);
    foreach (self::scan()['hits'] as $h) {
      if ($h['history']) continue; $q = '"' . $h['table'] . '"'; $col = '"' . $h['column'] . '"'; $n = 0;
      foreach (self::OLD as $core) {
        $sep = '[ .\\-]{0,2}'; $en = implode($sep, str_split($core)); $bnc = implode($sep, preg_split('//u', self::bn($core), -1, PREG_SPLIT_NO_EMPTY));
        $steps = [['\\+880' . $sep . $en, '+880' . ltrim(self::NEW, '0')], ['880' . $sep . $en, '880' . ltrim(self::NEW, '0')], ['0?' . $en, self::NEW], ['(\\+?৮৮০' . $sep . '|০)?' . $bnc, $newBn]];
        foreach ($steps as [$from, $to]) {
          try { $n += DB::update("update $q set $col = regexp_replace($col::text, ?, ?, 'g')" . (in_array(self::type($h['table'], $h['column']), ['json', 'jsonb'], true) ? '::' . self::type($h['table'], $h['column']) : '') . " where $col::text ~ ?", [$from, $to, $from]); } catch (\Throwable $e) { $done[] = ['table' => $h['table'], 'column' => $h['column'], 'error' => mb_substr($e->getMessage(), 0, 160)]; }
        }
      }
      if ($n > 0) $done[] = ['table' => $h['table'], 'column' => $h['column'], 'rows' => $n, 'keys' => $h['keys']];
    }
    foreach (['store.settings2', 'ai.facts', 'store.home6', 'store.home4', 'store.home5'] as $c) { try { Cache::forget($c); } catch (\Throwable $e) {} }
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? 'System', 'action' => 'hotline.replace', 'subject' => 'Old hotline replaced with ' . self::NEW . ' in current content', 'meta' => json_encode($done), 'ip' => request()?->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return $done;
  }
  private static function type(string $t, string $c): string { static $m = []; return $m["$t.$c"] ??= (string)(DB::selectOne('select data_type d from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?', [$t, $c])->d ?? 'text'); }
}
