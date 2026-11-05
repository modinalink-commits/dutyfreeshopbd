<?php
namespace App\Http\Middleware;

use App\Support\SupplierPortal as SP;
use Closure;
use Illuminate\Http\Request;

/** Gate for /supplier-portal: logged-in, active supplier user; forced logout honoured; first login must change password and accept terms. */
class SupplierPortalAuth
{
  public function handle(Request $r, Closure $next)
  {
    $u = SP::user();
    $iat = (int)session('sp_iat');
    if (!$u || ($u->force_logout_at && strtotime((string)$u->force_logout_at) > $iat)) {
      session()->forget(['sp_uid','sp_iat']);
      return $r->expectsJson() ? response()->json(['error'=>'auth'], 401) : redirect('/supplier-portal/login');
    }
    $path = $r->path();
    if ($u->must_change && !str_starts_with($path, 'supplier-portal/password') && $path !== 'supplier-portal/logout') return redirect('/supplier-portal/password');
    if (!$u->must_change && $u->terms_ver !== SP::TERMS && !str_starts_with($path, 'supplier-portal/terms') && $path !== 'supplier-portal/logout') return redirect('/supplier-portal/terms');
    $res = $next($r);
    if (method_exists($res, 'header')) $res->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private');
    return $res;
  }
}
