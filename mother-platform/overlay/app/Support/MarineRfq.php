<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marine Supply: quotation-only catalogue items (first set: Bangladesh Ship Provisions, 160 SKUs, brief of 26 Nov 2026).
 *
 * Why these are NOT rows of the mother `products` table: a store product needs a price and stock, and the marine channel shows a store product only
 * when it is published in the store. A food item with no price would then be sold at 0 on dutyfreebd.com, enter the feeds and the bulk-publish checks.
 * These items have no price by design (the desk quotes in USD), so they live in their own table and reuse the marine pieces around it:
 * the Provisions marine category, the ship-fit rule, the requisition form and its search, the private file store and the audit log.
 *
 * Rules:
 *  - SKU is the key. An import never changes the state (draft / published), a packshot, a port check or a price.
 *  - A different brand or pack on an existing SKU is a conflict for a person to decide, never an overwrite.
 *  - Pack size (what one unit contains) and order unit (what the buyer counts) are separate fields; the order quantity is never taken from the pack.
 *  - Sourcing, retailer links, supplier names and port checks are for the desk only: publicRow() is the only shape that leaves the server.
 *  - Port availability is shown only from a desk check that is still valid.
 */
class MarineRfq
{
  const HEAD = ['sku', 'parent_category', 'category_slug', 'category_name_en', 'subcategory_name_en', 'name_en', 'name_bn', 'brand', 'pack_qty', 'pack_unit', 'units_per_multipack', 'order_uom', 'pack_note', 'handling', 'sale_mode', 'currency', 'price_usd', 'inventory_status', 'sourcing_region', 'import_state', 'image_file', 'image_kind', 'image_alt'];
  const HEAD_CABIN = ['sku', 'parent_category', 'section', 'category_slug', 'category_name_en', 'name_en', 'name_bn', 'material', 'size_spec', 'order_uom', 'pack_note', 'sale_mode', 'currency', 'price_usd', 'inventory_status', 'import_state', 'image_file', 'image_kind', 'image_alt', 'source_id'];
  /** The catalogues. Each has its own bundled files, release switch and page on the marine site. 'px' = required image width and height. */
  const SETS = [
    'provisions' => ['name' => 'Ship Provisions', 'title' => 'Bangladesh Ship Provisions', 'dir' => 'prov160', 'mcat' => 'provisions', 'parent' => 'provisions', 'rows' => 160, 'sections' => 12, 'px' => null, 'page' => '/provisions.html', 'release' => 'marine_rfq_released'],
    'cabin' => ['name' => 'Cabin & Galley Stores', 'title' => 'Cabin & Galley Stores', 'dir' => 'cabin120', 'mcat' => 'cabin', 'parent' => 'cabin-galley-stores', 'rows' => 120, 'sections' => 10, 'px' => 600, 'page' => '/cabin-galley.html', 'release' => 'marine_rfq_released_cabin'],
  ];
  public static function set(?string $k): string { return isset(self::SETS[(string)$k]) ? (string)$k : 'provisions'; }
  const PACK_UNITS = ['kg', 'g', 'L', 'mL', 'pc', 'pcs', 'bunch'];
  const ORDER_UOM = ['BAG', 'PACK', 'BOTTLE', 'KG', 'BUNCH', 'PC', 'CAN', 'JAR', 'CARTON', 'BOX', 'TRAY', 'TIN', 'SACK', 'DOZEN', 'L', 'EA', 'SET', 'ROLL', 'PAIR', 'TUBE', 'CANISTER', 'REAM'];
  const HANDLING = ['AMBIENT' => 'Ambient', 'FRESH' => 'Fresh', 'CHILLED' => 'Chilled', 'FROZEN' => 'Frozen', 'COLD_CHAIN_CHECK' => 'Confirm cold chain', 'LABEL_CHECK' => 'Confirm label storage'];
  const STATES = ['draft' => 'Draft', 'published' => 'Published'];
  const PORTS = ['chattogram' => 'Chattogram', 'mongla' => 'Mongla', 'payra' => 'Payra'];
  /** A section that is a browse selection across the catalogue, not a product category. */
  const SELECTIONS = ['chattogram-market-selection'];
  /** Fields that identify the variant: a change here on an existing SKU is a conflict. */
  const IDENTITY = ['brand', 'pack_qty', 'pack_unit', 'units_per_multipack', 'material', 'size_spec'];
  /** Fields an import may refresh on an existing SKU. Never: state, price, packshot, port checks, sourcing notes (those are uploaded by the desk and are not kept in the code repository). */
  const REFRESH = ['catalog', 'group_name', 'source_ref', 'mcat', 'section_slug', 'section_name', 'sub_name', 'name_en', 'name_bn', 'pack_note', 'order_uom', 'handling', 'inventory_status', 'sourcing_region', 'image_file', 'image_kind', 'image_alt'];
  const IMG_EXT = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
  const IMG_MAX = 3145728;

