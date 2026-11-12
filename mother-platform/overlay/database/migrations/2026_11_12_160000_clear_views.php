<?php
use Illuminate\Database\Migrations\Migration;
/** One-shot: drop compiled Blade views so edited templates are recompiled. */
return new class extends Migration {
  public function up(): void { try { \Illuminate\Support\Facades\Artisan::call('view:clear'); } catch (\Throwable $e) {} }
  public function down(): void {}
};
