<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    foreach (['purpose' => 'string', 'due_date' => 'date', 'paid_at' => 'date'] as $col => $type) {
      if (!Schema::hasColumn('payroll_entries', $col)) Schema::table('payroll_entries', fn(Blueprint $t) => $t->{$type}($col)->nullable());
    }
    foreach (DB::table('payroll_entries as p')->leftJoin('employees as e', 'e.id', '=', 'p.employee_id')->whereNull('p.purpose')->select('p.id', 'p.month', 'p.status', 'p.updated_at', 'e.pay_type')->get() as $r) {
      try { $due = \Carbon\Carbon::createFromFormat('Y-m-d', $r->month.'-01')->endOfMonth()->toDateString(); } catch (\Throwable $e) { $due = null; }
      DB::table('payroll_entries')->where('id', $r->id)->update(['purpose' => ['commission' => 'Commission', 'both' => 'Salary + commission'][$r->pay_type ?? ''] ?? 'Salary', 'due_date' => $due, 'paid_at' => $r->status === 'paid' ? substr((string)$r->updated_at, 0, 10) : null]);
    }
  }
  public function down(): void {}
};
