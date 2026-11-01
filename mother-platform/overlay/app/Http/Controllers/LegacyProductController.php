<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Temporary token-protected intake of the old DutyFreeBD products into a staging table, plus a dry-run comparison with the live catalog. */
class LegacyProductController extends Controller
{
  private function ok(Request $r): bool { $t = (string)DB::table('settings')->where('key', 'legacy_import_token')->value('value'); return $t !== '' && hash_equals($t, (string)$r->query('k')); }
  public function ingest(Request $r) {
    if (!$this->ok($r)) abort(404);
    $rows = json_decode((string)$r->input('rows'), true); if (!is_array($rows)) return response()->json(['ok' => false]);
    $n = 0;
    foreach ($rows as $x) {
      $id = (int)($x['id'] ?? 0); if (!$id) continue;
      DB::table('legacy_products')->updateOrInsert(['old_id' => $id], [
        'name' => mb_substr((string)($x['name'] ?? ''), 0, 300), 'slug' => mb_substr((string)($x['slug'] ?? ''), 0, 300), 'product_code' => mb_substr((string)($x['product_code'] ?? ''), 0, 100) ?: null, 'barcode' => mb_substr((string)($x['barcode'] ?? ''), 0, 100) ?: null,
        'category_id' => (int)($x['category_id'] ?? 0) ?: null, 'sub_category_id' => (int)($x['sub_category_id'] ?? 0) ?: null, 'sub_sub_category_id' => (int)($x['sub_sub_category_id'] ?? 0) ?: null, 'brand_id' => (int)($x['brand_id'] ?? 0) ?: null,
        'status' => isset($x['status']) ? (int)$x['status'] : null, 'price' => (float)($x['price'] ?? 0), 'sale_price' => (float)($x['sale_price'] ?? 0), 'purchase_price' => (float)($x['purchase_price'] ?? 0), 'stock' => (int)($x['stock'] ?? 0),
        'thumbnail_img' => mb_substr((string)($x['thumbnail_img'] ?? ''), 0, 300) ?: null, 'product_position' => (int)($x['product_position'] ?? 0), 'old_created_at' => mb_substr((string)($x['created_at'] ?? ''), 0, 30), 'old_updated_at' => mb_substr((string)($x['updated_at'] ?? ''), 0, 30),
        'raw' => json_encode($x, JSON_UNESCAPED_UNICODE), 'src_page' => (int)($x['_page'] ?? 0), 'updated_at' => now(), 'created_at' => now()]);
      $n++;
    }
    return response()->json(['ok' => true, 'saved' => $n]);
  }
  public function stats(Request $r) {
    if (!$this->ok($r)) abort(404);
    return response()->json(['rows' => DB::table('legacy_products')->count(), 'pages' => DB::table('legacy_products')->distinct()->count('src_page')]);
  }
  public function review() {
    $q = fn($sql) => (int)DB::selectOne($sql)->n;
    $d = [
      'staged' => $q('select count(*) n from legacy_products'),
      'live_products' => $q('select count(*) n from products'),
      'matched' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id'),
      'unmatched' => $q('select count(*) n from legacy_products l where not exists (select 1 from products p where p.ext_id = l.old_id)'),
      'price_diff' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where abs(coalesce(p.price,0) - l.price) > 0.5'),
      'live_not_in_old' => $q('select count(*) n from products p where p.ext_id is not null and not exists (select 1 from legacy_products l where l.old_id = p.ext_id)'),
      'live_no_ext' => $q('select count(*) n from products where ext_id is null'),
      'done' => $q('select count(*) n from legacy_products where action is not null'),
    ];
    $d['cmp'] = [
      'price = old sale price' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where abs(coalesce(p.price,0) - l.sale_price) < 0.5'),
      'price = old list price' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where abs(coalesce(p.price,0) - l.price) < 0.5'),
      'offer = old sale price' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where abs(coalesce(p.offer_price,0) - l.sale_price) < 0.5'),
      'compare = old list price' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where abs(coalesce(p.compare_price,0) - l.price) < 0.5'),
      'missing: same name as a live product (other id)' => $q("select count(*) n from legacy_products l where not exists (select 1 from products p where p.ext_id = l.old_id) and exists (select 1 from products p where lower(trim(p.name)) = lower(trim(l.name)))"),
      'live-not-in-old: published' => $q('select count(*) n from products p where p.ext_id is not null and p.published and not exists (select 1 from legacy_products l where l.old_id = p.ext_id)'),
      'live-not-in-old: max ext_id' => $q('select coalesce(max(ext_id),0) n from products p where p.ext_id is not null and not exists (select 1 from legacy_products l where l.old_id = p.ext_id)'),
      'live-not-in-old: min ext_id' => $q('select coalesce(min(ext_id),0) n from products p where p.ext_id is not null and not exists (select 1 from legacy_products l where l.old_id = p.ext_id)'),
      'old max id' => $q('select coalesce(max(old_id),0) n from legacy_products'),
      'live published (matched)' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where p.published'),
      'old active (matched)' => $q('select count(*) n from legacy_products l join products p on p.ext_id = l.old_id where l.status = 1'),
    ];
    $d['liveonly'] = DB::select('select p.ext_id, p.name, p.price, p.published from products p where p.ext_id is not null and not exists (select 1 from legacy_products l where l.old_id = p.ext_id) order by p.ext_id desc limit 10');
    $d['status'] = DB::select('select coalesce(status::text, \'null\') s, count(*) n from legacy_products group by 1 order by 2 desc');
    $d['unm_by_status'] = DB::select('select coalesce(l.status::text, \'null\') s, count(*) n from legacy_products l where not exists (select 1 from products p where p.ext_id = l.old_id) group by 1 order by 2 desc');
    $d['sample'] = DB::select('select l.old_id, l.name, l.status, l.price, l.stock from legacy_products l where not exists (select 1 from products p where p.ext_id = l.old_id) order by l.old_id desc limit 12');
    $d['cols'] = collect(DB::select("select column_name from information_schema.columns where table_name = 'products' order by ordinal_position"))->pluck('column_name')->implode(', ');
    return view('legacy_products', ['d' => $d]);
  }
}
