<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** SEOAutoGeneratorService: one place that writes the standard SEO fields for Main / Sub / Child categories and Brands (products, blogs and landing pages can plug in later).
 *  Context comes from the real catalog (parent names, sub / child names, brands and categories that really hold products); nothing is invented.
 *  Every field is tracked as 'auto' or 'manual' in seo_modes: automatic runs fill empty fields and refresh auto fields, and never overwrite a manual field or text that was already there. */
class SeoAutoGenerator {
  const TYPES = ['main' => 'Main Categories', 'sub' => 'Sub Categories', 'child' => 'Child Categories', 'brand' => 'Brands'];
  const FIELDS = ['title', 'desc', 'focus', 'keywords', 'canonical', 'og_title', 'og_desc', 'og_image', 'crumb', 'alt', 'schema'];
  const LABELS = ['title' => 'SEO Title', 'desc' => 'Meta Description', 'focus' => 'Focus Keyword', 'keywords' => 'SEO Keywords', 'canonical' => 'Canonical URL', 'og_title' => 'OG Title', 'og_desc' => 'OG Description', 'og_image' => 'OG Image', 'crumb' => 'Breadcrumb Title', 'alt' => 'Image Alt Text', 'schema' => 'Schema Type'];
  const CORE = ['title', 'desc', 'focus', 'keywords', 'og_title', 'og_desc', 'crumb', 'schema'];

  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasColumn('brands', 'seo_modes') && Schema::hasColumn('category_nodes', 'meta_title') && Schema::hasColumn('categories', 'seo_modes'); } catch (\Throwable $e) { $r = false; } } return $r; }
  public static function table(string $type): string { return ['main' => 'categories', 'sub' => 'category_nodes', 'child' => 'category_nodes', 'brand' => 'brands'][$type] ?? abort(404); }
  public static function col(string $type, string $f): string {
    return ['title' => self::table($type) === 'categories' ? 'title' : 'meta_title', 'desc' => 'meta_description', 'focus' => 'focus_keyword', 'keywords' => 'meta_keywords', 'canonical' => 'canonical', 'og_title' => 'og_title', 'og_desc' => 'og_description', 'og_image' => 'og_image', 'crumb' => 'breadcrumb_title', 'alt' => 'image_alt', 'schema' => 'schema_type'][$f];
  }
  public static function row(string $type, int $id): ?object {
    $q = DB::table(self::table($type))->where('id', $id); if ($type === 'sub') $q->where('level', 2); elseif ($type === 'child') $q->where('level', 3); return $q->first();
  }
  public static function modes(?object $row): array { $m = json_decode((string)($row->seo_modes ?? ''), true); return is_array($m) ? $m : []; }

  // ---------- text helpers ----------
  private static function site(): string { return SeoMgr::site(); }
  private static function latin(string $s): bool { return (bool)preg_match('/[A-Za-z]/', $s); }
  private static function len(string $s): int { return mb_strlen($s); }
  private static function clean(string $s): string { return trim(preg_replace('/\s+/u', ' ', $s)); }
  private static function amp(string $s): string { return preg_replace('/\s+and\s+/iu', ' & ', $s); }
  private static function lst(array $a): string { $a = array_values($a); $n = count($a); if ($n <= 1) return $a[0] ?? ''; return implode(', ', array_slice($a, 0, $n - 1)) . ' and ' . $a[$n - 1]; }
  private static function pick(array $cands, int $max): string { foreach ($cands as $c) if (self::len($c) <= $max) return $c; return end($cands); }
  private static function hasActive(): bool { static $h = null; if ($h === null) { try { $h = Schema::hasColumn('products', 'active'); } catch (\Throwable $e) { $h = false; } } return $h; }
  private static function products(array $w) { $q = DB::table('products'); if (self::hasActive()) $q->where('products.active', true); foreach ($w as $k => $v) $q->where('products.' . $k, $v); return $q; }

  /** Real catalog context for one record. */
  public static function context(string $type, object $row): array {
    $c = ['name' => self::clean((string)$row->name), 'main' => null, 'sub' => null, 'kids' => [], 'brands' => [], 'cats' => [], 'image' => trim((string)($row->image ?? $row->logo ?? '')), 'slug' => (string)($row->slug ?? '')];
    $w = []; 
    if ($type === 'main') { $w = ['category_id' => $row->id]; $c['main'] = $c['name']; }
    elseif ($type === 'sub') { $m = DB::table('categories')->find($row->category_id); $c['main'] = $m->name ?? null; $c['mainSlug'] = $m->slug ?? ''; $w = ['category_id' => $row->category_id, 'sub' => $row->name]; $c['sub'] = $c['name']; }
    elseif ($type === 'child') {
      $m = DB::table('categories')->find($row->category_id); $s = DB::table('category_nodes')->find($row->parent_id);
      $c['main'] = $m->name ?? null; $c['mainSlug'] = $m->slug ?? ''; $c['sub'] = $s->name ?? null; $c['subSlug'] = $s->slug ?? ''; $w = ['category_id' => $row->category_id, 'sub' => $s->name ?? '', 'sub_sub' => $row->name];
    } else $w = ['brand_id' => $row->id];
    if (!$w) return $c;
    try {
      if ($type === 'main') $c['kids'] = self::products($w)->join('category_nodes as n', fn($j) => $j->on('n.category_id', '=', 'products.category_id')->where('n.level', '=', 2)->on('n.name', '=', 'products.sub'))->where('n.active', true)->groupBy('n.name')->orderByRaw('count(*) desc')->limit(3)->pluck('n.name')->all();
      elseif ($type === 'sub') $c['kids'] = self::products($w)->whereNotNull('products.sub_sub')->where('products.sub_sub', '!=', '')->groupBy('products.sub_sub')->orderByRaw('count(*) desc')->limit(3)->pluck('products.sub_sub')->all();
      if ($type !== 'brand') $c['brands'] = self::products($w)->join('brands as b', 'b.id', '=', 'products.brand_id')->groupBy('b.name')->orderByRaw('count(*) desc')->limit(3)->pluck('b.name')->all();
      else $c['cats'] = self::products($w)->join('categories as k', 'k.id', '=', 'products.category_id')->groupBy('k.name')->orderByRaw('count(*) desc')->limit(3)->pluck('k.name')->all();
    } catch (\Throwable $e) { report($e); }
    return $c;
  }

  /** The fields for a record (logical name => text). Empty string = not applicable. */
  public static function generate(string $type, object $row): array {
    $x = self::context($type, $row); $n = $x['name']; $site = self::site(); $bn = !self::latin($n);
    $nt = self::amp($n); $in = $bn ? 'বাংলাদেশে' : 'in Bangladesh';
    // SEO title (~50-60 characters where practical)
    $cand = $type === 'child' && $x['sub'] && !$bn ? ["$nt – {$x['sub']} $in | $site", "$nt $in | $site", "$nt | $site"] : ["$nt $in | $site", "$nt | $site"];
    $title = self::pick($cand, 60);
    // Meta description (~140-160): base sentence, then real catalog context while it fits
    $where = $type === 'sub' && $x['main'] ? ($bn ? " {$x['main']}-এর" : " in our {$x['main']} collection") : ($type === 'child' && $x['sub'] ? ($bn ? " {$x['sub']}-এর" : " in {$x['sub']} ({$x['main']})") : '');
    if ($bn) $base = "$n$where পণ্য এখন $site-এ — সহজে অর্ডার ও দ্রুত ডেলিভারি সুবিধা।";
    else $base = "Explore $n products$where available from $site with convenient ordering and delivery options.";
    if (self::len($base) > 160) $base = $bn ? "$n পণ্য এখন $site-এ।" : "Explore $n at $site.";
    $extras = [];
    if (!$bn) {
      if ($x['kids']) $extras[] = 'Browse ' . self::lst($x['kids']) . '.';
      if ($x['brands']) $extras[] = 'Brands include ' . self::lst($x['brands']) . '.';
      if ($x['cats']) $extras[] = 'Find it in ' . self::lst($x['cats']) . '.';
    }
    $desc = $base; foreach ($extras as $e) if (self::len($desc . ' ' . $e) <= 160) $desc .= ' ' . $e;
    // focus keyword + keywords (short, human, no repetition)
    $nl = mb_strtolower($n); $focus = $bn ? "$n বাংলাদেশ" : "$nl bangladesh";
    $kw = [$nl]; if ($type === 'sub' || $type === 'child') { if ($x['sub'] && $type === 'child') $kw[] = mb_strtolower($x['sub']); if ($x['main']) $kw[] = mb_strtolower($x['main']); }
    $kw[] = $bn ? "$n বাংলাদেশ" : "$nl bangladesh"; $kw[] = $bn ? "$n দাম" : "$nl price in bangladesh";
    foreach (array_slice($x['kids'], 0, 2) as $k) $kw[] = mb_strtolower($k);
    $seen = []; $kws = []; foreach ($kw as $k) { $k = self::clean($k); $key = preg_replace('/[^\p{L}\p{N}]+/u', '', $k); if ($k === '' || isset($seen[$key]) || self::len($k) > 70) continue; $seen[$key] = 1; $kws[] = $k; if (count($kws) >= 6) break; }
    // canonical = final public URL (brands have no public page yet)
    $canon = '';
    if ($type === 'main' && $x['slug'] !== '') $canon = url('/' . $x['slug']) . '/';
    elseif ($type === 'sub' && !empty($x['mainSlug']) && $x['slug'] !== '') $canon = url('/' . $x['mainSlug'] . '/' . $x['slug']);
    elseif ($type === 'child' && !empty($x['mainSlug']) && !empty($x['subSlug']) && $x['slug'] !== '') $canon = url('/' . $x['mainSlug'] . '/' . $x['subSlug'] . '/' . $x['slug']);
    $img = $x['image']; $alt = '';
    if ($img !== '') $alt = $type === 'brand' ? "$n logo" : ($type === 'main' ? "$n category at $site" : "$n – {$x['main']} at $site");
    return ['title' => $title, 'desc' => $desc, 'focus' => $focus, 'keywords' => implode(', ', $kws), 'canonical' => $canon, 'og_title' => $title, 'og_desc' => $desc, 'og_image' => $img, 'crumb' => mb_substr($n, 0, 60), 'alt' => $alt, 'schema' => $type === 'brand' ? 'Brand' : 'CollectionPage'];
  }

  /** Fill empty fields and refresh auto fields of one record. Manual fields, and text written before this system existed, are never overwritten. $refresh=false = only fill what is empty. */
  public static function apply(string $type, int $id, bool $refresh = true): int {
    if (!self::ready()) return 0;
    $row = self::row($type, $id); if (!$row) return 0;
    $gen = self::generate($type, $row); $modes = self::modes($row); $upd = []; $n = 0;
    foreach (self::FIELDS as $f) {
      $col = self::col($type, $f); $cur = trim((string)($row->$col ?? '')); $m = $modes[$f] ?? null; $g = (string)$gen[$f];
      if ($m === 'manual' || $m === 'legacy') continue;
      if ($cur === '') { if ($g !== '') { $upd[$col] = $g; $modes[$f] = 'auto'; $n++; } continue; }
      if ($m === 'auto') { if ($refresh && $g !== '' && $g !== $cur) { $upd[$col] = $g; $n++; } continue; }
      $modes[$f] = 'legacy'; // text that was already there: protected, shown as Manual, and the storefront keeps its old logic for it
    }
    if ($upd || $modes !== self::modes($row)) { DB::table(self::table($type))->where('id', $id)->update($upd + ['seo_modes' => json_encode($modes), 'seo_auto_at' => $upd ? now() : ($row->seo_auto_at ?? null)]); }
    return $n;
  }
  /** After a category / brand is created or changed: its own SEO, and the SEO of everything below it (parent names are part of the context). */
  public static function onSave(string $type, int $id, bool $cascade = true): void {
    try {
      self::apply($type, $id, true);
      if (!$cascade) return;
      if ($type === 'main') foreach (DB::table('category_nodes')->where('category_id', $id)->get(['id', 'level']) as $c) self::apply($c->level == 2 ? 'sub' : 'child', (int)$c->id, true);
      elseif ($type === 'sub') foreach (DB::table('category_nodes')->where('parent_id', $id)->where('level', 3)->pluck('id') as $cid) self::apply('child', (int)$cid, true);
    } catch (\Throwable $e) { report($e); }
  }
  /** Generate Missing SEO for a whole type (or all four). Only-missing is the default and never touches existing text. */
  public static function bulk(?string $type = null, bool $onlyMissing = true): array {
    $out = []; foreach ($type ? [$type] : array_keys(self::TYPES) as $t) {
      $q = DB::table(self::table($t)); if ($t === 'sub') $q->where('level', 2); elseif ($t === 'child') $q->where('level', 3);
      $n = 0; $rec = 0; foreach ($q->pluck('id') as $id) { $k = self::apply($t, (int)$id, !$onlyMissing); if ($k) { $n += $k; $rec++; } }
      $out[$t] = ['records' => $rec, 'fields' => $n];
    }
    return $out;
  }
  /** One-time backfill for records that existed before this feature (runs once, from the background worker). */
  public static function backfillOnce(): void {
    if (!self::ready() || SeoMgr::get('seo_auto_backfill', '') === '1') return;
    self::bulk(null, true); SeoMgr::put('seo_auto_backfill', '1');
  }

  /** Admin list status: ['state' => complete|missing, 'origin' => auto|manual, 'missing' => [labels]]. */
  public static function status(string $type, object $row): array {
    $modes = self::modes($row); $miss = []; $man = 0;
    $core = self::CORE; if ($type !== 'brand') $core[] = 'canonical'; if (trim((string)($row->image ?? $row->logo ?? '')) !== '') $core[] = 'alt';
    foreach ($core as $f) if (trim((string)($row->{self::col($type, $f)} ?? '')) === '') $miss[] = self::LABELS[$f];
    foreach (self::FIELDS as $f) if (in_array($modes[$f] ?? null, ['manual', 'legacy'], true) && trim((string)($row->{self::col($type, $f)} ?? '')) !== '') $man++;
    return ['state' => $miss ? 'missing' : 'complete', 'origin' => $man ? 'manual' : 'auto', 'missing' => $miss, 'manual' => $man];
  }
}
