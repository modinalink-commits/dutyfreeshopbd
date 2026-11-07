<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner asked to remove the MCP / OAuth experiment: drop its tables and setting. */
return new class extends Migration {
  public function up(): void {
    try { foreach (['mcp_refresh_tokens', 'mcp_oauth_codes', 'mcp_clients', 'mcp_audit', 'mcp_tokens'] as $t) Schema::dropIfExists($t); DB::table('settings')->where('key', 'mcp_enabled')->delete(); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
