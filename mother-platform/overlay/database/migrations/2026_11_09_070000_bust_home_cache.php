<?php
use Illuminate\Database\Migrations\Migration;
/** Home banner switches are cached under the versioned store cache; bump it so the new hero shows at once. */
return new class extends Migration {
  public function up(): void { try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) { report($e); } }
  public function down(): void {}
};
