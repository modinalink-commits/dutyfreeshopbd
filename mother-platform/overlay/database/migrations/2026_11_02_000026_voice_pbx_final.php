<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('voice_call_attempts', function (Blueprint $t) {
      foreach (['pbx_job_uuid' => fn() => $t->string('pbx_job_uuid', 80)->nullable(), 'recording_name' => fn() => $t->string('recording_name', 255)->nullable(), 'webhook_payload' => fn() => $t->text('webhook_payload')->nullable(),
        'approx_duration' => fn() => $t->unsignedInteger('approx_duration')->nullable(), 'final_duration' => fn() => $t->unsignedInteger('final_duration')->nullable(), 'ended_at' => fn() => $t->timestamp('ended_at')->nullable(), 'error' => fn() => $t->string('error', 255)->nullable()] as $c => $f)
        if (!Schema::hasColumn('voice_call_attempts', $c)) $f();
    });
    if (!Schema::hasTable('voice_optouts')) Schema::create('voice_optouts', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('phone', 15)->unique(); $t->timestamp('opted_out_at')->nullable(); $t->string('source', 30)->default('voice_offer'); $t->string('call_id', 80)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
