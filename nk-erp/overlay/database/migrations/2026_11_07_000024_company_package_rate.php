<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AYGas bills a new package cylinder (cylinder + gas) at its own DO rate, apart from refill.
        Schema::table('company_rates', fn (Blueprint $t) => $t->decimal('pkg_rate', 12, 2)->default(0));
        // A challan line is refill or package.
        Schema::table('company_entries', fn (Blueprint $t) => $t->string('ctype', 10)->default('refill'));
    }

    public function down(): void
    {
        Schema::table('company_entries', fn (Blueprint $t) => $t->dropColumn('ctype'));
        Schema::table('company_rates', fn (Blueprint $t) => $t->dropColumn('pkg_rate'));
    }
};
