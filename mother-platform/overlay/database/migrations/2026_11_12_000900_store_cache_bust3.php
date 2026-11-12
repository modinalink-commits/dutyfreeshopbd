<?php
use Illuminate\Database\Migrations\Migration;
/** Home badge preview switcher added: refresh the cached storefront. */
return new class extends Migration {
  public function up(): void { \App\Http\Controllers\StoreController::bust(); }
  public function down(): void {}
};
