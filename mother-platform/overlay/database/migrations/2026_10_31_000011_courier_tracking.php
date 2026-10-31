<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      $t->string('courier_stage', 30)->nullable()->index();
      $t->decimal('courier_cod', 12, 2)->nullable(); $t->decimal('courier_return_fee', 10, 2)->nullable(); $t->decimal('collected_amount', 12, 2)->nullable();
      $t->string('settlement_status', 20)->default('Pending'); $t->timestamp('settlement_date')->nullable(); $t->string('settlement_ref', 120)->nullable();
      $t->string('courier_location', 200)->nullable(); $t->timestamp('courier_delivered_at')->nullable(); $t->timestamp('courier_synced_at')->nullable();
    });
    Schema::create('courier_events', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->index(); $t->string('courier', 40); $t->string('status', 120); $t->string('stage', 30)->nullable(); $t->string('location', 200)->nullable(); $t->text('note')->nullable(); $t->timestamp('happened_at')->nullable(); $t->timestamps(); });
  }
  public function down(): void {}
};
