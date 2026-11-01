<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('voice_campaigns')) Schema::create('voice_campaigns', function (Blueprint $t) {
      $t->id(); $t->string('name', 160); $t->string('type', 30)->default('last_product'); $t->string('segment', 30)->default('all'); $t->text('segment_phones')->nullable();
      $t->json('segment_cfg')->nullable(); $t->decimal('discount_value', 6, 2)->default(5); $t->string('discount_type', 10)->default('percent'); $t->unsignedSmallInteger('validity_days')->default(3); $t->date('expiry_date')->nullable();
      $t->boolean('whole_cart')->default(false); $t->boolean('reusable')->default(false);
      $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->string('call_start_time', 5)->default('10:00'); $t->string('call_end_time', 5)->default('20:00');
      $t->unsignedInteger('daily_limit')->default(200); $t->boolean('retry_on')->default(true); $t->unsignedSmallInteger('retry_limit')->default(2); $t->unsignedSmallInteger('retry_gap_hours')->default(6); $t->unsignedSmallInteger('cooldown_days')->default(7);
      $t->boolean('dtmf_on')->default(true); $t->boolean('sms_follow')->default(false); $t->boolean('wa_follow')->default(false);
      $t->text('script')->nullable(); $t->text('sms_text')->nullable(); $t->string('audio_path', 255)->nullable();
      $t->string('status', 12)->default('draft')->index(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamp('activated_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('voice_campaign_customers')) Schema::create('voice_campaign_customers', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('campaign_id')->index(); $t->unsignedBigInteger('customer_id')->index(); $t->unsignedBigInteger('product_id')->nullable()->index(); $t->unsignedBigInteger('order_id')->nullable();
      $t->string('phone', 15)->index(); $t->string('name')->nullable(); $t->string('product_name', 255)->nullable(); $t->decimal('last_price', 12, 2)->default(0); $t->timestamp('last_order_at')->nullable();
      $t->decimal('discount_value', 6, 2)->default(5); $t->timestamp('offer_expires_at')->nullable(); $t->string('offer_status', 12)->default('scheduled')->index(); // scheduled|active|used|expired|void
      $t->string('call_status', 20)->default('pending')->index(); // pending|calling|answered|not_answered|busy|failed|interested|transferred|accepted|opted_out
      $t->unsignedSmallInteger('call_attempts')->default(0); $t->timestamp('next_attempt_at')->nullable()->index(); $t->string('pbx_call_id', 80)->nullable()->index();
      $t->boolean('interested')->default(false); $t->boolean('transferred')->default(false); $t->boolean('opted_out')->default(false); $t->boolean('converted')->default(false);
      $t->unsignedBigInteger('converted_order_id')->nullable(); $t->timestamp('called_at')->nullable(); $t->timestamp('answered_at')->nullable(); $t->unsignedInteger('duration')->default(0); $t->timestamp('used_at')->nullable();
      $t->decimal('used_discount', 12, 2)->default(0); $t->decimal('used_total', 12, 2)->default(0); $t->string('skip_reason', 120)->nullable(); $t->timestamps(); $t->unique(['campaign_id', 'customer_id']);
    });
    if (!Schema::hasTable('voice_call_attempts')) Schema::create('voice_call_attempts', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('vc_id')->index(); $t->unsignedBigInteger('campaign_id')->index(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('phone', 15); $t->unsignedSmallInteger('attempt')->default(1);
      $t->string('pbx_call_id', 80)->nullable()->index(); $t->timestamp('started_at')->nullable(); $t->timestamp('answered_at')->nullable(); $t->unsignedInteger('duration')->default(0); $t->string('result', 20)->default('calling');
      $t->string('digit', 3)->nullable(); $t->string('cause', 60)->nullable(); $t->string('recording_ref', 255)->nullable(); $t->boolean('test')->default(false); $t->timestamps();
    });
    if (Schema::hasTable('customers')) Schema::table('customers', function (Blueprint $t) {
      if (!Schema::hasColumn('customers', 'promo_sms')) $t->boolean('promo_sms')->default(true);
      if (!Schema::hasColumn('customers', 'promo_whatsapp')) $t->boolean('promo_whatsapp')->default(true);
      if (!Schema::hasColumn('customers', 'promo_call')) $t->boolean('promo_call')->default(true);
    });
  }
  public function down(): void {}
};
