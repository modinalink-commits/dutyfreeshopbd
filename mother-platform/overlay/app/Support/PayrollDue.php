<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Every month each active employee gets one unpaid payroll row (their "due"). Fixed salary = salary; commission staff start at 0 and the amount is entered from the month's accounts. Idempotent. */
class PayrollDue {
  public static function purpose(string $payType): string { return ['commission' => 'Commission', 'both' => 'Salary + commission'][$payType] ?? 'Salary'; }
  public static function dueDate(string $mon): string { try { return \Carbon\Carbon::createFromFormat('Y-m-d', $mon.'-01')->endOfMonth()->toDateString(); } catch (\Throwable $e) { return now('Asia/Dhaka')->toDateString(); } }
  public static function create(?string $mon = null): int {
    $mon = $mon ?: now('Asia/Dhaka')->format('Y-m'); $n = 0;
    foreach (DB::table('employees')->where('active', true)->get() as $e) {
      if (DB::table('payroll_entries')->where('employee_id', $e->id)->where('month', $mon)->exists()) continue;
      $pt = $e->pay_type ?? 'salary';
      DB::table('payroll_entries')->insert(['employee_id' => $e->id, 'month' => $mon, 'amount' => $pt === 'commission' ? 0 : $e->salary,
        'note' => $pt === 'both' ? "Fixed salary + commission: add this month's commission to the amount" : ($pt === 'commission' ? "Commission: enter this month's amount from the accounts" : null),
        'purpose' => self::purpose($pt), 'due_date' => self::dueDate($mon), 'status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]); $n++;
    }
    return $n;
  }
  /** Called from the background worker; creates the current month's dues automatically (at most once an hour). */
  public static function tick(): void {
    try { if (!Schema::hasTable('payroll_entries') || !Cache::add('payroll.due.tick', 1, 3600)) return; self::create(); } catch (\Throwable $e) { report($e); }
  }
}
