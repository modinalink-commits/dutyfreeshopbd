<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner 18 Nov 2026: staff "reason for idle time" entries leave the Active Issues list for good; they live in the monthly staff record. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('deploy_issues')) return;
    DB::table('deploy_issues')->where('fingerprint', 'like', 'staff_idle:%')->whereIn('status', ['open', 'investigating'])
      ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => 'System (moved to monthly staff record)', 'updated_at' => now()]);
  }
  public function down(): void {}
};
