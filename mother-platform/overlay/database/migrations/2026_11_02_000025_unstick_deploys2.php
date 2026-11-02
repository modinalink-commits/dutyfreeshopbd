<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('deployments')) return;
        // older than 25 minutes since it was created = interrupted by a restart (a real deploy takes ~1-2 min)
        DB::table('deployments')
            ->whereIn('status', ['Queued','Downloading','Building','Testing','Deploying'])
            ->where('created_at', '<', now()->subMinutes(25))
            ->update(['status' => 'Failed', 'stage' => 'Failed', 'error' => 'Interrupted by server restart. Re-queued automatically.', 'finished_at' => now(), 'updated_at' => now()]);
    }
    public function down(): void {}
};
