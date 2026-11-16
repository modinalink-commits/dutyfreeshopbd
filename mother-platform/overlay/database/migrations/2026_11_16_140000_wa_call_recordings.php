<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Owner 16 Nov 2026: recordings of WhatsApp calls answered in the admin (recorded in the answering browser, both sides mixed). Additive; down() drops only these tables (files stay on disk). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('wa_call_recordings')) Schema::create('wa_call_recordings', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('call_id')->unique(); // wa_calls.id: one recording per call
      $t->string('status', 20)->default('recording')->index(); // recording | complete | incomplete | failed | deleted | expired
      $t->string('path', 255)->nullable(); $t->string('mime', 60)->nullable(); $t->unsignedBigInteger('bytes')->default(0); $t->unsignedInteger('chunks')->default(0); $t->unsignedInteger('duration')->nullable();
      $t->string('customer_wa', 40)->nullable()->index(); $t->string('customer_phone', 20)->nullable()->index(); $t->string('customer_name', 160)->nullable(); $t->string('phone_id', 40)->nullable(); $t->string('business_number', 60)->nullable();
      $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->unsignedBigInteger('thread_id')->nullable(); $t->unsignedBigInteger('order_id')->nullable()->index();
      $t->timestamp('notice_at')->nullable(); $t->string('verify_result', 300)->nullable(); $t->string('verify_by', 120)->nullable(); $t->string('fail_reason', 200)->nullable();
      $t->timestamp('started_at')->nullable(); $t->timestamp('ended_at')->nullable(); $t->timestamp('expires_at')->nullable()->index();
      $t->string('removed_by', 120)->nullable(); $t->timestamp('removed_at')->nullable();
      $t->timestamps();
    });
    if (!Schema::hasTable('wa_call_recording_logs')) Schema::create('wa_call_recording_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('recording_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable();
      $t->string('action', 30); $t->string('detail', 200)->nullable(); $t->string('ip', 60)->nullable(); $t->timestamp('created_at')->nullable()->index();
    });
  }
  public function down(): void { Schema::dropIfExists('wa_call_recording_logs'); Schema::dropIfExists('wa_call_recordings'); }
};
