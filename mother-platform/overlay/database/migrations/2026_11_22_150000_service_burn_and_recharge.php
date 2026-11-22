<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Services and Balances (22 Nov 2026), part 2: a balance history (so the page can show the real daily use and how long the balance lasts) and a "Recharge" link per service that opens the provider's own page. Nothing is paid from here. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('service_accounts') && !Schema::hasColumn('service_accounts', 'recharge_url')) Schema::table('service_accounts', function (Blueprint $t) { $t->string('recharge_url', 300)->nullable(); });
    if (!Schema::hasTable('service_balance_log')) Schema::create('service_balance_log', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('service_id'); $t->decimal('balance', 16, 2); $t->timestamp('at')->useCurrent();
      $t->index(['service_id', 'at']);
    });
    if (!Schema::hasTable('service_accounts')) return;
    $urls = [
      'Alpha SMS (main)' => 'https://sms.net.bd', 'Bulk SMS (backup)' => 'https://bulksmsbd.net', 'ElevenLabs (voice)' => 'https://elevenlabs.io/app/subscription',
      'Hostinger VPS' => 'https://hpanel.hostinger.com/billing/subscriptions', 'Domains (dutyfreebd.com and others)' => 'https://hpanel.hostinger.com/domains',
      'Meta Ads account balance' => 'https://business.facebook.com/billing_hub/payment_settings?asset_id=2303729536822063',
      'OpenAI API' => 'https://platform.openai.com/settings/organization/billing/overview', 'Anthropic API' => 'https://platform.claude.com/settings/billing',
    ];
    foreach ($urls as $name => $u) DB::table('service_accounts')->where('name', $name)->whereNull('recharge_url')->update(['recharge_url' => $u]);
    // first point of the history: whatever balance is already known today
    foreach (DB::table('service_accounts')->whereNotNull('balance')->get() as $r) {
      if (!DB::table('service_balance_log')->where('service_id', $r->id)->exists()) DB::table('service_balance_log')->insert(['service_id' => $r->id, 'balance' => $r->balance, 'at' => now()]);
    }
  }
  public function down(): void {
    Schema::dropIfExists('service_balance_log');
    if (Schema::hasTable('service_accounts') && Schema::hasColumn('service_accounts', 'recharge_url')) Schema::table('service_accounts', function (Blueprint $t) { $t->dropColumn('recharge_url'); });
  }
};
