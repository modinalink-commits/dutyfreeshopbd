<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Deployment Center v2: issue tracking table + deployment type column. Additive only. */
return new class extends Migration {
  public $withinTransaction = false;
  private function note(string $m): void { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Deployment Center migration problem', 'subject' => mb_substr($m, 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
  public function up(): void {
    try {
      if (!Schema::hasTable('deploy_issues')) Schema::create('deploy_issues', function (Blueprint $t) {
        $t->id(); $t->string('fingerprint', 90)->index(); $t->string('title', 200); $t->string('severity', 10)->default('warning'); $t->string('service', 60)->nullable();
        $t->text('description')->nullable(); $t->text('cause')->nullable(); $t->text('action')->nullable();
        $t->string('status', 14)->default('open')->index(); $t->unsignedBigInteger('deployment_id')->nullable()->index();
        $t->timestamp('detected_at')->nullable(); $t->timestamp('resolved_at')->nullable(); $t->string('resolved_by', 80)->nullable(); $t->text('note')->nullable(); $t->timestamps();
      });
    } catch (\Throwable $e) { $this->note('deploy_issues: ' . $e->getMessage()); }
    try {
      if (Schema::hasTable('deployments') && !Schema::hasColumn('deployments', 'dtype')) Schema::table('deployments', function (Blueprint $t) { $t->string('dtype', 12)->nullable(); });
    } catch (\Throwable $e) { $this->note('deployments.dtype: ' . $e->getMessage()); }
  }
  public function down(): void {}
};
