<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
  public function up(): void { try { \App\Support\AiMemory::backfill(90); } catch (\Throwable $e) { report($e); } }
  public function down(): void {}
};
