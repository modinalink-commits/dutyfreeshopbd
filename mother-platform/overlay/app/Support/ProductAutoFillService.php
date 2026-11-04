<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/**
 * Turns the AI reading of a product photo into a reviewed, pre-filled product form.
 * Rules: no price is ever filled; the SKU is only a suggestion; categories must exist in the catalogue; nothing is saved until staff presses Save.
 */
class ProductAutoFillService {
  const SETTINGS = ['on'=>'AI Product Auto-Fill', 'cat'=>'Auto Category Detection', 'sku'=>'Auto SKU Suggestion', 'desc'=>'Auto Description', 'seo'=>'Auto SEO', 'dup'=>'Duplicate Detection'];
  const LOW = 80;
  public static function on(string $k): bool { return AiImage::set('ai_fill_'.$k, '1') !== '0'; }

  /** [mainId => ['name'=>, 'subs'=>[sub => [child, ...]]]] – same hierarchy the product form offers. */
  public static function tree(): array {
    $cats = DB::table('categories')->orderBy('position')->orderBy('name')->get(); $out = [];
    foreach ($cats->whereNull('parent_id') as $m) {
      $subs = [];
      foreach ($cats->where('parent_id', $m->id) as $ch) $subs[$ch->name] = $cats->where('parent_id', $ch->id)->pluck('name')->all();
      foreach (DB::table('products')->where('category_id', $m->id)->whereNotNull('sub')->where('sub', '!=', '')->select('sub', 'sub_sub')->distinct()->get() as $r) {
        $subs[$r->sub] = $subs[$r->sub] ?? []; if ($r->sub_sub && !in_array($r->sub_sub, $subs[$r->sub], true)) $subs[$r->sub][] = $r->sub_sub;
      }
      $nd = DB::table('category_nodes')->where('category_id', $m->id)->orderBy('position')->orderBy('name')->get(); $ord = [];
      foreach ($nd->where('level', 2)->where('active', true) as $n2) { $ord[$n2->name] = $nd->where('parent_id', $n2->id)->where('active', true)->pluck('name')->all(); foreach ($subs[$n2->name] ?? [] as $x) if (!in_array($x, $ord[$n2->name], true)) $ord[$n2->name][] = $x; }
      foreach ($subs as $k => $v) if (!isset($ord[$k])) $ord[$k] = $v;
      $out[(int)$m->id] = ['name'=>$m->name, 'subs'=>$ord];
    }
    return $out;
  }
  /** Text block handed to the AI: category tree, brands and a few existing product names (to copy the naming style). */
  public static function context(): string {
    $t = []; foreach (self::tree() as $m) { $s = []; foreach ($m['subs'] as $sub => $ch) $s[] = $sub.($ch ? ' ['.implode(', ', array_slice($ch, 0, 25)).']' : ''); $t[] = '- MAIN: '.$m['name'].($s ? ' | SUB: '.implode('; ', array_slice($s, 0, 60)) : ''); }
    $b = DB::table('brands')->orderBy('name')->pluck('name')->all();
    $names = []; foreach (DB::table('products')->where('published', true)->select('category_id', 'name')->orderByDesc('id')->limit(300)->get()->groupBy('category_id') as $g) foreach ($g->take(5) as $p) $names[] = $p->name;
    return "CATEGORY TREE (copy names exactly):\n".implode("\n", $t)."\n\nKNOWN BRANDS: ".implode(', ', array_slice($b, 0, 250))."\n\nEXISTING NAMES (naming style): ".implode(' | ', array_slice($names, 0, 40));
  }
  /** Next free DF-number. Only a suggestion: the unique check at save time is the real guard. */
  public static function suggestSku(): string {
    $max = (int)(DB::selectOne("select max(substring(sku from 4)::bigint) as m from products where sku ~ '^DF-[0-9]{1,9}$'")->m ?? 0);
    $n = max($max, (int)DB::table('products')->max('id')) + 1;
    while (DB::table('products')->where('sku', 'DF-'.$n)->exists()) $n++;
    return 'DF-'.$n;
  }

