<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI order-confirmation calls (19 Nov 2026): one row per call attempt, tied to the order version the customer heard. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_call_verifications')) Schema::create('order_call_verifications', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('version_hash', 40)->nullable(); $t->unsignedSmallInteger('attempt')->default(1);
      $t->string('phone', 15)->index(); $t->string('pbx_call_id', 80)->nullable()->index();
      // queued | calling | answered | confirmed | pending_requested | no_input | no_answer | busy | failed | stale
      $t->string('status', 20)->default('queued')->index(); $t->string('digit', 3)->nullable(); $t->string('cause', 250)->nullable();
      $t->timestamp('started_at')->nullable(); $t->timestamp('answered_at')->nullable(); $t->timestamp('ended_at')->nullable(); $t->unsignedInteger('duration')->default(0);
      $t->timestamp('next_attempt_at')->nullable(); $t->string('recording_ref', 255)->nullable(); $t->boolean('test')->default(false); $t->text('webhook_payload')->nullable(); $t->timestamps();
      $t->index(['order_id', 'version_hash']);
    });
  }
  public function down(): void { Schema::dropIfExists('order_call_verifications'); }
};
