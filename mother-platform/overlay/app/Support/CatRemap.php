<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** One place that says where a product belongs in the 8 main categories. Importers / store scans call apply() so every new store is sorted the same way. */
class CatRemap {
  /** legacy category slug => main category slug (the 8 mains: cigarette-vape, perfume, beauty, fashion, watch, gadget-electronics, others, daily-needs) */
  const LEGACY = ['sunglass' => 'fashion', 'hair-care' => 'beauty', 'skin-care' => 'beauty', 'wellness' => 'beauty'];

  /** @return array [main slug, sub] */
  public static function map(string $slug, string $sub, string $name = '', string $desc = ''): array {
    $n = mb_strtolower($name . ' ' . $desc); $s = trim($sub);
    switch ($slug) {
      case 'sunglass':
        if (preg_match('/protective case|lens cleaner|lens solution|cleaning cloth|lens case|eyewear case|spectacle case|eyeglass chain/', $n) || $s === 'Accessories') return ['fashion', 'Eyewear Accessories'];
        if (preg_match('/contact\s*lens|colou?r(ed)?\s*lens/', $n)) return ['fashion', 'Contact Lens'];
        if ($s === 'Lens' || preg_match('/\blenses\b|single vision|progressive|photochromic lens|anti[\s-]*reflective/', $n)) return ['fashion', 'Lens'];
        if (preg_match('/sunglass|polari|shades|aviator|wayfarer|ray-?ban|gradient/', $n) && !preg_match('/eyeglass(es)? frame/', $n)) return ['fashion', 'Sunglasses'];
        return ['fashion', 'Eyeglasses'];
      case 'lighter-accessories':
        if ($s === 'Others') return preg_match('/\bwig\b|\bkiss\b/', $n) ? ['fashion', 'Accessories'] : ['fashion', 'Lighter'];
        if ($s === 'Gadgets' || $s === 'Home Decor') return ['fashion', 'Accessories'];
        if ($s === 'Bags') return ['fashion', 'Bags & Wallets'];
        return ['fashion', $s !== '' ? $s : 'Accessories'];
      case 'hair-care': return ['beauty', 'Hair Care'];
      case 'skin-care':
        if (preg_match('/\b(baby|babies|infant|newborn|toddler)\b/', $n) || $s === 'Baby Care') return ['others', 'Baby Care'];
        if (in_array($s, ['TOOTHPASTE', 'Mouth Spray'], true) || preg_match('/toothpaste|mouth\s*(wash|spray)/', $n)) return ['beauty', 'Oral Care'];
        if (in_array($s, ['Shower Gel', 'Soap', 'Hand Wash'], true)) return ['beauty', 'Bath & Body'];
        if (in_array($s, ['Personal Care', 'Deodorant', 'Shave Gel', 'Powder'], true)) return ['beauty', 'Personal Care'];
        if (preg_match('/shampoo|conditioner|hair\s*(oil|gel|cream|color|colour)/', $n)) return ['beauty', 'Hair Care'];
        return ['beauty', 'Skincare'];
      case 'wellness': return ['beauty', $s !== '' ? $s : 'Wellness'];
    }
    return [$slug, $sub];
  }

  /** For importers: given the category row a product was about to be inserted into, return the final [category row, sub]. */
  public static function apply($catRow, string $sub, string $name = '', string $desc = '') {
    if (!$catRow || !isset(self::LEGACY[$catRow->slug])) return [$catRow, $sub];
    [$ms, $ns] = self::map($catRow->slug, $sub, $name, $desc);
    if ($ms === $catRow->slug) return [$catRow, $ns];
    $row = DB::table('categories')->whereNull('parent_id')->where('slug', $ms)->first();
    return $row ? [$row, $ns] : [$catRow, $sub];
  }
}
