<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// A save bug left New Selling Rates without a pending status. Re-run the (idempotent, margin-protected) apply for recent stocked-in purchases.
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('purchase_items') || !Schema::hasColumn('purchase_items', 'new_selling_rate')) return;
      $ids = DB::table('purchase_items as i')->join('purchases as p', 'p.id', '=', 'i.purchase_id')->whereNotNull('i.new_selling_rate')->whereNull('i.price_update_status')->whereIn('p.status', ['received', 'approved'])->where('p.stock_applied', true)->where('p.created_at', '>=', now()->subDays(3))->distinct()->pluck('p.id');
      foreach ($ids as $id) { $p = DB::table('purchases')->find($id); if ($p) DB::transaction(fn() => \App\Support\Procure::applyNewRates($p)); }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
