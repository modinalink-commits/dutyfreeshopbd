<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    $min = DB::table('categories')->where('slug', '!=', 'cigarette-vape')->min('position');
    if ($min !== null) DB::table('categories')->where('slug', 'cigarette-vape')->update(['position' => (int)$min - 1]);
  }
  public function down(): void {}
};
