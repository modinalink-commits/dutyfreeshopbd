<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner request: Manager role can manage HR and Payroll (employees, pay type, monthly salary/commission). Sets hr=all on every role named like "manager" (idempotent; Admin can change it in Permissions). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('roles')) return;
    foreach (DB::table('roles')->whereRaw("lower(name) like '%manager%'")->get() as $r) {
      $p = json_decode($r->permissions ?: '{}', true) ?: [];
      if (($p['hr'] ?? null) === 'all') continue;
      $p['hr'] = 'all';
      DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($p), 'updated_at' => now()]);
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'permissions.update', 'subject' => 'Role '.$r->name.': HR and payroll = all, by owner request', 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    }
  }
  public function down(): void {}
};
