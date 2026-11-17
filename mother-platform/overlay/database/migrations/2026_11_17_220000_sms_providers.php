<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Two SMS providers (Alpha SMS + BulkSMSBD): which provider carried a message, its request id, real delivery status and every attempt. */
return new class extends Migration {
  public function up(): void {
    Schema::table('sms_logs', function (Blueprint $t) {
      if (!Schema::hasColumn('sms_logs', 'uid')) $t->string('uid', 36)->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'provider')) $t->string('provider', 16)->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'request_id')) $t->string('request_id', 60)->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'delivery')) $t->string('delivery', 20)->nullable();
      if (!Schema::hasColumn('sms_logs', 'fallback_reason')) $t->string('fallback_reason', 250)->nullable();
      if (!Schema::hasColumn('sms_logs', 'attempts')) $t->unsignedSmallInteger('attempts')->default(0);
    });
    if (!Schema::hasTable('sms_attempts')) Schema::create('sms_attempts', function (Blueprint $t) {
      $t->id(); $t->string('uid', 36)->index(); $t->unsignedBigInteger('log_id')->nullable()->index();
      $t->string('provider', 16); $t->string('to', 20); $t->string('event', 60)->nullable();
      $t->string('state', 12); $t->string('class', 12)->nullable(); $t->string('code', 20)->nullable(); $t->string('message', 250)->nullable();
      $t->string('request_id', 60)->nullable()->index(); $t->string('delivery', 20)->nullable(); $t->string('delivery_raw', 120)->nullable();
      $t->unsignedSmallInteger('checks')->default(0); $t->timestamp('checked_at')->nullable(); $t->string('fallback_reason', 250)->nullable();
      $t->timestamps(); $t->index(['provider', 'state', 'delivery']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('sms_attempts');
    Schema::table('sms_logs', function (Blueprint $t) { foreach (['uid', 'provider', 'request_id', 'delivery', 'fallback_reason', 'attempts'] as $c) if (Schema::hasColumn('sms_logs', $c)) $t->dropColumn($c); });
  }
};
