<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
/** Keeps parcels sent to the courier in step automatically (Delivered, Returned, Cancelled), without anyone opening the admin. */
class MotherCourierSync extends Command {
  protected $signature = 'mother:courier-sync';
  protected $description = 'Poll open courier parcels and update the matching orders';
  public function handle() { $n = \App\Support\CourierSync::run(80); $this->info("courier-sync changed=$n"); return 0; }
}
