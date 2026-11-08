<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        try { if (Schema::hasTable('watch_source') && !Schema::hasColumn('watch_source', 'details_at')) Schema::table('watch_source', fn(Blueprint $t) => $t->timestamp('details_at')->nullable()->index()); } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
