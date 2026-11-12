<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner confirmed the courier-collection differences on these parcels are partial deliveries: acknowledge them so they leave the attention box. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('orders', 'mismatch_ack_at')) Schema::table('orders', function ($t) { $t->timestamp('mismatch_ack_at')->nullable(); $t->string('mismatch_ack_by', 120)->nullable(); });
    DB::table('orders')->whereIn('id', [103344, 103544, 103181, 102779, 102286, 101988, 101948])->whereNull('mismatch_ack_at')->update(['mismatch_ack_at' => now(), 'mismatch_ack_by' => 'Mohammad Faruk (partial parcel)']);
  }
  public function down(): void {}
};
