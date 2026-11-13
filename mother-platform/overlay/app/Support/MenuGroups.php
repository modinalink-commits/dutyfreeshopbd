<?php
namespace App\Support;
/** Owner rule (13 Nov 2026): the storefront menu shows 9 main groups (Lighter & Accessories is its own main group right after Cigarette and Vape, owner 13 Nov 2026 evening); the other categories sit inside them. Display only: categories, products, importers, prepaid rules and feeds are untouched. A category not listed here is shown on its own so nothing disappears. */
class MenuGroups {
  const GROUPS = [
    ['Cigarette and Vape', 'cigarette-vape', ['cigarette-vape']],
    ['Lighter and Accessories', 'lighter-accessories', ['lighter-accessories']],
    ['Perfume and Atar', 'perfume', ['perfume']],
    ['Beauty and Care', 'beauty', ['beauty', 'skin-care', 'hair-care', 'wellness']],
    ['Fashion and Accessories', 'fashion', ['fashion', 'sunglass']],
    ['Watch', 'watch', ['watch']],
    ['Gadget and Electronics', 'gadget-electronics', ['gadget-electronics']],
    ['Baby Care', 'others', ['others']],
    ['Daily Needs', 'daily-needs', ['daily-needs']],
  ];
  /** $cats = active categories keyed by slug. Returns [['name'=>, 'lead'=>category, 'members'=>[category,...], 'multi'=>bool], ...] */
  public static function build($cats): array {
    $out = []; $used = [];
    foreach (self::GROUPS as [$name, $lead, $slugs]) {
      $m = []; foreach ($slugs as $s) if (isset($cats[$s])) { $m[] = $cats[$s]; $used[$s] = true; }
      if (!$m) continue;
      $l = $cats[$lead] ?? $m[0];
      $out[] = ['name' => count($m) > 1 ? $name : $m[0]->name, 'lead' => $l, 'members' => $m, 'multi' => count($m) > 1];
    }
    foreach ($cats as $s => $c) if (!isset($used[$s])) $out[] = ['name' => $c->name, 'lead' => $c, 'members' => [$c], 'multi' => false];
    return $out;
  }
}
