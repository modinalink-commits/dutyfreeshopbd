<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    foreach (['home_categories', 'home_stores'] as $k) {
      if (DB::table('settings')->where('key', $k)->exists()) DB::table('settings')->where('key', $k)->update(['value' => '0']);
    }
  }
  public function down(): void {}
};
