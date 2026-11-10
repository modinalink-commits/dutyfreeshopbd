<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extension pool: extra PBX extensions kept in a list (not tied to an admin user). Passwords stored encrypted. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('pbx_extensions')) return;
    Schema::create('pbx_extensions', function (Blueprint $t) {
      $t->id(); $t->string('extension', 40)->unique(); $t->string('auth_username', 60)->nullable(); $t->string('display_name', 120)->nullable();
      $t->text('password_enc')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('pbx_extensions'); }
};
