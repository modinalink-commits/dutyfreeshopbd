<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: Port Weather. marine_weather = last good data per port and source (with the time the data is for and the time we last checked);
 *  marine_weather_warnings = official BMD warnings, read from the CAP feed or entered by the marine desk with a link to the original bulletin. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('marine_weather')) Schema::create('marine_weather', function (Blueprint $t) {
      $t->id(); $t->string('port', 20); $t->string('source', 10); $t->longText('payload')->nullable(); $t->boolean('ok')->default(false); $t->string('note', 200)->nullable();
      $t->timestamp('data_time')->nullable(); $t->timestamp('checked_at')->nullable(); $t->timestamp('ok_at')->nullable(); $t->timestamp('next_at')->nullable(); $t->string('last_modified', 60)->nullable(); $t->timestamps();
      $t->unique(['port', 'source']);
    });
    if (!Schema::hasTable('marine_weather_warnings')) Schema::create('marine_weather_warnings', function (Blueprint $t) {
      $t->id(); $t->string('source', 10)->default('desk'); $t->string('cap_guid', 120)->nullable()->unique(); $t->string('cap_identifier', 190)->nullable()->index();
      $t->string('ports', 60)->default(''); $t->unsignedTinyInteger('signal')->nullable(); $t->string('title', 200); $t->string('event', 80)->nullable(); $t->string('severity', 20)->nullable(); $t->string('bulletin_url', 300);
      $t->timestamp('issued_at')->nullable(); $t->timestamp('expires_at')->nullable()->index(); $t->timestamp('ended_at')->nullable(); $t->string('ended_by', 80)->nullable();
      $t->timestamp('ack_at')->nullable(); $t->string('ack_by', 80)->nullable(); $t->string('created_by', 80)->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('marine_weather_warnings'); Schema::dropIfExists('marine_weather'); }
};
