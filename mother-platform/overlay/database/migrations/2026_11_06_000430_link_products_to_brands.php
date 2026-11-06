<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
/** Owner decision (6 Nov 2026): link products to the 18 brand records by sub-category name (DUNHILL sub-category -> DUNHILL brand). Only fills products that have NO brand yet; never changes an existing brand link. */
return new class extends Migration {
  public function up(): void {
    $names = ['DUNHILL','BENSON','555 EXPRESS','MARLBORO','MANCHESTER','MEVIUS','DAVIDOFF','DOUBLE HAPPINESS','CAMEL','WINSTON','LM','ORIS','PATRON','MILANO','MOND','ESSE','LAMER','XSO'];
    foreach (DB::table('brands')->whereIn(DB::raw('upper(name)'), $names)->get(['id', 'name']) as $b) {
      DB::table('products')->whereNull('brand_id')->whereRaw('upper(trim(sub)) = ?', [mb_strtoupper(trim($b->name))])->update(['brand_id' => $b->id]);
    }
    try { foreach (DB::table('brands')->whereIn(DB::raw('upper(name)'), $names)->pluck('id') as $id) \App\Support\SeoAutoGenerator::onSave('brand', (int)$id, false); } catch (\Throwable $e) { report($e); }
    foreach (['store.home6', 'store.sitemap5', 'store.globe_products', 'store.catalogfeed4', 'ai.catalog'] as $k) Cache::forget($k);
  }
  public function down(): void {}
};
