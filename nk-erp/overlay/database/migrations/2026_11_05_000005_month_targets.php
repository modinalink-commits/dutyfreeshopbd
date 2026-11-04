<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_months', function (Blueprint $table) {
            $table->decimal('tons_target', 8, 2)->default(0);
            $table->decimal('excl_target', 8, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('company_months', fn (Blueprint $t) => $t->dropColumn(['tons_target', 'excl_target']));
    }
};
