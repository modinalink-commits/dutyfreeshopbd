<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// customers.customer_level: silver | gold | platinum | diamond. Empty = worked out automatically from the membership level / spend.
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'customer_level')) Schema::table('customers', fn($t) => $t->string('customer_level', 16)->nullable());
  }
  public function down(): void {}
};
