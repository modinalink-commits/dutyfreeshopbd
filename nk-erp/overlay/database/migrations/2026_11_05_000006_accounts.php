<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('type', 10); // cash, bank, mobile
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('head', 60);
            $table->decimal('amount', 12, 2);
            $table->foreignId('account_id')->constrained('money_accounts');
            $table->string('note', 255)->nullable();
            $table->foreignId('reversal_of')->nullable()->constrained('expenses');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->date('transfer_date');
            $table->foreignId('from_account_id')->constrained('money_accounts');
            $table->foreignId('to_account_id')->constrained('money_accounts');
            $table->decimal('amount', 14, 2);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('investment', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['partners', 'account_transfers', 'expenses', 'money_accounts'] as $t) Schema::dropIfExists($t);
    }
};