  /** Run the AI on a stored image and build the detection record (also written to ai_product_scans.detected). */
  public static function detect(string $abs, ?string $focus = null): array {
    $r = ProductImageAIService::analyze($abs, self::context(), $focus);
    if (!$r['ok']) return ['ok'=>false, 'error'=>$r['error']];
    $d = $r['data']; $f = &$d['fields'];
    $tree = self::tree(); $mainId = null; $notes = [];
    if (self::on('cat')) {
      foreach ($tree as $id => $m) if ($f['main_category']['v'] !== null && strcasecmp($m['name'], $f['main_category']['v']) === 0) { $mainId = $id; $f['main_category']['v'] = $m['name']; }
      if ($mainId === null) { if ($f['main_category']['v'] !== null) $notes[] = 'Category "'.$f['main_category']['v'].'" is not in the catalogue.'; $f['main_category'] = ['v'=>null, 'c'=>0]; }
      if ($mainId !== null && $f['sub_category']['v'] !== null) {
        $hit = null; foreach ($tree[$mainId]['subs'] as $sub => $ch) if (strcasecmp($sub, $f['sub_category']['v']) === 0) $hit = $sub;
        if ($hit) { $f['sub_category']['v'] = $hit; if ($f['child_category']['v'] !== null) { $ch2 = null; foreach ($tree[$mainId]['subs'][$hit] as $x) if (strcasecmp($x, $f['child_category']['v']) === 0) $ch2 = $x; if ($ch2) $f['child_category']['v'] = $ch2; else { $f['child_category']['c'] = min($f['child_category']['c'], 55); $notes[] = 'Child category is new – please review.'; } }
        } else { $f['sub_category']['c'] = min($f['sub_category']['c'], 55); $f['child_category']['c'] = min($f['child_category']['c'], 40); $notes[] = 'Sub category is new – please review.'; }
      } elseif ($mainId !== null) { $f['child_category'] = ['v'=>null, 'c'=>0]; }
    } else foreach (['main_category', 'sub_category', 'child_category'] as $k) $f[$k] = ['v'=>null, 'c'=>0];
    $brandId = null; if ($f['brand']['v'] !== null) { $b = DB::table('brands')->whereRaw('lower(name) = ?', [mb_strtolower($f['brand']['v'])])->first(); if ($b) { $brandId = (int)$b->id; $f['brand']['v'] = $b->name; } else $notes[] = 'Brand "'.$f['brand']['v'].'" is not in Brands – add it first or leave it empty.'; }
    $d['brand_id'] = $brandId; $d['main_id'] = $mainId; $d['mode'] = $mainId ? StockGuard::mode($mainId) : null;
    $d['sku'] = self::on('sku') ? self::suggestSku() : null;
    $d['matches'] = self::on('dup') ? ProductMatchingService::find(['name'=>$f['name']['v'], 'brand'=>$f['brand']['v'], 'variant'=>$f['variant']['v'], 'size'=>$f['size']['v'], 'barcode'=>$f['barcode']['v'], 'code'=>$f['product_code']['v']]) : [];
    if (!self::on('desc')) { $f['short_description'] = ['v'=>null, 'c'=>0]; $f['description'] = ['v'=>null, 'c'=>0]; }
    if (!self::on('seo')) { foreach (['seo_title', 'seo_description'] as $k) $f[$k] = ['v'=>null, 'c'=>0]; $d['keywords'] = []; $d['tags'] = []; }
    $d['app_notes'] = $notes;
    if ($f['name']['v'] === null || $f['name']['c'] < 40) $d['identified'] = false;
    unset($f);
    return ['ok'=>true, 'data'=>$d];
  }

