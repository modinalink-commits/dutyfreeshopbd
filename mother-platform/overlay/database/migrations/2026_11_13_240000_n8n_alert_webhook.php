<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// n8n (Telegram) alert forwarding target: the private n8n webhook URL. New High/Critical admin alerts are posted here.
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('settings')) return;
      $u = 'https://n8n.187-53-139-216.sslip.io/webhook/df-alert-6d47234499f5543f57d8bb98f9dce694';
      $ex = DB::table('settings')->where('key', 'n8n_alert_webhook')->first();
      if ($ex) { if (!(string)$ex->value) DB::table('settings')->where('key', 'n8n_alert_webhook')->update(['value' => $u]); }
      else DB::table('settings')->insert(['key' => 'n8n_alert_webhook', 'value' => $u]);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
