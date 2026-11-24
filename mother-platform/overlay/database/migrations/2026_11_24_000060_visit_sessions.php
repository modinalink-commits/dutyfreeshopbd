<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per storefront visit (browser session that ran our script): where it came from, where it landed, how far it got. Anonymous: no IP, no phone, referrer host only. Kept 60 days. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('visit_sessions')) return;
    Schema::create('visit_sessions', function (Blueprint $t) {
      $t->id(); $t->date('day'); $t->string('sid', 24)->unique(); $t->string('vid', 40)->nullable();
      $t->string('source', 24); $t->string('campaign', 80)->nullable(); $t->string('ref_host', 120)->nullable();
      $t->string('land_type', 12); $t->string('land_path', 160)->nullable(); $t->string('device', 8); $t->string('cc', 2)->nullable();
      $t->unsignedSmallInteger('pages')->default(1); $t->unsignedInteger('secs')->default(0); $t->unsignedTinyInteger('step')->default(0);
      $t->boolean('missing')->default(false); $t->boolean('oos')->default(false); $t->string('err', 120)->nullable(); $t->unsignedInteger('load_ms')->nullable();
      $t->timestamps(); $t->index(['day', 'source']);
    });
  }
  public function down(): void { Schema::dropIfExists('visit_sessions'); }
};
