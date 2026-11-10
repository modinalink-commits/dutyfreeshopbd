<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Call Clients billing cycle: per-client cycle (start date + length), optional prepaid minutes and rate, and one CDR row per answered call reported by the relay. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('voice_carrier_clients')) {
      Schema::table('voice_carrier_clients', function (Blueprint $t) {
        if (!Schema::hasColumn('voice_carrier_clients', 'cycle_start')) $t->date('cycle_start')->nullable();
        if (!Schema::hasColumn('voice_carrier_clients', 'cycle_days')) $t->unsignedSmallInteger('cycle_days')->default(30);
        if (!Schema::hasColumn('voice_carrier_clients', 'prepaid_minutes')) $t->unsignedInteger('prepaid_minutes')->nullable();
        if (!Schema::hasColumn('voice_carrier_clients', 'rate_per_min')) $t->decimal('rate_per_min', 8, 2)->nullable();
      });
    }
    if (!Schema::hasTable('voice_call_cdrs')) {
      Schema::create('voice_call_cdrs', function (Blueprint $t) {
        $t->id(); $t->string('uuid', 40)->unique(); $t->unsignedBigInteger('client_id')->nullable();
        $t->string('dest', 20); $t->unsignedInteger('billsec'); $t->timestamp('ended_at')->useCurrent(); $t->timestamps();
        $t->index(['client_id', 'ended_at']);
      });
    }
  }
  public function down(): void {
    Schema::dropIfExists('voice_call_cdrs');
    if (Schema::hasTable('voice_carrier_clients')) Schema::table('voice_carrier_clients', function (Blueprint $t) { $t->dropColumn(['cycle_start', 'cycle_days', 'prepaid_minutes', 'rate_per_min']); });
  }
};
