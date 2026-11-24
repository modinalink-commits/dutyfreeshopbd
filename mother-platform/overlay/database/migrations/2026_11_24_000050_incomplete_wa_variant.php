<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** WhatsApp cart reminder: which message variant (A or B) was sent, for the A/B comparison. */
return new class extends Migration {
  public function up(): void { if (Schema::hasTable('incomplete_orders') && !Schema::hasColumn('incomplete_orders', 'wa_variant')) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->string('wa_variant', 2)->nullable()); }
  public function down(): void { if (Schema::hasColumn('incomplete_orders', 'wa_variant')) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->dropColumn('wa_variant')); }
};
