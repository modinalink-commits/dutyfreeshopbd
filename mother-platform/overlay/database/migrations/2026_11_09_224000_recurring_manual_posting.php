<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner: auto posting off, manual posting only. Every recurring master is switched to manual (post + deduct happen when the owner presses Post on the day he pays). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('recurring_expenses')) return;
    DB::table('recurring_expenses')->update(['auto_post' => false, 'auto_deduct' => false, 'updated_at' => now()]);
  }
  public function down(): void {}
};
