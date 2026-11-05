<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One record store for the 15 ERP module forms. Each record keeps its own approval trail.
        Schema::create('erp_records', function (Blueprint $table) {
            $table->id();
            $table->string('module', 4)->index();
            $table->unsignedTinyInteger('form')->default(0);
            $table->string('record_no', 24)->unique();
            $table->json('data');
            $table->string('status', 12)->default('draft')->index();
            $table->json('trail')->nullable();
            $table->string('file', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_records');
    }
};
