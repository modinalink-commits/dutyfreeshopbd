<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Display order of brands in every list (1 = top). Our own brand starts at the top, the rest keep their code order.
        Schema::table('brands', fn (Blueprint $t) => $t->unsignedInteger('position')->default(0)->index());
        $own = DB::table('brands')->where('for_sale', true)->orderBy('id')->value('id');
        $ids = DB::table('brands')->orderByRaw('case when id = ? then 0 else 1 end', [$own ?? 0])->orderBy('code')->pluck('id');
        foreach ($ids as $i => $id) DB::table('brands')->where('id', $id)->update(['position' => $i + 1]);
    }

    public function down(): void
    {
        Schema::table('brands', fn (Blueprint $t) => $t->dropColumn('position'));
    }
};
