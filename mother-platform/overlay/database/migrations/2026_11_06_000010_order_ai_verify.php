<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI Verify: latest result per order, one history row per run, and the individual checks of each run. Additive; no secrets stored. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_ai_verifications')) Schema::create('order_ai_verifications', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->unique(); $t->unsignedBigInteger('customer_id')->nullable();
      $t->smallInteger('verification_score')->default(0); $t->string('verification_status', 20)->default('pending')->index(); $t->string('risk_level', 12)->nullable();
      $t->smallInteger('customer_reliability')->nullable(); $t->smallInteger('fraud_score')->nullable();
      $t->jsonb('reason_codes')->nullable(); $t->jsonb('summary')->nullable(); $t->string('rule_version', 20)->nullable(); $t->string('fingerprint', 40)->nullable();
      $t->timestamp('verified_at')->nullable(); $t->string('triggered_by', 60)->nullable(); $t->integer('run_count')->default(0);
      $t->string('override_status', 20)->nullable(); $t->string('override_reason', 300)->nullable(); $t->string('override_by', 120)->nullable(); $t->timestamp('override_at')->nullable();
      $t->timestamps();
    });
    if (!Schema::hasTable('order_ai_verification_history')) Schema::create('order_ai_verification_history', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->integer('run_no')->default(1); $t->string('prev_status', 20)->nullable(); $t->string('status', 20);
      $t->smallInteger('score')->default(0); $t->string('risk_level', 12)->nullable(); $t->smallInteger('customer_reliability')->nullable(); $t->smallInteger('fraud_score')->nullable();
      $t->jsonb('reason_codes')->nullable(); $t->jsonb('snapshot')->nullable(); $t->string('rule_version', 20)->nullable(); $t->string('triggered_by', 60)->nullable();
      $t->string('user_name', 120)->nullable(); $t->string('note', 300)->nullable(); $t->timestamp('created_at')->nullable();
    });
    if (!Schema::hasTable('order_ai_verification_checks')) Schema::create('order_ai_verification_checks', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('history_id')->index(); $t->unsignedBigInteger('order_id')->index(); $t->string('grp', 24); $t->string('code', 40); $t->string('result', 8);
      $t->string('title', 120); $t->text('detail')->nullable(); $t->smallInteger('points')->default(0);
    });
  }
  public function down(): void {}
};
