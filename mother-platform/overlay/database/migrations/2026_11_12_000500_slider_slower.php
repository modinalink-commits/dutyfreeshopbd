<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Home product sliders were turning every 5 s (felt too fast): default is now 9 s. Only a never-changed value (missing or 5) is moved; anything the owner set stays. */
return new class extends Migration {
  public function up(): void {
    $v = DB::table('settings')->where('key', 'slide_speed')->value('value');
    if ($v === null || trim((string)$v) === '' || trim((string)$v) === '5') DB::table('settings')->updateOrInsert(['key' => 'slide_speed'], ['value' => '9', 'updated_at' => now(), 'created_at' => now()]);
  }
  public function down(): void {}
};
