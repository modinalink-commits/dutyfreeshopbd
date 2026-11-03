<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

/** Read-only PostgreSQL health report for super admins: settings, connections, table sizes, scan ratios, autovacuum, and EXPLAIN ANALYZE of the known hot queries (inside a READ ONLY transaction with a timeout). Nothing is changed. */
class DbHealthController extends Controller {
  private function q(string $sql, array $b = []): array { try { return DB::select($sql, $b); } catch (\Throwable $e) { return [(object)['error' => substr($e->getMessage(), 0, 200)]]; } }
  private function tbl(array $rows): string { if (!$rows) return "(none)\n"; $o = ''; foreach ($rows as $r) $o .= implode(' | ', array_map(fn($v) => is_null($v) ? '-' : (string)$v, (array)$r))."\n"; return $o; }
  public function index() {
    abort_unless(auth()->check() && in_array(auth()->user()->role, ['super', 'admin'], true), 403);
    $o = "DB HEALTH (read-only) ".now()->toDateTimeString()."\n\n";
    $o .= "== version\n".$this->tbl($this->q('select version()'));
    $o .= "\n== key settings\n".$this->tbl($this->q("select name, setting, unit from pg_settings where name in ('shared_buffers','work_mem','effective_cache_size','maintenance_work_mem','max_connections','random_page_cost','effective_io_concurrency','autovacuum','checkpoint_completion_target','wal_buffers','default_statistics_target','jit')"));
    $o .= "\n== connections by state\n".$this->tbl($this->q('select state, count(*) from pg_stat_activity where datname = current_database() group by 1 order by 2 desc'));
    $o .= "\n== cache hit ratio (table/index blocks)\n".$this->tbl($this->q("select round(sum(heap_blks_hit)::numeric / nullif(sum(heap_blks_hit)+sum(heap_blks_read),0) * 100, 2) as table_hit_pct from pg_statio_user_tables"));
    $o .= $this->tbl($this->q("select round(sum(idx_blks_hit)::numeric / nullif(sum(idx_blks_hit)+sum(idx_blks_read),0) * 100, 2) as index_hit_pct from pg_statio_user_indexes"));
    $o .= "\n== database size\n".$this->tbl($this->q('select pg_size_pretty(pg_database_size(current_database()))'));
    $o .= "\n== top 15 tables: name | rows(est) | total size | seq_scan | idx_scan | dead_tup | last_autovacuum | last_autoanalyze\n".$this->tbl($this->q("select relname, n_live_tup, pg_size_pretty(pg_total_relation_size(relid)), seq_scan, idx_scan, n_dead_tup, to_char(last_autovacuum,'MM-DD HH24:MI'), to_char(last_autoanalyze,'MM-DD HH24:MI') from pg_stat_user_tables order by pg_total_relation_size(relid) desc limit 15"));
    $o .= "\n== tables with most sequential-scanned rows (seq_tup_read, seq_scan)\n".$this->tbl($this->q("select relname, seq_scan, seq_tup_read, idx_scan, n_live_tup from pg_stat_user_tables where seq_scan > 0 order by seq_tup_read desc limit 10"));
    $o .= "\n== indexes on orders: name | size | scans\n".$this->tbl($this->q("select indexrelname, pg_size_pretty(pg_relation_size(indexrelid)), idx_scan from pg_stat_user_indexes where relname = 'orders' order by idx_scan desc"));
    $o .= "\n== pg_stat_statements\n".$this->tbl($this->q("select round(total_exec_time::numeric) total_ms, calls, round(mean_exec_time::numeric,1) mean_ms, left(regexp_replace(query, '\\s+', ' ', 'g'), 110) q from pg_stat_statements order by total_exec_time desc limit 12"));
    $tests = [
      'orders list newest 50' => "select * from orders order by created_at desc, id desc limit 50",
      'orders status New newest 50' => "select * from orders where status = 'New' order by created_at desc, id desc limit 50",
      'dashboard new-customer count (30d)' => "select count(distinct phone) from orders where created_at >= now() - interval '30 days' and status <> 'Cancel' and not exists (select 1 from orders p where p.phone = orders.phone and p.created_at < orders.created_at)",
      'orders by phone (checkout / customer history)' => "select count(*) from orders where phone = (select phone from orders order by id desc limit 1)",
      'recent orders by phone 10 min (checkout limiter)' => "select count(*) from orders where phone = (select phone from orders order by id desc limit 1) and created_at > now() - interval '10 minutes'",
      'daily trend 30d' => "select to_char(created_at + interval '6 hours','YYYY-MM-DD') d, count(*), sum(total) from orders where created_at >= now() - interval '30 days' group by 1 order by 1",
      'duplicate guard lookup (fingerprint, 90s)' => "select id from orders where order_fingerprint = 'x' and created_at > now() - interval '90 seconds' limit 1",
    ];
    foreach ($tests as $name => $sql) {
      $o .= "\n== EXPLAIN ANALYZE: $name\n";
      try {
        DB::beginTransaction(); DB::statement('SET TRANSACTION READ ONLY'); DB::statement('SET LOCAL statement_timeout = 8000');
        foreach (DB::select('EXPLAIN (ANALYZE, BUFFERS, TIMING OFF) '.$sql) as $r) $o .= ((array)$r)['QUERY PLAN']."\n";
      } catch (\Throwable $e) { $o .= 'error: '.substr($e->getMessage(), 0, 200)."\n"; }
      finally { try { DB::rollBack(); } catch (\Throwable $e) {} }
    }
    return response($o, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
  }
}
