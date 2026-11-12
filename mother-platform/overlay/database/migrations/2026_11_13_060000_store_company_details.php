<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Owner (13 Nov 2026): every source store keeps its company details (company name, website, email, mobile, support number, address) - staff only, never shown to customers.
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasTable('stores')) return;
            Schema::table('stores', function (Blueprint $t) {
                if (!Schema::hasColumn('stores', 'company_name')) $t->string('company_name', 160)->nullable();
                if (!Schema::hasColumn('stores', 'website')) $t->string('website', 200)->nullable();
                if (!Schema::hasColumn('stores', 'company_email')) $t->string('company_email', 120)->nullable();
                if (!Schema::hasColumn('stores', 'company_phone')) $t->string('company_phone', 40)->nullable();
                if (!Schema::hasColumn('stores', 'support_phone')) $t->string('support_phone', 40)->nullable();
                if (!Schema::hasColumn('stores', 'company_address')) $t->string('company_address', 300)->nullable();
            });
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
