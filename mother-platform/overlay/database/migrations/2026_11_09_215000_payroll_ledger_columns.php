<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    foreach (['account_id' => 'unsignedBigInteger', 'entry_ref' => 'string'] as $col => $type) {
      if (!Schema::hasColumn('payroll_entries', $col)) Schema::table('payroll_entries', fn(Blueprint $t) => $t->{$type}($col)->nullable());
    }
  }
  public function down(): void {}
};
