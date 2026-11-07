<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_whatsapp_confirmations')) {
      Schema::create('order_whatsapp_confirmations', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('order_id')->index();
        $t->unsignedBigInteger('customer_id')->nullable()->index();
        $t->unsignedBigInteger('store_id')->nullable();
        $t->string('confirmation_type', 30)->default('ORDER_APPROVED');
        $t->unsignedSmallInteger('seq')->default(1); // 1 = the automatic one; 2.. = audited manual resends
        $t->string('mobile', 30)->nullable();
        $t->string('approval_source', 12)->nullable();
        $t->string('template_name', 80)->nullable();
        $t->text('message_body')->nullable();
        $t->string('external_message_id', 190)->nullable()->index();
        $t->string('send_status', 12)->default('PENDING')->index(); // PENDING QUEUED SENT DELIVERED READ FAILED
        $t->timestamp('sent_at')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamp('failed_at')->nullable();
        $t->text('failure_reason')->nullable();
        $t->boolean('permanent')->default(false);
        $t->unsignedSmallInteger('retry_count')->default(0);
        $t->unsignedBigInteger('resend_by')->nullable(); $t->timestamp('resend_at')->nullable(); $t->string('resend_reason', 300)->nullable();
        $t->timestamps();
        $t->unique(['order_id', 'confirmation_type', 'seq'], 'owc_one_per_order_type_seq');
      });
    }
    if (!Schema::hasTable('order_whatsapp_events')) {
      Schema::create('order_whatsapp_events', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('confirmation_id')->index(); $t->unsignedBigInteger('order_id')->index();
        $t->string('event', 30); $t->text('detail')->nullable(); $t->timestamp('created_at')->useCurrent();
      });
    }
  }
  public function down(): void { Schema::dropIfExists('order_whatsapp_events'); Schema::dropIfExists('order_whatsapp_confirmations'); }
};
