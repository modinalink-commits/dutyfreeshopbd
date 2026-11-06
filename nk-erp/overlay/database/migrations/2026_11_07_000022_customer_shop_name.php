<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shop name kept apart from the customer name; owner_name already exists.
        Schema::table('customers', fn (Blueprint $t) => $t->string('shop_name', 150)->nullable()->after('name'));
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('shop_name'));
    }
};
