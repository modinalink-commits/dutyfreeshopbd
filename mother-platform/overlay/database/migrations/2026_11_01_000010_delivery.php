<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('delivery_zones')) Schema::create('delivery_zones', function (Blueprint $t) {
      $t->id(); $t->string('level', 10)->default('district'); $t->string('name', 120); $t->string('parent', 120)->nullable(); $t->decimal('charge', 10, 2)->default(0); $t->decimal('express_charge', 10, 2)->nullable();
      $t->boolean('active')->default(true); $t->unsignedSmallInteger('sort')->default(0); $t->timestamps(); $t->index(['active', 'level']);
    });
    if (!Schema::hasTable('delivery_overrides')) Schema::create('delivery_overrides', function (Blueprint $t) {
      $t->id(); $t->string('kind', 10); $t->unsignedBigInteger('ref_id')->nullable(); $t->string('ref_name', 160)->nullable(); $t->string('mode', 8)->default('free'); $t->decimal('charge', 10, 2)->nullable();
      $t->string('label', 120)->nullable(); $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
    });
    if (Schema::hasTable('orders')) foreach (['delivery_zone' => fn(Blueprint $t) => $t->string('delivery_zone', 160)->nullable(), 'delivery_original' => fn(Blueprint $t) => $t->decimal('delivery_original', 10, 2)->nullable(),
      'delivery_free_discount' => fn(Blueprint $t) => $t->decimal('delivery_free_discount', 10, 2)->default(0), 'delivery_manual_adj' => fn(Blueprint $t) => $t->decimal('delivery_manual_adj', 10, 2)->nullable(),
      'delivery_manual_by' => fn(Blueprint $t) => $t->string('delivery_manual_by', 80)->nullable(), 'delivery_note' => fn(Blueprint $t) => $t->string('delivery_note', 200)->nullable()] as $c => $f)
      if (!Schema::hasColumn('orders', $c)) Schema::table('orders', fn(Blueprint $t) => $f($t));
    // seed settings from the old configuration (only when missing)
    $s = DB::table('settings')->pluck('value', 'key')->all(); $put = function ($k, $v) use ($s) { if (!array_key_exists($k, $s)) DB::table('settings')->insert(['key' => $k, 'value' => (string)$v, 'created_at' => now(), 'updated_at' => now()]); };
    $in = (float)($s['ship_1_charge'] ?? 70); $out = (float)($s['ship_2_charge'] ?? 130); $ex = (float)($s['ship_3_charge'] ?? 150);
    $fm = 0; try { $r = DB::table('discount_rules')->where('key', 'freedelivery')->where('enabled', true)->value('config'); if ($r) $fm = (float)(json_decode($r, true)['Free over TK'] ?? 0); } catch (\Throwable $e) {}
    $put('delv_on', '1'); $put('delv_default', (string)$out); $put('delv_inside', (string)$in); $put('delv_outside', (string)$out); $put('delv_express', (string)$ex);
    $put('delv_free_on', $fm > 0 ? '1' : '0'); $put('delv_free_min', (string)($fm > 0 ? $fm : 3000));
    $put('delv_inside_label', trim((string)($s['ship_1_name'] ?? '')) ?: 'Inside Chattogram'); $put('delv_outside_label', trim((string)($s['ship_2_name'] ?? '')) ?: 'Outside Chattogram'); $put('delv_express_label', trim((string)($s['ship_3_name'] ?? '')) ?: 'Express / Same Day');
    $put('delv_inside_names', 'Chattogram,Chittagong,Chattagram,চট্টগ্রাম');
  }
  public function down(): void {}
};
