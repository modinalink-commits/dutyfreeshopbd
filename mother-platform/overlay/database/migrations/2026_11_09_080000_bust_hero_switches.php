<?php
use Illuminate\Database\Migrations\Migration;
/** Hero slide switches are now read into the store settings; bump the versioned cache so they apply at once. */
return new class extends Migration {
  public function up(): void { try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) { report($e); } }
  public function down(): void {}
};
