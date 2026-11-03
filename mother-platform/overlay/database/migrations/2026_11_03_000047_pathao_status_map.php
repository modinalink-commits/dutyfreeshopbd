<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Pathao status map: every status Pathao can send is a row on OUR server that can be adjusted (Pathao outcome label, what the order does,
 * courier stage). Unknown statuses are recorded and flagged ("Courier Status Mapping Required") instead of being guessed.
 * Also keeps the exact raw Pathao value, the raw payload and the Pathao outcome label on the order.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('pathao_status_map')) Schema::create('pathao_status_map', function (Blueprint $t) {
      $t->id(); $t->string('raw_key', 120)->unique(); $t->string('raw_label', 160); $t->string('outcome', 30)->nullable(); $t->string('action', 30)->default('none'); $t->string('stage', 30)->nullable();
      $t->boolean('needs_mapping')->default(false); $t->boolean('confirmed')->default(true); $t->unsignedInteger('seen_count')->default(0); $t->timestamp('last_seen_at')->nullable(); $t->text('note')->nullable(); $t->timestamps();
    });
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'pathao_raw_status')) $t->string('pathao_raw_status', 160)->nullable();
      if (!Schema::hasColumn('orders', 'pathao_raw_payload')) $t->text('pathao_raw_payload')->nullable();
      if (!Schema::hasColumn('orders', 'pathao_outcome')) $t->string('pathao_outcome', 30)->nullable();
      if (!Schema::hasColumn('orders', 'courier_flag')) $t->string('courier_flag', 60)->nullable()->index();
    });
    $key = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string)$s)));
    $now = now(); $rows = [];
    // [label, Pathao outcome, action, courier stage, needs mapping, confirmed, note]
    foreach ([
      ['Partial Return', 'Partial Return', 'partial_pending', 'Delivered', 0, 1, 'Pathao dashboard category'],
      ['Partial Delivery', 'Partial Return', 'partial_pending', 'Delivered', 0, 0, 'Assumed Pathao wording for a partial delivery. Edit if Pathao uses another word.'],
      ['Exchange', 'Exchange', 'exchange_pending', null, 0, 1, 'Pathao dashboard category'],
      ['Regular Return', 'Regular Return', 'return_pending', 'Returned', 0, 1, 'Pathao dashboard category'],
      ['Return', 'Regular Return', 'return_pending', 'Returned', 0, 1, 'Seen live on real parcels'],
      ['Paid Return', 'Paid Return', 'none', null, 1, 1, 'Pathao dashboard category. Choose which flow it belongs to, then save.'],
    ] as [$l, $o, $a, $s, $n, $c, $note]) $rows[$key($l)] = ['raw_key' => $key($l), 'raw_label' => $l, 'outcome' => $o, 'action' => $a, 'stage' => $s, 'needs_mapping' => (bool)$n, 'confirmed' => (bool)$c, 'seen_count' => 0, 'note' => $note, 'created_at' => $now, 'updated_at' => $now];
    // Everything Pathao has already sent for existing parcels: ordinary tracking words are fine as they are; return-like words need a decision.
    foreach (DB::table('orders')->whereNotNull('courier_status')->where('courier_status', '<>', '')->selectRaw('courier_status s, count(*) n, max(courier_synced_at) at')->groupBy('courier_status')->get() as $r) {
      $k = $key($r->s); if ($k === '' || isset($rows[$k])) { if (isset($rows[$k])) $rows[$k]['seen_count'] = (int)$r->n; continue; }
      $needs = (bool)preg_match('/return|partial|exchange|paid/', $k);
      $rows[$k] = ['raw_key' => $k, 'raw_label' => mb_substr((string)$r->s, 0, 160), 'outcome' => null, 'action' => 'none', 'stage' => null, 'needs_mapping' => $needs, 'confirmed' => true, 'seen_count' => (int)$r->n, 'last_seen_at' => $r->at, 'note' => $needs ? 'Seen on existing parcels. Choose what it means, then save.' : 'Ordinary tracking status', 'created_at' => $now, 'updated_at' => $now];
    }
    foreach (array_chunk(array_values($rows), 100) as $chunk) { $cols = array_unique(array_merge(...array_map('array_keys', $chunk))); foreach ($chunk as &$c) foreach ($cols as $col) $c[$col] = $c[$col] ?? null; unset($c); DB::table('pathao_status_map')->insertOrIgnore($chunk); }
    if (DB::getDriverName() === 'pgsql' && !DB::connection()->pretending()) {
      DB::statement("UPDATE orders SET pathao_raw_status = courier_status WHERE pathao_raw_status IS NULL AND courier_status IS NOT NULL AND coalesce(courier,'Pathao') = 'Pathao'");
      DB::statement("UPDATE orders SET pathao_outcome = 'Regular Return' WHERE pathao_outcome IS NULL AND lower(courier_status) IN ('return','regular return') AND coalesce(courier,'Pathao') = 'Pathao'");
    }
  }
  public function down(): void {}
};
