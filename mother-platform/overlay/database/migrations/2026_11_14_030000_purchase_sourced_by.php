<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Owner (14 Nov 2026): a purchase invoice entered by someone else on behalf of the sourcing person can be credited to that person (sourcing commission). Null = the user who entered it. */
return new class extends Migration {
  public function up(): void {
    try { if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'sourced_by')) Schema::table('purchases', fn(Blueprint $t) => $t->unsignedBigInteger('sourced_by')->nullable()->index()); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
