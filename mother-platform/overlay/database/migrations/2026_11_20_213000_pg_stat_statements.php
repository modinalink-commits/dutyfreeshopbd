<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Backend check-up 20 Nov 2026 (owner approved the restart): turn on pg_stat_statements so the heaviest queries can be named.
 *  ALTER SYSTEM only writes postgresql.auto.conf; it takes effect at the next database restart. Nothing is changed unless the server
 *  ships the extension. down() removes the setting and the extension. A failure here never stops a deploy. */
return new class extends Migration {
  public $withinTransaction = false;
  private function run(string $sql): void { try { DB::statement($sql); } catch (\Throwable $e) { report($e); } }
  private function ok(): bool {
    try { return DB::getDriverName() === 'pgsql' && (bool)DB::selectOne("select 1 x from pg_available_extensions where name = 'pg_stat_statements'"); } catch (\Throwable $e) { return false; }
  }
  public function up(): void {
    if (!$this->ok()) return;
    try { $cur = (string)DB::selectOne("select setting from pg_settings where name = 'shared_preload_libraries'")->setting; } catch (\Throwable $e) { return; }
    if ($cur !== '' && !str_contains($cur, 'pg_stat_statements')) return; // another library is preloaded: leave it alone
    if ($cur === '') $this->run("alter system set shared_preload_libraries = 'pg_stat_statements'");
    $this->run('create extension if not exists pg_stat_statements');
  }
  public function down(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    $this->run('drop extension if exists pg_stat_statements');
    $this->run('alter system reset shared_preload_libraries');
  }
};
