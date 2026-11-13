<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (14 Nov 2026): commission staff who source products (first: Sakib). Commission is built from the order lines the person
 * actually sourced: profit of those lines (sale - buy cost - delivery - packaging), minus a monthly marketing cost, then shared.
 * Every number lives in the agreement row (editable), nothing is hard-coded. Idempotent; no existing column is changed.
 */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('sourcing_agreements')) Schema::create('sourcing_agreements', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->unsignedBigInteger('employee_id')->nullable(); $t->string('name', 120);
        $t->decimal('packaging_cost', 10, 2)->default(30); $t->decimal('marketing_cost', 12, 2)->default(30000); $t->decimal('share_pct', 5, 2)->default(50);
        $t->json('store_ids')->nullable();            // source stores whose lines default to this person
        $t->string('count_statuses', 200)->default('Delivered');
        $t->boolean('deduct_discount')->default(true); $t->boolean('add_shipping_income')->default(true); $t->boolean('carry_deficit')->default(false);
        $t->date('starts_on')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
      });
      if (!Schema::hasTable('sourcing_statements')) Schema::create('sourcing_statements', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('agreement_id')->index(); $t->string('month', 7);
        $t->decimal('gross_profit', 14, 2)->default(0); $t->decimal('marketing_cost', 14, 2)->default(0); $t->decimal('carry_in', 14, 2)->default(0);
        $t->decimal('shareable', 14, 2)->default(0); $t->decimal('staff_share', 14, 2)->default(0); $t->decimal('company_share', 14, 2)->default(0); $t->decimal('carry_out', 14, 2)->default(0);
        $t->integer('line_count')->default(0); $t->json('snapshot')->nullable();
        $t->unsignedBigInteger('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->unsignedBigInteger('payroll_entry_id')->nullable(); $t->timestamps();
        $t->unique(['agreement_id', 'month']);
      });
      if (Schema::hasTable('order_items')) Schema::table('order_items', function (Blueprint $t) {
        if (!Schema::hasColumn('order_items', 'sourced_by')) $t->unsignedBigInteger('sourced_by')->nullable()->index();
        if (!Schema::hasColumn('order_items', 'sourced_cost')) $t->decimal('sourced_cost', 12, 2)->nullable();      // real buy price per unit
        if (!Schema::hasColumn('order_items', 'sourced_status')) $t->string('sourced_status', 12)->nullable();        // pending / approved / rejected
        if (!Schema::hasColumn('order_items', 'sourced_at')) $t->timestamp('sourced_at')->nullable();
        if (!Schema::hasColumn('order_items', 'sourced_checked_by')) $t->unsignedBigInteger('sourced_checked_by')->nullable();
        if (!Schema::hasColumn('order_items', 'sourced_checked_at')) $t->timestamp('sourced_checked_at')->nullable();
      });
      // first agreement: Sakib, with the terms the owner stated (৳30 packaging, ৳30,000 marketing, 50/50). Stores are chosen by the owner on the page.
      $u = DB::table('users')->whereRaw('lower(email) = ?', ['sakib@dutyfreebd.com'])->first();
      if ($u && !DB::table('sourcing_agreements')->where('user_id', $u->id)->exists()) {
        $emp = Schema::hasTable('employees') ? DB::table('employees')->whereRaw('lower(name) like ?', ['%sakib%'])->value('id') : null;
        DB::table('sourcing_agreements')->insert(['user_id' => $u->id, 'employee_id' => $emp, 'name' => $u->name ?: 'Sakib', 'packaging_cost' => 30, 'marketing_cost' => 30000, 'share_pct' => 50, 'store_ids' => json_encode([]), 'count_statuses' => 'Delivered', 'starts_on' => '2026-11-01', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
