<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 80);
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories');
            $table->string('gl_account', 60)->nullable();
            $table->string('type', 16)->nullable(); // Fixed, Utility, Variable, Claim
            $table->string('form', 12)->default('general'); // general, fuel, rent, reimb, bill
            $table->string('approval_rule', 60)->nullable();
            $table->decimal('monthly_budget', 12, 2)->nullable();
            $table->string('source', 12)->nullable(); // payroll, fleet, purchase: shown for reports, entered elsewhere
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_no', 20)->unique();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->date('expense_date')->index();
            $table->decimal('amount', 12, 2);
            $table->string('cost_center', 80)->nullable();
            $table->string('payment', 12); // cash, trip, bank, mobile, payable, salary
            $table->foreignId('account_id')->nullable()->constrained('money_accounts');
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->string('description', 255)->nullable();
            $table->json('extra')->nullable();
            $table->json('splits')->nullable();
            $table->string('receipt_path', 255)->nullable();
            $table->string('status', 12)->default('draft')->index(); // draft, submitted, approved, posted, paid, rejected
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses');
            $table->foreignId('recurring_id')->nullable();
            $table->timestamps();
        });
        Schema::create('expense_recurring', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('every', 10); // month, year, low
            $table->unsignedTinyInteger('day')->nullable();
            $table->string('cost_center', 80)->nullable();
            $table->date('next_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('expense_rules', function (Blueprint $table) {
            $table->id();
            $table->string('who', 60);
            $table->decimal('up_to', 12, 2)->nullable(); // null = above the previous limit
            $table->decimal('above', 12, 2)->nullable();
            $table->string('verified_by', 60);
            $table->string('approver', 20); // manager, super_admin
            $table->timestamps();
        });
        Schema::table('expenses', fn (Blueprint $t) => $t->foreignId('claim_id')->nullable()->index());

        // Category groups and the categories named in the approved design. Budgets are left empty for you to set.
        $now = now();
        $g = fn ($code, $name) => DB::table('expense_categories')->insertGetId(['code' => $code, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        $c = fn ($p, $code, $name, $gl, $type, $form, $rule, $src = null) => DB::table('expense_categories')->insert(['code' => $code, 'name' => $name, 'parent_id' => $p, 'gl_account' => $gl,
            'type' => $type, 'form' => $form, 'approval_rule' => $rule, 'source' => $src, 'created_at' => $now, 'updated_at' => $now]);
        $o = $g('EX-100', 'Office');
        $c($o, 'EX-101', 'Office rent', '6201 Office rent', 'Fixed', 'rent', 'Manager');
        $c($o, 'EX-102', 'Electricity', '6230 Electricity', 'Utility', 'bill', 'Manager');
        $c($o, 'EX-103', 'Mobile & internet', '6240 Mobile & internet', 'Utility', 'bill', 'Manager');
        $c($o, 'EX-104', 'Office supplies', '6250 Office supplies', 'Variable', 'general', 'Manager');
        $w = $g('EX-200', 'Warehouse');
        $c($w, 'EX-201', 'Warehouse rent', '6210 Warehouse rent', 'Fixed', 'rent', 'Manager');
        $c($w, 'EX-203', 'Loading / unloading', '6240 Handling', 'Variable', 'general', 'Manager ≤ ৳5,000');
        $v = $g('EX-300', 'Vehicle');
        $c($v, 'EX-301', 'Fuel', '6310 Vehicle fuel', 'Variable', 'fuel', 'Manager ≤ ৳3,000');
        $c($v, 'EX-302', 'Driver allowance', '6320 Duty allowance', 'Variable', 'general', 'Manager ≤ ৳3,000');
        $c($v, 'EX-303', 'Salesman allowance', '6320 Duty allowance', 'Variable', 'general', 'Manager ≤ ৳3,000');
        $c($v, 'EX-306', 'Toll', '6330 Toll & parking', 'Variable', 'general', 'Manager ≤ ৳3,000');
        $c($v, 'EX-307', 'Parking', '6330 Toll & parking', 'Variable', 'general', 'Manager ≤ ৳3,000');
        $c($v, 'EX-308', 'Maintenance', '6340 Vehicle repair', 'Variable', 'general', 'Manager', 'fleet');
        $e = $g('EX-400', 'Employee');
        $c($e, 'EX-401', 'Salary', '6410 Salaries', 'Fixed', 'general', 'Payroll', 'payroll');
        $c($e, 'EX-408', 'Expense reimbursement', '6420 Employee reimbursement', 'Claim', 'reimb', 'Manager');
        $s = $g('EX-500', 'Sales & distribution');
        $c($s, 'EX-504', 'Sales promotion', '6510 Promotion', 'Variable', 'general', 'Super Admin');
        $f = $g('EX-600', 'Financial & admin');
        $c($f, 'EX-603', 'Software subscription', '6620 Software & hosting', 'Fixed', 'bill', 'Manager');
        $c($f, 'EX-604', 'SMS gateway', '6620 Software & hosting', 'Variable', 'bill', 'Manager');
        $x = $g('EX-900', 'Other');
        $c($x, 'EX-901', 'Other', '6900 Other expenses', 'Variable', 'general', 'Manager');

        // The design's sample approval limits. Change them in Expense settings.
        foreach ([['Driver / salesman (duty)', 3000, null, 'Manager (receipt)', 'manager'], ['Driver / salesman (duty)', null, 3000, 'Manager → Accounts', 'super_admin'],
            ['Warehouse staff', 5000, null, 'Accounts', 'manager'], ['Accounts (office bills)', 50000, null, 'Accounts', 'manager'], ['Anyone', null, 50000, 'Accounts', 'super_admin']] as [$who, $up, $ab, $ver, $ap]) {
            DB::table('expense_rules')->insert(['who' => $who, 'up_to' => $up, 'above' => $ab, 'verified_by' => $ver, 'approver' => $ap, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn('claim_id'));
        foreach (['expense_rules', 'expense_recurring', 'expense_claims', 'expense_categories'] as $t) Schema::dropIfExists($t);
    }
};
