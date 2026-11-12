<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner asked (12 Nov): turn the home "Recently ordered" live-orders section ON. */
return new class extends Migration {
  public function up(): void {
    DB::table('settings')->updateOrInsert(['key' => 'home_live_orders'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]);
    \App\Http\Controllers\StoreController::bust();
  }
  public function down(): void {}
};
