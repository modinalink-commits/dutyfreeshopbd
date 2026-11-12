<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Deployment 48a0bb2 finished and went live, but the status checker marked it "Interrupted" mid-run. Correct that one record and clear its alert (verified live + healthy). */
return new class extends Migration {
  public function up(): void {
    DB::table('deployments')->where('sha', 'like', '48a0bb2%')->where('status', 'Failed')->where('error', 'like', 'Interrupted:%')
      ->update(['status' => 'Completed', 'stage' => 'Completed', 'progress' => 100, 'error' => null, 'updated_at' => now()]);
    try { \App\Support\DeployHealth::resolve('deploy_failed:', 'System (verified: release is live)', true); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
