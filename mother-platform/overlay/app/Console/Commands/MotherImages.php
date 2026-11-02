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
    $this->line("IMAGES uploads {$a['processed']} (left {$a['remaining']}), mirrored $done, failed $fail");
    return 0;
  }
}
