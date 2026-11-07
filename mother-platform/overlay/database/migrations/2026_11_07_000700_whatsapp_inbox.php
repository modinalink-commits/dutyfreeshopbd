<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('wa_conversations')) Schema::create('wa_conversations', function (Blueprint $t) {
      $t->id(); $t->string('wa_id', 24)->unique(); $t->string('name', 160)->nullable(); $t->unsignedBigInteger('customer_id')->nullable()->index();
      $t->unsignedBigInteger('assigned_to')->nullable()->index(); $t->string('assigned_name', 80)->nullable(); $t->string('status', 10)->default('open')->index();
      $t->timestamp('last_message_at')->nullable()->index(); $t->timestamp('last_inbound_at')->nullable(); $t->unsignedInteger('unread')->default(0); $t->string('last_text', 200)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('wa_messages')) Schema::create('wa_messages', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('conversation_id')->index(); $t->string('wamid', 120)->nullable()->unique(); $t->string('direction', 3); $t->string('type', 20)->default('text');
      $t->text('body')->nullable(); $t->string('media_id', 120)->nullable(); $t->string('mime', 80)->nullable(); $t->string('template_name', 120)->nullable();
      $t->string('status', 12)->default('received'); $t->string('error', 300)->nullable(); $t->string('sent_by', 80)->nullable(); $t->unsignedBigInteger('campaign_id')->nullable()->index(); $t->timestamp('ts')->nullable()->index(); $t->timestamps();
    });
    if (!Schema::hasTable('wa_templates')) Schema::create('wa_templates', function (Blueprint $t) {
      $t->id(); $t->string('name', 120); $t->string('language', 16); $t->string('category', 30)->nullable(); $t->string('status', 20)->nullable(); $t->text('body')->nullable(); $t->unsignedSmallInteger('params')->default(0); $t->timestamps(); $t->unique(['name', 'language']);
    });
    if (!Schema::hasTable('wa_campaigns')) Schema::create('wa_campaigns', function (Blueprint $t) {
      $t->id(); $t->string('name', 160); $t->string('template_name', 120); $t->string('language', 16); $t->string('audience', 20); $t->text('audience_data')->nullable(); $t->text('param_map')->nullable();
      $t->string('status', 12)->default('draft')->index(); $t->unsignedInteger('total')->default(0); $t->string('created_by', 80)->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('finished_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('wa_campaign_recipients')) Schema::create('wa_campaign_recipients', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('campaign_id')->index(); $t->string('wa_id', 24); $t->string('name', 120)->nullable(); $t->string('status', 12)->default('pending')->index(); $t->string('wamid', 120)->nullable()->index(); $t->string('error', 300)->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
      $t->unique(['campaign_id', 'wa_id']);
    });
    if (!Schema::hasTable('wa_events')) Schema::create('wa_events', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('conversation_id')->nullable()->index(); $t->string('user_name', 80)->nullable()->index(); $t->string('action', 24); $t->string('meta', 300)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('wa_optouts')) Schema::create('wa_optouts', function (Blueprint $t) { $t->id(); $t->string('wa_id', 24)->unique(); $t->string('reason', 120)->nullable(); $t->timestamps(); });
  }
  public function down(): void {}
};
