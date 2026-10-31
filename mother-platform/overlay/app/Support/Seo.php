<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/** Product SEO generator: meta title, meta description, meta keywords and URL slug are filled automatically. */
class Seo {
  public static function gen(object $p, ?string $cat = null, ?string $brand = null): array {
    $name = trim((string)$p->name); $price = number_format((float)$p->price); $sub = trim((string)($p->sub ?? ''));
    $title = $name.' দাম ৳'.$price.' | '.($cat ?: 'Shop').' | DutyFree Shop BD';
    if (mb_strlen($title) > 72) $title = $name.' দাম ৳'.$price.' | DutyFree Shop BD';
    if (mb_strlen($title) > 72) $title = mb_substr($name, 0, 46).'… ৳'.$price.' | DutyFree Shop BD';
    $where = trim(($cat ?: '').($sub && $sub !== $cat ? ' / '.$sub : ''), ' /');
    $desc = $name.' অরিজিনাল ইমপোর্টেড, ডিউটি ফ্রি দামে মাত্র ৳'.$price.'।'.($where ? ' '.$where.'।' : '').' ক্যাশ অন ডেলিভারি, সারা বাংলাদেশে হোম ডেলিভারি। এখনই অর্ডার করুন।';
    if (mb_strlen($desc) > 158) $desc = mb_substr($desc, 0, 155).'…';
    $kw = [$name, $name.' দাম', $name.' price in bangladesh', $name.' original', $cat, $sub, $brand, $cat ? $cat.' price in bangladesh' : null, 'duty free', 'ডিউটি ফ্রি', 'original imported', 'dutyfree shop bd', 'cash on delivery'];
    $out = []; $len = 0;
    foreach ($kw as $k) { $k = trim((string)$k); if ($k === '' || in_array(mb_strtolower($k), array_map('mb_strtolower', $out), true)) continue; if ($len + mb_strlen($k) + 2 > 480) break; $out[] = $k; $len += mb_strlen($k) + 2; }
    return ['meta_title'=>$title, 'meta_description'=>$desc, 'meta_keywords'=>implode(', ', $out)];
  }

  /** Fill every product. $overwrite=false keeps anything an editor already wrote. */
  public static function fillAll(bool $overwrite = false): array {
    $cats = DB::table('categories')->pluck('name', 'id')->all(); $brands = DB::table('brands')->pluck('name', 'id')->all();
    $n = 0; $slugs = 0;
    DB::table('products')->orderBy('id')->chunkById(300, function ($rows) use ($cats, $brands, $overwrite, &$n) {
      foreach ($rows as $p) {
        $g = self::gen($p, $cats[$p->category_id] ?? null, $brands[$p->brand_id ?? 0] ?? null); $upd = [];
        foreach ($g as $k => $v) if ($overwrite || trim((string)($p->$k ?? '')) === '') $upd[$k] = $v;
        if ($upd) { DB::table('products')->where('id', $p->id)->update($upd); $n++; }
      }
    });
    $slugs = DB::table('products')->where(fn($q) => $q->whereNull('path_slug')->orWhere('path_slug', ''))->count();
    if ($slugs) Slugs::assignAll();
    foreach (['store.home6', 'store.home4', 'store.home5', 'store.sitemap5'] as $k) Cache::forget($k);
    return ['updated'=>$n, 'slugs'=>$slugs, 'total'=>DB::table('products')->count()];
  }
}