  // ---------- pure rules (covered by the test script) ----------

  /** CSV text -> [rows keyed by header, problems]. Accepts a UTF-8 byte order mark and CRLF line ends. */
  public static function parseCsv(string $text, ?array $head = null): array {
    $bad = [];
    if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) $text = substr($text, 3);
    if (!mb_check_encoding($text, 'UTF-8')) return [[], ['The file is not UTF-8 text.']];
    $fh = fopen('php://temp', 'r+'); fwrite($fh, $text); rewind($fh); $rows = []; $h = null; $n = 0;
    while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
      $n++; if ($r === [null] || (count($r) === 1 && trim((string)$r[0]) === '')) continue;
      if ($h === null) { $h = array_map(fn($x) => trim((string)$x), $r); if ($head !== null && $h !== $head) { $miss = array_diff($head, $h); $extra = array_diff($h, $head); $bad[] = 'Header row does not match the expected columns' . ($miss ? ' (missing: ' . implode(', ', $miss) . ')' : '') . ($extra ? ' (unknown: ' . implode(', ', $extra) . ')' : '') . '.'; } continue; }
      if (count($r) !== count($h)) { $bad[] = 'Line ' . $n . ': ' . count($r) . ' columns, expected ' . count($h) . '.'; continue; }
      $rows[] = ['_line' => $n] + array_combine($h, array_map(fn($x) => trim((string)$x), $r));
    }
    fclose($fh); if ($h === null) $bad[] = 'The file has no header row.';
    return [$rows, $bad];
  }

  private static function has(string $re, string $in): bool { return preg_match('/(?<![a-z])(?:' . $re . ')(?![a-z])/u', $in) === 1; }
  /** Tobacco and alcohol are never listed publicly (owner rule). */
  public static function restricted(string $name): bool {
    return self::has('cigarettes?|cigars?|tobacco|bidi|vape|e-?cig(arette)?s?|hookah|shisha|beers?|wines?|whisk(y|ey)|vodka|rum|gin|brandy|liquors?|liqueurs?|spirits?|alcohol(ic)?|champagne|tequila', ' ' . mb_strtolower($name) . ' ');
  }
  /** Food names that only sound like the goods the ship-fit rule removes: okra is "lady's finger", a cut of poultry is a "breast", small vegetables are "baby". Baby food and infant formula are still caught. */
  const FOOD_TERMS = 'lad(y|ies)\'?s? ?fingers?|(chicken|duck|turkey|poultry|quail) breasts?|breast (fillets?|meat|cuts?)|baby (corn|potato(es)?|carrots?|spinach|tomato(es)?|marrow|onions?|shrimps?|squid|octopus|okra|beets?|leaves|greens)';
  public static function unfit(string $name, string $path): bool { return MarineCats::unfit(trim(preg_replace('/(?<![a-z])(?:' . self::FOOD_TERMS . ')(?![a-z])/iu', ' ', $name)), $path); }
  public static function num(string $v): ?float { return (is_numeric($v) && (float)$v > 0 && (float)$v <= 100000) ? round((float)$v, 3) : null; }
  public static function fmt($v): string { $s = rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.'); return $s === '' ? '0' : $s; }

  /** One CSV row -> [database fields or null, problems]. Nothing is guessed: a row with a problem is not imported. */
  public static function norm(array $r, string $set = 'provisions'): array {
    if ($set === 'cabin') return self::normCabin($r);
    $p = []; $sku = (string)($r['sku'] ?? '');
    if (!preg_match('/^[A-Z]{2,5}-[A-Z]{2,4}-\d{3}$/', $sku)) $p[] = 'SKU is missing or not in the form MDP-XX-000.';
    if (($r['parent_category'] ?? '') !== 'provisions' || !MarineCats::ok($r['parent_category'] ?? null)) $p[] = 'Parent category must be an existing marine category (provisions).';
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string)($r['category_slug'] ?? '')) || strlen((string)$r['category_slug']) > 60) $p[] = 'Category slug is missing or malformed.';
    foreach (['category_name_en' => 80, 'subcategory_name_en' => 80, 'name_en' => 200, 'name_bn' => 200, 'image_alt' => 200] as $k => $max) { $v = (string)($r[$k] ?? ''); if ($v === '') $p[] = $k . ' is empty.'; elseif (mb_strlen($v) > $max) $p[] = $k . ' is longer than ' . $max . ' characters.'; }
    if (($r['name_bn'] ?? '') !== '' && !preg_match('/[\x{0980}-\x{09FF}]/u', $r['name_bn'])) $p[] = 'name_bn has no Bengali text.';
    if (preg_match('/[\x{0980}-\x{09FF}]/u', (string)($r['name_en'] ?? ''))) $p[] = 'name_en contains Bengali text.';
    if (mb_strlen((string)($r['brand'] ?? '')) > 120) $p[] = 'brand is longer than 120 characters.';
    $q = self::num((string)($r['pack_qty'] ?? '')); if ($q === null) $p[] = 'pack_qty must be a positive number.';
    if (!in_array($r['pack_unit'] ?? '', self::PACK_UNITS, true)) $p[] = 'pack_unit "' . ($r['pack_unit'] ?? '') . '" is not a known pack unit.';
    $m = (string)($r['units_per_multipack'] ?? ''); $mp = null; if ($m !== '') { if (!ctype_digit($m) || (int)$m < 2 || (int)$m > 1000) $p[] = 'units_per_multipack must be a whole number of 2 or more, or empty.'; else $mp = (int)$m; }
    if (!in_array($r['order_uom'] ?? '', self::ORDER_UOM, true)) $p[] = 'order_uom "' . ($r['order_uom'] ?? '') . '" is not a known order unit.';
    if (!isset(self::HANDLING[$r['handling'] ?? ''])) $p[] = 'handling "' . ($r['handling'] ?? '') . '" is not a known handling class.';
    if (mb_strlen((string)($r['pack_note'] ?? '')) > 120) $p[] = 'pack_note is longer than 120 characters.';
    if (($r['sale_mode'] ?? '') !== 'RFQ') $p[] = 'sale_mode must be RFQ.';
    if (($r['currency'] ?? '') !== 'USD') $p[] = 'currency must be USD.';
    if (($r['price_usd'] ?? '') !== '') $p[] = 'price_usd must be empty: the desk prices each quotation.';
    if (($r['inventory_status'] ?? '') !== 'supplier_confirmation') $p[] = 'inventory_status must be supplier_confirmation.';
    if (($r['import_state'] ?? '') !== 'draft') $p[] = 'import_state must be draft.';
    if (($r['image_file'] ?? '') !== 'images/' . $sku . '.webp') $p[] = 'image_file must be images/' . $sku . '.webp (one image per SKU).';
    if (!in_array($r['image_kind'] ?? '', ['illustration', 'packshot'], true)) $p[] = 'image_kind must be illustration or packshot.';
    $name = (string)($r['name_en'] ?? '');
    if (self::restricted($name . ' ' . ($r['brand'] ?? ''))) $p[] = 'Tobacco or alcohol is never listed on the marine site.';
    elseif (self::unfit($name, 'Provisions > ' . ($r['category_name_en'] ?? '') . ' > ' . ($r['subcategory_name_en'] ?? ''))) $p[] = 'The ship-fit rule keeps this item off the marine site (women\'s, beauty or baby goods).';
    if ($p) return [null, $p];
    return [[
      'sku' => $sku, 'catalog' => 'provisions', 'mcat' => 'provisions', 'group_name' => null, 'material' => null, 'size_spec' => null, 'source_ref' => null, 'section_slug' => $r['category_slug'], 'section_name' => $r['category_name_en'], 'sub_name' => $r['subcategory_name_en'],
      'name_en' => $name, 'name_bn' => $r['name_bn'], 'brand' => ($r['brand'] ?? '') !== '' ? $r['brand'] : null,
      'pack_qty' => self::fmt($q), 'pack_unit' => $r['pack_unit'], 'units_per_multipack' => $mp, 'pack_note' => ($r['pack_note'] ?? '') !== '' ? $r['pack_note'] : null,
      'order_uom' => $r['order_uom'], 'handling' => $r['handling'], 'inventory_status' => 'supplier_confirmation', 'sourcing_region' => ($r['sourcing_region'] ?? '') !== '' ? $r['sourcing_region'] : null,
      'image_file' => $sku . '.webp', 'image_kind' => $r['image_kind'], 'image_alt' => $r['image_alt'],
    ], []];
  }

  /** Cabin & Galley Stores row: no brand and no pack quantity; a reference size or specification and a material instead. The customer name carries no maker. */
  public static function normCabin(array $r): array {
    $p = []; $sku = (string)($r['sku'] ?? '');
    if (!preg_match('/^[A-Z]{2,5}-[A-Z]{2,4}-\d{3}$/', $sku)) $p[] = 'SKU is missing or not in the form MDCG-XX-000.';
    if (($r['parent_category'] ?? '') !== self::SETS['cabin']['parent'] || !MarineCats::ok('cabin')) $p[] = 'Parent category must be cabin-galley-stores.';
    if (!in_array($r['section'] ?? '', ['Galley Stores', 'Cabin Stores'], true)) $p[] = 'section must be Galley Stores or Cabin Stores.';
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string)($r['category_slug'] ?? '')) || strlen((string)$r['category_slug']) > 60) $p[] = 'Category slug is missing or malformed.';
    foreach (['category_name_en' => 80, 'name_en' => 200, 'name_bn' => 200, 'material' => 120, 'size_spec' => 120, 'image_alt' => 200] as $k => $max) { $v = (string)($r[$k] ?? ''); if ($v === '') $p[] = $k . ' is empty.'; elseif (mb_strlen($v) > $max) $p[] = $k . ' is longer than ' . $max . ' characters.'; }
    if (($r['name_bn'] ?? '') !== '' && !preg_match('/[\x{0980}-\x{09FF}]/u', $r['name_bn'])) $p[] = 'name_bn has no Bengali text.';
    foreach (['name_en', 'material', 'size_spec', 'image_alt'] as $k) if (preg_match('/[\x{0980}-\x{09FF}]/u', (string)($r[$k] ?? ''))) $p[] = $k . ' contains Bengali text.';
    if (array_key_exists('brand', $r) && $r['brand'] !== '') $p[] = 'brand must be empty for this catalogue.';
    if (!in_array($r['order_uom'] ?? '', self::ORDER_UOM, true)) $p[] = 'order_uom "' . ($r['order_uom'] ?? '') . '" is not a known order unit.';
    if (mb_strlen((string)($r['pack_note'] ?? '')) > 120) $p[] = 'pack_note is longer than 120 characters.';
    if (($r['sale_mode'] ?? '') !== 'RFQ') $p[] = 'sale_mode must be RFQ.';
    if (($r['currency'] ?? '') !== 'USD') $p[] = 'currency must be USD.';
    if (($r['price_usd'] ?? '') !== '') $p[] = 'price_usd must be empty until an approved quotation exists.';
    if (($r['inventory_status'] ?? '') !== 'supplier_confirmation') $p[] = 'inventory_status must be supplier_confirmation.';
    if (($r['import_state'] ?? '') !== 'draft') $p[] = 'import_state must be draft.';
    if (($r['image_file'] ?? '') !== 'images/' . $sku . '.webp') $p[] = 'image_file must be images/' . $sku . '.webp (one image per SKU).';
    if (!in_array($r['image_kind'] ?? '', ['illustration', 'packshot'], true)) $p[] = 'image_kind must be illustration or packshot.';
    if (($r['source_id'] ?? '') !== '' && !preg_match('/^[A-Z]\d{2,3}$/', $r['source_id'])) $p[] = 'source_id is malformed.';
    $name = (string)($r['name_en'] ?? '');
    if (self::restricted($name)) $p[] = 'Tobacco or alcohol is never listed on the marine site.';
    elseif (self::unfit($name, 'Cabin & Galley Stores > ' . ($r['section'] ?? '') . ' > ' . ($r['category_name_en'] ?? ''))) $p[] = 'The ship-fit rule keeps this item off the marine site (women\'s, beauty or baby goods).';
    if ($p) return [null, $p];
    return [[
      'sku' => $sku, 'catalog' => 'cabin', 'mcat' => 'cabin', 'group_name' => $r['section'], 'material' => $r['material'], 'size_spec' => $r['size_spec'], 'source_ref' => ($r['source_id'] ?? '') !== '' ? $r['source_id'] : null,
      'section_slug' => $r['category_slug'], 'section_name' => $r['category_name_en'], 'sub_name' => '',
      'name_en' => $name, 'name_bn' => $r['name_bn'], 'brand' => null, 'pack_qty' => null, 'pack_unit' => null, 'units_per_multipack' => null, 'pack_note' => ($r['pack_note'] ?? '') !== '' ? $r['pack_note'] : null,
      'order_uom' => $r['order_uom'], 'handling' => null, 'inventory_status' => 'supplier_confirmation', 'sourcing_region' => null,
      'image_file' => $sku . '.webp', 'image_kind' => $r['image_kind'], 'image_alt' => $r['image_alt'],
    ], []];
  }

  /**
   * Checks the whole set before anything is written.
   * $expect = ['rows' => 160, 'sections' => 12] (null = not checked); $manifest = rows of the image manifest; $imageExists(file) = true when the image is on disk.
   * Returns ['ok' => bool, 'file' => [problems of the file as a whole], 'rows' => [sku or line => [problems]], 'good' => [sku => fields], 'stats' => [...]].
   */
  public static function preflight(array $rows, array $fileProblems, ?array $manifest, callable $imageExists, ?array $expect = null, string $set = 'provisions'): array {
    $file = $fileProblems; $bad = []; $good = []; $seen = []; $sections = []; $imgs = [];
    foreach ($rows as $r) {
      $key = ($r['sku'] ?? '') !== '' ? $r['sku'] : 'line ' . ($r['_line'] ?? '?'); [$d, $p] = self::norm($r, $set);
      if (isset($seen[$r['sku'] ?? ''])) $p[] = 'Duplicate SKU: also on line ' . $seen[$r['sku']] . '.'; elseif (($r['sku'] ?? '') !== '') $seen[$r['sku']] = $r['_line'] ?? 0;
      if ($d) { if (isset($imgs[$d['image_file']])) $p[] = 'The image is already used by ' . $imgs[$d['image_file']] . '.'; $imgs[$d['image_file']] = $d['sku']; $io = $imageExists($d['image_file']); if ($io !== true) $p[] = is_string($io) ? $io : 'Image file ' . $d['image_file'] . ' is missing.'; }
      if ($p) { $bad[$key] = array_merge($bad[$key] ?? [], $p); continue; }
      $good[$d['sku']] = $d; $sections[$d['section_slug']] = $d['section_name'];
    }
    $all = []; foreach ($rows as $r) { $s = $r['category_slug'] ?? ''; if ($s !== '') $all[$s][$r['category_name_en'] ?? ''] = 1; }
    foreach ($all as $s => $names) if (count($names) > 1) $file[] = 'Category "' . $s . '" has more than one name: ' . implode(' / ', array_keys($names)) . '.';
    if ($manifest !== null) {
      $mk = []; foreach ($manifest as $m) $mk[$m['sku'] ?? ''] = $m;
      foreach ($rows as $r) { $sku = $r['sku'] ?? ''; $m = $mk[$sku] ?? null; $key = $sku !== '' ? $sku : 'line ' . ($r['_line'] ?? '?');
        if (!$m) { $bad[$key][] = 'Not in the image manifest.'; unset($good[$sku]); continue; }
        if (($m['image_file'] ?? '') !== ($r['image_file'] ?? '') || ($m['image_alt'] ?? '') !== ($r['image_alt'] ?? '') || (array_key_exists('name_en', $m) && $m['name_en'] !== ($r['name_en'] ?? '')) || (array_key_exists('image_kind', $m) && $m['image_kind'] !== ($r['image_kind'] ?? ''))) { $bad[$key][] = 'The image manifest disagrees with the import row (file, alt text or name).'; unset($good[$sku]); }
        unset($mk[$sku]); }
      foreach (array_keys($mk) as $x) $file[] = 'Image manifest lists ' . $x . ', which is not in the import file.';
    }
    $nsec = count(array_unique(array_filter(array_map(fn($r) => $r['category_slug'] ?? '', $rows))));
    if ($expect) { if (isset($expect['rows']) && count($rows) !== $expect['rows']) $file[] = 'Expected ' . $expect['rows'] . ' rows, found ' . count($rows) . '.'; if (isset($expect['sections']) && $nsec !== $expect['sections']) $file[] = 'Expected ' . $expect['sections'] . ' sections, found ' . $nsec . '.'; }
    return ['ok' => !$file && !$bad, 'file' => array_values(array_unique($file)), 'rows' => $bad, 'good' => $good, 'stats' => ['rows' => count($rows), 'unique_skus' => count($seen), 'sections' => $nsec, 'valid' => count($good), 'with_problems' => count($bad)]];
  }

  private static function same($a, $b): bool { if ($a === null || $a === '') return $b === null || $b === ''; if ($b === null || $b === '') return false; if (is_numeric($a) && is_numeric($b)) return abs((float)$a - (float)$b) < 0.0005; return (string)$a === (string)$b; }
  /**
   * What an import would do. $good = sku => fields (from preflight); $existing = sku => current row (array); $taken = sku => note, for SKUs used by something else (a store product).
   * Each result row: ['sku', 'action' => created|updated|conflict|skipped, 'detail', 'set' => fields to write].
   */
  public static function plan(array $good, array $existing, array $taken = []): array {
    $out = [];
    foreach ($good as $sku => $d) {
      if (isset($taken[$sku])) { $out[] = ['sku' => $sku, 'action' => 'conflict', 'detail' => $taken[$sku], 'set' => []]; continue; }
      $e = $existing[$sku] ?? null;
      if (!$e) { $out[] = ['sku' => $sku, 'action' => 'created', 'detail' => 'New draft: ' . $d['name_en'] . ', ' . self::pack($d) . ' per ' . $d['order_uom'], 'set' => $d]; continue; }
      $diff = []; foreach (self::IDENTITY as $k) if (!self::same($e[$k] ?? null, $d[$k] ?? null)) $diff[] = $k . ': "' . ($e[$k] ?? '') . '" in the catalogue, "' . ($d[$k] ?? '') . '" in the file';
      if ($diff) { $out[] = ['sku' => $sku, 'action' => 'conflict', 'detail' => 'Different brand or pack on the same SKU (' . implode('; ', $diff) . '). Decide by hand: keep the catalogue item, or give the new variant its own SKU.', 'set' => []]; continue; }
      $set = []; foreach (self::REFRESH as $k) if (array_key_exists($k, $d) && !self::same($e[$k] ?? null, $d[$k])) $set[$k] = $d[$k];
      $out[] = $set ? ['sku' => $sku, 'action' => 'updated', 'detail' => 'Changed: ' . implode(', ', array_keys($set)) . '. State, packshot and port checks kept.', 'set' => $set] : ['sku' => $sku, 'action' => 'skipped', 'detail' => 'Already in the catalogue, no change.', 'set' => []];
    }
    return $out;
  }
  public static function counts(array $plan, array $preflightRows = []): array { $c = ['created' => 0, 'updated' => 0, 'conflict' => 0, 'skipped' => 0, 'rejected' => count($preflightRows)]; foreach ($plan as $p) $c[$p['action']]++; return $c; }

  /** Pack size as one label, as the printed catalogue writes it: "5 kg", "4 × 80 g", "5L". */
  public static function pack($d): string { $d = (array)$d; if (!empty($d['size_spec'])) return (string)$d['size_spec']; $s = self::fmt($d['pack_qty'] ?? 0) . (($d['pack_unit'] ?? '') === 'L' ? '' : ' ') . ($d['pack_unit'] ?? ''); if (!empty($d['units_per_multipack'])) $s = (int)$d['units_per_multipack'] . ' × ' . $s; return trim($s); }
  public static function isSelection(string $slug): bool { return in_array($slug, self::SELECTIONS, true); }
  /** A desk check counts while it names a supplier and a quantity and has not expired. */
  public static function checkValid($c, string $today): bool { $c = (array)$c; return !empty($c['supplier']) && !empty($c['qty_available']) && isset(self::PORTS[$c['port'] ?? '']) && !empty($c['valid_until']) && substr((string)$c['valid_until'], 0, 10) >= $today; }
  public static function publishProblems($it): array {
    $it = (array)$it; $p = [];
    if (self::restricted(($it['name_en'] ?? '') . ' ' . ($it['brand'] ?? ''))) $p[] = 'Tobacco or alcohol is never listed on the marine site.';
    elseif (self::unfit((string)($it['name_en'] ?? ''), self::SETS[self::set($it['catalog'] ?? null)]['name'] . ' > ' . ($it['section_name'] ?? '') . ' > ' . ($it['sub_name'] ?? ''))) $p[] = 'The ship-fit rule keeps this item off the marine site.';
    if (empty($it['image_file']) && empty($it['packshot_path'])) $p[] = 'The item has no image.';
    if (empty($it['name_en']) || (empty($it['pack_unit']) && empty($it['size_spec'])) || empty($it['order_uom'])) $p[] = 'Name, pack or order unit is missing.';
    return $p;
  }
  /**
   * The only shape that leaves the server. No sourcing, retailer, supplier, procurement, checker or Bengali field.
   * $checks = valid desk checks of this item; $img = image URL.
   */
  public static function publicRow($it, array $checks, string $img, string $today): array {
    $it = (array)$it; $pack = !empty($it['packshot_path']); $av = [];
    foreach ($checks as $c) { $c = (array)$c; if (self::checkValid($c, $today)) $av[] = ['port' => self::PORTS[$c['port']], 'until' => substr((string)$c['valid_until'], 0, 10)]; }
    return ['id' => (int)$it['id'], 'sku' => (string)$it['sku'], 'name' => (string)$it['name_en'], 'brand' => (string)($it['brand'] ?? ''), 'section' => (string)$it['section_slug'], 'sub' => (string)($it['sub_name'] ?? ''),
      'material' => (string)($it['material'] ?? ''), 'pack' => self::pack($it), 'pack_note' => (string)($it['pack_note'] ?? ''), 'order_unit' => (string)$it['order_uom'], 'handling' => self::HANDLING[(string)($it['handling'] ?? '')] ?? '',
      'img' => $img, 'img_label' => $pack ? '' : 'Illustrative image', 'alt' => $pack ? (string)$it['name_en'] . ', product photo' : (string)$it['image_alt'],
      'group' => (string)($it['group_name'] ?? ''), 'sale' => 'RFQ', 'currency' => 'USD', 'availability' => $av, 'draft' => ($it['state'] ?? '') !== 'published'];
  }
  /** Size text of a requisition line. Provisions: the pack and its note. Cabin and galley: the reference size and the material (the "Confirm ..." note is advice for the form, not a size). */
  public static function lineSize($it): string { $it = (array)$it; return !empty($it['size_spec']) ? self::pack($it) . (!empty($it['material']) ? ' · ' . $it['material'] : '') : self::pack($it) . (!empty($it['pack_note']) ? ', ' . $it['pack_note'] : ''); }
  /** What the requisition form asks the buyer to add on the line, or null. Cabin textiles need the real berth or window size. */
  public static function lineAsk($it): ?string { $it = (array)$it; if (empty($it['size_spec'])) return null;
    if (($it['section_slug'] ?? '') === 'cabin-bedding-linen' || self::has('curtains?|mattress(es)?|bed ?sheets?|duvets?|bedspreads?', ' ' . mb_strtolower((string)($it['name_en'] ?? '')) . ' ')) return 'Berth or window size, e.g. 190 x 80 cm';
    return !empty($it['pack_note']) ? 'Size or details. ' . $it['pack_note'] : 'Size or details (optional)'; }
  /** Market sources sheet (keyed by source_id) -> source_id => JSON of the desk-only note. */
  public static function sourcesById(array $rows): array { $out = [];
    foreach ($rows as $r) { $id = (string)($r['source_id'] ?? ''); if (!preg_match('/^[A-Z]\d{2,3}$/', $id)) continue; $d = [];
      foreach (['source_description' => 'primary_retailer', 'market_evidence' => 'listing_signal', 'source_url' => 'source_url', 'reviewed_on' => 'reviewed_on'] as $k => $to) { $v = mb_substr(trim(strip_tags((string)($r[$k] ?? ''))), 0, 300); if ($v === '' || ($k === 'source_url' && !preg_match('#^https://[^\s<>"\']+$#', $v))) continue; $d[$to] = $v; }
      if ($d) $out[$id] = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    return $out; }
  /** The line a requisition stores for one of these items. Quantity and unit come from the buyer; the pack is only the size text. */
  public static function orderLine($it, float $qty, ?string $unit, ?string $note): array {
    $it = (array)$it;
    return ['product_id' => null, 'rfq_item_id' => (int)$it['id'], 'sku' => mb_substr((string)$it['sku'], 0, 60), 'name' => mb_substr((string)$it['name_en'] . (!empty($it['brand']) ? ' (' . $it['brand'] . ')' : ''), 0, 200),
      'size' => mb_substr(self::lineSize($it), 0, 80), 'qty' => $qty, 'unit' => $unit ?: (string)$it['order_uom'], 'list_usd' => null, 'note' => $note];
  }
  /** Sourcing sheet rows -> sku => JSON of the desk-only notes. Only known columns are kept; links must be https. */
  const SOURCING = ['international_name', 'brand', 'pack', 'sourcing_region', 'primary_retailer', 'listing_signal', 'reviewed_on', 'source_url', 'alternate_retailer', 'alternate_source_url', 'procurement_route', 'port_stock'];
  public static function sourcing(array $rows): array {
    $out = [];
    foreach ($rows as $r) { $sku = (string)($r['sku'] ?? ''); if (!preg_match('/^[A-Z]{2,5}-[A-Z]{2,4}-\d{3}$/', $sku)) continue; $d = [];
      foreach (self::SOURCING as $k) { $v = mb_substr(trim(strip_tags((string)($r[$k] ?? ''))), 0, 300); if ($v === '') continue; if (str_ends_with($k, '_url') && !preg_match('#^https://[^\s<>"\']+$#', $v)) continue; $d[$k] = $v; }
      if ($d) $out[$sku] = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    return $out;
  }
  public static function imageProblem(string $ext, ?string $mime, int $size): ?string {
    $ext = strtolower($ext); if (!isset(self::IMG_EXT[$ext])) return 'The packshot must be a WebP, JPG or PNG image.';
    if ($mime !== self::IMG_EXT[$ext]) return 'The image content does not match its type.';
    if ($size <= 0 || $size > self::IMG_MAX) return 'The packshot must be 3 MB or smaller.';
    return null;
  }

  // ---------- data ----------
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasTable('marine_rfq_items') && Schema::hasTable('marine_rfq_checks') && Schema::hasColumn('marine_rfq_items', 'catalog'); } catch (\Throwable $e) { $r = false; } } return $r; }
  public static function dir(string $f = '', string $set = 'provisions'): string { return resource_path('marine/' . self::SETS[self::set($set)]['dir'] . ($f !== '' ? '/' . $f : '')); }
  /** The public site and the requisition form list these items only after the catalogue is released (one switch, off until engineering approves). */
  public static function released(string $set = 'provisions'): bool { try { return (string)DB::table('settings')->where('key', self::SETS[self::set($set)]['release'])->value('value') === '1'; } catch (\Throwable $e) { return false; } }
  /** Catalogues that are released now. */
  public static function releasedSets(): array { return array_values(array_filter(array_keys(self::SETS), fn($k) => self::released($k))); }
  public static function token(bool $new = false): string {
    $t = $new ? '' : (string)DB::table('settings')->where('key', 'marine_rfq_preview_token')->value('value');
    if (!preg_match('/^[0-9a-f]{32}$/', $t)) { $t = bin2hex(random_bytes(16)); DB::table('settings')->updateOrInsert(['key' => 'marine_rfq_preview_token'], ['value' => $t, 'updated_at' => now()]); }
    return $t;
  }
  public static function tokenOk(?string $t): bool { if (!is_string($t) || !preg_match('/^[0-9a-f]{32}$/', $t)) return false; try { $k = (string)DB::table('settings')->where('key', 'marine_rfq_preview_token')->value('value'); return $k !== '' && hash_equals($k, $t); } catch (\Throwable $e) { return false; } }
  /** Items a signed-in account may put on a requisition: published, and only after release. */
  public static function forOrder() { return DB::table('marine_rfq_items')->where('state', 'published')->whereIn('catalog', self::releasedSets() ?: ['-']); }
  public static function orderable(): bool { return self::ready() && (bool)self::releasedSets(); }
}
