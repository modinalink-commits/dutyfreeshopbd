<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Admin-only history of where each Watch product came from (never shown to customers).
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasTable('watch_source')) Schema::create('watch_source', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('product_id')->nullable()->index(); $t->unsignedBigInteger('store_product_id')->unique(); $t->unsignedBigInteger('store_id')->nullable();
                $t->string('source_name', 300)->nullable(); $t->string('source_sku', 120)->nullable()->index(); $t->string('source_url', 600)->nullable();
                $t->decimal('source_price', 12, 2)->nullable(); $t->decimal('our_price', 12, 2)->nullable(); $t->decimal('markup_pct', 6, 2)->nullable(); $t->boolean('source_in_stock')->nullable(); $t->string('note', 255)->nullable(); $t->timestamps();
            });
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
