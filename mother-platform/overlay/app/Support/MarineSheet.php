<?php
namespace App\Support;

/**
 * Marine Supply: reads the item list out of a file a customer attaches to a requisition (owner 26 Nov 2026).
 * Read automatically: Excel .xlsx (first sheet), .csv / .txt, and tables inside Word .docx. Every other file (PDF, photo, old .xls / .doc) is only attached for the desk.
 * Nothing is guessed: a row becomes an item only when it has a description and a quantity; the price is never taken from the file.
 * No outside library: .xlsx and .docx are zip files of XML, read with ZipArchive and SimpleXML (no network, size limits against oversized archives).
 */
class MarineSheet
{
  const MAX_ROWS = 3000; const MAX_COLS = 30; const MAX_XML = 25000000; const MAX_ITEMS = 250;
  const H_NAME = '/^(item\s*)?(description|desc|particulars?|product|products|article|item|items|item\s*name|name|goods|stores?|commodity|material)(\s*of\s*(goods|items|stores))?$/i';
  const H_QTY = '/^(qty|qnty|quantity|quantities|req(uired)?\.?\s*(qty|quantity)?|order(ed)?\s*(qty|quantity)|qty\.?\s*(req(uired)?|ordered|needed)\.?|nos?\.?|pcs)$/i';
  const H_UNIT = '/^(unit|units|uom|u\/m|unit\s*of\s*measure(ment)?|pack(ing)?)$/i';
  const H_CODE = '/^(impa(\s*(code|no\.?|number))?|issa(\s*(code|no\.?))?|code|item\s*(code|no\.?|number)|sku|part\s*(no\.?|number)|ref(erence)?\.?(\s*no\.?)?|product\s*code)$/i';

