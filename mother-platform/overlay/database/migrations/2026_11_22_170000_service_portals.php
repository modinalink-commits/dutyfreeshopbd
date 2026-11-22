<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Services and Balances (22 Nov 2026), part 3: every other paid system/API the website really uses (found in the code), each with its own portal link. Expiry dates are NOT guessed: they stay blank until read from the token or typed in. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) return;
    $has = Schema::hasColumn('service_accounts', 'recharge_url'); $now = now();
    $add = function (?string $code, string $name, string $cat, string $kind, string $unit, int $sort, ?string $url, ?string $note, ?string $exp = null, int $remind = 30) use ($has, $now) {
      if (DB::table('service_accounts')->where('name', $name)->exists()) return;
      $row = ['code' => $code, 'name' => $name, 'category' => $cat, 'kind' => $kind, 'unit' => $unit, 'sort' => $sort, 'note' => $note, 'expires_on' => $exp, 'remind_days' => $remind, 'created_at' => $now, 'updated_at' => $now];
      if ($has) $row['recharge_url'] = $url;
      DB::table('service_accounts')->insert($row);
    };
    $add(null, 'Courier fraud check (BDCourier)', 'Fraud check', 'manual', 'checks', 100, 'https://bdcourier.com', 'Used on every new order. Type the plan expiry date and the checks left from the BDCourier portal.');
    $add(null, 'Steadfast courier and fraud score', 'Courier', 'manual', 'BDT', 110, 'https://portal.packzy.com', 'Courier booking and the Steadfast fraud score. Type the expiry of the API key if the portal shows one.');
    $add('tok_fc_pathao', 'Pathao merchant token (fraud check)', 'Courier', 'auto', 'days', 120, 'https://merchant.pathao.com', 'The expiry date is read from the token itself.');
    $add('tok_fc_redx', 'RedX token (fraud check)', 'Courier', 'auto', 'days', 130, 'https://redx.com.bd', 'The expiry date is read from the token itself.');
    $add(null, 'Carrybee courier', 'Courier', 'manual', 'BDT', 140, null, 'Type the balance or the key expiry if Carrybee shows one.');
    $add(null, 'Meta Conversions API token', 'Ads', 'manual', 'days', 150, 'https://business.facebook.com/events_manager2', 'Token used to send purchase events to Meta. Type the expiry date if the token has one.');
    $add(null, 'GitHub deploy token', 'Hosting', 'manual', 'days', 160, 'https://github.com/settings/tokens', 'Token the admin uses to deploy updates. If it expires, deploys stop: type its expiry date.');
    $add(null, 'Business email (Hostinger)', 'Hosting', 'manual', 'days', 170, $has ? 'https://hpanel.hostinger.com/billing/subscriptions' : null, 'Starter Business Email, auto-renew ON. Renewal date read from Hostinger billing on 22 Nov 2026.', '2027-10-31');
    // the website's AI (chat, agent, briefs) calls Anthropic only; OpenAI is not used anywhere in the code
    DB::table('service_accounts')->where('name', 'Anthropic API')->update(['note' => DB::raw("'Powers the website AI: customer chat, AI agent and briefs. ' || COALESCE(note, '')")]);
    DB::table('service_accounts')->where('name', 'OpenAI API')->update(['active' => false, 'note' => 'Not used by the website: its AI runs on Anthropic. Switched off here; turn it on if you start using OpenAI.']);
  }
  public function down(): void {
    if (!Schema::hasTable('service_accounts')) return;
    DB::table('service_accounts')->whereIn('name', ['Courier fraud check (BDCourier)', 'Steadfast courier and fraud score', 'Pathao merchant token (fraud check)', 'RedX token (fraud check)', 'Carrybee courier', 'Meta Conversions API token', 'GitHub deploy token', 'Business email (Hostinger)'])->delete();
  }
};
