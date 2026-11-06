<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('orders', 'courier_note_source')) Schema::table('orders', fn($t) => $t->string('courier_note_source', 16)->nullable());
    if (!Schema::hasTable('courier_note_log')) {
      DB::statement("create table courier_note_log (id bigserial primary key, order_id bigint null, courier varchar(24) null, source varchar(16) not null, note text not null, sent_at timestamp not null default now())");
      DB::statement('create index cnl_order on courier_note_log (order_id)');
    }
  }
  public function down(): void { Schema::dropIfExists('courier_note_log'); }
};
