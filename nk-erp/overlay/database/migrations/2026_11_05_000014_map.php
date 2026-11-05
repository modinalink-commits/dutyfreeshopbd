<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
        });
        Schema::table('routes', fn (Blueprint $t) => $t->json('zone')->nullable()); // polygon [[lat,lng],...]
    }

    public function down(): void
    {
        Schema::table('routes', fn (Blueprint $t) => $t->dropColumn('zone'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng']));
    }
};