  /** Preview rows: [key, label, value, confidence, editable]. */
  public static function rows(array $d): array {
    $f = $d['fields']; $L = ['name'=>'Product Name', 'brand'=>'Brand', 'main_category'=>'Main Category', 'sub_category'=>'Sub Category', 'child_category'=>'Child Category', 'product_type'=>'Product Type', 'style'=>'Style', 'variant'=>'Variant', 'size'=>'Size', 'pack_size'=>'Pack Size', 'qty_per_pack'=>'Quantity per Pack', 'origin'=>'Country / Origin', 'product_code'=>'Product Code', 'barcode'=>'Barcode', 'short_description'=>'Short Description', 'description'=>'Description', 'seo_title'=>'SEO Title', 'seo_description'=>'SEO Description', 'alt_text'=>'Image Alt Text'];
    $edit = ['name', 'brand', 'main_category', 'sub_category', 'child_category', 'short_description', 'description', 'seo_title', 'seo_description'];
    $rows = []; foreach ($L as $k => $lab) { if (($f[$k]['v'] ?? null) === null && !in_array($k, ['name', 'brand', 'main_category'], true)) continue; $rows[] = ['key'=>$k, 'label'=>$lab, 'value'=>$f[$k]['v'], 'conf'=>$f[$k]['c'], 'edit'=>in_array($k, $edit, true)]; }
    if ($d['sku'] ?? null) $rows[] = ['key'=>'sku', 'label'=>'SKU (suggested)', 'value'=>$d['sku'], 'conf'=>100, 'edit'=>true];
    return $rows;
  }

  /** Form values for /admin/products/new. Prices and stock quantity are NEVER taken from the AI. */
  public static function prefill(object $scan): array {
    $d = json_decode((string)$scan->detected, true) ?: []; $e = json_decode((string)$scan->edited, true) ?: []; $f = $d['fields'] ?? [];
    $val = fn($k) => array_key_exists($k, $e) ? (trim((string)$e[$k]) ?: null) : ($f[$k]['v'] ?? null);
    $tree = self::tree(); $mainId = $d['main_id'] ?? null; $mn = $val('main_category');
    if ($mn !== null) { $mainId = null; foreach ($tree as $id => $m) if (strcasecmp($m['name'], $mn) === 0) $mainId = $id; }
    $brandId = $d['brand_id'] ?? null; $bn = $val('brand');
    if ($bn !== null && (array_key_exists('brand', $e) || !$brandId)) { $b = DB::table('brands')->whereRaw('lower(name) = ?', [mb_strtolower($bn)])->first(); $brandId = $b ? (int)$b->id : null; } elseif ($bn === null) $brandId = null;
    $out = ['name'=>$val('name') ?? '', 'brand_id'=>$brandId, 'category_id'=>$mainId, 'sub'=>$val('sub_category'), 'sub_sub'=>$val('child_category')];
    if (self::on('sku')) $out['sku'] = array_key_exists('sku', $e) ? trim((string)$e['sku']) : ($d['sku'] ?? '');
    if ($out['sku'] ?? '') { if (DB::table('products')->where('sku', $out['sku'])->exists()) $out['sku'] = self::suggestSku(); }
    if (self::on('desc')) {
      $short = $val('short_description'); $long = $val('description'); $h = '';
      if ($long) $h .= '<p>'.nl2br(e($long)).'</p>'; elseif ($short) $h .= '<p>'.e($short).'</p>';
      $spec = []; foreach (['brand'=>'Brand', 'product_type'=>'Type', 'variant'=>'Variant', 'size'=>'Size', 'pack_size'=>'Pack size', 'origin'=>'Origin'] as $k => $lab) if (($f[$k]['v'] ?? null) !== null && ($f[$k]['c'] ?? 0) >= 70) $spec[] = '<li><b>'.$lab.':</b> '.e($f[$k]['v']).'</li>';
      if ($spec) $h .= '<ul>'.implode('', $spec).'</ul>';
      $out['description'] = $h;
    }
    if (self::on('seo')) {
      $out['meta_title'] = $val('seo_title'); $out['meta_description'] = $val('seo_description');
      $kw = array_values(array_unique(array_merge($d['keywords'] ?? [], $d['tags'] ?? []))); $out['meta_keywords'] = $kw ? mb_substr(implode(', ', $kw), 0, 480) : null;
      $out['slug'] = \App\Support\Slugs::slug($out['name']) ?: null;
    }
    $mode = $mainId ? StockGuard::mode($mainId) : 'SOURCING';
    $out['stock'] = 0; $out['stock_status'] = $mode === 'STOCK_CONTROLLED' ? 'out_of_stock' : 'in_stock';
    $out['images'] = [$scan->image];
    return $out;
  }
}
