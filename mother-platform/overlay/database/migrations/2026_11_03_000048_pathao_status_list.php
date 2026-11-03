<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Every delivery status Pathao's merchant panel can show (read from its Order Status filter). Ordinary tracking words are mapped; anything that could change returns, stock or money waits for the owner's decision in the Pathao Status Map. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('pathao_status_map')) return;
    $k = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($s)));
    // [label, stage, needs_mapping, note]
    $rows = [
      ['Pending', 'Pending', 0, 'Tracking'], ['At Sorting', 'At Hub', 0, 'Tracking'], ['Assigned for Delivery', 'Out for Delivery', 0, 'Tracking'], ['At Delivery Hub', 'At Hub', 0, 'Tracking'],
      ['In Transit', 'In Transit', 0, 'Tracking'], ['On Hold', 'Hold', 0, 'Tracking'], ['Reattempt Requested', 'Rescheduled', 0, 'Delivery will be tried again'], ['Delivery', 'Delivered', 0, 'Parcel delivered'],
      ['Return Requested', null, 1, 'A return has been requested. Choose what it means for the order.'], ['Reprocess Pending', null, 1, 'Pathao is reprocessing the parcel. Choose what it means.'], ['Reprocessed', null, 1, 'Choose what it means.'],
      ['Returned', null, 1, 'Pathao says the parcel came back. Usually Return Pending here; Returned is set only when our warehouse confirms receipt.'],
      ['Return In Transit', null, 1, 'Return journey. Usually Return Pending.'], ['Return At Sorting', null, 1, 'Return journey. Usually Return Pending.'], ['Assigned for return', null, 1, 'Return journey. Usually Return Pending.'],
      ['Return On Hold', null, 1, 'Return journey. Usually Return Pending.'], ['Return To Merchant', null, 1, 'Return journey, on its way to us. Usually Return Pending.'],
      ['Returned To Inventory', null, 1, 'Pathao says the goods are back in its inventory. Choose what it means.'], ['At Inventory', null, 1, 'Choose what it means.'], ['First Mile Hub', null, 1, 'Choose what it means.'],
      ['Damaged', null, 1, 'Parcel damaged. Needs a decision.'], ['Lost', null, 1, 'Parcel lost. Needs a decision.'], ['Handed over to Admin', null, 1, 'Needs a decision.'],
    ];
    foreach ($rows as [$label, $stage, $need, $note]) {
      DB::table('pathao_status_map')->insertOrIgnore(['raw_key' => $k($label), 'raw_label' => $label, 'action' => 'none', 'stage' => $stage, 'needs_mapping' => (bool)$need, 'confirmed' => false, 'seen_count' => 0, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }
    \Illuminate\Support\Facades\Cache::forget('pathao.status_map');
  }
  public function down(): void {}
};
