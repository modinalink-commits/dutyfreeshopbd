<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class OrdersProcessScheduled extends Command
{
    protected $signature = 'orders:process-scheduled';
    protected $description = 'Move Scheduled orders whose date/time has arrived to Approved (Asia/Dhaka)';
    public function handle(): int
    {
        $n = \App\Support\OrderSchedule::tick();
        $this->info($n . ' scheduled order(s) approved');
        return 0;
    }
}
