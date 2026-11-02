<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Audit and repair of product image paths: finds missing/broken files, restores them from the old server, rebuilds thumbnails. Every changed value is backed up first. */
class ImageAudit {
  const OWN = ['dutyfreeshopbd.com', 'www.dutyfreeshopbd.com', 'dutyfreebd.com', 'www.dutyfreebd.com'];
  const OLD = 'https://dutyfreebd.store';

  /** @return array{state:string, abs:?string, thumbs:bool} state: ok | thumb_missing | none | file_missing | corrupt | old_server | external | legacy_path */
  public static function check(?string $v): array {
    $v = trim((string)$v);
    if ($v === '') return ['state' => 'none', 'abs' => null, 'thumbs' => false];
    if (preg_match('#^https?://#i', $v)) {
      $h = strtolower((string)parse_url($v, PHP_URL_HOST));
      if (in_array($h, self::OWN, true)) { $p = (string)parse_url($v, PHP_URL_PATH); if (str_starts_with($p, '/uploads/')) return self::check($p); return ['state' => 'legacy_path', 'abs' => null, 'thumbs' => false]; }
      return ['state' => ($h === 'dutyfreebd.store' || str_contains($h, 'dutyfreebd')) ? 'old_server' : 'external', 'abs' => null, 'thumbs' => false];
    }
    if (!str_starts_with($v, '/uploads/')) return ['state' => 'legacy_path', 'abs' => null, 'thumbs' => false];
    $rel = ltrim(explode('?', $v)[0], '/'); if (str_contains($rel, '..')) return ['state' => 'file_missing', 'abs' => null, 'thumbs' => false];
    $abs = public_path($rel);
    if (!is_file($abs)) return ['state' => 'file_missing', 'abs' => $abs, 'thumbs' => false];
    if (filesize($abs) < 100) return ['state' => 'corrupt', 'abs' => $abs, 'thumbs' => false];
    $i = @getimagesize($abs);
    if (!$i || !in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return ['state' => 'corrupt', 'abs' => $abs, 'thumbs' => false];
    $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $abs);
    $th = is_file($base.'-150.webp') && (is_file($base.'-300.webp') || max($i[0], $i[1]) <= 300);
    return ['state' => $th || $i[2] === IMAGETYPE_GIF ? 'ok' : 'thumb_missing', 'abs' => $abs, 'thumbs' => $th];
  }

