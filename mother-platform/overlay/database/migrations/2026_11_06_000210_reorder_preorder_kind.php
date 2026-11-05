<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pre-order requests: unpublished products sent to suppliers (same RFQ flow, labelled Pre-Order). */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('reorder_requests')) Schema::table('reorder_requests', function (Blueprint $t) { if (!Schema::hasColumn('reorder_requests', 'kind')) $t->string('kind', 12)->default('reorder'); });
  }
  public function down(): void {}
};
