<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('customers')) {
      try { DB::statement('ALTER TABLE customers ALTER COLUMN phone DROP NOT NULL'); } catch (\Throwable $e) {}
      foreach (['email_verified_at' => fn(Blueprint $t) => $t->timestamp('email_verified_at')->nullable(), 'profile_image' => fn(Blueprint $t) => $t->string('profile_image', 500)->nullable(), 'last_login_at' => fn(Blueprint $t) => $t->timestamp('last_login_at')->nullable()] as $c => $f)
        if (!Schema::hasColumn('customers', $c)) Schema::table('customers', fn(Blueprint $t) => $f($t));
      try { DB::statement('CREATE INDEX IF NOT EXISTS customers_email_lower_idx ON customers (lower(email))'); } catch (\Throwable $e) {}
    }
    if (!Schema::hasTable('customer_auth_providers')) Schema::create('customer_auth_providers', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->string('provider', 20); $t->string('provider_user_id', 120); $t->string('provider_email', 160)->nullable(); $t->string('provider_name', 160)->nullable(); $t->string('avatar', 500)->nullable(); $t->timestamps();
      $t->unique(['provider', 'provider_user_id']);
    });
    if (!Schema::hasTable('customer_login_history')) Schema::create('customer_login_history', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('provider', 20); $t->string('ip', 45)->nullable(); $t->string('device', 160)->nullable(); $t->string('status', 12)->default('success'); $t->timestamps();
    });
    if (!Schema::hasTable('customer_password_resets')) Schema::create('customer_password_resets', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->string('token_hash', 64)->index(); $t->timestamp('expires_at'); $t->timestamp('used_at')->nullable(); $t->timestamps();
    });
    // existing email-password customers get an "email" provider row
    if (Schema::hasTable('customers')) DB::statement("INSERT INTO customer_auth_providers (customer_id, provider, provider_user_id, provider_email, provider_name, created_at, updated_at)
      SELECT id, 'email', lower(email), email, name, now(), now() FROM customers WHERE password IS NOT NULL AND email IS NOT NULL AND email <> '' ON CONFLICT DO NOTHING");
    $s = DB::table('settings')->pluck('value', 'key')->all(); foreach (['auth_email_on' => '1', 'auth_fb_on' => '0'] as $k => $v) if (!array_key_exists($k, $s)) DB::table('settings')->insert(['key' => $k, 'value' => $v, 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void {}
};