  /** Products whose main image is empty but whose gallery holds a usable image: promote it (old value logged, nothing else touched). */
  public static function fillFromGallery(): int {
    $n = 0;
    $rows = DB::table('products')->where(fn($q) => $q->whereNull('image')->orWhere('image', ''))->pluck('id');
    foreach ($rows as $id) {
      foreach (DB::table('product_images')->where('product_id', $id)->orderBy('id')->pluck('path') as $path) {
        if (!$path) continue; $c = self::check($path); if (!in_array($c['state'], ['ok', 'thumb_missing'], true)) continue;
        DB::table('products')->where('id', $id)->update(['image' => $path, 'updated_at' => now()]);
        try { DB::table('image_audit_log')->insert(['kind' => 'repair', 'table_name' => 'products', 'row_id' => $id, 'col' => 'image', 'old_value' => '', 'state' => 'none', 'attempt' => 'gallery', 'result' => 'fixed', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
        $n++; break;
      }
    }
    return $n;
  }

  /** One row per product with its worst image state (main image + gallery). */
  public static function scan(): array {
    $prods = DB::table('products')->select('id', 'name', 'sku', 'image', 'published')->orderBy('id')->get();
    $gal = DB::table('product_images')->select('product_id', 'path')->get()->groupBy('product_id');
    $rank = ['ok' => 0, 'thumb_missing' => 1, 'old_server' => 2, 'external' => 2, 'legacy_path' => 3, 'corrupt' => 4, 'file_missing' => 5, 'none' => 6];
    $cnt = array_fill_keys(array_keys($rank), 0); $bad = []; $withImg = 0;
    foreach ($prods as $p) {
      $c = self::check($p->image); $worst = $c['state']; $path = $p->image;
      if ($p->image) $withImg++;
      foreach (($gal[$p->id] ?? []) as $g) { $gc = self::check($g->path); if ($rank[$gc['state']] > $rank[$worst] && $gc['state'] !== 'none') { $worst = $gc['state']; $path = $g->path; } }
      $cnt[$worst]++;
      if ($worst !== 'ok') $bad[] = ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'path' => $path, 'state' => $worst, 'published' => (bool)$p->published];
    }
    $rep = ['at' => now()->toDateTimeString(), 'total' => count($prods), 'with_images' => $withImg, 'counts' => $cnt, 'working' => $cnt['ok'], 'broken' => $cnt['file_missing'] + $cnt['corrupt'] + $cnt['legacy_path'], 'missing' => $cnt['none'], 'old_server' => $cnt['old_server'] + $cnt['external'], 'thumb_missing' => $cnt['thumb_missing'], 'bad' => $bad];
    $store = $rep; $store['bad'] = array_slice($bad, 0, 400);
    DB::table('settings')->updateOrInsert(['key' => 'image_audit_report'], ['value' => json_encode($store, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]);
    return $rep;
  }

  private static function backup(string $tb, $id, string $col, ?string $old): void {
    DB::table('image_audit_log')->insert(['kind' => 'backup', 'table_name' => $tb, 'row_id' => $id, 'col' => $col, 'old_value' => $old, 'created_at' => now(), 'updated_at' => now()]);
  }
  private static function log(array $p, string $path, string $state, string $attempt, string $result, ?string $err = null): void {
    DB::table('image_audit_log')->insert(['kind' => 'repair', 'table_name' => 'products', 'row_id' => $p['id'], 'sku' => $p['sku'], 'name' => mb_substr((string)$p['name'], 0, 190), 'old_value' => mb_substr($path, 0, 500), 'state' => $state, 'attempt' => $attempt, 'result' => $result, 'error' => $err, 'created_at' => now(), 'updated_at' => now()]);
  }

  /** Repair one product; returns [result, detail]. */
  public static function repair(array $p, bool $dry = false): array {
    $state = $p['state']; $path = (string)$p['path'];
    try {
      if ($state === 'thumb_missing') {
        $c = self::check($path); if (!$c['abs']) return ['skip', 'no file'];
        if ($dry) return ['would', 'rebuild thumbnails'];
        if (Img::backupOn()) Img::backup($c['abs']);
        $r = Img::process($c['abs'], 'product'); self::log($p, $path, $state, 'thumbnail', $r['ok'] ? 'fixed' : 'failed', $r['error'] ?? null);
        return [$r['ok'] ? 'fixed' : 'failed', $r['error'] ?? 'thumbnails built'];
      }
      if (in_array($state, ['old_server', 'external'], true) || ($state === 'legacy_path' && preg_match('#^https?://#i', $path))) {
        $u = $path; $h = strtolower((string)parse_url($path, PHP_URL_HOST));
        // links that still point at our own new domain but use the old /public/storage/images/... path: the real file lives on the old server
        if (in_array($h, ['dutyfreebd.com', 'www.dutyfreebd.com', 'dutyfreeshopbd.com', 'www.dutyfreeshopbd.com'], true) && preg_match('#^/public/storage/images/#', (string)parse_url($path, PHP_URL_PATH))) $u = self::OLD . parse_url($path, PHP_URL_PATH);
        if (!$dry) { $loc = Img::mirror($u, 'product'); }
        if ($dry) return ['would', 'copy from '.parse_url($u, PHP_URL_HOST)];
        return self::apply($p, $path, $loc ?? null, 'mirror', $state);
      }
      // missing/none/corrupt/legacy relative path: look the old file up through the legacy product record
      $l = DB::table('legacy_products')->where('new_product_id', $p['id'])->first(['thumbnail_img', 'raw']);
      $cands = [];
      if ($l && $l->thumbnail_img) { $t = ltrim($l->thumbnail_img, '/'); $cands[] = self::OLD.'/public/storage/'.(str_starts_with($t, 'images/') ? $t : 'images/product_thumbnail_img/'.basename($t)); }
      if ($l && $l->raw) { $raw = json_decode($l->raw, true) ?: []; foreach (['image', 'images', 'product_image'] as $k) if (!empty($raw[$k]) && is_string($raw[$k])) $cands[] = self::OLD.'/public/storage/images/products/'.basename($raw[$k]); }
      if (preg_match('#^/?(public/)?storage/#', $path)) $cands[] = self::OLD.'/'.ltrim($path, '/');
      $cands = array_values(array_unique($cands)); if (!$cands) return ['failed', 'no source known'];
      if ($dry) return ['would', 'try '.count($cands).' old URL(s)'];
      foreach ($cands as $u) { $loc = Img::mirror($u, 'product'); if ($loc) return self::apply($p, $path, $loc, 'old-server copy', $state); }
      self::log($p, $path, $state, 'old-server copy', 'failed', 'old image not reachable'); return ['failed', 'old image not reachable'];
    } catch (\Throwable $e) { self::log($p, $path, $state, 'repair', 'failed', mb_substr($e->getMessage(), 0, 200)); return ['failed', $e->getMessage()]; }
  }

  private static function apply(array $p, string $old, ?string $loc, string $attempt, string $state): array {
    if (!$loc) { self::log($p, $old, $state, $attempt, 'failed', 'download failed'); return ['failed', 'download failed']; }
    $row = DB::table('products')->where('id', $p['id'])->first(['image']);
    if ($row && ($row->image === $old || !self::ok($row->image))) { self::backup('products', $p['id'], 'image', $row->image); DB::table('products')->where('id', $p['id'])->update(['image' => $loc, 'updated_at' => now()]); }
    foreach (DB::table('product_images')->where('product_id', $p['id'])->where('path', $old)->get(['id', 'path']) as $g) { self::backup('product_images', $g->id, 'path', $g->path); DB::table('product_images')->where('id', $g->id)->update(['path' => $loc]); }
    if (!DB::table('product_images')->where('product_id', $p['id'])->exists()) DB::table('product_images')->insert(['product_id' => $p['id'], 'path' => $loc, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
    self::log($p, $old, $state, $attempt, 'fixed'); return ['fixed', $loc];
  }
  private static function ok(?string $v): bool { return in_array(self::check($v)['state'], ['ok', 'thumb_missing'], true); }
}
