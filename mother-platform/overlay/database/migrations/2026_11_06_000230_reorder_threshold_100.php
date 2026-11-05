<?php
use App\Support\Procure;
use Illuminate\Database\Migrations\Migration;

/** Updated rule: alert below 100 (all products, published or not), urgency 0 / 1-24 / 25-49 / 50-99, every mapped product. */
return new class extends Migration {
  public function up(): void {
    foreach (['reorder_threshold' => '100', 'reorder_u_high' => '24', 'reorder_u_med' => '49', 'reorder_sourcing' => '1', 'reorder_contact' => '8801824522800'] as $k => $v) Procure::put($k, $v);
  }
  public function down(): void {}
};
