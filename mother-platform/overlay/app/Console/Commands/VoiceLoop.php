<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Dedicated fast loop for the voice campaign: one tick every 4 seconds by default, setting loop_gap, each tick starts up to loop_batch calls (default 2) = about 30 a minute; 26 Nov 2026 owner asked for the highest speed the system allows. Runs up to 20 minutes, then the worker starts a fresh one. */
class VoiceLoop extends Command
{
  protected $signature = 'voice:loop';
  protected $description = 'Voice campaign dialling loop';
  public function handle(): int {
    $lock = Cache::lock('vc_loop', 1300); if (!$lock->get()) return 0;
    try {
      $end = time() + 1200;
      while (time() < $end) {
        Cache::put('vc_loop_hb', time(), 300);
        if (!\App\Support\Voice::on()) break;
        try { \App\Support\Voice::tick(max(1, min(6, (int)(\App\Support\Voice::set('loop_batch') ?: 2)))); } catch (\Throwable $e) { report($e); }
        sleep(max(3, (int)(\App\Support\Voice::set('loop_gap') ?: 4)));
      }
    } finally { Cache::forget('vc_loop_hb'); $lock->release(); }
    return 0;
  }
}
