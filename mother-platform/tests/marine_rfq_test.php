<?php
/**
 * Marine ship provisions: tests of the pure rules in MarineRfq (not deployed: this file is outside overlay/). No Laravel and no database.
 * They read the real data set in overlay/resources/marine/prov160. The two sourcing checks run only when a sourcing CSV is given (it is desk-only and not in this repository).
 * Run:  php marine_rfq_test.php [path/to/sourcing.csv]
 */
$ov = __DIR__ . '/../overlay'; $src = $ov . '/resources/marine/prov160'; $sourcing = $argv[1] ?? null;
require $ov . '/app/Support/MarineCats.php'; require $ov . '/app/Support/MarineRfq.php';
use App\Support\MarineRfq as R;
$n = 0; $f = 0; function ok($c, $m) { global $n, $f; $n++; if (!$c) { $f++; echo "FAIL: $m\n"; } }
$csv = file_get_contents($src . '/import.csv'); $man = file_get_contents($src . '/manifest.csv');
[$rows, $fp] = R::parseCsv($csv, R::HEAD); [$mrows, $mp] = R::parseCsv($man);
ok(!$fp && count($rows) === 160, 'csv parses to 160 rows, no file problem'); ok(!$mp && count($mrows) === 160, 'manifest parses');
[$rb, $fb] = R::parseCsv("\xEF\xBB\xBF" . $csv, R::HEAD); ok(!$fb && count($rb) === 160 && $rb[0]['sku'] === 'MDP-DP-001', 'BOM is stripped, first header is sku');
[$rc] = R::parseCsv(str_replace("\n", "\r\n", $csv), R::HEAD); ok(count($rc) === 160 && $rc[159]['image_alt'] === $rows[159]['image_alt'], 'CRLF line ends');
[$_r, $hb] = R::parseCsv("sku,name\nA,B\n", R::HEAD); ok(count($hb) === 1 && str_contains($hb[0], 'missing'), 'wrong header reported');
[$_r, $cb] = R::parseCsv("a,b\n1\n2,3\n"); ok(count($cb) === 1 && count($_r) === 1, 'short row reported, good row kept');
[$_r, $ub] = R::parseCsv("a\n\xff\xfe\n"); ok($ub === ['The file is not UTF-8 text.'], 'non UTF-8 rejected');
$img = fn($f) => is_file($src . '/img/' . $f);
$pf = R::preflight($rows, $fp, $mrows, $img, ['rows' => 160, 'sections' => 12]);
ok($pf['ok'], 'real set passes preflight: ' . json_encode([$pf['file'], array_slice($pf['rows'], 0, 3)], JSON_UNESCAPED_UNICODE));
ok($pf['stats'] === ['rows' => 160, 'unique_skus' => 160, 'sections' => 12, 'valid' => 160, 'with_problems' => 0], 'stats 160/160/12');
$g = $pf['good']; ok($g['MDP-DP-002']['pack_qty'] === '5' && $g['MDP-DP-002']['pack_unit'] === 'kg' && $g['MDP-DP-002']['order_uom'] === 'BAG', 'pack and order unit kept apart');
ok($g['MDP-DE-007']['units_per_multipack'] === 4 && R::pack($g['MDP-DE-007']) === '4 × 80 g', 'multipack label');
ok(R::pack($g['MDP-DP-001']) === '1 kg' && $g['MDP-DP-001']['image_file'] === 'MDP-DP-001.webp' && $g['MDP-DP-001']['name_bn'] === 'বাসমতি চাল', 'pack label, image, Bengali name stored');
ok($g['MDP-FV-017']['brand'] === null && $g['MDP-FV-017']['sourcing_region'] === 'Chattogram', 'empty brand is null');
ok(R::isSelection($g['MDP-CT-001']['section_slug']) && !R::isSelection($g['MDP-DP-001']['section_slug']), 'Chattogram Market Selection is a selection');
// broken sets
$b = $rows; $b[1]['sku'] = 'MDP-DP-001'; $x = R::preflight($b, [], null, $img, ['rows' => 160, 'sections' => 12]); ok(!$x['ok'] && str_contains(implode(' ', $x['rows']['MDP-DP-001']), 'Duplicate SKU') && $x['stats']['valid'] === 159, 'duplicate SKU');
$b = $rows; unset($b[5]); $x = R::preflight(array_values($b), [], $mrows, $img, ['rows' => 160, 'sections' => 12]); ok(!$x['ok'] && str_contains(implode(' ', $x['file']), 'Expected 160 rows, found 159') && str_contains(implode(' ', $x['file']), 'Image manifest lists'), 'missing row and manifest leftover');
$b = $rows; $b[0]['pack_qty'] = '0'; $b[1]['pack_qty'] = 'two'; $b[2]['order_uom'] = 'kgs'; $b[3]['handling'] = 'WARM'; $b[4]['price_usd'] = '2.5'; $b[5]['import_state'] = 'published'; $b[6]['name_bn'] = 'Onion'; $b[7]['image_file'] = 'images/other.webp'; $b[8]['units_per_multipack'] = '1'; $b[9]['sale_mode'] = 'BUY';
$x = R::preflight($b, [], null, $img); ok(count($x['rows']) === 10 && $x['stats']['valid'] === 150, 'ten malformed rows each reported: ' . count($x['rows']));
ok(str_contains($x['rows']['MDP-DP-005'][0], 'price_usd must be empty') && str_contains($x['rows']['MDP-DP-006'][0], 'import_state must be draft'), 'price and state guarded');
$x = R::preflight($rows, [], null, fn($f) => $f !== 'MDP-FV-003.webp'); ok(!$x['ok'] && $x['rows']['MDP-FV-003'] === ['Image file MDP-FV-003.webp is missing.'], 'missing image reported');
$b = $rows; $b[0]['category_name_en'] = 'Dry Pantry'; $x = R::preflight($b, [], null, $img); ok(str_contains(implode(' ', $x['file']), 'more than one name'), 'one name per section');
$b = $mrows; $b[3]['image_alt'] = 'x'; $x = R::preflight($rows, [], $b, $img); ok(count($x['rows']) === 1 && $x['stats']['valid'] === 159, 'manifest disagreement');
$b = $rows; $b[0]['name_en'] = 'Red Wine Vinegar'; $b[1]['name_en'] = 'Baby Food Cereal'; $b[2]['name_en'] = 'Ginger Paste'; $b[3]['name_en'] = 'Ladies Hand Cream'; $x = R::preflight($b, [], null, $img);
ok(isset($x['rows']['MDP-DP-001']) && isset($x['rows']['MDP-DP-002']) && !isset($x['rows']['MDP-DP-003']) && isset($x['rows']['MDP-DP-004']), 'restricted and ship-fit words are reported, ginger is not gin');
ok(!R::restricted('Ginger') && !R::restricted('Chicken Drumstick') && R::restricted('Dark Rum') && R::restricted('Beer 330 mL'), 'restricted words');
ok(!R::unfit("Okra (Lady's Finger)", 'Provisions') && !R::unfit('Boneless Chicken Breast', 'Provisions') && !R::unfit('Canned Whole Baby Corn', 'Provisions') && R::unfit('Baby Formula', 'Provisions') && R::unfit('Infant Cereal', 'Provisions') && R::unfit('Lipstick', 'Provisions'), 'food names are not women or baby goods; baby food still is');
// plan
$p1 = R::plan($g, []); ok(R::counts($p1) === ['created' => 160, 'updated' => 0, 'conflict' => 0, 'skipped' => 0, 'rejected' => 0], 'first run creates 160');
$db = []; $id = 0; foreach ($p1 as $r) $db[$r['sku']] = $r['set'] + ['id' => ++$id, 'state' => 'draft', 'packshot_path' => null, 'pack_qty' => $r['set']['pack_qty'] . '.000'];
$p2 = R::plan($g, $db); ok(R::counts($p2) === ['created' => 0, 'updated' => 0, 'conflict' => 0, 'skipped' => 160, 'rejected' => 0], 'second run: nothing to do (decimal from the database equals the file)');
$db['MDP-DP-001']['state'] = 'published'; $db['MDP-DP-001']['packshot_path'] = 'x.webp'; $db['MDP-DP-001']['name_en'] = 'Basmati'; $db['MDP-DP-002']['brand'] = 'Other'; $db['MDP-DP-003']['pack_qty'] = '2.000'; $db['MDP-DE-007']['units_per_multipack'] = 6; unset($db['MDP-DP-004']);
$p3 = R::plan($g, $db, ['MDP-DP-005' => 'SKU is used by store product #9']); $by = []; foreach ($p3 as $r) $by[$r['sku']] = $r;
ok(R::counts($p3) === ['created' => 1, 'updated' => 1, 'conflict' => 4, 'skipped' => 154, 'rejected' => 0], 'mixed run: ' . json_encode(R::counts($p3)));
ok($by['MDP-DP-001']['action'] === 'updated' && $by['MDP-DP-001']['set'] === ['name_en' => 'Basmati Rice'], 'update writes only the changed field, never state or packshot');
ok($by['MDP-DP-002']['action'] === 'conflict' && $by['MDP-DP-002']['set'] === [] && str_contains($by['MDP-DP-002']['detail'], 'brand'), 'brand change is a conflict');
ok($by['MDP-DP-003']['action'] === 'conflict' && $by['MDP-DE-007']['action'] === 'conflict' && $by['MDP-DP-005']['action'] === 'conflict', 'pack, multipack and taken SKU are conflicts');
foreach ($p3 as $r) foreach (['state', 'packshot_path', 'price_usd', 'id'] as $k) if ($r['action'] !== 'created' && array_key_exists($k, $r['set'])) ok(false, 'import must not write ' . $k);
ok(!array_intersect(R::REFRESH, ['state', 'price_usd', 'packshot_path', 'packshot_ref']) && !array_intersect(R::REFRESH, R::IDENTITY), 'refresh list excludes protected and identity fields');
// public shape
$it = $db['MDP-CT-001'] + ['sourcing' => '{"primary_retailer":"Fish Park"}', 'published_by' => 'x']; $today = '2026-11-26';
$chk = [['port' => 'chattogram', 'supplier' => 'Secret Fish Ltd', 'qty_available' => '40 kg', 'valid_until' => '2026-11-28', 'checked_by' => 'Staff'], ['port' => 'mongla', 'supplier' => 'S', 'qty_available' => '5', 'valid_until' => '2026-11-25'], ['port' => 'payra', 'supplier' => '', 'qty_available' => '5', 'valid_until' => '2026-12-25']];
$pr = R::publicRow($it, $chk, 'https://x/i.webp', $today); $js = json_encode($pr, JSON_UNESCAPED_UNICODE);
ok($pr['availability'] === [['port' => 'Chattogram', 'until' => '2026-11-28']], 'only a valid check gives availability');
ok(!preg_match('/Secret|Fish Park|sourcing|supplier|retailer|procure|checked|Staff|রূপ|name_bn|published_by/u', $js), 'no desk-only data in the public row: ' . $js);
ok(array_keys($pr) === ['id', 'sku', 'name', 'brand', 'section', 'sub', 'material', 'pack', 'pack_note', 'order_unit', 'handling', 'img', 'img_label', 'alt', 'group', 'sale', 'currency', 'availability', 'draft'], 'public field whitelist');
ok($pr['draft'] === true && $pr['img_label'] === 'Illustrative image' && $pr['pack'] === '1 kg' && $pr['order_unit'] === 'KG' && $pr['pack_note'] === '4–5 fish per kg' && !array_key_exists('price', $pr), 'draft flag, image label, pack, no price');
$pk = R::publicRow(['packshot_path' => 'p.webp', 'state' => 'published'] + $it, [], 'u', $today); ok($pk['img_label'] === '' && $pk['draft'] === false && str_contains($pk['alt'], 'product photo'), 'approved packshot drops the illustrative label');
ok(R::publicRow($it, [], 'u', $today)['availability'] === [], 'no check, no availability');
// order line
$mp7 = ['id' => 77] + $g['MDP-DE-007']; $ln = R::orderLine($mp7, 3.0, null, null); ok($ln['qty'] === 3.0 && $ln['unit'] === 'PACK' && $ln['size'] === '4 × 80 g' && $ln['list_usd'] === null && $ln['product_id'] === null && $ln['rfq_item_id'] === 77 && $ln['sku'] === 'MDP-DE-007', 'order line: buyer quantity, pack as size, no price');
$ln = R::orderLine($db['MDP-CT-001'], 12.5, 'kg', 'cleaned'); ok($ln['unit'] === 'kg' && $ln['size'] === '1 kg, 4–5 fish per kg' && $ln['note'] === 'cleaned', 'buyer unit wins, pack note in size');
ok(str_contains(R::orderLine($db['MDP-DP-001'], 1, null, null)['name'], '(Akij Essential)'), 'brand in the line name');
// publish and image rules
ok(R::publishProblems($db['MDP-DP-001']) === [] && R::publishProblems(['name_en' => 'Table Wine'] + $db['MDP-DP-001']) !== [] && R::publishProblems(['image_file' => null, 'packshot_path' => null] + $db['MDP-DP-001']) !== [], 'publish checks');
ok(R::imageProblem('webp', 'image/webp', 5000) === null && R::imageProblem('webp', 'text/html', 5000) !== null && R::imageProblem('svg', 'image/svg+xml', 5000) !== null && R::imageProblem('jpg', 'image/jpeg', 4000000) !== null, 'packshot file rules');
ok(R::pack($g['MDP-WB-003']) === '2L' && R::pack($g['MDP-WB-001']) === '500 mL', 'litres as printed'); ok(R::fmt('0.500') === '0.5' && R::fmt('12') === '12' && R::num('-1') === null && R::num('abc') === null, 'numbers');
if ($sourcing && is_file($sourcing)) {
[$sr] = R::parseCsv(file_get_contents($sourcing)); $sm = R::sourcing($sr); $s1 = json_decode($sm['MDP-DP-002'], true);
ok(count($sm) === 160 && $s1['primary_retailer'] === 'Shwapno' && str_starts_with($s1['source_url'], 'https://') && !isset($s1['alternate_retailer']), 'sourcing sheet read: 160 rows, empty fields dropped');
}
ok(R::sourcing([['sku' => 'MDP-DP-001', 'source_url' => 'javascript:alert(1)', 'primary_retailer' => '<b>X</b>', 'secret' => 'y'], ['sku' => 'bad', 'primary_retailer' => 'Z']]) === ['MDP-DP-001' => '{"primary_retailer":"X"}'], 'sourcing: only https links, known columns, valid SKUs');
// ---- second catalogue: Cabin & Galley Stores ----
$cs = $ov . '/resources/marine/cabin120'; [$cr, $cfp] = R::parseCsv(file_get_contents($cs . '/import.csv'), R::HEAD_CABIN); [$cm] = R::parseCsv(file_get_contents($cs . '/manifest.csv'));
ok(!$cfp && count($cr) === 120 && $cr[0]['sku'] === 'MDCG-CK-001', 'cabin csv with a byte order mark parses to 120 rows');
$cimg = function ($f) use ($cs) { $p = $cs . '/img/' . $f; if (!is_file($p)) return false; $i = @getimagesize($p); return ($i && $i[0] === 600 && $i[1] === 600 && $i['mime'] === 'image/webp') ? true : 'Image ' . $f . ' is not a 600 x 600 WebP file.'; };
$cp = R::preflight($cr, $cfp, $cm, $cimg, ['rows' => 120, 'sections' => 10], 'cabin');
ok($cp['ok'] && $cp['stats'] === ['rows' => 120, 'unique_skus' => 120, 'sections' => 10, 'valid' => 120, 'with_problems' => 0], 'cabin set passes preflight: ' . json_encode([$cp['file'], array_slice($cp['rows'], 0, 4)], JSON_UNESCAPED_UNICODE));
$cg = $cp['good']; $c1 = $cg['MDCG-CK-001'] ?? [];
ok(($c1['catalog'] ?? '') === 'cabin' && $c1['mcat'] === 'cabin' && $c1['group_name'] === 'Galley Stores' && $c1['material'] === 'Stainless steel' && $c1['size_spec'] === '30 x 18 cm' && $c1['order_uom'] === 'EA' && $c1['brand'] === null && $c1['pack_qty'] === null && $c1['handling'] === null && $c1['source_ref'] === 'C01', 'cabin row mapped: material, reference size, no brand, no pack quantity');
ok(R::pack($c1) === '30 x 18 cm' && R::lineSize($c1) === '30 x 18 cm · Stainless steel', 'cabin size label');
ok(count(array_filter($cg, fn($d) => $d['brand'] !== null)) === 0 && count(array_unique(array_column($cg, 'group_name'))) === 2, 'no brand anywhere; two sections');
$x = R::preflight($cr, [], $cm, fn($f) => $f === 'MDCG-CK-002.webp' ? 'Image MDCG-CK-002.webp is not a 600 x 600 WebP file.' : true, null, 'cabin'); ok($x['rows'] === ['MDCG-CK-002' => ['Image MDCG-CK-002.webp is not a 600 x 600 WebP file.']], 'wrong image size is reported by name');
$b = $cr; $b[0]['order_uom'] = 'PCS'; $b[1]['name_en'] = ''; $b[2]['material'] = ''; $b[3]['price_usd'] = '4'; $b[4]['section'] = 'Deck'; $b[5]['brand'] = 'Acme'; $x = R::preflight($b, [], null, fn($f) => true, null, 'cabin'); ok(count($x['rows']) === 6, 'cabin malformed rows reported: ' . count($x['rows']));
$q1 = R::plan($cg, []); ok(R::counts($q1) === ['created' => 120, 'updated' => 0, 'conflict' => 0, 'skipped' => 0, 'rejected' => 0], 'cabin first run creates 120');
$cdb = []; $i = 500; foreach ($q1 as $r) $cdb[$r['sku']] = $r['set'] + ['id' => ++$i, 'state' => 'draft', 'packshot_path' => null];
ok(R::counts(R::plan($cg, $cdb)) === ['created' => 0, 'updated' => 0, 'conflict' => 0, 'skipped' => 120, 'rejected' => 0], 'cabin repeat run skips 120');
$cdb['MDCG-CK-001']['size_spec'] = '40 x 20 cm'; $cdb['MDCG-CK-002']['material'] = 'Steel'; $cdb['MDCG-CK-003']['pack_note'] = 'x'; $q3 = R::plan($cg, $cdb); ok(R::counts($q3)['conflict'] === 2 && R::counts($q3)['updated'] === 1, 'changed size or material is a conflict, changed note is an update');
ok(R::counts(R::plan($g + $cg, $db + $cdb))['created'] === 1, 'the two catalogues do not collide on SKU');
$row = R::publicRow($cdb['MDCG-CL-010'], [], 'u', $today); ok($row['brand'] === '' && $row['material'] === 'Blackout fabric' && $row['pack'] === 'One eyelet panel' && $row['group'] === 'Cabin Stores' && $row['handling'] === '' && $row['sub'] === '' && $row['img_label'] === 'Illustrative image', 'cabin public row');
ok(R::lineAsk($cdb['MDCG-CL-010']) === 'Berth or window size, e.g. 190 x 80 cm' && R::lineAsk($cdb['MDCG-CL-001']) === 'Berth or window size, e.g. 190 x 80 cm' && str_starts_with(R::lineAsk($cdb['MDCG-CK-004']), 'Size or details') && R::lineAsk($db['MDP-DP-003']) === null, 'textiles ask for the berth or window size; provisions ask nothing');
$ln = R::orderLine($cdb['MDCG-CL-010'], 4.0, null, '120 x 90 cm, two windows'); ok($ln['unit'] === 'EA' && $ln['name'] === 'Blackout Curtain Panel' && $ln['size'] === 'One eyelet panel · Blackout fabric' && $ln['note'] === '120 x 90 cm, two windows' && $ln['qty'] === 4.0 && $ln['list_usd'] === null, 'cabin order line: quantity in the order unit, buyer dimensions in the note');
ok(R::publishProblems($cdb['MDCG-CL-010']) === [], 'cabin item can be published');
ok(R::sourcesById([['source_id' => 'C01', 'source_description' => 'Shop', 'market_evidence' => 'Listed', 'source_url' => 'https://x.example/a', 'reviewed_on' => '2026-11-26'], ['source_id' => 'zz', 'source_description' => 'no']]) === ['C01' => '{"primary_retailer":"Shop","listing_signal":"Listed","source_url":"https://x.example/a","reviewed_on":"2026-11-26"}'], 'market sources keyed by source id');
echo ($f ? "FAILED $f of $n" : "ALL $n PASSED") . "\n"; exit($f ? 1 : 0);
