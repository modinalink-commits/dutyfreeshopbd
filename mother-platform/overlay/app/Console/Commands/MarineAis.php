<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Marine Supply: keeps the AIS data stream open for 20 minutes, then the worker starts a fresh one (see App\Support\MarineAis). */
class MarineAis extends Command
{
  protected $signature = 'marine:ais {mode=local}';
  protected $description = 'Collect AIS vessel data for the marine site';
  public function handle(): int {
    $wide = $this->argument('mode') === 'wide'; $hb = $wide ? 'marine_ais_hbw' : 'marine_ais_hb';
    $lock = Cache::lock($wide ? 'marine_ais_loopw' : 'marine_ais_loop', 1320); if (!$lock->get()) return 0;
    try { Cache::put($hb, time(), 300); \App\Support\MarineAis::run(1200, $wide ? 'wide' : 'local'); }
    finally { Cache::forget($hb); $lock->release(); }
    return 0;
  }
}
