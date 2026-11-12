<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Sunglass Virtual Try-On: product overlay PNGs (transparent cut-outs) + customer selfies kept ONLY after separate explicit consent (admin-only, auto-expiring). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('tryon_assets')) Schema::create('tryon_assets', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->unique(); $t->string('status', 16)->default('pending'); $t->string('png')->nullable();
      $t->unsignedInteger('w')->nullable(); $t->unsignedInteger('h')->nullable(); $t->text('error')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('tryon_photos')) Schema::create('tryon_photos', function (Blueprint $t) {
      $t->id(); $t->string('token_hash', 64)->index(); $t->string('file'); $t->string('mime', 20); $t->unsignedInteger('bytes');
      $t->unsignedBigInteger('product_id')->nullable(); $t->string('consent_version', 20)->default('v1'); $t->timestamp('consent_at')->nullable();
      $t->timestamp('expires_at')->nullable()->index(); $t->string('ip_hash', 64)->nullable(); $t->timestamps();
    });
    if (!DB::table('settings')->where('key', 'tryon_retention_days')->exists()) DB::table('settings')->insert(['key' => 'tryon_retention_days', 'value' => '30', 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void { Schema::dropIfExists('tryon_photos'); Schema::dropIfExists('tryon_assets'); }
};
