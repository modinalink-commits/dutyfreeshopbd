<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Sentry project "dutyfreebd-laravel" (org dutyfreebd). The DSN is a public client key: it can only send events, not read them. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('settings')) return;
    DB::table('settings')->updateOrInsert(['key' => 'sentry_dsn'], ['value' => 'https://dd437a94d1500cba14cb329809b1bc27@o4512402655150080.ingest.us.sentry.io/4512402694733824', 'updated_at' => now(), 'created_at' => now()]);
  }
  public function down(): void {}
};
