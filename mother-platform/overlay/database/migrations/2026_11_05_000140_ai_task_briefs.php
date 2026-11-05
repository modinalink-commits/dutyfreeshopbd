<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** AI Brief Builder: structured briefs for AI tasks, their versions (with the AI result and the context snapshot) and templates. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (!Schema::hasTable('ai_task_briefs')) Schema::create('ai_task_briefs', function (Blueprint $t) {
        $t->id(); $t->string('title', 200); $t->string('type', 40)->nullable(); $t->string('status', 14)->default('Draft'); $t->unsignedInteger('version')->default(1);
        $t->longText('data'); $t->unsignedSmallInteger('completeness')->default(0); $t->longText('result')->nullable(); $t->string('result_status', 14)->nullable(); $t->text('result_meta')->nullable(); $t->boolean('accepted')->default(false);
        $t->string('created_by', 80)->nullable(); $t->string('updated_by', 80)->nullable(); $t->timestamps(); $t->index('updated_at');
      });
      if (!Schema::hasTable('ai_task_brief_versions')) Schema::create('ai_task_brief_versions', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('brief_id')->index(); $t->unsignedInteger('version'); $t->longText('data'); $t->text('changes')->nullable(); $t->longText('result')->nullable(); $t->longText('context')->nullable();
        $t->string('model', 60)->nullable(); $t->unsignedInteger('tokens_in')->default(0); $t->unsignedInteger('tokens_out')->default(0); $t->string('by', 80)->nullable(); $t->timestamps();
      });
      if (!Schema::hasTable('ai_task_templates')) Schema::create('ai_task_templates', function (Blueprint $t) {
        $t->id(); $t->string('name', 120); $t->string('type', 40)->nullable(); $t->longText('data'); $t->string('created_by', 80)->nullable(); $t->timestamps();
      });
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'AI Brief Builder tables failed', 'subject' => mb_substr($e->getMessage(), 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
