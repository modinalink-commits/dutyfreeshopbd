<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine quotation-only items: a second catalogue (Cabin & Galley Stores). Its items have a material and a reference size instead of a pack quantity and handling class,
 *  so those three columns may be empty. Existing provisions rows keep their values and get catalog = provisions. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('marine_rfq_items')) return;
    Schema::table('marine_rfq_items', function (Blueprint $t) {
      if (!Schema::hasColumn('marine_rfq_items', 'catalog')) $t->string('catalog', 12)->default('provisions')->index();
      if (!Schema::hasColumn('marine_rfq_items', 'group_name')) $t->string('group_name', 80)->nullable();
      if (!Schema::hasColumn('marine_rfq_items', 'material')) $t->string('material', 120)->nullable();
      if (!Schema::hasColumn('marine_rfq_items', 'size_spec')) $t->string('size_spec', 120)->nullable();
      if (!Schema::hasColumn('marine_rfq_items', 'source_ref')) $t->string('source_ref', 20)->nullable();
    });
    Schema::table('marine_rfq_items', function (Blueprint $t) {
      $t->decimal('pack_qty', 10, 3)->nullable()->change(); $t->string('pack_unit', 12)->nullable()->change(); $t->string('handling', 24)->nullable()->change();
    });
  }
  public function down(): void {
    if (!Schema::hasTable('marine_rfq_items')) return;
    Schema::table('marine_rfq_items', function (Blueprint $t) { foreach (['catalog', 'group_name', 'material', 'size_spec', 'source_ref'] as $c) if (Schema::hasColumn('marine_rfq_items', $c)) $t->dropColumn($c); });
  }
};
