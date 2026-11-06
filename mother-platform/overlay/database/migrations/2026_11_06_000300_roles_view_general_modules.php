<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner request: managers and staff can SEE all general modules. Raises none -> view (never lowers or changes an existing level) for orders, products, customers, couriers, marketing, reports on every role except super/admin. Sensitive modules (purchase/supplier cost, accounts, settings, hr, costs, staff log) are left exactly as they are. The Admin can change anything in Permissions. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('roles')) return;
    $mods = ['orders', 'products', 'customers', 'couriers', 'marketing', 'reports'];
    foreach (DB::table('roles')->get() as $r) {
      $n = strtolower((string)$r->name); if (in_array($n, ['super', 'admin', 'super admin', 'administrator'], true)) continue;
      $p = json_decode($r->permissions ?: '{}', true) ?: []; $ch = [];
      foreach ($mods as $m) { if (($p[$m] ?? 'none') === 'none') { $p[$m] = 'view'; $p[$m . '_download'] = $p[$m . '_download'] ?? false; $ch[] = $m; } }
      if (!$ch) continue;
      DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($p), 'updated_at' => now()]);
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'permissions.update', 'subject' => 'Role ' . $r->name . ': view granted on ' . implode(', ', $ch) . ' (owner request)', 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    }
  }
  public function down(): void {}
};
