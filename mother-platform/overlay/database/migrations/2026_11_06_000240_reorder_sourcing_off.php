<?php
use App\Support\Procure;
use Illuminate\Database\Migrations\Migration;

/** Only Cigarette & Vape (stock-controlled categories) are requested from suppliers. */
return new class extends Migration {
  public function up(): void { Procure::put('reorder_sourcing', '0'); }
  public function down(): void {}
};
