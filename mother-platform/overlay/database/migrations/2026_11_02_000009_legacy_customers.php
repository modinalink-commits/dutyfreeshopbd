<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('legacy_customers')) Schema::create('legacy_customers', function (Blueprint $t) {
      $t->id(); $t->string('phone', 11)->unique(); $t->string('name', 160)->nullable(); $t->string('email', 160)->nullable(); $t->text('address')->nullable();
      $t->string('tier', 40)->nullable(); $t->string('ip_status', 40)->nullable(); $t->unsignedInteger('orders_count')->default(0); $t->decimal('orders_amount', 14, 2)->default(0);
      $t->string('last_order', 40)->nullable(); $t->text('comment')->nullable(); $t->unsignedInteger('src_page')->nullable(); $t->boolean('merged')->default(false); $t->timestamps();
    });
    if (!DB::table('settings')->where('key', 'legacy_import_token')->exists()) DB::table('settings')->insert(['key' => 'legacy_import_token', 'value' => 'ca820d3bdd6a6885d83bcbd5b5aca6e1', 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void {}
};
