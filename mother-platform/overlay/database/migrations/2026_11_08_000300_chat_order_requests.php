<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_order_requests')) Schema::create('chat_order_requests', function (Blueprint $t) {
      $t->id(); $t->foreignId('thread_id')->index(); $t->string('channel', 20)->nullable(); $t->string('name', 160); $t->string('phone', 20)->index(); $t->text('address'); $t->unsignedSmallInteger('area')->default(1);
      $t->text('note')->nullable(); $t->json('cart'); $t->json('items')->nullable(); $t->decimal('total', 12, 2)->default(0); $t->string('status', 12)->default('review')->index(); $t->string('reason', 200)->nullable();
      $t->foreignId('order_id')->nullable(); $t->string('invoice', 40)->nullable(); $t->foreignId('handled_by')->nullable(); $t->timestamp('handled_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('chat_order_requests'); }
};
