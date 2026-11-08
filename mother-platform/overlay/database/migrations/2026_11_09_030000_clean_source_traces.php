<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
/** Owner rule: no trace of the source shops on our pages. Product names carried "| Time Access" and "Luxotix ..."; descriptions carried "Brand: amour.com.bd". Clean names (new links + 301 from the old ones), clean descriptions, and blank the SEO fields of renamed products so the SEO fill rewrites them. */
return new class extends Migration {
  private const RX = '/time\s*-?\s*access|luxotix|amour\.com\.bd|ryans|startech|timeaccess/i';
  public function up(): void {
    try {
      $ids = DB::table('watch_source')->whereNotNull('product_id')->pluck('product_id')->all(); if (!$ids) return;
      $old = []; $changed = [];
      foreach (array_chunk($ids, 500) as $chunk) {
        foreach (DB::table('products')->whereIn('id', $chunk)->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id', 'description']) as $p) {
          $n = (string)$p->name; $c = $n;
          $c = preg_replace('/\s*[|\-–]\s*Time\s*Access\b.*$/iu', '', $c);
          $c = preg_replace('/\bTime\s*-?\s*Access\b/iu', '', $c);
          $c = preg_replace('/\bLuxotix\b\s*/iu', '', $c);
          $c = trim(preg_replace('/\s{2,}/u', ' ', $c), " \t-|–");
          $upd = [];
          if ($c !== '' && $c !== $n) { $old[$p->id] = \App\Http\Controllers\StoreController::url($p); $upd = ['name' => $c, 'path_slug' => null]; foreach (['meta_title', 'meta_description', 'meta_keywords', 'seo_title', 'seo_description', 'seo_keywords'] as $col) if (Schema::hasColumn('products', $col)) $upd[$col] = null; $changed[] = $p->id; }
          $d = (string)$p->description;
          if ($d !== '' && preg_match(self::RX, $d) || preg_match('/<li>\s*<strong>\s*Brand:?\s*<\/strong>\s*(Amouri|Amour)/i', $d)) {
            $nd = preg_replace('#<li>(?:(?!</li>).)*?(amour|luxotix|time\s*-?\s*access|ryans|startech)(?:(?!</li>).)*?</li>#isu', '', $d);
            $nd = preg_replace('#<li>\s*<strong>\s*Brand:?\s*</strong>\s*(Amouri|Amour)\s*</li>#i', '', $nd);
            $nd = preg_replace('/\b(amour\.com\.bd|luxotix|time\s*-?\s*access|ryans|startech)\b/iu', '', $nd);
            $nd = preg_replace('#<h3>\s*Specifications\s*</h3>\s*<ul>\s*</ul>#i', '', $nd);
            if ($nd !== $d) $upd['description'] = $nd;
          }
          if ($upd) DB::table('products')->where('id', $p->id)->update($upd + ['updated_at' => now()]);
        }
      }
      if ($changed) {
        \App\Support\Slugs::assignAll();
        Cache::forget('v' . (Cache::get('store.ver') ?: 0) . '.store.catslugs');
        if (Schema::hasTable('seo_redirects')) foreach (array_chunk($changed, 500) as $chunk) foreach (DB::table('products')->whereIn('id', $chunk)->get(['id', 'name', 'ext_id', 'sub', 'sub_sub', 'path_slug', 'category_id']) as $p) { $new = \App\Http\Controllers\StoreController::url($p); if (($old[$p->id] ?? '') !== '' && $old[$p->id] !== $new) \App\Support\SeoMgr::addRedirect($old[$p->id], $new, 301, 'source-trace'); }
      }
      foreach (['store.cats', 'store.catslugs', 'store.subs', 'store.home11', 'store.sitemap14', 'ai.catalog2'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
      try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