  public static function kind(string $ext): ?string { $e = strtolower($ext); return $e === 'xlsx' ? 'xlsx' : ($e === 'docx' ? 'docx' : (in_array($e, ['csv', 'txt'], true) ? 'csv' : null)); }
  /** [rows, problem]: rows is a list of lists of strings. */
  public static function rows(string $path, string $ext): array {
    try { return match (self::kind($ext)) { 'xlsx' => self::xlsx($path), 'docx' => self::docx($path), 'csv' => self::csv($path), default => [[], 'This kind of file is attached but not read automatically.'] }; }
    catch (\Throwable $e) { return [[], 'The file could not be read.']; }
  }
  private static function zipXml(\ZipArchive $z, string $name): ?\SimpleXMLElement {
    $st = $z->statName($name); if (!$st || $st['size'] > self::MAX_XML) return null; $s = $z->getFromName($name); if ($s === false) return null;
    $prev = libxml_use_internal_errors(true); $x = simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT); libxml_clear_errors(); libxml_use_internal_errors($prev); return $x ?: null;
  }
  private static function col(string $ref): int { $n = 0; foreach (str_split(preg_replace('/[^A-Z]/', '', strtoupper($ref))) as $c) $n = $n * 26 + (ord($c) - 64); return $n - 1; }
  private static function xlsx(string $path): array {
    if (!class_exists(\ZipArchive::class)) return [[], 'Excel files cannot be read on this server. Save the sheet as CSV and attach that.'];
    $z = new \ZipArchive(); if ($z->open($path) !== true) return [[], 'This is not a valid .xlsx file.'];
    $ss = []; if ($x = self::zipXml($z, 'xl/sharedStrings.xml')) foreach ($x->si as $si) { $t = isset($si->t) ? (string)$si->t : ''; foreach ($si->r as $r) $t .= (string)$r->t; $ss[] = $t; }
    $sheet = null; for ($i = 0; $i < $z->numFiles; $i++) { $n = $z->getNameIndex($i); if (preg_match('~^xl/worksheets/sheet(\d+)\.xml$~', $n, $m) && ($sheet === null || (int)$m[1] < $sheet[0])) $sheet = [(int)$m[1], $n]; }
    $x = $sheet ? self::zipXml($z, $sheet[1]) : null; $z->close(); if (!$x) return [[], 'The first sheet could not be read (it may be too large).'];
    $rows = [];
    foreach ($x->sheetData->row as $row) {
      if (count($rows) >= self::MAX_ROWS) break; $r = []; $next = 0;
      foreach ($row->c as $c) {
        $ci = isset($c['r']) ? self::col((string)$c['r']) : $next; $next = $ci + 1; if ($ci < 0 || $ci >= self::MAX_COLS) continue; $t = (string)$c['t'];
        $v = $t === 's' ? ($ss[(int)$c->v] ?? '') : ($t === 'inlineStr' ? (string)$c->is->t : (string)$c->v);
        if ($t === '' || $t === 'n') { if (is_numeric($v) && str_contains(strtolower($v), 'e') || (is_numeric($v) && strlen($v) > 12 && str_contains($v, '.'))) $v = rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.'); }
        $r[$ci] = trim(preg_replace('/\s+/u', ' ', $v));
      }
      if ($r) { $mx = max(array_keys($r)); $o = []; for ($i = 0; $i <= $mx; $i++) $o[] = $r[$i] ?? ''; $rows[] = $o; }
    }
    return [$rows, null];
  }
  private static function docx(string $path): array {
    if (!class_exists(\ZipArchive::class)) return [[], 'Word files cannot be read on this server.'];
    $z = new \ZipArchive(); if ($z->open($path) !== true) return [[], 'This is not a valid .docx file.']; $st = $z->statName('word/document.xml'); $s = ($st && $st['size'] <= self::MAX_XML) ? $z->getFromName('word/document.xml') : false; $z->close();
    if ($s === false) return [[], 'The Word file could not be read.'];
    $txt = fn(string $xml) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace(['~<w:tab[^>]*/>~', '~</w:p>~', '~<w:br[^>]*/>~'], ' ', $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8')));
    $rows = [];
    if (preg_match_all('~<w:tr[ >].*?</w:tr>~s', $s, $trs)) foreach ($trs[0] as $tr) { if (count($rows) >= self::MAX_ROWS) break; if (!preg_match_all('~<w:tc[ >].*?</w:tc>~s', $tr, $tcs)) continue; $r = array_slice(array_map($txt, $tcs[0]), 0, self::MAX_COLS); if (implode('', $r) !== '') $rows[] = $r; }
    if (!$rows && preg_match_all('~<w:p[ >].*?</w:p>~s', $s, $ps)) foreach ($ps[0] as $p) { if (count($rows) >= self::MAX_ROWS) break; $t = $txt($p); if ($t !== '') $rows[] = [$t]; }
    return [$rows, null];
  }
  private static function csv(string $path): array {
    $h = fopen($path, 'r'); if (!$h) return [[], 'The file could not be opened.']; $first = (string)fgets($h, 65536); rewind($h);
    $d = ','; $best = 0; foreach ([',', ';', "\t", '|'] as $x) { $n = substr_count($first, $x); if ($n > $best) { $best = $n; $d = $x; } }
    $rows = []; while (($r = fgetcsv($h, 0, $d, '"', '')) !== false && count($rows) < self::MAX_ROWS) { if ($r === [null]) continue; $r = array_slice(array_map(fn($v) => trim(preg_replace('/\s+/u', ' ', mb_convert_encoding((string)$v, 'UTF-8', 'UTF-8, ISO-8859-1'))), $r), 0, self::MAX_COLS); if (!$rows && isset($r[0])) $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]); if (implode('', $r) !== '') $rows[] = $r; }
    fclose($h); return [$rows, null];
  }

  private static function num(string $v): ?float { $v = str_replace([',', ' '], '', $v); if ($v === '' || !is_numeric($v)) return null; $q = round((float)$v, 2); return ($q > 0 && $q <= 100000) ? $q : null; }
  /** "10 kg Rice", "Rice - 10 kg", "Rice x 10": only for a single-column list (a Word list or a one-column sheet). */
  private static function loose(string $t): ?array {
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(kg|kgs|g|gm|ltr|l|litres?|liters?|pcs?|pc|nos?|box(?:es)?|ctn|cartons?|pkt|packs?|packets?|bottles?|btl|cans?|tins?|rolls?|sets?|pairs?|doz(?:en)?|bags?|m|mtr)?\.?\s*(?:x|of|[-:])?\s+(.{2,200})$/iu', $t, $m)) { $q = self::num($m[1]); return $q ? ['name' => trim($m[3]), 'qty' => $q, 'unit' => $m[2] ?: null] : null; }
    if (preg_match('/^(.{2,200}?)\s*(?:[-:x×]|qty\.?)\s*(\d+(?:[.,]\d+)?)\s*([a-z]{1,8})?\.?$/iu', $t, $m)) { $q = self::num($m[2]); return $q ? ['name' => trim($m[1]), 'qty' => $q, 'unit' => ($m[3] ?? '') ?: null] : null; }
    return null;
  }
  /** rows -> ['items' => [[code, name, qty, unit], ...], 'skipped' => rows that looked like items but had no usable quantity, 'more' => items beyond the limit, 'header' => bool]. */
  public static function items(array $rows): array {
    $map = null; $start = 0;
    foreach (array_slice($rows, 0, 25, true) as $i => $r) { $m = []; foreach ($r as $c => $v) { $v = trim((string)$v, " \t.:*#"); if ($v === '' || mb_strlen($v) > 40) continue; foreach (['name' => self::H_NAME, 'qty' => self::H_QTY, 'unit' => self::H_UNIT, 'code' => self::H_CODE] as $k => $re) if (!isset($m[$k]) && preg_match($re, $v)) { $m[$k] = $c; break; } } if (isset($m['name'], $m['qty'])) { $map = $m; $start = $i + 1; break; } }
    $items = []; $skipped = 0; $more = 0; $push = function (array $it) use (&$items, &$more) { if (count($items) >= self::MAX_ITEMS) { $more++; return; } $it['name'] = mb_substr($it['name'], 0, 200); $it['unit'] = isset($it['unit']) && $it['unit'] !== null ? (mb_substr(trim((string)$it['unit']), 0, 20) ?: null) : null; $it['code'] = isset($it['code']) && trim((string)$it['code']) !== '' ? mb_substr(trim((string)$it['code']), 0, 60) : null; $items[] = $it; };
    foreach (array_slice($rows, $start) as $r) {
      $r = array_values(array_map(fn($v) => trim((string)$v), $r)); $filled = array_values(array_filter($r, fn($v) => $v !== '')); if (!$filled) continue;
      if ($map) {
        $name = $r[$map['name']] ?? ''; if (mb_strlen($name) < 2) continue; $q = self::num($r[$map['qty']] ?? ''); $unit = isset($map['unit']) ? ($r[$map['unit']] ?? '') : null;
        if ($q === null && preg_match('/^(\d+(?:[.,]\d+)?)\s*([a-zA-Z]{1,8})\.?$/', $r[$map['qty']] ?? '', $mm)) { $q = self::num($mm[1]); $unit = $unit ?: $mm[2]; } // "10 kg" in the quantity column
        if ($q === null) { if (!preg_match('/^(total|sub\s*total|grand\s*total|remarks?|note|signature|prepared|approved)/i', $name)) $skipped++; continue; }
        $push(['code' => isset($map['code']) ? ($r[$map['code']] ?? '') : null, 'name' => $name, 'qty' => $q, 'unit' => $unit]);
      } elseif (count($filled) === 1) { $l = self::loose($filled[0]); if ($l) $push($l + ['code' => null]); elseif (mb_strlen($filled[0]) > 3 && mb_strlen($filled[0]) < 120 && preg_match('/\d/', $filled[0])) $skipped++; }
      else { // no header row: the longest text cell is the description, the first clean number that is not a serial number is the quantity
        $name = ''; $ni = -1; foreach ($r as $c => $v) if (self::num($v) === null && mb_strlen($v) > mb_strlen($name)) { $name = $v; $ni = $c; } if (mb_strlen($name) < 2) continue;
        $q = null; $unit = null; foreach ($r as $c => $v) { if ($c <= $ni) continue; $n = self::num($v); if ($n !== null) { $q = $n; $u = $r[$c + 1] ?? ''; if ($u !== '' && self::num($u) === null && mb_strlen($u) <= 10) $unit = $u; break; } }
        if ($q === null) { $skipped++; continue; } $push(['code' => null, 'name' => $name, 'qty' => $q, 'unit' => $unit]);
      }
    }
    return ['items' => $items, 'skipped' => $skipped, 'more' => $more, 'header' => $map !== null];
  }
}
