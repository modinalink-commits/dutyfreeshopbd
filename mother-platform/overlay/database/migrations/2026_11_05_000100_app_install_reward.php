<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('app_installs')) Schema::create('app_installs', function (Blueprint $t) {
      $t->id(); $t->string('install_id', 48)->unique(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('phone', 20)->nullable()->index();
      $t->string('ua', 160)->nullable(); $t->string('ip', 45)->nullable(); $t->timestamp('first_seen_at')->nullable(); $t->timestamp('last_seen_at')->nullable(); $t->timestamp('activated_at')->nullable(); $t->timestamps();
    });
    // One row per customer, ever: phone and install id are unique, so a reinstall (new install id) or a second install by the same customer can never create a second reward.
    if (!Schema::hasTable('app_rewards')) Schema::create('app_rewards', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('phone', 20)->unique(); $t->string('install_id', 48)->nullable()->unique();
      $t->decimal('amount', 10, 2); $t->decimal('balance', 10, 2); $t->decimal('used_total', 10, 2)->default(0);
      $t->string('status', 12)->default('available')->index(); $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('invoice', 30)->nullable(); $t->decimal('applied_amount', 10, 2)->default(0);
      $t->string('source', 40)->default('App Install Reward'); $t->unsignedInteger('restore_count')->default(0);
      $t->timestamp('unlocked_at')->nullable(); $t->timestamp('reserved_at')->nullable(); $t->timestamp('used_at')->nullable(); $t->timestamp('restored_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasColumn('orders', 'via_app')) Schema::table('orders', function (Blueprint $t) { $t->boolean('via_app')->default(false); });
    // Switched off until an admin turns it on in Discount Center; nothing is given before that.
    if (!DB::table('discount_rules')->where('key', 'appinstall')->exists())
      DB::table('discount_rules')->insert(['key' => 'appinstall', 'title' => 'App Install Welcome Discount', 'help' => 'Fixed reward for installing the app with a verified mobile. Auto-applied to the first App order, once per customer, never below the 15% protected profit.',
        'enabled' => false, 'config' => json_encode(['Reward TK' => 100, 'Restore on cancel (1=yes)' => 1, 'Keep unused part after safe-cap (1=yes)' => 0]), 'position' => (int)DB::table('discount_rules')->max('position') + 1, 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void {}
};
