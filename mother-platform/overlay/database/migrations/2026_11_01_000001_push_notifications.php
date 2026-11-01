<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('push_subscriptions')) Schema::create('push_subscriptions', function (Blueprint $t) {
      $t->id(); $t->foreignId('customer_id')->nullable()->index(); $t->string('endpoint_hash', 64)->unique(); $t->text('endpoint'); $t->text('p256dh'); $t->text('auth');
      $t->string('ua', 200)->nullable(); $t->string('label', 40)->nullable(); $t->boolean('active')->default(true)->index(); $t->unsignedSmallInteger('fail_count')->default(0); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('push_campaigns')) Schema::create('push_campaigns', function (Blueprint $t) {
      $t->id(); $t->string('kind', 20)->default('marketing'); $t->string('type', 40)->nullable(); $t->string('title', 120); $t->text('body'); $t->string('image', 500)->nullable(); $t->string('cta', 40)->nullable(); $t->string('url', 500)->default('/');
      $t->string('audience_type', 30)->default('all'); $t->string('audience_value', 120)->nullable(); $t->timestamp('schedule_at')->nullable()->index(); $t->string('status', 20)->default('draft')->index();
      $t->unsignedInteger('last_sub_id')->default(0); $t->unsignedInteger('targeted')->default(0); $t->unsignedInteger('sent')->default(0); $t->unsignedInteger('failed')->default(0); $t->unsignedInteger('clicks')->default(0);
      $t->string('created_by', 80)->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('customer_notifications')) Schema::create('customer_notifications', function (Blueprint $t) {
      $t->id(); $t->foreignId('customer_id')->index(); $t->foreignId('campaign_id')->nullable(); $t->string('category', 20)->default('orders'); $t->string('type', 40)->nullable(); $t->string('title', 160); $t->text('body')->nullable(); $t->string('url', 500)->nullable(); $t->string('image', 500)->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamp('created_at')->nullable();
    });
    if (!Schema::hasTable('customer_notification_prefs')) Schema::create('customer_notification_prefs', function (Blueprint $t) {
      $t->foreignId('customer_id')->primary(); foreach (['orders', 'delivery', 'offers', 'coupons', 'membership', 'support'] as $c) $t->boolean($c)->default(true); $t->timestamps();
    });
  }
  public function down(): void {}
};
