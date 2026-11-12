<?php
use Illuminate\Database\Migrations\Migration;
/** The slider-speed setting was changed straight in the database: drop the storefront cache so the home page reads it now. */
return new class extends Migration {
  public function up(): void { try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {} }
  public function down(): void {}
};
