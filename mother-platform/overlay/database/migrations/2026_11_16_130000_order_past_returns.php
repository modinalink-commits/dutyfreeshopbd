<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/**
 * Owner 16 Nov 2026: goods of an EARLIER order that come back with a later parcel. One row per returned line. It keeps both links separately:
 * source_order_id / source_item_id = the order the goods were sold on (the return belongs to it), carrier_order_id = the parcel they came back with.
 * Nothing on either order is changed by this table. Additive; down() drops only this table.
 */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('order_past_returns')) return;
    Schema::create('order_past_returns', function (Blueprint $t) {
      $t->id(); $t->string('batch_token', 60); $t->unsignedBigInteger('carrier_order_id')->nullable()->index(); $t->unsignedBigInteger('source_order_id')->index(); $t->unsignedBigInteger('source_item_id')->index();
      $t->unsignedBigInteger('product_id')->nullable(); $t->string('product_name', 255)->nullable(); $t->unsignedSmallInteger('pack_size')->default(1);
      $t->unsignedInteger('cartons')->default(0); $t->unsignedInteger('packets')->default(0); $t->unsignedInteger('damaged_packets')->default(0); // cartons = whole sellable units; packets = sellable loose packets; damaged = received but not sellable (never stock)
      $t->decimal('unit_price', 12, 2)->default(0); $t->decimal('refund_value', 12, 2)->default(0); // value of what came back at the source order's real selling price after its discount
      $t->string('status', 12)->default('received')->index(); // received | voided
      $t->unsignedBigInteger('received_by')->nullable(); $t->string('received_name', 120)->nullable(); $t->timestamp('received_at')->nullable();
      $t->unsignedBigInteger('void_by')->nullable(); $t->string('void_name', 120)->nullable(); $t->timestamp('void_at')->nullable(); $t->string('void_reason', 300)->nullable();
      $t->string('settle_status', 12)->default('pending'); // pending | refunded | adjusted | none
      $t->decimal('settle_amount', 12, 2)->nullable(); $t->string('settle_method', 40)->nullable(); $t->string('settle_note', 300)->nullable(); $t->unsignedBigInteger('settle_order_id')->nullable();
      $t->unsignedBigInteger('settle_by')->nullable(); $t->string('settle_name', 120)->nullable(); $t->timestamp('settle_at')->nullable();
      $t->timestamps(); $t->unique(['batch_token', 'source_item_id'], 'order_past_returns_token_item_uq');
    });
  }
  public function down(): void { Schema::dropIfExists('order_past_returns'); }
};
