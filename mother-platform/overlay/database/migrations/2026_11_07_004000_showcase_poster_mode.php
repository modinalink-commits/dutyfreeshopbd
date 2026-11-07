<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Showcase slots can show a finished poster creative (full card image) instead of a cut-out product on a glow background.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('showcase_slots')) return;
    if (!Schema::hasColumn('showcase_slots', 'mode')) Schema::table('showcase_slots', function (Blueprint $t) { $t->string('mode', 10)->default('cutout'); });
    foreach (['555-original', '555-gold', 'mevius-sky-blue', 'mevius-wind-blue', 'marlboro-gold', 'dunhill-switch'] as $n) {
      DB::table('showcase_slots')->where('image', '/showcase/' . $n . '.webp')->update(['image' => '/showcase/poster-' . $n . '.webp', 'mode' => 'poster', 'updated_at' => now()]);
    }
  }
  public function down(): void {}
};
