<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): Manager and Admin roles both get the shipment-tick permissions (critical override + allow duplicate).
return new class extends Migration {
    public function up(): void {
        try {
            foreach (DB::table('roles')->whereRaw('lower(name) like ? or lower(name) like ?', ['%manager%', '%admin%'])->get() as $r) {
                $p = json_decode((string)$r->permissions, true) ?: []; $p['ship_override'] = true; $p['dup_allow'] = true;
                DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($p), 'updated_at' => now()]);
            }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
