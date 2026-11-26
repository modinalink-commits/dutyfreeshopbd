<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: quotation-only catalogue items (Bangladesh Ship Provisions), the desk's port checks for them, and the link from a requisition line.
 *  New tables and one nullable column only: the store catalogue (products, categories) is not touched. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('marine_rfq_items')) Schema::create('marine_rfq_items', function (Blueprint $t) {
      $t->id(); $t->string('sku', 40)->unique(); $t->string('mcat', 12)->default('provisions')->index();
      $t->string('section_slug', 60)->index(); $t->string('section_name', 80); $t->string('sub_name', 80);
      $t->string('name_en', 200); $t->string('name_bn', 200)->nullable(); $t->string('brand', 120)->nullable();
      $t->decimal('pack_qty', 10, 3); $t->string('pack_unit', 12); $t->unsignedSmallInteger('units_per_multipack')->nullable(); $t->string('pack_note', 120)->nullable();
      $t->string('order_uom', 12); $t->string('handling', 24);
      $t->string('sale_mode', 8)->default('RFQ'); $t->string('currency', 3)->default('USD'); $t->decimal('price_usd', 12, 2)->nullable(); $t->string('inventory_status', 40)->default('supplier_confirmation');
      $t->string('sourcing_region', 60)->nullable(); $t->text('sourcing')->nullable();            // desk only
      $t->string('image_file', 120)->nullable(); $t->string('image_kind', 20)->default('illustration'); $t->string('image_alt', 200)->nullable();
      $t->string('packshot_path', 190)->nullable(); $t->string('packshot_ref', 200)->nullable(); $t->string('packshot_by', 120)->nullable(); $t->timestamp('packshot_at')->nullable();
      $t->string('state', 12)->default('draft')->index(); $t->string('published_by', 120)->nullable(); $t->timestamp('published_at')->nullable();
      $t->string('created_by', 120)->nullable(); $t->string('updated_by', 120)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('marine_rfq_checks')) Schema::create('marine_rfq_checks', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('item_id'); $t->string('port', 12); $t->string('supplier', 160); $t->string('pack_checked', 80)->nullable(); $t->string('qty_available', 60); $t->string('order_unit', 12)->nullable();
      $t->boolean('handling_ok')->default(false); $t->timestamp('checked_at')->nullable(); $t->string('checked_by', 120)->nullable(); $t->date('valid_until'); $t->string('note', 300)->nullable(); $t->timestamps();
      $t->unique(['item_id', 'port']);
    });
    if (Schema::hasTable('marine_order_lines') && !Schema::hasColumn('marine_order_lines', 'rfq_item_id')) Schema::table('marine_order_lines', function (Blueprint $t) { $t->unsignedBigInteger('rfq_item_id')->nullable()->index(); });
  }
  public function down(): void {
    if (Schema::hasTable('marine_order_lines') && Schema::hasColumn('marine_order_lines', 'rfq_item_id')) Schema::table('marine_order_lines', function (Blueprint $t) { $t->dropColumn('rfq_item_id'); });
    Schema::dropIfExists('marine_rfq_checks'); Schema::dropIfExists('marine_rfq_items');
  }
};
