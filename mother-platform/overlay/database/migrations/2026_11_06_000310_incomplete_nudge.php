<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Incomplete-order SMS (owner request): marks each incomplete order once nudged. The rule starts now: only incomplete orders created after this migration are ever texted (old open ones are never touched). */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('incomplete_orders') && !Schema::hasColumn('incomplete_orders', 'nudged_at')) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->timestamp('nudged_at')->nullable());
    if (\App\Support\Procure::set('inc_nudge_cutoff', '') === '') \App\Support\Procure::put('inc_nudge_cutoff', now()->toDateTimeString());
  }
  public function down(): void {}
};
