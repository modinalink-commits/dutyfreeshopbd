<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

/** Read-only PostgreSQL health report for super admins: settings, connections, table sizes, scan ratios, autovacuum, and EXPLAIN ANALYZE of the known hot queries (inside a READ ONLY transaction with a timeout). Nothing is changed. */
class DbHealthController extends Controller {
  private function q(string $sql, array $b = []): array { try { return DB::select($sql, $b); } catch (\Throwable $e) { return [(object)['error' => substr($e->getMessage(), 0, 200)]]; } }
  private function tbl(array $rows): string { if (!$rows) return "(none)\n"; $o = ''; foreach ($rows as $r) $o .= implode(' | ', array_map(fn($v) => is_null($v) ? '-' : (string)$v, (array)$r))."\n"; return $o; }
  public function index() {
    abort_unless(auth()->check() && in_array(auth()->user()->role, ['super', 'admin'], true), 403);
    // ?live=1: a light sample for finding the heavy queries (pg_stat_statements is not installed): what is running right now + the scan counters of the hot tables. Read only.
    // ?recon=1: read-only stock / ledger consistency numbers (backend check-up). Changes nothing.
    if (request()->boolean('recon')) {
      $b = "(select product_id, sum(qty_remaining) q, sum(qty_remaining * unit_cost) v from stock_batches where status = 'open' and qty_remaining > 0 group by 1)";
      return response()->json([
        'in_stock_products' => $this->q("select count(*) n, sum(stock) units, round(sum(stock * coalesce(nullif(avg_cost,0), cost, 0))::numeric, 2) avg_value from products where stock > 0"),
        'open_batches' => $this->q("select count(distinct product_id) products, sum(qty_remaining) units, round(sum(qty_remaining * unit_cost)::numeric, 2) fifo_value from stock_batches where status = 'open' and qty_remaining > 0"),
        'stock_vs_batches_diff' => $this->q("select count(*) products, coalesce(sum(abs(d)), 0) units from (select coalesce(p.stock, 0) - coalesce(b.q, 0) d from products p full join $b b on b.product_id = p.id where coalesce(p.stock, 0) > 0 or coalesce(b.q, 0) > 0) x where d <> 0"),
        'stock_vs_batches_sample' => $this->q("select p.id, left(p.name, 40) name, p.stock, coalesce(b.q, 0) batch_qty from products p full join $b b on b.product_id = p.id where (coalesce(p.stock, 0) > 0 or coalesce(b.q, 0) > 0) and coalesce(p.stock, 0) <> coalesce(b.q, 0) order by abs(coalesce(p.stock, 0) - coalesce(b.q, 0)) desc limit 12"),
        'negative_stock' => $this->q("select count(*) n from products where stock < 0"),
        'stock_vs_last_log' => $this->q("select count(*) products from products p join lateral (select after_qty from stock_logs l where l.product_id = p.id order by l.id desc limit 1) l on true where p.stock > 0 and l.after_qty <> p.stock"),
        'stock_vs_last_log_sample' => $this->q("select p.id, left(p.name, 40) name, p.stock, l.after_qty last_log_qty from products p join lateral (select after_qty from stock_logs l where l.product_id = p.id order by l.id desc limit 1) l on true where p.stock > 0 and l.after_qty <> p.stock limit 10"),
        'suppliers' => $this->q("select count(*) n from suppliers"),
        'entries_without_account' => $this->q("select count(*) n from account_entries e left join money_accounts a on a.id = e.account_id where a.id is null"),
        'entries_bad_amount' => $this->q("select count(*) n from account_entries where amount is null or amount < 0 or direction not in ('income', 'expense')"),
      ])->header('Cache-Control', 'no-store');
    }
    // ?top=1: the heaviest statements since the last reset (needs pg_stat_statements, loaded after a database restart). Read only.
    if (request()->boolean('top')) return response()->json([
      'preload' => $this->q("select setting from pg_settings where name = 'shared_preload_libraries'"),
      'available' => $this->q("select default_version, installed_version from pg_available_extensions where name = 'pg_stat_statements'"),
      'by_time' => $this->q("select calls, round(total_exec_time::numeric / 1000, 1) total_s, round(mean_exec_time::numeric, 1) mean_ms, rows, shared_blks_read blks_read, left(regexp_replace(query, '\s+', ' ', 'g'), 300) q from pg_stat_statements where dbid = (select oid from pg_database where datname = current_database()) order by total_exec_time desc limit 20"),
      'by_mean' => $this->q("select calls, round(mean_exec_time::numeric, 1) mean_ms, round(total_exec_time::numeric / 1000, 1) total_s, left(regexp_replace(query, '\s+', ' ', 'g'), 300) q from pg_stat_statements where dbid = (select oid from pg_database where datname = current_database()) and calls >= 5 order by mean_exec_time desc limit 12"),
    ])->header('Cache-Control', 'no-store');
    if (request()->boolean('live')) return response()->json([
      'at' => microtime(true),
      'active' => $this->q("select pid, round(extract(epoch from clock_timestamp() - query_start) * 1000) ms, application_name app, left(regexp_replace(query, '\s+', ' ', 'g'), 420) q from pg_stat_activity where datname = current_database() and state = 'active' and pid <> pg_backend_pid() order by query_start limit 12"),
      'tables' => $this->q("select relname, seq_scan, seq_tup_read, idx_scan from pg_stat_user_tables where relname in ('order_items','products','orders','settings','category_nodes','purchase_items','sms_logs','customers','chat_messages','store_products')"),
      'reset' => $this->q("select to_char(stats_reset, 'YYYY-MM-DD HH24:MI') r from pg_stat_database where datname = current_database()"),
    ])->header('Cache-Control', 'no-store');
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
