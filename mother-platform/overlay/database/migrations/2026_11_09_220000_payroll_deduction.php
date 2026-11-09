<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    foreach (['base_amount' => null, 'deduction' => 0] as $col => $def) {
      if (!Schema::hasColumn('payroll_entries', $col)) Schema::table('payroll_entries', function (Blueprint $t) use ($col, $def) { $c = $t->decimal($col, 12, 2); $def === null ? $c->nullable() : $c->default($def); });
    }
  }
  public function down(): void {}
};
