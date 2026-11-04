<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner request: the Manager role may VIEW Inventory Pricing (Average Price). Adds costs=view to every role named like "manager" (idempotent; the Admin can change it any time in Permissions). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('roles')) return;
    foreach (DB::table('roles')->whereRaw("lower(name) like '%manager%'")->get() as $r) {
      $p = json_decode($r->permissions ?: '{}', true) ?: [];
      if (($p['costs'] ?? null) === 'view') continue;
      $p['costs'] = 'view'; $p['costs_download'] = false;
      DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($p), 'updated_at' => now()]);
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'permissions.update', 'subject' => 'Role '.$r->name.': Inventory Pricing (cost view) granted by owner request', 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    }
  }
  public function down(): void {}
};
