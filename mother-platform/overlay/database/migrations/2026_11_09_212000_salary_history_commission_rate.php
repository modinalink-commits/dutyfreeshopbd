<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('employees', 'commission_rate')) Schema::table('employees', fn(Blueprint $t) => $t->decimal('commission_rate', 6, 2)->nullable());
    if (!Schema::hasTable('salary_history')) Schema::create('salary_history', function (Blueprint $t) { $t->id(); $t->foreignId('employee_id')->index(); $t->decimal('old_salary', 12, 2); $t->decimal('new_salary', 12, 2); $t->decimal('change_amount', 12, 2); $t->decimal('change_pct', 8, 2)->nullable(); $t->date('effective_date'); $t->timestamps(); });
    DB::unprepared("CREATE OR REPLACE FUNCTION log_salary_change() RETURNS trigger AS \$\$ BEGIN
      IF NEW.salary IS DISTINCT FROM OLD.salary AND COALESCE(OLD.salary,0) > 0 THEN
        INSERT INTO salary_history (employee_id, old_salary, new_salary, change_amount, change_pct, effective_date, created_at, updated_at)
        VALUES (NEW.id, OLD.salary, NEW.salary, NEW.salary - OLD.salary, ROUND((NEW.salary - OLD.salary) / OLD.salary * 100, 2), CURRENT_DATE, NOW(), NOW());
      END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
    DB::unprepared("DROP TRIGGER IF EXISTS trg_salary_change ON employees");
    DB::unprepared("CREATE TRIGGER trg_salary_change AFTER UPDATE OF salary ON employees FOR EACH ROW EXECUTE FUNCTION log_salary_change()");
  }
  public function down(): void {}
};
