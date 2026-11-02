<?php
namespace App\Console\Commands;
use App\Support\Img;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Background image optimiser: runs by itself every couple of minutes. (1) optimises every uploaded file not yet processed, (2) copies images hosted on other websites into our own storage and optimises them, then points the product/category/banner/post at the local copy. Originals are kept (backup folder / source URL record). */
class MotherImages extends Command {
  protected $signature = 'mother:images';
  protected $description = 'Optimise all site images in the background';
  /** table => [column, kind] */
  const TARGETS = [['products', 'image', 'product'], ['product_images', 'path', 'product'], ['categories', 'image', 'category'], ['sliders', 'image', 'banner'], ['sliders', 'mobile_image', 'mobile'], ['banners', 'image', 'banner'], ['posts', 'featured_image', 'auto']];
  public function handle() {
    if (!Img::ok()) { $this->line('GD not available, skipping'); return 0; }
    $lock = fopen(storage_path('framework/img-optimizer.lock'), 'c'); if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return 0; // one run at a time
    @set_time_limit(0); $t0 = microtime(true);
    $a = Img::runBatch(40, 30);
    $own = ['dutyfreeshopbd.com', 'www.dutyfreeshopbd.com', 'dutyfreebd.com', 'www.dutyfreebd.com']; $done = 0; $fail = 0;
    foreach (self::TARGETS as [$tb, $col, $kind]) {
      if (!Schema::hasTable($tb) || !Schema::hasColumn($tb, $col)) continue;
      foreach (DB::table($tb)->where($col, 'like', 'http%')->whereNotIn($col, DB::table('media_sources')->where('tries', '>=', 3)->select('url'))->limit(60)->get(['id', $col]) as $row) {
        if (microtime(true) - $t0 > 55) break 2;
        $u = $row->$col; $h = parse_url($u, PHP_URL_HOST); if (!$h || in_array(strtolower($h), $own, true)) continue;
        $local = Img::mirror($u, $kind);
        if ($local) { DB::table($tb)->where('id', $row->id)->update([$col => $local]); $done++; } else $fail++;
      }
    }
    if ($done) foreach (['store.home2','store.home4','store.home6','store.home5','store.cats','store.live2','store.live3','store.live4'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
    try { $this->autoRepair($t0); } catch (\Throwable $e) { $this->line('image repair: '.$e->getMessage()); }
    $this->line("IMAGES uploads {$a['processed']} (left {$a['remaining']}), mirrored $done, failed $fail");
    return 0;
  }

  /** Server-side product image repair (no browser): a few products per run until nothing repairable is left. */
  private function autoRepair(float $t0): void {
    try { $g = \App\Support\ImageAudit::fillFromGallery(); if ($g) { $this->line("image repair: $g products got their gallery image as main image"); foreach (['store.home2','store.home4','store.home5','store.home6','store.cats','store.live2','store.live3','store.live4'] as $k) \Illuminate\Support\Facades\Cache::forget($k); } } catch (\Throwable $e) {}
    $st = (string)DB::table('settings')->where('key', 'img_auto_repair')->value('value');
    if ($st === 'off' || microtime(true) - $t0 > 40) return;
    // after a clean run, re-check only every 15 minutes (the hourly feed sync can bring bad paths back)
    if ($st === 'done' && !\Illuminate\Support\Facades\Cache::add('img_repair_recheck', 1, 900)) return;
    $failed = json_decode((string)DB::table('settings')->where('key', 'img_repair_failed')->value('value'), true) ?: [];
    $rep = \App\Support\ImageAudit::scan();
    $todo = array_values(array_filter($rep['bad'], fn($p) => $p['state'] !== 'none' && !in_array($p['id'], $failed, true)));
    if (!$todo) { DB::table('settings')->updateOrInsert(['key' => 'img_auto_repair'], ['value' => 'done', 'updated_at' => now(), 'created_at' => now()]); $this->line('image repair: finished'); return; }
    if ($st === 'done') DB::table('settings')->updateOrInsert(['key' => 'img_auto_repair'], ['value' => 'run', 'updated_at' => now(), 'created_at' => now()]);
    $fixed = 0;
    foreach (array_slice($todo, 0, 30) as $p) {
      if (microtime(true) - $t0 > 100) break;
      [$res] = \App\Support\ImageAudit::repair($p);
      if ($res === 'fixed') $fixed++; else $failed[] = $p['id'];
    }
    DB::table('settings')->updateOrInsert(['key' => 'img_repair_failed'], ['value' => json_encode(array_values(array_unique($failed))), 'updated_at' => now(), 'created_at' => now()]);
    foreach (['store.home2','store.home4','store.home5','store.home6','store.cats','store.live2','store.live3','store.live4'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
    $this->line("image repair: fixed $fixed, left " . max(0, count($todo) - 30));
  }
}
