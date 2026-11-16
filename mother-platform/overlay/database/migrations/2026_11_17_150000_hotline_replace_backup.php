<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** 17 Nov 2026: keeps the old text of every row the hotline audit rewrites (old hotline -> 09644999000), so the change can be put back row by row. Read by (tbl, col, row_id) only. down() drops the table. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('hotline_replace_backup')) return;
    Schema::create('hotline_replace_backup', function (Blueprint $t) { $t->id(); $t->string('tbl', 80); $t->string('col', 80); $t->unsignedBigInteger('row_id'); $t->text('old_value')->nullable(); $t->timestamp('created_at')->nullable(); $t->index(['tbl', 'col', 'row_id']); });
  }
  public function down(): void { Schema::dropIfExists('hotline_replace_backup'); }
};
