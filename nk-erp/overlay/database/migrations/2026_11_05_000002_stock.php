<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 80)->unique();
            $table->string('company_name', 150)->nullable();
            $table->json('sizes');
            $table->boolean('is_active')->default(false);
            $table->boolean('for_sale')->default(false);
            $table->boolean('empty_collection')->default(true);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });

        // Posted stock movements are never edited or deleted; mistakes are fixed with a reversal entry.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 20)->unique();
            $table->string('kind', 20)->index();
            $table->foreignId('brand_id')->constrained('brands');
            $table->unsignedSmallInteger('size_kg');
            $table->integer('full_delta')->default(0);
            $table->integer('empty_delta')->default(0);
            $table->integer('company_delta')->default(0);
            $table->integer('damaged_delta')->default(0);
            $table->string('reference', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('reversal_of')->nullable()->constrained('stock_movements');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['brand_id', 'size_kg']);
        });

        Schema::create('brand_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 20)->unique();
            $table->foreignId('from_brand_id')->constrained('brands');
            $table->foreignId('to_brand_id')->constrained('brands');
            $table->unsignedSmallInteger('size_kg');
            $table->unsignedInteger('qty');
            $table->string('party', 120)->nullable();
            $table->string('reason', 255);
            $table->string('status', 12)->default('waiting')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_exchanges');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('brands');
    }
};
