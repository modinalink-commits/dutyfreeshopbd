<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class MotherInstall extends Command {
  protected $signature = 'mother:install';
  protected $description = 'Create first super admin and first store';
  public function handle() {
    if (!DB::table('users')->where('role','super')->exists()) {
      DB::table('users')->insert(['name'=>'Mohammad Faruk','email'=>env('ADMIN_EMAIL','admin@example.com'),'password'=>Hash::make(env('ADMIN_PASSWORD')),'role'=>'super','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
      $this->info('admin created');
    }
    if (!DB::table('stores')->exists()) DB::table('stores')->insert(['name'=>'DutyFreeBD','domain'=>'dutyfreebd.com','created_at'=>now(),'updated_at'=>now()]);
  }
}
