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
  /** Old category tree and brands (small), stored as one JSON setting. */
  public function taxonomy(Request $r) {
    if (!$this->ok($r)) abort(404);
    $j = json_decode((string)$r->input('data'), true); if (!is_array($j)) return response()->json(['ok' => false]);
    DB::table('settings')->updateOrInsert(['key' => 'legacy_taxonomy'], ['value' => json_encode($j, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]);
    return response()->json(['ok' => true, 'cats' => count($j['cats'] ?? []), 'subs' => count($j['subs'] ?? []), 'subsubs' => count($j['subsubs'] ?? []), 'brands' => count($j['brands'] ?? [])]);
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
    $nm = "lower(trim(p.name)) = lower(trim(l.name))";
    $d['cmp'] += [
      'ext_id = old_id + 999' => $q("select count(*) n from legacy_products l join products p on p.ext_id = l.old_id + 999"),
      'ext_id = old_id + 999 AND same name' => $q("select count(*) n from legacy_products l join products p on p.ext_id = l.old_id + 999 and $nm"),
      'ext_id = old_id AND same name (old rule)' => $q("select count(*) n from legacy_products l join products p on p.ext_id = l.old_id and $nm"),
      'distinct old products with a same-name live product' => $q("select count(*) n from legacy_products l where exists (select 1 from products p where $nm)"),
      'old products with NO live product by name and NO +999 id' => $q("select count(*) n from legacy_products l where not exists (select 1 from products p where $nm) and not exists (select 1 from products p where p.ext_id = l.old_id + 999)"),
      'same-name live products that are duplicated (2+ live rows)' => $q("select count(*) n from (select lower(trim(name)) k from products group by 1 having count(*) > 1) z"),
      'live products matching no old product (by name or +999)' => $q("select count(*) n from products p where not exists (select 1 from legacy_products l where $nm) and not exists (select 1 from legacy_products l where p.ext_id = l.old_id + 999)"),
    ];
    $d['liveonly'] = DB::select('select p.ext_id, p.name, p.price, p.published from products p where p.ext_id is not null and not exists (select 1 from legacy_products l where l.old_id = p.ext_id) order by p.ext_id desc limit 10');
    $d['msample'] = DB::select("select l.old_id, left(l.name,28) n, l.price o_price, l.sale_price o_sale, l.purchase_price o_cost, (l.raw->>'discount') o_d1, (l.raw->>'double_products_discount') o_d2, (l.raw->>'triple_products_discount') o_d3, p.price p_price, p.offer_price p_offer, p.compare_price p_cmp, p.cost p_cost, p.buy1_price b1, p.buy2_price b2, p.buy3_price b3, p.buy4_price b4, p.buy5_price b5, p.published pub, l.status o_st from legacy_products l join products p on p.ext_id = l.old_id + 999 where l.raw->>'double_products_discount' <> '0' order by l.old_id desc limit 6");
    $tx = json_decode((string)DB::table('settings')->where('key', 'legacy_taxonomy')->value('value'), true) ?: [];
    $norm = fn($v) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower(preg_replace('/[^\p{L}\p{N} ]+/u', ' ', (string)$v)));
    $newCats = DB::table('categories')->get(['id', 'name'])->keyBy(fn($c) => $norm($c->name));
    $newNodes = DB::table('category_nodes')->get(['id', 'name', 'level'])->groupBy(fn($c) => $norm($c->name));
    $newBrands = DB::table('brands')->get(['id', 'name'])->keyBy(fn($c) => $norm($c->name));
    $d['tx'] = ['loaded' => (bool)$tx, 'cats' => array_map(fn($c) => $c['name'].($newCats->has($norm($c['name'])) ? '  ✓' : '  ✗'), $tx['cats'] ?? []),
      'subs_total' => count($tx['subs'] ?? []), 'subs_found' => count(array_filter($tx['subs'] ?? [], fn($c) => $newNodes->has($norm($c['name'])))),
      'subsubs_total' => count($tx['subsubs'] ?? []), 'subsubs_found' => count(array_filter($tx['subsubs'] ?? [], fn($c) => $newNodes->has($norm($c['name'])))),
      'brands' => array_map(fn($c) => $c['name'].($newBrands->has($norm($c['name'])) ? '  ✓' : '  ✗'), $tx['brands'] ?? []),
      'new_cats' => DB::table('categories')->pluck('name')->all()];
    $d['status'] = DB::select('select coalesce(status::text, \'null\') s, count(*) n from legacy_products group by 1 order by 2 desc');
    $d['unm_by_status'] = DB::select('select coalesce(l.status::text, \'null\') s, count(*) n from legacy_products l where not exists (select 1 from products p where p.ext_id = l.old_id) group by 1 order by 2 desc');
    $d['sample'] = DB::select('select l.old_id, l.name, l.status, l.price, l.stock from legacy_products l where not exists (select 1 from products p where p.ext_id = l.old_id) order by l.old_id desc limit 12');
    $d['cols'] = collect(DB::select("select column_name from information_schema.columns where table_name = 'products' order by ordinal_position"))->pluck('column_name')->implode(', ');
    $d['result'] = DB::table('settings')->where('key', 'legacy_products_result')->value('value'); return view('legacy_products', ['d' => $d]);
  }

  private function norm($v): string { return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(preg_replace('/[^\p{L}\p{N} ]+/u', ' ', (string)$v))); }
  private function clean($v): string { return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}&\-\' ]+/u', ' ', (string)$v))); }
  /** Mirror every staged old product into the live catalog (backup first). */
  public function apply(Request $r) {
    abort_unless($r->input('confirm') === 'IMPORT', 422);
    @set_time_limit(600);
    if (!\Illuminate\Support\Facades\Schema::hasTable('products_backup_legacy')) DB::statement('create table products_backup_legacy as select * from products');
    $tx = json_decode((string)DB::table('settings')->where('key', 'legacy_taxonomy')->value('value'), true) ?: [];
    $oc = collect($tx['cats'] ?? [])->keyBy('id'); $os = collect($tx['subs'] ?? [])->keyBy('id'); $oss = collect($tx['subsubs'] ?? [])->keyBy('id'); $ob = collect($tx['brands'] ?? [])->keyBy('id');
    $cats = DB::table('categories')->get(['id', 'name'])->keyBy(fn($c) => $this->norm($c->name))->map(fn($c) => $c->id)->all();
    $brands = DB::table('brands')->get(['id', 'name'])->keyBy(fn($c) => $this->norm($c->name))->map(fn($c) => $c->id)->all();
    $cigId = $cats[$this->norm('Cigarette and Vape')] ?? null;
    $catFor = function ($oldId) use (&$cats, $oc, $cigId) {
      $o = $oc[$oldId] ?? null; if (!$o) return null; $n = $this->clean($o['name']); $k = $this->norm($n);
      if (isset($cats[$k])) return $cats[$k];
      if ($cigId && preg_match('/cigarette|vape/i', $n)) return $cats[$k] = $cigId;
      $id = DB::table('categories')->insertGetId(['name' => $n, 'created_at' => now(), 'updated_at' => now()]); return $cats[$k] = $id;
    };
    $brandFor = function ($oldId) use (&$brands, $ob) {
      $o = $ob[$oldId] ?? null; if (!$o) return null; $n = $this->clean($o['name']); $k = $this->norm($n);
      if (isset($brands[$k])) return $brands[$k];
      $id = DB::table('brands')->insertGetId(['name' => $n, 'slug' => \App\Support\Slugs::slug($n), 'active' => true, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]); return $brands[$k] = $id;
    };
    $node = function ($catId, $name, $level, $parent) {
      $name = $this->clean($name); if ($name === '' || !$catId) return;
      $ex = DB::table('category_nodes')->where('category_id', $catId)->where('level', $level)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists();
      if (!$ex) DB::table('category_nodes')->insert(['category_id' => $catId, 'parent_id' => $parent, 'level' => $level, 'name' => $name, 'slug' => \App\Support\Slugs::slug($name) ?: 'x', 'position' => 0, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    };
    $st = ['updated' => 0, 'inserted' => 0, 'by_id' => 0, 'by_name' => 0, 'published' => 0, 'unpublished' => 0];
    $claimed = [];
    $stockStatus = fn($n) => $n > 0 ? 'in_stock' : 'out_of_stock';
    DB::table('legacy_products')->orderBy('old_id')->chunkById(150, function ($rows) use (&$st, &$claimed, $catFor, $brandFor, $node, $oc, $os, $oss, $stockStatus) {
      foreach ($rows as $l) {
        $x = json_decode((string)$l->raw, true) ?: [];
        $list = (float)$l->price; $sale = (float)$l->sale_price; $d1 = (float)($x['discount'] ?? 0);
        if ($sale <= 0 && $list > 0) $sale = $list - $d1; if ($sale <= 0) $sale = $list;
        $price = $sale > 0 ? $sale : $list; $cmp = ($list > $price) ? $list : null;
        $pub = (int)$l->status === 1; $stock = max(0, (int)$l->stock);
        $row = ['price' => $price, 'compare_price' => $cmp, 'offer_price' => null, 'cost' => (float)$l->purchase_price, 'stock' => $stock, 'stock_status' => $stockStatus($stock), 'published' => $pub,
          'legacy_product_id' => $l->old_id, 'legacy_source' => 'dutyfreebd.com', 'migrated_at' => now(), 'updated_at' => now()];
        $dn = [2 => 'double_products_discount', 3 => 'triple_products_discount', 4 => 'four_products_discount', 5 => 'five_products_discount'];
        foreach ($dn as $n => $f) { $dv = (float)($x[$f] ?? 0); $row["buy{$n}_price"] = $dv > 0 && $list > 0 ? max(0, $n * $list - $dv) : null; }
        $p = DB::table('products')->where('ext_id', $l->old_id + 999)->first(); $how = 'by_id';
        if (!$p) {
          $c = DB::table('products')->whereRaw('lower(trim(name)) = ?', [mb_strtolower(trim((string)$l->name))])->whereNotIn('id', $claimed ?: [0])->get();
          if ($c->count() === 1) { $p = $c->first(); $how = 'by_name'; }
        }
        if ($p) {
          $claimed[] = $p->id;
          foreach (['barcode' => $l->barcode, 'video_url' => $x['video_url'] ?? null] as $k => $v) if (empty($p->$k) && !empty($v)) $row[$k] = mb_substr((string)$v, 0, 500);
          if (empty($p->description) && !empty($x['details'])) $row['description'] = $x['details'];
          foreach (['meta_title' => 'meta_title', 'meta_description' => 'meta_description', 'meta_keywords' => 'meta_key'] as $k => $f) if (empty($p->$k) && !empty($x[$f])) $row[$k] = mb_substr((string)$x[$f], 0, 500);
          if (empty($p->ext_id)) $row['ext_id'] = $l->old_id + 999;
          DB::table('products')->where('id', $p->id)->update($row); $st['updated']++; $st[$how]++;
        } else {
          $catId = $catFor($l->category_id) ?? DB::table('categories')->orderBy('id')->value('id');
          $sub = $os[$l->sub_category_id]['name'] ?? null; $subsub = $oss[$l->sub_sub_category_id]['name'] ?? null;
          $sub = $sub ? $this->clean($sub) : null; $subsub = $subsub ? $this->clean($subsub) : null;
          if ($sub) { $node($catId, $sub, 2, null); }
          $base = \App\Support\Slugs::slug($l->slug ?: $l->name) ?: 'product'; $slug = $base; $i = 2; $subSlug = \App\Support\Slugs::subSlug($sub);
          while (DB::table('products')->where('category_id', $catId)->whereRaw(\App\Support\Slugs::subSql() . ' = ?', [$subSlug])->where('path_slug', $slug)->exists()) $slug = $base . '-' . $i++;
          $sku = $l->product_code && !DB::table('products')->where('sku', $l->product_code)->exists() ? $l->product_code : null;
          $row += ['name' => mb_substr((string)$l->name, 0, 200), 'sku' => $sku, 'category_id' => $catId, 'brand_id' => $brandFor($x['brand_id'] ?? null), 'sub' => $sub, 'sub_sub' => $subsub, 'barcode' => $l->barcode,
            'video_url' => !empty($x['video_url']) ? mb_substr((string)$x['video_url'], 0, 500) : null, 'description' => $x['details'] ?? null, 'meta_title' => $x['meta_title'] ?? null, 'meta_description' => $x['meta_description'] ?? null, 'meta_keywords' => $x['meta_key'] ?? null,
            'slug' => $base, 'path_slug' => $slug, 'ext_id' => $l->old_id + 999, 'created_at' => $l->old_created_at ?: now()];
          $nid = DB::table('products')->insertGetId($row); $claimed[] = $nid; $st['inserted']++;
        }
        $pub ? $st['published']++ : $st['unpublished']++;
        DB::table('legacy_products')->where('old_id', $l->old_id)->update(['action' => $p ? 'updated' : 'inserted', 'new_product_id' => $p->id ?? $nid ?? null]);
      }
    }, 'old_id');
    DB::table('settings')->updateOrInsert(['key' => 'legacy_products_result'], ['value' => json_encode($st), 'updated_at' => now(), 'created_at' => now()]);
    return redirect('/admin/legacy-products')->with('ok', 'Imported: ' . json_encode($st));
  }
}
