<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clearance: an owner-approved sale below the protected margin for stock that is about to be lost (near expiry, damaged, old stock).
 * One row per clearance run of a product. The first row is Davidoff Gold Malaysia (owner decision of 14 Nov 2026).
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('product_clearances')) Schema::create('product_clearances', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('reason', 40); $t->string('note', 300)->nullable();
      $t->decimal('price', 12, 2); $t->decimal('normal_price', 12, 2); $t->decimal('normal_compare', 12, 2)->nullable(); $t->decimal('cost_at_start', 12, 2)->default(0);
      $t->integer('stock_at_start')->nullable(); $t->date('ends_on')->nullable(); $t->string('status', 12)->default('pending')->index();
      $t->string('requested_by', 80)->nullable(); $t->string('approved_by', 80)->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('ended_at')->nullable(); $t->string('ended_reason', 120)->nullable();
      $t->timestamps();
    });
    // Owner policy (14 Nov 2026): a clearance product may go down to its purchase cost, never below. Davidoff Gold Malaysia moves from 3,199 to its cost price as the first clearance.
    try {
      $p = DB::table('products')->where('id', 1242)->first();
      if ($p && stripos((string)$p->name, 'Davidoff Gold Malaysia') !== false && abs((float)$p->price - 3199) < 0.01 && !DB::table('product_clearances')->where('product_id', 1242)->whereIn('status', ['active', 'pending'])->exists()) {
        $cost = 0.0; try { $cost = (float)(DB::table('products as p')->where('p.id', 1242)->selectRaw(\App\Support\Costing::fifoSql('p') . ' as c')->value('c') ?? 0); } catch (\Throwable $e) {}
        if ($cost > 0) {
          $new = (float)ceil($cost);
          DB::transaction(function () use ($p, $cost, $new) {
            DB::table('products')->where('id', 1242)->update(['price' => $new, 'updated_at' => now()]);
            DB::table('product_clearances')->insert(['product_id' => 1242, 'reason' => 'near_expiry', 'note' => 'Owner decision 14 Nov 2026: product is about to spoil; sold at purchase cost', 'price' => $new, 'normal_price' => 3960, 'normal_compare' => $p->compare_price, 'cost_at_start' => $cost,
              'stock_at_start' => $p->stock, 'status' => 'active', 'requested_by' => 'Owner', 'approved_by' => 'Owner', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            if (Schema::hasTable('product_price_history')) DB::table('product_price_history')->insert(['product_id' => 1242, 'old_rate' => 3199, 'new_rate' => $new, 'purchase_rate' => $cost, 'changed_by' => 'Owner', 'source' => 'Clearance', 'note' => 'Clearance started at purchase cost (owner policy)', 'created_at' => now(), 'updated_at' => now()]);
          });
          try { \App\Support\BundlePricing::flush(); \App\Support\BundlePricing::sync([1242]); } catch (\Throwable $e) {}
          try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
        }
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void { Schema::dropIfExists('product_clearances'); }
};
