<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Marine Supply: one short Port Weather refresh (MET Norway, Copernicus Marine waves, BMD CAP feed). Started by the shared worker when something is due. */
class MarineWeather extends Command
{
  protected $signature = 'marine:weather {--force}';
  protected $description = 'Refresh Port Weather for the marine site';
  public function handle(): int {
    if (!\App\Support\MarineWeather::ready()) return 0;
    $lock = Cache::lock('marine_wx_run', 240); if (!$lock->get()) return 0;
    try { foreach (\App\Support\MarineWeather::refresh((bool)$this->option('force')) as $k => $v) $this->line($k . ': ' . $v); } finally { $lock->release(); }
    return 0;
  }
}
