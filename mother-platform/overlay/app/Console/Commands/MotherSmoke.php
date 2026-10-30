<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Http\Kernel;
class MotherSmoke extends Command {
  protected $signature = 'mother:smoke';
  protected $description = 'Request every admin page as the super admin and print the status';
  public function handle() {
    $user = \App\Models\User::where('role','super')->first();
    $kernel = app(Kernel::class);
    $oid = DB::table('orders')->value('id'); $pid = DB::table('products')->value('id');
    $paths = ['/login','/admin','/admin/orders','/admin/orders/new','/admin/staff-log','/admin/products','/admin/products/new','/admin/taxonomy','/admin/discounts','/admin/orders-export.csv','/admin/products-export.csv','/admin/coupons-export.csv'];
    if ($oid) $paths[] = "/admin/orders/$oid"; if ($pid) $paths[] = "/admin/products/$pid/edit";
    foreach ($paths as $path) {
      try {
        Auth::guard('web')->setUser($user);
        $res = $kernel->handle(Request::create($path, 'GET'));
        $status = $res->getStatusCode();
        if ($res instanceof \Symfony\Component\HttpFoundation\StreamedResponse) { ob_start(); $res->sendContent(); ob_end_clean(); }
        $this->line("SMOKE $status $path");
      } catch (\Throwable $e) { $this->line("SMOKE FAIL $path :: ".substr($e->getMessage(),0,200)); }
    }
  }
}
