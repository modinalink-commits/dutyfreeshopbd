<?php
namespace App\Console\Commands;
use App\Support\ImageAudit;
use Illuminate\Console\Command;
/** php artisan products:scan-images [--repair] [--limit=200] */
class ProductsScanImages extends Command {
  protected $signature = 'products:scan-images {--repair : fix what can be fixed (default is a dry run)} {--limit=200 : products repaired per run}';
  protected $description = 'Audit product images; with --repair restore missing files from the old server and rebuild thumbnails';
  public function handle() {
    @set_time_limit(0); $r = ImageAudit::scan();
    $this->line(sprintf('Total %d | with image %d | working %d | broken %d | no image %d | old-server %d | thumbnail missing %d', $r['total'], $r['with_images'], $r['working'], $r['broken'], $r['missing'], $r['old_server'], $r['thumb_missing']));
    $n = 0; $fixed = 0; $failed = 0; $t0 = microtime(true);
    foreach ($r['bad'] as $p) {
      if ($p['state'] === 'none' || $n >= (int)$this->option('limit') || microtime(true) - $t0 > 240) continue; $n++;
      [$res, $d] = ImageAudit::repair($p, !$this->option('repair')); $res === 'fixed' ? $fixed++ : ($res === 'failed' ? $failed++ : null);
      $this->line("#{$p['id']} {$p['state']} -> $res ($d)");
    }
    $this->line("Done: fixed $fixed, failed $failed".($this->option('repair') ? '' : ' (dry run; add --repair to apply)'));
    return 0;
  }
}
