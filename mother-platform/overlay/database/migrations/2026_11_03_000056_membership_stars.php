<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Membership star rating shown to customers (Silver 1, Gold 2, Platinum 3, Diamond 4). The discount % is never shown to customers.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('membership_levels')) return;
    if (!Schema::hasColumn('membership_levels', 'stars')) Schema::table('membership_levels', fn($t) => $t->unsignedSmallInteger('stars')->default(0));
    foreach (['silver' => 1, 'gold' => 2, 'platinum' => 3, 'diamond' => 4] as $k => $n) DB::table('membership_levels')->whereRaw('lower(name) like ?', ['%' . $k . '%'])->where('stars', 0)->update(['stars' => $n]);
  }
  public function down(): void {}
};
