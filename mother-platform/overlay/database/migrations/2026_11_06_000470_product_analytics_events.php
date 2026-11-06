<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Product analytics events (product_view now; add_to_cart / search_click / checkout later). Anonymous: no name, phone or address. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('product_analytics_events')) return;
    DB::statement("create table product_analytics_events (
      id bigserial primary key,
      event_type varchar(24) not null,
      product_id bigint null,
      store_id bigint null,
      visitor_id varchar(40) null,
      device_type varchar(10) null,
      source varchar(20) null,
      referrer varchar(120) null,
      metadata jsonb null,
      created_at timestamp not null default now()
    )");
    DB::statement('create index pae_type_time on product_analytics_events (event_type, created_at)');
    DB::statement('create index pae_product_time on product_analytics_events (product_id, created_at)');
  }
  public function down(): void { Schema::dropIfExists('product_analytics_events'); }
};
