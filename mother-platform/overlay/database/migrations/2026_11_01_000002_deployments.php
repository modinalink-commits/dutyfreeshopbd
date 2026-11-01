<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('deployments')) return;
    Schema::create('deployments', function (Blueprint $t) {
      $t->id(); $t->string('sha', 40)->nullable()->index(); $t->string('version', 20)->nullable();
      $t->string('subject', 250)->nullable(); $t->text('description')->nullable(); $t->text('notes')->nullable();
      $t->string('source', 12)->default('manual'); $t->string('status', 20)->default('Queued')->index();
      $t->unsignedTinyInteger('progress')->default(0); $t->string('stage', 30)->nullable();
      $t->string('release_id', 60)->nullable(); $t->string('from_release', 60)->nullable(); $t->boolean('breaking')->default(false);
      $t->text('error')->nullable(); $t->text('log')->nullable(); $t->string('requested_by', 80)->nullable();
      $t->timestamp('started_at')->nullable(); $t->timestamp('finished_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('deployments'); }
};
