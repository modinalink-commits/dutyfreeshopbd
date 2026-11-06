<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $t) {
            if (! Schema::hasColumn('brands', 'images')) $t->json('images')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brands', fn (Blueprint $t) => $t->dropColumn('images'));
    }
};
