<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->string('department', 60)->nullable();
            $t->string('designation', 60)->nullable();
            $t->date('joined_on')->nullable();
            $t->decimal('basic', 12, 2)->nullable();
            $t->decimal('allowance', 12, 2)->nullable();
            $t->decimal('commission_rate', 8, 2)->nullable(); // per cylinder sold on his trips
            $t->string('bank_account', 60)->nullable();
            $t->date('left_on')->nullable();
        });
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique(); // YYYY-MM
            $table->string('status', 12)->default('draft'); // draft, checked, approved, paid
            $table->foreignId('prepared_by')->nullable()->constrained('users');
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users');
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('money_accounts');
            $table->timestamps();
        });
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->unsignedSmallInteger('days')->default(0);
            $table->unsignedSmallInteger('leave_days')->default(0);
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('allowance', 12, 2)->default(0);
            $table->unsignedInteger('cylinders')->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('overtime_hours', 6, 1)->default(0);
            $table->decimal('overtime', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('reimbursement', 12, 2)->default(0);
            $table->decimal('advance_recovery', 12, 2)->default(0);
            $table->decimal('loan_recovery', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->unique(['payroll_id', 'employee_id']);
        });
        Schema::create('employee_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('entry_date')->index();
            $table->string('kind', 20); // opening, salary, commission, overtime, bonus, reimbursement, advance, loan, advance_recovery, loan_recovery, deduction, payment, adjustment
            $table->string('reference', 40)->nullable();
            $table->decimal('earned', 12, 2)->default(0);   // adds to salary payable
            $table->decimal('deducted', 12, 2)->default(0); // reduces salary payable
            $table->decimal('advance_delta', 12, 2)->default(0);
            $table->decimal('loan_delta', 12, 2)->default(0);
            $table->foreignId('account_id')->nullable()->constrained('money_accounts');
            $table->decimal('cash_out', 12, 2)->default(0); // money that left (or, negative, came back to) the account
            $table->foreignId('payroll_id')->nullable()->constrained('payrolls');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['employee_ledger', 'payroll_lines', 'payrolls'] as $t) Schema::dropIfExists($t);
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['department', 'designation', 'joined_on', 'basic', 'allowance', 'commission_rate', 'bank_account', 'left_on']));
    }
};
