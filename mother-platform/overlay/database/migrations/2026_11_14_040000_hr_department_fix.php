<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (14 Nov 2026): (1) department "Sore" is a typing mistake for "Store"; (2) the Admin employee showed ৳0 in the Departments table although
 * the salary was paid, because the fixed salary on the employee record was still 0. The fixed salary is taken from that employee's own latest
 * paid payroll row (the "full salary" the owner entered), never invented. Only rows that are still 0 are touched. Idempotent.
 */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('employees')) return;
      DB::table('employees')->whereRaw("lower(trim(department)) = 'sore'")->update(['department' => 'Store', 'updated_at' => now()]);
      if (!Schema::hasTable('payroll_entries') || !Schema::hasColumn('payroll_entries', 'base_amount')) return;
      foreach (DB::table('employees')->where('department', 'Admin')->where('salary', '<=', 0)->where(fn($q) => $q->whereNull('pay_type')->orWhere('pay_type', 'salary'))->get(['id']) as $e) {
        $p = DB::table('payroll_entries')->where('employee_id', $e->id)->where('status', 'paid')->orderByDesc('month')->orderByDesc('id')->first(['base_amount', 'amount']);
        $sal = $p ? (float)($p->base_amount ?: $p->amount) : 0;
        if ($sal > 0) {
          DB::table('employees')->where('id', $e->id)->update(['salary' => $sal, 'updated_at' => now()]);
          try { DB::table('audit_logs')->insert(['user_name' => 'System', 'action' => 'update', 'subject' => 'employees.'.$e->id.' fixed salary 0 -> '.$sal.' (from the paid payroll row, owner request 14 Nov 2026)', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $x) {}
        }
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
