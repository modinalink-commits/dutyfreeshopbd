<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mother Commerce Dialer: richer call records (dial/connect/end times, outcome, response, cancel reason, recording reference). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('call_logs')) return;
    $add = [
      'pbx_call_id' => fn(Blueprint $t, $c) => $t->string($c, 120)->nullable()->index(),
      'dial_started_at' => fn($t, $c) => $t->timestamp($c)->nullable(), 'connected_at' => fn($t, $c) => $t->timestamp($c)->nullable(), 'ended_at' => fn($t, $c) => $t->timestamp($c)->nullable(),
      'outcome' => fn($t, $c) => $t->string($c, 30)->nullable()->index(), 'response' => fn($t, $c) => $t->string($c, 40)->nullable(), 'cancel_reason' => fn($t, $c) => $t->string($c, 40)->nullable(),
      'recording_status' => fn($t, $c) => $t->string($c, 20)->default('none'), 'recording_url' => fn($t, $c) => $t->string($c, 600)->nullable(),
      'fail_reason' => fn($t, $c) => $t->string($c, 160)->nullable(), 'summary_at' => fn($t, $c) => $t->timestamp($c)->nullable(),
    ];
    foreach ($add as $c => $f) if (!Schema::hasColumn('call_logs', $c)) Schema::table('call_logs', fn(Blueprint $t) => $f($t, $c));
    if (!Schema::hasColumn('call_logs', 'order_id')) Schema::table('call_logs', fn(Blueprint $t) => $t->unsignedBigInteger('order_id')->nullable()->index());
  }
  public function down(): void {}
};
