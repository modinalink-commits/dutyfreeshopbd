<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Free Call (WebRTC customer <-> staff voice calls): calls, events, notes, signalling messages and agent availability.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('customer_calls')) Schema::create('customer_calls', function (Blueprint $t) {
      $t->id(); $t->string('call_uuid', 40)->unique(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('guest_name', 80)->nullable(); $t->string('mobile', 15)->index();
      $t->unsignedBigInteger('order_id')->nullable()->index(); $t->unsignedBigInteger('product_id')->nullable(); $t->text('source_url')->nullable(); $t->string('source_type', 20)->nullable();
      $t->unsignedBigInteger('assigned_staff_id')->nullable()->index(); $t->unsignedBigInteger('ring_staff_id')->nullable(); $t->text('tried_staff')->nullable();
      $t->string('status', 20)->default('waiting')->index();
      $t->timestamp('requested_at')->nullable()->index(); $t->timestamp('ringing_at')->nullable(); $t->timestamp('ring_expires_at')->nullable(); $t->timestamp('answered_at')->nullable(); $t->timestamp('connected_at')->nullable(); $t->timestamp('ended_at')->nullable();
      $t->unsignedInteger('waiting_seconds')->nullable(); $t->unsignedInteger('duration_seconds')->nullable(); $t->string('disconnect_reason', 40)->nullable();
      $t->string('customer_session_id', 64)->index(); $t->timestamp('cust_seen_at')->nullable(); $t->timestamp('agent_seen_at')->nullable(); $t->text('context')->nullable();
      $t->timestamps();
    });
    if (!Schema::hasTable('customer_call_events')) Schema::create('customer_call_events', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('call_id')->index(); $t->string('event_type', 30); $t->unsignedBigInteger('staff_id')->nullable(); $t->text('metadata')->nullable(); $t->timestamp('created_at')->nullable();
    });
    if (!Schema::hasTable('customer_call_notes')) Schema::create('customer_call_notes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('call_id')->index(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->unsignedBigInteger('order_id')->nullable(); $t->unsignedBigInteger('staff_id')->nullable();
      $t->text('note')->nullable(); $t->string('outcome', 30)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('customer_call_signals')) Schema::create('customer_call_signals', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('call_id')->index(); $t->string('to_side', 10); $t->string('type', 20); $t->text('payload')->nullable(); $t->timestamp('created_at')->nullable();
    });
    if (!Schema::hasTable('free_call_agents')) Schema::create('free_call_agents', function (Blueprint $t) {
      $t->unsignedBigInteger('staff_id')->primary(); $t->string('voice_status', 12)->default('offline'); $t->timestamp('last_available_at')->nullable(); $t->unsignedBigInteger('active_call_id')->nullable(); $t->timestamp('last_seen_at')->nullable(); $t->timestamp('updated_at')->nullable();
    });
  }
  public function down(): void {}
};
