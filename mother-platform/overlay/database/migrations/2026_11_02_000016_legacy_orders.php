<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staging for the old DutyFreeBD orders + link columns on live orders. Additive only. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('legacy_orders')) Schema::create('legacy_orders', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('old_id')->unique(); $t->string('invoice_no', 40)->nullable()->index(); $t->string('phone', 20)->nullable()->index(); $t->integer('status')->nullable()->index();
      $t->decimal('total', 14, 2)->default(0); $t->timestamp('old_created_at')->nullable(); $t->jsonb('raw')->nullable(); $t->unsignedInteger('src_page')->nullable();
      $t->string('action', 12)->nullable()->index(); $t->unsignedBigInteger('new_order_id')->nullable(); $t->timestamps();
    });
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'legacy_order_id')) $t->unsignedBigInteger('legacy_order_id')->nullable()->unique();
      if (!Schema::hasColumn('orders', 'legacy_source')) $t->string('legacy_source', 40)->nullable();
    });
  }
  public function down(): void {}
};
