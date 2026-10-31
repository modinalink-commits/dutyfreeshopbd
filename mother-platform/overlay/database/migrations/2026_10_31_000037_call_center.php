<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sip_accounts')) Schema::create('sip_accounts', function (Blueprint $t) {
      $t->id(); $t->string('account_name', 120)->nullable(); $t->string('sip_server', 255)->nullable(); $t->string('wss_url', 400)->nullable();
      $t->string('username', 120)->nullable(); $t->string('auth_username', 120)->nullable(); $t->text('password_enc')->nullable(); $t->string('domain', 255)->nullable();
      $t->string('display_name', 120)->nullable(); $t->string('outbound_proxy', 255)->nullable(); $t->string('dial_prefix', 20)->nullable(); $t->string('caller_id', 60)->nullable();
      $t->string('stun_server', 255)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('call_logs')) Schema::create('call_logs', function (Blueprint $t) {
      $t->id(); $t->string('direction', 10)->default('out'); $t->string('phone', 40)->index(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('customer_name', 160)->nullable();
      $t->string('extension', 40)->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable();
      $t->timestamp('started_at')->nullable()->index(); $t->integer('duration')->default(0); $t->string('status', 20)->default('failed')->index(); $t->text('note')->nullable(); $t->unsignedBigInteger('order_id')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void {}
};
