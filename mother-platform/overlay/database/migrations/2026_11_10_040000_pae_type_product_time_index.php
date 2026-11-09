<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Top Viewed Products report: five per-product counts filter by (product_id, event_type, created_at). One composite index serves all of them. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('product_analytics_events') || DB::getDriverName() !== 'pgsql') return;
    DB::unprepared('create index if not exists pae_product_type_time on product_analytics_events (product_id, event_type, created_at)');
    DB::unprepared('analyze product_analytics_events');
  }
  public function down(): void { if (DB::getDriverName() === 'pgsql') DB::unprepared('drop index if exists pae_product_type_time'); }
};
