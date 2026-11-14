<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\StaffIdle;

/** Staff idle check: counts real actions as work and keeps the admin locked for a staff member who owes a written reason. Never touches owner/admin accounts. */
class StaffIdleMw {
  public function handle(Request $r, Closure $next) {
    $u = $r->user();
    if ($u) \App\Support\StaffLive::touch($u, $r); // presence for the Live Staff Board (cache only)
    try { $act = $u && StaffIdle::on() && StaffIdle::applies($u); } catch (\Throwable $e) { $act = false; }
    if (!$act) return $next($r);
    $p = $r->path();
    $free = $p === 'admin/logout' || str_starts_with($p, 'admin/staff-idle/');
    if (!$free && StaffIdle::open((int)$u->id)) {
      if ($r->isMethod('get') && !$r->expectsJson() && !$r->ajax()) return redirect('/admin/staff-idle/notice');
      return response()->json(['ok' => false, 'locked' => true, 'error' => 'আপনার নামে একটি নোটিশ আছে। আগে কারণ জমা দিন।', 'redirect' => '/admin/staff-idle/notice'], 423);
    }
    $res = $next($r);
    try {
      // Work = a successful action (save, reply, status change). Opening pages, background polling and presence pings do not count.
      if (!$free && !$r->isMethod('get') && $res->getStatusCode() < 400 && !preg_match('#(poll|typing|ping|seen|heartbeat|my-status|presence|/live)$#', $p)) StaffIdle::work((int)$u->id);
    } catch (\Throwable $e) {}
    return $res;
  }
}
