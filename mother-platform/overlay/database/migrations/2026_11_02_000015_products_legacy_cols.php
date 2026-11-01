<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Additive: link a live product to the old DutyFreeBD product it came from. */
return new class extends Migration {
  public function up(): void {
    Schema::table('products', function (Blueprint $t) {
      if (!Schema::hasColumn('products', 'legacy_product_id')) $t->unsignedBigInteger('legacy_product_id')->nullable()->index();
      if (!Schema::hasColumn('products', 'legacy_source')) $t->string('legacy_source', 40)->nullable();
      if (!Schema::hasColumn('products', 'migrated_at')) $t->timestamp('migrated_at')->nullable();
    });
  }
  public function down(): void {}
};
