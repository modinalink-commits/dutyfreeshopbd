<?php
use Illuminate\Database\Migrations\Migration;
/** Home top layout changed (hero side cards): refresh the cached storefront. */
return new class extends Migration {
  public function up(): void { \App\Http\Controllers\StoreController::bust(); }
  public function down(): void {}
};
