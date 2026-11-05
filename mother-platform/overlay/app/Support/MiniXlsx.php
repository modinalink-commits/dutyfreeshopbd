<?php
namespace App\Support;

/** Tiny .xlsx writer (one sheet, header row styled + frozen + filtered). Needs PHP ZipArchive; callers fall back to CSV when it is missing. */
class MiniXlsx {
  public static function available(): bool { return true; }
  /** Minimal ZIP writer (no extension needed): deflate when zlib is there, otherwise stored. */
  private static function zip(array $files): string {
    $out = ''; $cd = ''; $n = 0; $dt = 0x0021; $tm = 0;
    foreach ($files as $name => $data) {
      $crc = crc32($data); $len = strlen($data); $m = 0; $c = $data;
      if (function_exists('gzdeflate')) { $d = gzdeflate($data, 6); if ($d !== false && strlen($d) < $len) { $c = $d; $m = 8; } }
      $off = strlen($out); $nl = strlen($name);
      $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $m, $tm, $dt, $crc, strlen($c), $len, $nl, 0) . $name . $c;
      $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $m, $tm, $dt, $crc, strlen($c), $len, $nl, 0, 0, 0, 0, 0, $off) . $name; $n++;
    }
    return $out . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), strlen($out), 0);
  }
  private static function col(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }
  private static function esc(string $v): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
  /** @param array $headers list of strings; @param array $rows list of lists (numbers stay numbers); @param array $widths optional column widths */
  public static function build(array $headers, array $rows, array $widths = [], string $sheet = 'Sheet1'): string {
    $n = count($headers); $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
    for ($i = 0; $i < $n; $i++) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . ($widths[$i] ?? 16) . '" customWidth="1"/>';
    $x .= '</cols><sheetData><row r="1">';
    foreach ($headers as $i => $h) $x .= '<c r="' . self::col($i) . '1" s="1" t="inlineStr"><is><t>' . self::esc((string)$h) . '</t></is></c>';
    $x .= '</row>'; $r = 2;
    foreach ($rows as $row) {
      $x .= '<row r="' . $r . '">';
      foreach (array_values($row) as $i => $v) {
        $ref = self::col($i) . $r;
        if ($v === '' || $v === null) continue;
        if (is_int($v) || is_float($v)) $x .= '<c r="' . $ref . '"><v>' . (is_float($v) ? rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.') : $v) . '</v></c>';
        else $x .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string)$v) . '</t></is></c>';
      }
      $x .= '</row>'; $r++;
    }
    $x .= '</sheetData><autoFilter ref="A1:' . self::col($n - 1) . max(1, $r - 1) . '"/></worksheet>';
    $files = [
      '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
      '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
      'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::esc(mb_substr($sheet, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
      'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
      'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>',
      'xl/worksheets/sheet1.xml' => $x,
    ];
    return self::zip($files);
  }

  /** Several sheets in one workbook. @param array $sheets list of ['name'=>string,'headers'=>list,'rows'=>list of lists,'widths'=>list]. Numbers stay numbers; the header row is styled and frozen. */
  public static function buildBook(array $sheets): string {
    $one = fn(array $sh) => self::sheetXml($sh['headers'] ?? [], $sh['rows'] ?? [], $sh['widths'] ?? []);
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $files = []; $used = [];
    foreach (array_values($sheets) as $i => $sh) {
      $n = $i + 1; $nm = trim(strtr((string)($sh['name'] ?? 'Sheet' . $n), ['\\' => ' ', '/' => ' ', '?' => ' ', '*' => ' ', '[' => ' ', ']' => ' ', ':' => ' '])) ?: 'Sheet' . $n; $nm = mb_substr($nm, 0, 31);
      while (in_array(mb_strtolower($nm), $used, true)) $nm = mb_substr($nm, 0, 27) . ' ' . $n; $used[] = mb_strtolower($nm);
      $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
      $wb .= '<sheet name="' . self::esc($nm) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
      $rel .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
      $files['xl/worksheets/sheet' . $n . '.xml'] = $one($sh);
    }
    $rel .= '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    // reuse the single-sheet package for the shared parts (styles, root rels)
    $base = self::unzipParts(self::build(['x'], [], [], 'x'));
    $all = ['[Content_Types].xml' => $ct . '</Types>', '_rels/.rels' => $base['_rels/.rels'], 'xl/workbook.xml' => $wb . '</sheets></workbook>', 'xl/_rels/workbook.xml.rels' => $rel, 'xl/styles.xml' => $base['xl/styles.xml']] + $files;
    return self::zip($all);
  }

  /** Reads the stored/deflated parts back out of a package made by zip() (only used to borrow styles.xml and the root rels). */
  private static function unzipParts(string $z): array {
    $out = []; $o = 0; $len = strlen($z);
    while ($o + 30 <= $len && substr($z, $o, 4) === "PK\x03\x04") {
      $h = unpack('vver/vflag/vm/vt/vd/Vcrc/Vcs/Vus/vnl/vel', substr($z, $o + 4, 26)); $name = substr($z, $o + 30, $h['nl']); $data = substr($z, $o + 30 + $h['nl'] + $h['el'], $h['cs']);
      $out[$name] = $h['m'] === 8 ? gzinflate($data) : $data; $o += 30 + $h['nl'] + $h['el'] + $h['cs'];
    }
    return $out;
  }

  private static function sheetXml(array $headers, array $rows, array $widths): string {
    $n = max(1, count($headers)); $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
    for ($i = 0; $i < $n; $i++) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . ($widths[$i] ?? 18) . '" customWidth="1"/>';
    $x .= '</cols><sheetData><row r="1">';
    foreach ($headers as $i => $h) $x .= '<c r="' . self::col($i) . '1" s="1" t="inlineStr"><is><t>' . self::esc((string)$h) . '</t></is></c>';
    $x .= '</row>'; $r = 2;
    foreach ($rows as $row) {
      $x .= '<row r="' . $r . '">';
      foreach (array_values($row) as $i => $v) {
        $ref = self::col($i) . $r; if ($v === '' || $v === null) continue;
        if (is_int($v) || is_float($v)) $x .= '<c r="' . $ref . '"><v>' . (is_float($v) ? rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.') : $v) . '</v></c>';
        else $x .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string)$v) . '</t></is></c>';
      }
      $x .= '</row>'; $r++;
    }
    return $x . '</sheetData></worksheet>';
  }
}
