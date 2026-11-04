<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Finds existing catalogue products that look like the one on the photo (barcode, SKU / code, brand + name + variant). Never creates or changes anything. */
class ProductMatchingService {
  const STRONG = 80;
  private static function tokens(?string $s): array {
    $s = mb_strtolower((string)$s); $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    $t = array_filter(explode(' ', trim($s)), fn($x) => $x !== '' && (mb_strlen($x) > 1 || ctype_digit($x)));
    return array_values(array_unique($t));
  }
  /** @param array $f flat values: name, brand, variant, size, barcode, code, sku */
  public static function find(array $f, int $limit = 5): array {
    $cand = [];
    $add = function ($p, int $score, string $why) use (&$cand) { if (!isset($cand[$p->id]) || $cand[$p->id]['score'] < $score) $cand[$p->id] = ['p'=>$p, 'score'=>$score, 'why'=>$why]; };
    $bar = preg_replace('/\D/', '', (string)($f['barcode'] ?? ''));
    if (strlen($bar) >= 8) foreach (DB::table('products')->where('barcode', $bar)->limit(5)->get() as $p) $add($p, 100, 'Same barcode');
    foreach (array_filter([(string)($f['sku'] ?? ''), (string)($f['code'] ?? '')]) as $c) {
      if (mb_strlen($c) < 3) continue;
      foreach (DB::table('products')->whereRaw('lower(sku) = ?', [mb_strtolower($c)])->limit(5)->get() as $p) $add($p, 95, 'Same SKU / code');
    }
    $name = trim((string)($f['name'] ?? '')); $want = self::tokens($name);
    if ($name !== '' && $want) {
      $sig = array_slice($want, 0, 6);
      $rows = DB::table('products')->where(function ($q) use ($sig) { foreach ($sig as $t) $q->orWhere('name', 'ilike', '%'.addcslashes($t, '%_\\').'%'); })->limit(400)->get();
      $extra = self::tokens(($f['variant'] ?? '').' '.($f['size'] ?? ''));
      foreach ($rows as $p) {
        $have = self::tokens($p->name); if (!$have) continue;
        $i = count(array_intersect($want, $have)); $u = count(array_unique(array_merge($want, $have))); if (!$u) continue;
        $score = (int)round(100 * $i / $u);
        if ($extra && !array_intersect($extra, $have)) $score = (int)round($score * 0.85);
        if ($score >= 50) $add($p, min(94, $score), $score >= self::STRONG ? 'Very similar name' : 'Similar name');
      }
    }
    uasort($cand, fn($a, $b) => $b['score'] <=> $a['score']);
    $cats = DB::table('categories')->pluck('name', 'id')->all(); $out = [];
    foreach (array_slice($cand, 0, $limit, true) as $c) { $p = $c['p'];
      $out[] = ['id'=>(int)$p->id, 'name'=>$p->name, 'sku'=>$p->sku, 'image'=>$p->image, 'category'=>trim(($cats[$p->category_id] ?? '').($p->sub ? ' / '.$p->sub : ''), ' /'), 'status'=>$p->published ? 'Published' : 'Draft', 'score'=>$c['score'], 'why'=>$c['why'], 'strong'=>$c['score'] >= self::STRONG];
    }
    return $out;
  }
  /** True when a strong match exists (used for the second duplicate check right before saving). */
  public static function strong(array $f): array { return array_values(array_filter(self::find($f, 3), fn($m) => $m['strong'])); }
}
