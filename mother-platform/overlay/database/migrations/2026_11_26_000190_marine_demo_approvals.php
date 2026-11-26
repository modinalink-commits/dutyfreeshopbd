<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Marine demo accounts (DEMO-OWNER, DEMO-AGENCY, DEMO-VESSEL): every approval, bonded stores included, and their demo vessel verified (owner, 26 Nov 2026).
 *  Data only, three named test accounts; nothing changes for any real account. Does nothing when the accounts do not exist yet. */
return new class extends Migration {
  const U = ['DEMO-OWNER', 'DEMO-AGENCY', 'DEMO-VESSEL'];
  public function up(): void {
    if (!Schema::hasTable('marine_accounts') || !Schema::hasColumn('marine_accounts', 'bonded_ok')) return;
    $ids = DB::table('marine_accounts')->whereIn('username', self::U)->pluck('id')->all(); if (!$ids) return;
    DB::table('marine_accounts')->whereIn('id', $ids)->update(['bonded_ok' => true, 'bonded_by' => 'owner instruction', 'bonded_at' => now(), 'updated_at' => now()]);
    if (Schema::hasTable('marine_account_vessels') && Schema::hasColumn('marine_account_vessels', 'verified')) DB::table('marine_account_vessels')->whereIn('account_id', $ids)->update(['verified' => true, 'updated_at' => now()]);
  }
  public function down(): void {
    if (!Schema::hasTable('marine_accounts') || !Schema::hasColumn('marine_accounts', 'bonded_ok')) return;
    DB::table('marine_accounts')->whereIn('username', self::U)->update(['bonded_ok' => false, 'bonded_by' => null, 'bonded_at' => null]);
  }
};
