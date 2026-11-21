<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Places one voice-campaign call to the end (started in the background by Voice::tick so several calls ring at the same time). */
class VoiceDialRun extends Command
{
  protected $signature = 'voice:dial {vcid}';
  protected $description = 'Place one voice campaign call';
  public function handle(): int {
    $r = DB::table('voice_campaign_customers')->find((int)$this->argument('vcid')); if (!$r) return 0;
    $c = DB::table('voice_campaigns')->find($r->campaign_id); if (!$c || $c->status !== 'active') return 0;
    \App\Support\Voice::dial($c, $r); return 0;
  }
}
