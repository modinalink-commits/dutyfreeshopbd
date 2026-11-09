<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('employees', 'pay_type')) Schema::table('employees', fn(Blueprint $t) => $t->string('pay_type')->default('salary'));
    if (!Schema::hasColumn('payroll_entries', 'note')) Schema::table('payroll_entries', fn(Blueprint $t) => $t->text('note')->nullable());
  }
  public function down(): void {}
};
