<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Log of parcels sent to the courier's key account manager (name + consignment id only). */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('courier_kam_messages')) return;
    DB::statement("create table courier_kam_messages (
      id bigserial primary key,
      order_id bigint not null,
      consignment_id varchar(60) not null,
      customer_name varchar(60) null,
      wa_id varchar(20) not null,
      via varchar(10) not null default 'text',
      ok boolean not null default false,
      error varchar(260) null,
      sent_by varchar(60) null,
      created_at timestamp not null default now()
    )");
    DB::statement('create index ckm_order on courier_kam_messages (order_id)');
    DB::statement('create index ckm_cons_time on courier_kam_messages (consignment_id, created_at)');
  }
  public function down(): void { Schema::dropIfExists('courier_kam_messages'); }
};
