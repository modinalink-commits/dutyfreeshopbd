<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Services and Balances (22 Nov 2026): one row per paid service/API/subscription. "auto" rows are read from the provider's own API; "manual" rows are typed in. No balance is ever guessed: a blank means "not set". */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('service_accounts')) Schema::create('service_accounts', function (Blueprint $t) {
      $t->id(); $t->string('code', 30)->nullable()->unique(); $t->string('name', 120); $t->string('category', 30)->default('Other')->index();
      $t->string('kind', 8)->default('manual'); $t->string('unit', 16)->default('BDT');
      $t->decimal('balance', 16, 2)->nullable(); $t->decimal('low_at', 16, 2)->nullable(); $t->date('expires_on')->nullable(); $t->unsignedSmallInteger('remind_days')->default(14);
      $t->text('note')->nullable(); $t->string('last_text', 200)->nullable(); $t->boolean('last_ok')->nullable(); $t->timestamp('checked_at')->nullable();
      $t->boolean('active')->default(true); $t->unsignedSmallInteger('sort')->default(100); $t->string('updated_by', 80)->nullable(); $t->timestamps();
    });
    if (DB::table('service_accounts')->count() > 0) return;
    $now = now(); $r = fn($code, $name, $cat, $kind, $unit, $sort, $low = null, $note = null) => ['code' => $code, 'name' => $name, 'category' => $cat, 'kind' => $kind, 'unit' => $unit, 'sort' => $sort, 'low_at' => $low, 'note' => $note, 'created_at' => $now, 'updated_at' => $now];
    DB::table('service_accounts')->insert([
      $r('alpha_sms', 'Alpha SMS (main)', 'SMS', 'auto', 'BDT', 10, 2000, 'Read from the provider. Low-balance limit is editable.'),
      $r('bulk_sms', 'Bulk SMS (backup)', 'SMS', 'auto', 'BDT', 20, 2000, 'Read from the provider. Low-balance limit is editable.'),
      $r('elevenlabs', 'ElevenLabs (voice)', 'Voice', 'auto', 'chars', 30, null, 'Characters left in the current plan period. Needs a key that may read the account.'),
      $r(null, 'Hostinger VPS', 'Hosting', 'manual', 'BDT', 40, null, 'Enter the renewal date.'),
      $r(null, 'Domains (dutyfreebd.com and others)', 'Domain', 'manual', 'BDT', 50, null, 'Enter the nearest renewal date.'),
      $r(null, 'PBX / VoIP calling credit', 'Voice', 'manual', 'BDT', 60),
      $r(null, 'Meta Ads account balance', 'Ads', 'manual', 'BDT', 70, null, 'Read it in Ads Manager and type it here.'),
      $r(null, 'OpenAI API', 'AI', 'manual', 'USD', 80, null, 'No public balance API; type the balance from the billing page.'),
      $r(null, 'Anthropic API', 'AI', 'manual', 'USD', 90, null, 'No public balance API; type the balance from the console.'),
    ]);
  }
  public function down(): void { Schema::dropIfExists('service_accounts'); }
};
