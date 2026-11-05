<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('app_installs')) return;
    Schema::table('app_installs', function ($t) {
      if (!Schema::hasColumn('app_installs', 'login_customer_id')) $t->unsignedBigInteger('login_customer_id')->nullable()->index();
      if (!Schema::hasColumn('app_installs', 'login_at')) $t->timestamp('login_at')->nullable();
    });
    try { DB::statement('create index if not exists app_installs_last_seen_idx on app_installs (last_seen_at)'); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
