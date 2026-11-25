<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** SmartPOS plan as shown in the owner's SmartPOS account (26 Nov 2026): Pro Plan, 1075 checks a day, 12 months, valid until 25 Nov 2027. The Services row keeps that end date; the daily quota itself is read live. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) return;
    DB::table('service_accounts')->where('code', 'smartpos')->update(['expires_on' => '2027-11-25', 'note' => 'Backup fraud check (Pro plan, 1075 checks a day, valid until 25 Nov 2027): used automatically when BD Courier fails or its checks run out.', 'updated_at' => now()]);
  }
  public function down(): void {}
};
