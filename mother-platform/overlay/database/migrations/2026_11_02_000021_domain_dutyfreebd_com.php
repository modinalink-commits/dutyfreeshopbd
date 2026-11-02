<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    // The store now lives on dutyfreebd.com: rewrite stored texts and links that still name the staging domain.
    try { DB::statement("update settings set value = replace(value, 'dutyfreeshopbd.com', 'dutyfreebd.com') where value like '%dutyfreeshopbd.com%' and key not in ('legacy_import_token')"); } catch (\Throwable $e) {}
    foreach (['pages' => ['body', 'content'], 'announcements' => ['text', 'message']] as $t => $cols) {
      foreach ($cols as $c) { try { DB::statement("update $t set $c = replace($c, 'dutyfreeshopbd.com', 'dutyfreebd.com') where $c like '%dutyfreeshopbd.com%'"); } catch (\Throwable $e) {} }
    }
    try { \Illuminate\Support\Facades\Cache::flush(); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
