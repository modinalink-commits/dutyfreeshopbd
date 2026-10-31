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
    $paths = ['/admin/inventory','/admin/inventory?view=grouped&stock=out&q=a','/admin/inventory/export/csv','/admin/inventory/export/xls','/admin/inventory/export/doc','/admin/inventory/export/pdf','/admin/stores','/admin/stores/1/edit','/admin/login','/','/perfume/','/sitemap.xml','/search?q=perfume','/track','/track?invoice=INV-000001','/checkout','/wishlist','/cart-items?ids=1:2,2:1&area=2&code=X','/nope-page','/admin/site','/admin/permissions','/admin/report-details','/admin','/admin/orders','/admin/orders/new','/admin/staff-log','/admin/products','/admin/products/new','/admin/taxonomy','/admin/modules','/admin/discounts','/admin/orders-export.csv','/admin/products-export.csv','/admin/coupons-export.csv'];
    foreach (\App\Models\Res::all() as $slug => $c) { $paths[] = "/admin/m/$slug"; if (empty($c['ro']) && empty($c['noCreate'])) $paths[] = "/admin/m/$slug/new"; foreach (['csv','xls','doc','pdf'] as $fm) $paths[] = "/admin/m/$slug/export/$fm"; }
    $paths[] = '/admin/reports';
    if ($oid) $paths[] = "/admin/orders/$oid"; if ($pid) { $paths[] = "/admin/products/$pid/edit"; $paths[] = \App\Http\Controllers\StoreController::url(DB::table('products')->where('published',true)->first() ?? DB::table('products')->first()); }
    $shown = false;
    foreach ($paths as $path) {
      try {
        Auth::guard('web')->setUser($user);
        $res = $kernel->handle(Request::create($path, 'GET'));
        $status = $res->getStatusCode();
        if ($res instanceof \Symfony\Component\HttpFoundation\StreamedResponse) { ob_start(); $res->sendContent(); ob_end_clean(); }
        $this->line("SMOKE $status $path");
        if ($status >= 500 && $status != 503 && empty($shown)) { $shown = true; $lf = storage_path('logs/laravel.log'); if (is_file($lf)) { $ls = file($lf); for ($i = count($ls)-1; $i >= 0; $i--) if (preg_match('/^\[20\d\d-/', $ls[$i])) { $this->line('LOGERR '.substr(trim($ls[$i]),0,500)); break; } } }
      } catch (\Throwable $e) { $this->line("SMOKE FAIL $path :: ".substr($e->getMessage(),0,200)); }
    }
  }
}
