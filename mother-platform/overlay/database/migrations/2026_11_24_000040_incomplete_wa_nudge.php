<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** WhatsApp reminder for an unfinished checkout: when it was sent (once per incomplete order). */
return new class extends Migration {
  public function up(): void { if (Schema::hasTable('incomplete_orders') && !Schema::hasColumn('incomplete_orders', 'wa_nudged_at')) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->timestamp('wa_nudged_at')->nullable()); }
  public function down(): void { if (Schema::hasColumn('incomplete_orders', 'wa_nudged_at')) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->dropColumn('wa_nudged_at')); }
};
