<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Single Packet twins. Every normal product P gets a twin "Single Packet <P name>" in Cigarette & Vape > Single Packet.
 *  Twin: own SKU (<sku>-SP), same image, price and cost = carton / pack_size (10), own stock counted in packets, delivery charge always applies (existing section override).
 *  Automatic creation for new products is switched on only after the owner has approved the bulk run (setting single_packet_auto = 1). */
class SinglePacket {
  const SUB = 'Single Packet';
  const PREFIX = 'Single Packet ';
  const SIZE = 10;

  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasTable('products') && Schema::hasColumn('products', 'parent_product_id'); } catch (\Throwable $e) { $r = false; } } return $r; }
  public static function auto(): bool { try { return self::ready() && (string)DB::table('settings')->where('key', 'single_packet_auto')->value('value') === '1'; } catch (\Throwable $e) { return false; } }
  private static function cols(): array { static $c = null; if ($c === null) $c = Schema::getColumnListing('products'); return $c; }

  /** The category that holds the Single Packet section (Cigarette & Vape). */
  public static function targetCat(): ?int {
    $id = DB::table('products')->whereRaw('lower(sub) = ?', ['single packet'])->whereNotNull('category_id')->groupBy('category_id')->orderByRaw('count(*) desc')->value('category_id');
    if ($id) return (int)$id;
    $c = DB::table('categories')->where('slug', 'cigarette-vape')->value('id');
    return $c ? (int)$c : null;
  }
  /** Categories whose products come in cartons of loose packets. Owner decision 14 Nov 2026: start with Cigarette and Vape only; more categories are added through the setting single_packet_cats (comma separated category ids). */
  public static function allowedCats(): array {
    try { $v = trim((string)DB::table('settings')->where('key', 'single_packet_cats')->value('value')); } catch (\Throwable $e) { $v = ''; }
    $ids = $v !== '' ? array_values(array_filter(array_map('intval', explode(',', $v)))) : [];
    if (!$ids && ($t = self::targetCat())) $ids = [$t];
    return $ids;
  }
  /** Affiliate-style categories have no loose packets. */
  public static function excludedCats(): array { return DB::table('categories')->where(fn($q) => $q->where('name', 'ilike', 'watch%')->orWhere('name', 'ilike', 'mobile%'))->pluck('id')->map(fn($i) => (int)$i)->all(); }

  /** Normal products that should have a twin. */
  public static function eligibleQuery() {
    $q = DB::table('products')->whereNull('parent_product_id')->where('name', 'not ilike', self::PREFIX.'%')
      ->where(fn($w) => $w->whereNull('sub')->orWhereRaw('lower(sub) <> ?', ['single packet']))
      ->where('price', '>', 0);
    $q->whereIn('category_id', self::allowedCats() ?: [0]);
    return $q;
  }
  /** Old hand-made Single Packet products (before the twin system). */
  public static function oldQuery() { return DB::table('products')->whereNull('parent_product_id')->whereRaw('lower(sub) = ?', ['single packet']); }

  private static function packPrice($v) { return $v === null || (float)$v <= 0 ? null : (float)ceil((float)$v / self::SIZE); }
  private static function packCost($v, int $dp = 2) { return $v === null || (float)$v <= 0 ? 0 : round((float)$v / self::SIZE, $dp); }

  public static function twinName($p): string { return mb_substr(self::PREFIX.trim((string)$p->name), 0, 200); }

  private static function fields($p, bool $new): array {
    $c = self::cols();
    $f = ['name' => self::twinName($p), 'price' => self::packPrice($p->price) ?? 0, 'cost' => self::packCost($p->cost ?? 0), 'image' => $p->image ?? null,
      'compare_price' => self::packPrice($p->compare_price ?? null), 'offer_price' => self::packPrice($p->offer_price ?? null), 'brand_id' => $p->brand_id ?? null,
      'group_key' => $p->group_key ?? null, 'description' => $p->description ?? null, 'size' => $p->size ?? null, 'updated_at' => now()];
    if (in_array('avg_cost', $c, true)) $f['avg_cost'] = self::packCost($p->avg_cost ?? 0, 4);
    if (in_array('pack_size', $c, true)) $f['pack_size'] = self::SIZE;
    if (in_array('bundle_rule', $c, true)) $f['bundle_rule'] = 'off';
    return array_intersect_key($f, array_flip($c));
  }

  /** Create or refresh the twin of one product. Returns 'created' | 'updated' | null. */
  public static function sync(int $pid, ?int $cat = null): ?string {
    if (!self::ready()) return null;
    $p = DB::table('products')->find($pid); if (!$p) return null;
    if ($p->parent_product_id || stripos((string)$p->name, self::PREFIX) === 0 || strtolower((string)$p->sub) === 'single packet') return null;
    if (!in_array((int)$p->category_id, self::allowedCats(), true) || (float)$p->price <= 0) return null;
    $cat = $cat ?: self::targetCat(); if (!$cat) return null;
    $tw = DB::table('products')->where('parent_product_id', $pid)->first();
    if ($tw) { DB::table('products')->where('id', $tw->id)->update(self::fields($p, false)); return 'updated'; }
    $f = self::fields($p, true); $c = self::cols();
    $mainName = (string)DB::table('categories')->where('id', $p->category_id)->value('name');
    $sku = $p->sku ? $p->sku.'-SP' : null; if ($sku && DB::table('products')->where('sku', $sku)->exists()) $sku = null;
    $slug = Slugs::slug($f['name']) ?: 'single-packet'; $s = $slug; $i = 2;
    while (DB::table('products')->where('category_id', $cat)->whereRaw(Slugs::subSql().' = ?', [Slugs::subSlug(self::SUB)])->where('path_slug', $s)->exists()) $s = $slug.'-'.$i++;
    $row = $f + ['sku' => $sku, 'category_id' => $cat, 'sub' => self::SUB, 'sub_sub' => $mainName ?: null, 'parent_product_id' => $pid, 'stock' => 0,
      'stock_status' => 'out_of_stock', 'published' => (bool)$p->published, 'slug' => $slug, 'path_slug' => $s, 'meta_title' => $f['name'], 'created_at' => now()];
    $row = array_intersect_key($row, array_flip($c));
    $nid = DB::table('products')->insertGetId($row);
    if ($p->image && Schema::hasTable('product_images')) DB::table('product_images')->insert(['product_id' => $nid, 'path' => $p->image, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
    return 'created';
  }

  /** Read-only picture of what the bulk run would do. */
  public static function preview(): array {
    $cat = self::targetCat();
    $elig = self::eligibleQuery()->count();
    $have = self::eligibleQuery()->whereExists(fn($q) => $q->select(DB::raw(1))->from('products as t')->whereColumn('t.parent_product_id', 'products.id'))->count();
    $old = self::oldQuery()->count(); $oldPub = self::oldQuery()->where('published', true)->count();
    $sample = self::eligibleQuery()->orderBy('id')->limit(12)->get(['id', 'name', 'sku', 'price', 'cost', 'published'])->map(fn($p) => ['main' => $p->name, 'twin' => self::twinName($p), 'sku' => $p->sku ? $p->sku.'-SP' : '—', 'price' => self::packPrice($p->price), 'cost' => self::packCost($p->cost)])->all();
    $ex = array_values(array_diff(DB::table('categories')->whereIn('id', self::allowedCats())->pluck('id')->map(fn($i) => (int)$i)->all(), [])); $allowedNames = $ex ? DB::table('categories')->whereIn('id', $ex)->pluck('name')->all() : []; $ex = [];
    return ['allowed_names' => $allowedNames, 'target' => $cat, 'target_name' => $cat ? (string)DB::table('categories')->where('id', $cat)->value('name') : null, 'eligible' => $elig, 'have' => $have, 'to_create' => max(0, $elig - $have), 'old' => $old, 'old_published' => $oldPub,
      'excluded_names' => $ex ? DB::table('categories')->whereIn('id', $ex)->pluck('name')->all() : [], 'excluded_products' => $ex ? DB::table('products')->whereIn('category_id', $ex)->whereNull('parent_product_id')->count() : 0,
      'sample' => $sample, 'auto' => self::auto(), 'ready' => self::ready()];
  }

  /** Owner-approved bulk run: hide the old hand-made Single Packet products (kept for order history), create all twins, switch automatic creation on. */
  public static function run(): array {
    $out = ['retired' => 0, 'created' => 0, 'updated' => 0];
    $cat = self::targetCat(); if (!$cat || !self::ready()) return $out + ['error' => 'not ready'];
    $lock = Schema::hasColumn('products', 'manual_unpublish_lock');
    $lg = Schema::hasColumn('products', 'legacy_source');
    foreach (self::oldQuery()->get(['id', 'path_slug']) as $o) {
      $u = ['published' => false, 'path_slug' => ($o->path_slug ?: 'old').'-old-'.$o->id, 'updated_at' => now()];
      if ($lock) $u['manual_unpublish_lock'] = true; if ($lg) $u['legacy_source'] = 'old_single_packet';
      DB::table('products')->where('id', $o->id)->update($u); $out['retired']++;
    }
    foreach (self::eligibleQuery()->orderBy('id')->pluck('id') as $id) {
      try { $r = DB::transaction(fn() => self::sync((int)$id, $cat)); if ($r === 'created') $out['created']++; elseif ($r === 'updated') $out['updated']++; } catch (\Throwable $e) { $out['errors'][] = $id.': '.mb_substr($e->getMessage(), 0, 80); }
    }
    DB::table('settings')->updateOrInsert(['key' => 'single_packet_auto'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]);
    try { Procure::audit('single_packet.bulk', 'Single Packet twins', null, $out); } catch (\Throwable $e) {}
    return $out;
  }
}
