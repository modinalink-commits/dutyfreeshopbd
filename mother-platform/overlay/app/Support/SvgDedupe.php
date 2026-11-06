<?php
namespace App\Support;
/**
 * Makes big admin pages lighter: the same inline icon (<svg>…</svg>) repeated on every row is stored ONCE in a hidden sprite and each place becomes <svg …><use href="#df-N"/></svg>.
 * Visuals are identical (the outer <svg> keeps its own size / viewBox / colour attributes). Icons with ids or url() references are left alone. Any problem returns the original HTML.
 */
class SvgDedupe {
  public static function run(string $html, int $minPage = 150000, int $minInner = 160): string {
    if (strlen($html) < $minPage || strpos($html, '<svg') === false) return $html;
    try {
      $map = []; $sprite = '';
      $out = preg_replace_callback('~<svg((?:\s[^>]*)?)>((?:(?!<svg|</svg>).)*)</svg>~s', function ($m) use (&$map, &$sprite, $minInner) {
        $inner = $m[2];
        if (strlen($inner) < $minInner || stripos($inner, ' id=') !== false || stripos($inner, 'url(') !== false || stripos($inner, '<use') !== false || stripos($inner, '<style') !== false || stripos($m[1], 'style="display:none') !== false) return $m[0];
        $k = md5($inner);
        if (!isset($map[$k])) { $map[$k] = 'df-sv' . (count($map) + 1); $sprite .= '<symbol id="' . $map[$k] . '">' . $inner . '</symbol>'; }
        return '<svg' . $m[1] . '><use href="#' . $map[$k] . '"/></svg>';
      }, $html);
      if ($out === null || !$map) return $html;
      $sp = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">' . $sprite . '</svg>';
      $p = stripos($out, '<body'); if ($p === false) return $html;
      $e = strpos($out, '>', $p); if ($e === false) return $html;
      return substr($out, 0, $e + 1) . $sp . substr($out, $e + 1);
    } catch (\Throwable $e) { return $html; }
  }
}
