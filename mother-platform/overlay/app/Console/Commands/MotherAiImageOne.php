<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;

/** One automatic AI Product Image (run in parallel by AiImageAuto::tick, so the whole category finishes quickly). */
class MotherAiImageOne extends Command
{
  protected $signature = 'mother:ai-image-one {slot=1}';
  protected $description = 'Process one pending AI product image';
  public function handle(): int {
    $slot = (int)$this->argument('slot');
    try { \App\Support\AiImageAuto::work(); } catch (\Throwable $e) { \App\Support\AiImageAuto::fail($e->getMessage()); }
    finally { \Illuminate\Support\Facades\Cache::forget('ai-img-child-' . $slot); }
    return 0;
  }
}
