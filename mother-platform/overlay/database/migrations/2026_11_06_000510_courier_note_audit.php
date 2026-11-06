<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/** Courier note audit: the final note that went to the courier, where it came from (Customer | Auto | Staff Edited) and when. */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'courier_note_final')) $t->text('courier_note_final')->nullable();
      if (!Schema::hasColumn('orders', 'courier_note_source')) $t->string('courier_note_source', 20)->nullable();
      if (!Schema::hasColumn('orders', 'courier_note_sent_at')) $t->timestamp('courier_note_sent_at')->nullable();
    });
  }
  public function down(): void {}
};
