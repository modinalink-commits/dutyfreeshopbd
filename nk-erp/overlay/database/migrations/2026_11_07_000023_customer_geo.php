<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the shop's map point was set: manual (pin), gps (salesman at the shop), address or area (looked up).
        Schema::table('customers', function (Blueprint $t) {
            $t->string('geo_source', 10)->nullable();
            $t->string('geo_label', 255)->nullable();
        });
        DB::table('customers')->whereNotNull('lat')->update(['geo_source' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['geo_source', 'geo_label']));
    }
};
