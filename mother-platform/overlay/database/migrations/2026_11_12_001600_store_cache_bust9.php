<?php
use Illuminate\Database\Migrations\Migration;
/** Home live badge style applied: refresh the cached storefront. */
return new class extends Migration {
  public function up(): void { \App\Http\Controllers\StoreController::bust(); }
  public function down(): void {}
};
