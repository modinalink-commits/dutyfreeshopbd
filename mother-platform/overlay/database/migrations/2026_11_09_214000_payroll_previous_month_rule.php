<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner rule: salary is for the completed month and is paid by the 5th of the next month. Remove any unpaid rows auto-created for a month that has not ended yet, and re-date the rest to the 5th of the following month. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('payroll_entries')) return;
    $cur = now('Asia/Dhaka')->format('Y-m');
    DB::table('payroll_entries')->where('month', '>=', $cur)->where('status', 'unpaid')->where('amount', '>=', 0)->delete();
    foreach (DB::table('payroll_entries')->get() as $r) {
      try { $d = \Carbon\Carbon::createFromFormat('Y-m-d', $r->month.'-01')->addMonthNoOverflow()->day(5)->toDateString(); DB::table('payroll_entries')->where('id', $r->id)->update(['due_date' => $d]); } catch (\Throwable $e) {}
    }
  }
  public function down(): void {}
};
