<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Owner (8 Nov 2026): at checkout every eligible discount applies together (Stackable), not "pick one". The protected-margin guard still runs after.
return new class extends Migration {
    public function up(): void {
        DB::table('settings')->updateOrInsert(['key' => 'order_disc_mode'], ['value' => 'stack', 'updated_at' => now(), 'created_at' => now()]);
    }
    public function down(): void {}
};
