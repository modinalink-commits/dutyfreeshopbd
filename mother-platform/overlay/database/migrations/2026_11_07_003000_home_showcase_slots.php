<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Homepage "6 Product Animated Showcase Strip": six admin-controlled slots (product, cut-out image, colour theme, link, on/off).
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('showcase_slots')) {
      Schema::create('showcase_slots', function (Blueprint $t) {
        $t->id(); $t->unsignedSmallInteger('position')->default(0)->index();
        $t->unsignedBigInteger('product_id')->nullable(); $t->string('title', 120)->nullable();
        $t->string('image', 255)->nullable(); $t->string('theme', 9)->default('#B45309'); $t->string('link', 255)->nullable();
        $t->boolean('active')->default(true); $t->timestamps();
      });
    }
    if (DB::table('showcase_slots')->count() > 0) return;
    $seed = [
      ['555 State Express Original London', '555-original', '#C99A1D'],
      ['555 State Express Gold London', '555-gold', '#1E3A8A'],
      ['Mevius Sky Blue Japan', 'mevius-sky-blue', '#1D6FE0'],
      ['Mevius Wind Blue Japan', 'mevius-wind-blue', '#22A7D6'],
      ['Marlboro Gold China', 'marlboro-gold', '#D4A017'],
      ['Dunhill Switch 8 Mg (Blue)', 'dunhill-switch', '#2563EB'],
    ];
    foreach ($seed as $i => [$name, $img, $col]) {
      $pid = null; try { $pid = DB::table('products')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->orderByDesc('published')->orderBy('id')->value('id'); } catch (\Throwable $e) {}
      DB::table('showcase_slots')->insert(['position' => $i + 1, 'product_id' => $pid, 'title' => $name, 'image' => '/showcase/' . $img . '.webp', 'theme' => $col, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
  }
  public function down(): void {}
};
