<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
/** Sales Channels: a channel is a customer-facing site (own domain, storefront design, allowed categories and a price rule per category) run from this one Main Admin. Products, stock, orders, payments, SMS, WhatsApp and every other setting stay on the existing DutyFreeBD setup.
 *  Adds slug/domain/status next to name/active/settings. Seeds "Duty Free Dubai" (Cigarettes + Vapes) and two clearly-labelled SAMPLE channels, all as DRAFT (private, no orders, no payments, never live). The samples can be deleted from the Edit screen. */
return new class extends Migration {
  private function hist(string $w): array { return [['at' => now()->toDateTimeString(), 'by' => 'system', 'what' => $w]]; }
  private function row(string $name, string $slug, string $domain, array $cats, string $primary, string $accent, string $theme, string $title): void {
    if (DB::table('sales_channels')->where('domain', $domain)->exists()) return;
    $s = ['brand' => ['logo_url' => '', 'favicon_url' => '', 'primary' => $primary, 'accent' => $accent, 'theme' => $theme],
      'hero' => ['image_url' => '', 'title' => $title, 'subtitle' => '', 'cta_text' => '', 'cta_url' => ''],
      'cats' => $cats, 'access_key' => Str::random(24), 'history' => $this->hist('Channel created as Draft (no orders, no payments)')];
    DB::table('sales_channels')->insert(['name' => $name, 'slug' => $slug, 'domain' => $domain, 'status' => 'draft', 'active' => false, 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
  }
  public function up(): void {
    if (!Schema::hasTable('sales_channels')) return;
    Schema::table('sales_channels', function (Blueprint $t) {
      if (!Schema::hasColumn('sales_channels', 'slug')) $t->string('slug', 60)->nullable()->unique();
      if (!Schema::hasColumn('sales_channels', 'domain')) $t->string('domain', 200)->nullable()->unique();
      if (!Schema::hasColumn('sales_channels', 'status')) $t->string('status', 12)->default('draft')->index();
    });
    $top = DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'name']);
    $byName = $top->pluck('id', 'name');
    $dub = []; foreach (['Cigarettes', 'Vapes'] as $n) if (isset($byName[$n])) $dub[] = ['id' => (int)$byName[$n], 'adj' => 0];
    $this->row('Duty Free Dubai', 'duty-free-dubai', 'dutyfreedubai.net', $dub, '#F95505', '#0F1B33', 'light', '');
    // two SAMPLE channels with different categories and different price rules (placeholder .test domains: never reachable)
    $ids = $top->pluck('id')->map(fn($v) => (int)$v)->values()->all();
    $a = array_slice($ids, 0, 2); $b = array_slice($ids, 2, 2); if (!$b) $b = array_slice($ids, 0, 1);
    $this->row('SAMPLE Channel A', 'sample-channel-a', 'sample-a.dutyfreebd.test', array_map(fn($id, $i) => ['id' => $id, 'adj' => $i === 0 ? 10 : -5], $a, array_keys($a)), '#0F766E', '#0F1B33', 'light', 'Sample channel A');
    $this->row('SAMPLE Channel B', 'sample-channel-b', 'sample-b.dutyfreebd.test', array_map(fn($id, $i) => ['id' => $id, 'mode' => $i === 0 ? 'pct' : 'tk', 'adj' => $i === 0 ? -8 : 100], $b, array_keys($b)), '#7C3AED', '#1F2937', 'premium', 'Sample channel B');
  }
  public function down(): void {
    if (!Schema::hasTable('sales_channels')) return;
    DB::table('sales_channels')->whereIn('domain', ['dutyfreedubai.net', 'sample-a.dutyfreebd.test', 'sample-b.dutyfreebd.test'])->where('status', 'draft')->delete();
    Schema::table('sales_channels', function (Blueprint $t) { foreach (['slug', 'domain', 'status'] as $c) if (Schema::hasColumn('sales_channels', $c)) $t->dropColumn($c); });
  }
};
