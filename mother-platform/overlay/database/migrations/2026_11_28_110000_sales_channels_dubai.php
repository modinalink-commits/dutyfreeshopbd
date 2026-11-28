<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Sales Channels: a channel is a customer-facing site (own domain and storefront design only; phone, SMS, WhatsApp, payments and every other setting come from the existing DutyFreeBD setup) run from this one Main Admin. Adds slug/domain/status next to the existing name/active/settings, and creates "Duty Free Dubai" as a DRAFT (no orders, no payments, not live). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sales_channels')) return;
    Schema::table('sales_channels', function (Blueprint $t) {
      if (!Schema::hasColumn('sales_channels', 'slug')) $t->string('slug', 60)->nullable()->unique();
      if (!Schema::hasColumn('sales_channels', 'domain')) $t->string('domain', 200)->nullable()->unique();
      if (!Schema::hasColumn('sales_channels', 'status')) $t->string('status', 12)->default('draft')->index();
    });
    if (!DB::table('sales_channels')->where('domain', 'dutyfreedubai.net')->exists()) {
      $s = ['brand' => ['logo_url' => '', 'favicon_url' => '', 'primary' => '#F95505', 'accent' => '#0F1B33', 'theme' => 'light'],
        'hero' => ['image_url' => '', 'title' => '', 'subtitle' => '', 'cta_text' => '', 'cta_url' => ''],
        'categories' => ['Cigarettes', 'Vapes'],
        'history' => [['at' => now()->toDateTimeString(), 'by' => 'system', 'what' => 'Channel created as Draft (no orders, no payments)']]];
      DB::table('sales_channels')->insert(['name' => 'Duty Free Dubai', 'slug' => 'duty-free-dubai', 'domain' => 'dutyfreedubai.net', 'status' => 'draft', 'active' => false, 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
    }
  }
  public function down(): void {
    if (!Schema::hasTable('sales_channels')) return;
    DB::table('sales_channels')->where('domain', 'dutyfreedubai.net')->where('status', 'draft')->delete();
    Schema::table('sales_channels', function (Blueprint $t) { foreach (['slug', 'domain', 'status'] as $c) if (Schema::hasColumn('sales_channels', $c)) $t->dropColumn($c); });
  }
};
