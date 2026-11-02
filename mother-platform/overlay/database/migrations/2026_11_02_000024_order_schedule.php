<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'scheduled_at')) $t->timestamp('scheduled_at')->nullable();
            if (!Schema::hasColumn('orders', 'scheduled_by')) $t->unsignedBigInteger('scheduled_by')->nullable();
            if (!Schema::hasColumn('orders', 'scheduled_note')) $t->string('scheduled_note', 500)->nullable();
            if (!Schema::hasColumn('orders', 'approved_at')) $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn('orders', 'auto_approved')) $t->boolean('auto_approved')->default(false);
        });
        try { DB::statement("create index if not exists orders_sched_idx on orders (scheduled_at) where status = 'Scheduled'"); } catch (\Throwable $e) {}
        DB::table('settings')->updateOrInsert(['key' => 'sched_pay_guard'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]);
    }
    public function down(): void {}
};
