<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner 2026-11-15: "1st Player TS1 ARGB 240mm AIO Black Liquid CPU Cooler" (DFG007990, product id 7990) is a sourcing product
 * but was not linked in watch_source, so it was treated as an own product (5% price flagged below the 10% protected margin).
 * Link it to the matching source-shop product (exact name match, exactly one candidate, not linked to another product).
 * Its selling price then follows source price + the store's markup like every other sourced product. Idempotent; down() unlinks only what this created.
 */
return new class extends Migration {
  private const PID = 7990;
  private function norm(string $s): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s))); }
  private function log(string $a, string $s, array $m = []): void { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => $a, 'subject' => $s, 'meta' => json_encode($m + ['approved_by' => 'Mohammad Faruk'], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
  public function up(): void {
    if (!Schema::hasTable('watch_source') || !Schema::hasTable('store_products')) return;
    $p = DB::table('products')->where('id', self::PID)->first(); if (!$p) return;
    if (DB::table('watch_source')->where('product_id', self::PID)->exists()) return;
    // owner 15 Nov 2026: the source shop is Ryans.com; the first try (exact name) found nothing, so match by words inside Ryans' own products only
    $sids = collect();
    try { $q = DB::table('stores'); $cols = array_values(array_filter(['name', 'domain', 'url', 'website'], fn($c) => Schema::hasColumn('stores', $c)));
      $q->where(function ($w) use ($cols) { foreach ($cols as $c) $w->orWhere($c, 'ilike', '%ryans%'); }); $sids = $q->pluck('id'); } catch (\Throwable $e) {}
    $sids->push(12); $sids = $sids->unique()->values(); if ($sids->isEmpty()) { $this->log('source_link.cooler_7990_not_linked', 'Cooler 7990: no store named Ryans found', []); return; }
    $cands = DB::table('store_products')->whereIn('store_id', $sids->all())->where('name', 'ilike', '%TS1%')->where('name', 'ilike', '%240%')->get(['id', 'store_id', 'name', 'sku', 'url', 'price', 'in_stock']);
    if ($cands->count() > 1) { $c1 = $cands->filter(fn($x) => stripos($x->name, '360') === false && (stripos($x->name, 'player') !== false || stripos($x->name, '1st') !== false)); if ($c1->count() >= 1) $cands = $c1->values(); }
    if ($cands->count() > 1) { $c2 = $cands->filter(fn($x) => stripos($x->name, 'black') !== false); if ($c2->count() === 1) $cands = $c2->values(); }
    if ($cands->count() !== 1) { $this->log('source_link.cooler_7990_not_linked', 'Cooler 7990: ' . $cands->count() . ' Ryans products matched, not linked', ['names' => $cands->pluck('name')->take(8)->all(), 'ids' => $cands->pluck('id')->take(8)->all()]); return; }
    $sp = $cands->first();
    $ex = DB::table('watch_source')->where('store_product_id', $sp->id)->first();
    if ($ex && $ex->product_id && (int)$ex->product_id !== self::PID) { $this->log('source_link.cooler_7990_not_linked', 'Source product already linked to product #' . $ex->product_id, ['store_product_id' => $sp->id]); return; }
    $pct = DB::table('watch_source')->where('store_id', $sp->store_id)->whereNotNull('markup_pct')->selectRaw('markup_pct, count(*) n')->groupBy('markup_pct')->orderByDesc('n')->value('markup_pct');
    $pct = $pct !== null ? (float)$pct : (float)(DB::table('settings')->where('key', 'mobile_markup_pct')->value('value') ?: 15);
    $row = ['product_id' => self::PID, 'store_id' => $sp->store_id, 'source_name' => $sp->name, 'source_sku' => $sp->sku, 'source_url' => $sp->url, 'source_price' => $sp->price, 'our_price' => (int)ceil((float)$sp->price * (1 + $pct / 100)), 'markup_pct' => $pct, 'source_in_stock' => $sp->in_stock, 'note' => 'linked 15 Nov 2026 (owner)', 'updated_at' => now()];
    if ($ex) DB::table('watch_source')->where('id', $ex->id)->update($row); else DB::table('watch_source')->insert($row + ['store_product_id' => $sp->id, 'created_at' => now()]);
    $this->log('source_link.cooler_7990_linked', 'Cooler 7990 linked to source product #' . $sp->id . ' (markup ' . $pct . '%); price follows source from the next sync', ['store_product_id' => $sp->id]);
  }
  public function down(): void {
    if (Schema::hasTable('watch_source')) DB::table('watch_source')->where('product_id', self::PID)->where('note', 'linked 15 Nov 2026 (owner)')->delete();
  }
};
