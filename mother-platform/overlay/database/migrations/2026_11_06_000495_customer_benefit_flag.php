<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/** Customer Benefit Eligibility: an authorised flag (suspended | review) set by management, with its reason. Null = normal. */
return new class extends Migration {
  public function up(): void {
    Schema::table('customers', function (Blueprint $t) {
      if (!Schema::hasColumn('customers', 'benefit_flag')) $t->string('benefit_flag', 20)->nullable();
      if (!Schema::hasColumn('customers', 'benefit_note')) $t->string('benefit_note', 255)->nullable();
    });
  }
  public function down(): void {}
};
