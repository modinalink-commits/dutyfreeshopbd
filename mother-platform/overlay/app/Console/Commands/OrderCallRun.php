<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Runs one AI order-confirmation call to the end (started in the background by OrderCall). */
class OrderCallRun extends Command
{
  protected $signature = 'ordercall:run {id}';
  protected $description = 'Run one AI order-confirmation call';
  public function handle(): int { \App\Support\OrderCall::session((int)$this->argument('id')); return 0; }
}
