<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: requisitions -> USD quotations -> purchase orders, crew personal orders, and port calls (owner brief 26 Nov 2026).
 *  marine_orders = one requisition from a verified account through to delivery; marine_order_lines = its items (catalog product or free text);
 *  marine_port_calls = a vessel's call at one of our ports. New tables, plus a "verified" flag on marine_account_vessels (an agency may declare a vessel; the desk verifies the appointment).
 *  No payment, stock or accounting entries are made here yet: the owner takes no money on the marine site for now. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('marine_account_vessels') && !Schema::hasColumn('marine_account_vessels', 'verified')) Schema::table('marine_account_vessels', function (Blueprint $t) { $t->boolean('verified')->default(true); });
    if (!Schema::hasTable('marine_port_calls')) Schema::create('marine_port_calls', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('account_id')->index(); $t->string('imo', 12)->index(); $t->string('vessel', 120)->nullable(); $t->string('port', 20); $t->string('place', 20)->nullable(); $t->string('berth', 80)->nullable();
      $t->timestamp('eta')->nullable(); $t->timestamp('etd')->nullable(); $t->string('agent_name', 120)->nullable(); $t->string('agent_contact', 120)->nullable(); $t->string('status', 12)->default('expected')->index(); $t->string('note', 400)->nullable(); $t->string('updated_by', 120)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('marine_orders')) Schema::create('marine_orders', function (Blueprint $t) {
      $t->id(); $t->string('number', 20)->nullable()->unique(); $t->string('kind', 10)->default('vessel'); $t->unsignedBigInteger('account_id')->index(); $t->unsignedBigInteger('port_call_id')->nullable()->index();
      $t->string('imo', 12)->nullable()->index(); $t->string('vessel', 120)->nullable(); $t->string('port', 20); $t->string('place', 20)->nullable(); $t->timestamp('eta')->nullable();
      $t->string('status', 12)->default('requested')->index(); $t->string('note', 1000)->nullable(); $t->string('desk_note', 1000)->nullable(); $t->string('internal_note', 1000)->nullable();
      $t->decimal('subtotal', 12, 2)->nullable(); $t->decimal('delivery_charge', 12, 2)->nullable(); $t->decimal('total', 12, 2)->nullable(); $t->date('valid_until')->nullable(); $t->string('quoted_by', 120)->nullable(); $t->timestamp('quoted_at')->nullable();
      $t->string('po_ref', 60)->nullable(); $t->string('ordered_by', 160)->nullable(); $t->timestamp('ordered_at')->nullable(); $t->timestamp('delivery_at')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->timestamp('closed_at')->nullable(); $t->string('close_reason', 300)->nullable(); $t->string('closed_by', 160)->nullable();
      $t->timestamps();
    });
    if (!Schema::hasTable('marine_order_lines')) Schema::create('marine_order_lines', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('product_id')->nullable(); $t->string('sku', 60)->nullable(); $t->string('name', 200); $t->string('size', 80)->nullable(); $t->decimal('qty', 10, 2); $t->string('unit', 20)->nullable();
      $t->decimal('list_usd', 12, 2)->nullable(); $t->decimal('unit_usd', 12, 2)->nullable(); $t->boolean('available')->default(true); $t->string('note', 200)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {
    Schema::dropIfExists('marine_order_lines'); Schema::dropIfExists('marine_orders'); Schema::dropIfExists('marine_port_calls');
    if (Schema::hasTable('marine_account_vessels') && Schema::hasColumn('marine_account_vessels', 'verified')) Schema::table('marine_account_vessels', function (Blueprint $t) { $t->dropColumn('verified'); });
  }
};
