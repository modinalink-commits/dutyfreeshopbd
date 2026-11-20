<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Backend check-up 20 Nov 2026: planner settings for this database only (new connections pick them up, no restart).
 *  The server runs on SSD but PostgreSQL still had the spinning-disk default random_page_cost = 4, which makes it prefer full table scans
 *  (products: 1 million full scans, order_items: 94 billion rows read by scans in 8 days), and JIT compiling, which costs CPU on every heavy web query.
 *  down() puts the server defaults back. A failure here never stops a deploy. */
return new class extends Migration {
  const SET = ['random_page_cost' => '1.1', 'jit' => 'off', 'effective_cache_size' => '6GB'];
  private function run(string $sql): void { try { DB::statement($sql); } catch (\Throwable $e) { report($e); } }
  private function db(): string { return '"' . str_replace('"', '""', (string)DB::selectOne('select current_database() d')->d) . '"'; }
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    try { $db = $this->db(); } catch (\Throwable $e) { return; }
    foreach (self::SET as $k => $v) $this->run("alter database $db set $k = '$v'");
  }
  public function down(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    try { $db = $this->db(); } catch (\Throwable $e) { return; }
    foreach (array_keys(self::SET) as $k) $this->run("alter database $db reset $k");
  }
};
