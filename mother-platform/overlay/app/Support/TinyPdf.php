<?php
namespace App\Support;

/** A very small PDF writer for plain tabular reports (A4 portrait, Helvetica, Latin text). No library needed.
 *  Text outside Latin-1 is transliterated or dropped, so callers keep report text in English. */
class TinyPdf {
  const W = 595.28, H = 841.89;
  private array $pages = []; private string $cur = ''; public float $y = 0; public float $m = 36;
  private string $footer = '';

  public function __construct(string $footer = '') { $this->footer = $footer; $this->addPage(); }
  /** Latin-1 text only: other scripts are dropped (not shown as "?"). */
  public static function plain(string $s): string {
    $s = preg_replace('/[^\x{20}-\x{24F}]+/u', ' ', $s) ?? $s; $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s); if ($t === false) $t = preg_replace('/[^\x20-\x7E]/', '', $s);
    return trim(preg_replace('/\(\s*\)|\s{2,}/', ' ', (string)$t));
  }
  public static function clean(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::plain($s)); }
  /** Rough Helvetica width, good enough for cutting text to a column. */
  public static function width(string $s, float $size): float { return strlen($s) * $size * 0.5; }
  public static function fit(string $s, float $w, float $size): string { $s = trim($s); $max = (int)floor($w / ($size * 0.5)); return strlen($s) > $max ? rtrim(substr($s, 0, max(1, $max - 2))) . '..' : $s; }
  public function addPage(): void { if ($this->cur !== '') $this->pages[] = $this->cur; $this->cur = ''; $this->y = self::H - $this->m; }
  public function need(float $h): void { if ($this->y - $h < $this->m + 16) $this->addPage(); }
  public function text(float $x, float $y, string $s, float $size = 9, bool $bold = false, array $rgb = [0.06, 0.1, 0.2], string $align = 'L', float $w = 0): void {
    $pl = self::plain($s); if ($pl === '') return; $c = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $pl);
    if ($align !== 'L' && $w > 0) { $tw = self::width($pl, $size); $x = $align === 'R' ? $x + $w - $tw : $x + ($w - $tw) / 2; }
    $this->cur .= sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, $c);
  }
  public function rect(float $x, float $y, float $w, float $h, array $rgb): void { $this->cur .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h); }
  public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.9, 0.91, 0.92]): void { $this->cur .= sprintf("%.3f %.3f %.3f RG 0.5 w %.2f %.2f m %.2f %.2f l S\n", $rgb[0], $rgb[1], $rgb[2], $x1, $y1, $x2, $y2); }
  public function heading(string $s, float $size = 12): void { $this->need($size + 22); $this->y -= $size + 8; $this->text($this->m, $this->y, $s, $size, true); $this->y -= 6; }
  public function note(string $s, float $size = 8): void { $this->need($size + 6); $this->y -= $size + 3; $this->text($this->m, $this->y, $s, $size, false, [0.42, 0.45, 0.5]); }
  /** $cols: [[title, width, align], ...]; $rows: list of cell arrays; optional row tint by index => rgb. */
  public function table(array $cols, array $rows, array $tint = []): void {
    $size = 8; $rh = 14; $x0 = $this->m;
    $head = function () use ($cols, $x0, $size, $rh) {
      $this->y -= $rh; $this->rect($x0, $this->y - 3, array_sum(array_column($cols, 1)), $rh, [0.95, 0.96, 0.97]); $x = $x0;
      foreach ($cols as [$t, $w, $a]) { $this->text($x + 3, $this->y + 1, self::fit($t, $w - 6, $size), $size, true, [0.29, 0.33, 0.39], $a, $w - 6); $x += $w; }
    };
    $this->need($rh * 3); $head();
    foreach ($rows as $i => $r) {
      if ($this->y - $rh < $this->m + 16) { $this->addPage(); $head(); }
      $this->y -= $rh; $x = $x0;
      if (isset($tint[$i])) $this->rect($x0, $this->y - 3, array_sum(array_column($cols, 1)), $rh, $tint[$i]);
      foreach ($cols as $k => [$t, $w, $a]) { $this->text($x + 3, $this->y + 1, self::fit(self::plain((string)($r[$k] ?? '')), $w - 6, $size), $size, false, [0.06, 0.1, 0.2], $a, $w - 6); $x += $w; }
      $this->line($x0, $this->y - 3, $x0 + array_sum(array_column($cols, 1)), $this->y - 3);
    }
    $this->y -= 4;
  }
  public function output(): string {
    $pages = $this->pages; $pages[] = $this->cur; $n = count($pages); $objs = []; $kids = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $id = 5;
    foreach ($pages as $i => $c) {
      if ($this->footer !== '') $c .= sprintf("BT /F1 7 Tf 0.5 0.53 0.58 rg %.2f 22 Td (%s) Tj ET\nBT /F1 7 Tf 0.5 0.53 0.58 rg %.2f 22 Td (Page %d of %d) Tj ET\n", $this->m, self::clean($this->footer), self::W - $this->m - 50, $i + 1, $n);
      $objs[$id] = '<< /Length ' . strlen($c) . " >>\nstream\n" . $c . "endstream";
      $objs[$id + 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $id . ' 0 R >>';
      $kids[] = ($id + 1) . ' 0 R'; $id += 2;
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
    ksort($objs); $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    foreach ($objs as $k => $o) { $off[$k] = strlen($out); $out .= $k . " 0 obj\n" . $o . "\nendobj\n"; }
    $xref = strlen($out); $max = max(array_keys($objs));
    $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($k = 1; $k <= $max; $k++) $out .= sprintf("%010d 00000 n \n", $off[$k] ?? 0);
    return $out . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
  }
}
