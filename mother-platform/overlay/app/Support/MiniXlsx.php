<?php
namespace App\Support;

/** Tiny .xlsx writer (one sheet, header row styled + frozen + filtered). Needs PHP ZipArchive; callers fall back to CSV when it is missing. */
class MiniXlsx {
  public static function available(): bool { return class_exists(\ZipArchive::class); }
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
    $tmp = tempnam(sys_get_temp_dir(), 'xl'); $z = new \ZipArchive(); $z->open($tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);
    foreach ($files as $name => $data) $z->addFromString($name, $data);
    $z->close(); $bin = (string)file_get_contents($tmp); @unlink($tmp); return $bin;
  }
}
