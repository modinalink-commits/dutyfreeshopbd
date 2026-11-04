<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('size_kg');
            $table->date('from_date');
            $table->decimal('do_rate', 10, 2);
            $table->decimal('transport', 10, 2)->default(0);
            foreach (['target', 'slab', 'exclusive', 'deadline', 'late', 'dd'] as $c) {
                $table->decimal($c, 10, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['size_kg', 'from_date']);
        });

        Schema::create('company_months', function (Blueprint $table) {
            $table->string('month', 7)->primary(); // YYYY-MM
            $table->decimal('kg_commission', 8, 2)->default(0);
            $table->unsignedTinyInteger('deadline_day')->default(16);
            $table->json('flags')->nullable(); // e.g. {"target_12":true,"slab_35":true}
            $table->decimal('ait_pct', 5, 2)->default(10);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        // Money with the company. Positive = money AYGas holds for us, negative = bills. Never edited; reverse instead.
        Schema::create('company_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->index();
            $table->string('kind', 16); // opening, deposit, challan, incentive, commission, adjustment, reversal
            $table->string('deposit_kind', 16)->nullable(); // bank, bill_payment, security
            $table->string('challan_no', 40)->nullable();
            $table->unsignedSmallInteger('size_kg')->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->decimal('do_rate', 10, 2)->nullable();
            $table->decimal('transport', 10, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('gross', 14, 2)->nullable();
            $table->decimal('ait', 14, 2)->nullable();
            $table->string('for_month', 7)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('reversal_of')->nullable()->constrained('company_entries');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_entries');
        Schema::dropIfExists('company_months');
        Schema::dropIfExists('company_rates');
    }
};
