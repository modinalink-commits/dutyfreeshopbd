<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Every admin user gets his own extension: sip_accounts rows with user_id. The row with user_id NULL stays as the shared PBX server settings. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('sip_accounts', 'user_id')) Schema::table('sip_accounts', function (Blueprint $t) { $t->unsignedBigInteger('user_id')->nullable()->index(); });
    $base = DB::table('sip_accounts')->whereNull('user_id')->orderBy('id')->first();
    if ($base && $base->username && !DB::table('sip_accounts')->whereNotNull('user_id')->exists()) {
      $u = DB::table('users')->where('name', 'ilike', '%faruk%')->orderBy('id')->first() ?: DB::table('users')->orderBy('id')->first();
      if ($u) { $row = (array)$base; unset($row['id']); $row['user_id'] = $u->id; $row['created_at'] = now(); $row['updated_at'] = now(); DB::table('sip_accounts')->insert($row); }
    }
  }
  public function down(): void {}
};
