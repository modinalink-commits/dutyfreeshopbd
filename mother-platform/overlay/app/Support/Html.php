<?php
namespace App\Support;
/** Allow-list HTML sanitizer for product descriptions (admin rich text + storefront output). */
class Html {
  const ALLOWED = ['h2','h3','h4','p','br','strong','b','em','i','u','ul','ol','li','table','thead','tbody','tr','th','td','a','img','div','span','blockquote'];
  const DROP = ['script','style','svg','iframe','object','embed','form','input','button','textarea','select','link','meta'];
  public static function isHtml(?string $h): bool { $h = (string)$h; return $h !== strip_tags($h); }
  public static function clean(?string $h): ?string {
    $h = trim((string)$h);
    if ($h === '') return null;
    if (!self::isHtml($h)) return $h;
    $prev = libxml_use_internal_errors(true);
    $d = new \DOMDocument('1.0', 'UTF-8');
    $d->loadHTML('<?xml encoding="utf-8"?><div id="r">'.$h.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    $root = $d->getElementById('r');
    if (!$root) return trim(strip_tags($h)) ?: null;
    self::walk($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $d->saveHTML($c);
    $out = trim($out);
    return $out !== '' ? $out : null;
  }
  private static function walk(\DOMNode $n): void {
    foreach (iterator_to_array($n->childNodes) as $c) {
      if (!($c instanceof \DOMElement)) continue;
      $t = strtolower($c->tagName);
      if (in_array($t, self::DROP, true)) { $n->removeChild($c); continue; }
      self::walk($c);
      if (!in_array($t, self::ALLOWED, true)) { while ($c->firstChild) $n->insertBefore($c->firstChild, $c); $n->removeChild($c); continue; }
      foreach (iterator_to_array($c->attributes) as $a) {
        $an = strtolower($a->name); $v = trim($a->value);
        $ok = ($t === 'a' && $an === 'href' && preg_match('#^(https?://|/|mailto:|tel:)#i', $v))
          || ($t === 'img' && $an === 'src' && preg_match('#^(https?://|/)#i', $v))
          || ($t === 'img' && $an === 'alt')
          || ($an === 'style' && preg_match('/^text-align:\s*(left|right|center|justify)\s*;?$/i', $v))
          || (in_array($an, ['colspan','rowspan'], true) && ctype_digit($v));
        if (!$ok) $c->removeAttribute($a->name);
      }
      if ($t === 'a') $c->setAttribute('rel', 'noopener nofollow');
      if ($t === 'img') $c->setAttribute('loading', 'lazy');
    }
  }
}
