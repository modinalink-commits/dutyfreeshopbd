<?php
namespace App\Console\Commands;
use App\Support\{Procure, Sms, AiImageAuto};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Keeps the owner's standing decisions from drifting: every run re-asserts them and writes an audit line for each correction. */
class MotherGuard extends Command {
  protected $signature = 'mother:guard';
  protected $description = 'Re-assert standing settings (WhatsApp order confirmation on approval, AI chat always on) and repair product images';
  public function handle(): int {
    $fixed = [];
    try {
      // 1. WhatsApp order confirmation: sent the moment an order is approved, by AI or by staff
      foreach (['enabled', 'on_ai', 'on_manual', 'retry'] as $k) { if (Procure::set('wa_conf_' . $k, '1') !== '1') { Procure::put('wa_conf_' . $k, '1'); $fixed[] = 'wa_conf_' . $k; } }
      // 2. AI chat is always open (no schedule), on every channel
      foreach (['ai_chat_auto', 'ai_chat_chan'] as $k) { if (Sms::secret($k) === '0') { Sms::setSecret($k, '1'); $fixed[] = $k; } }
      // 3. Product photos: a clean AI photo never goes back to a supplier photo
      try { AiImageAuto::repairMain(); } catch (\Throwable $e) { report($e); }
    } catch (\Throwable $e) { report($e); }
    if ($fixed) {
      try { DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System Guard', 'action'=>'guard.fix', 'subject'=>'Restored standing settings: ' . implode(', ', $fixed), 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    }
    $this->info($fixed ? 'restored: ' . implode(', ', $fixed) : 'all in place');
    return 0;
  }
}
