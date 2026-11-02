<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\OrderActivity;
/** Logs successful staff actions on an order (courier, payment, edit, comment, print, schedule). Status changes are mirrored by a DB trigger. */
class OrderActivityMw {
  public function handle(Request $r, Closure $next) {
    $res = $next($r);
    try {
      if (!$r->isMethod('post') || $res->getStatusCode() >= 400) return $res;
      if (!preg_match('#^admin/orders/(\d+)/(.+)$#', $r->path(), $m)) return $res;
      $id = (int)$m[1]; $p = $m[2];
      if (preg_match('/^(steadfast|redx|carrybee|courier)-send$/', $p, $c)) OrderActivity::log($id,'courier_assigned',null,$c[1],ucfirst($c[1]).' courier assigned');
      elseif (preg_match('/^pay-(\w+)$/', $p, $c)) OrderActivity::log($id, in_array($c[1],['reject','rejected','decline']) ? 'payment_rejected' : 'payment_verified', null, $c[1], 'Manual payment '.$c[1]);
      elseif ($p === 'revise' || preg_match('#^revisions/\d+/apply$#', $p)) OrderActivity::log($id,'order_edited',null,null,(\DB::table('orders')->where('id',$id)->value('status') === 'Cancel' ? 'Cancelled Order Edited (still Cancelled). ' : '').($p === 'revise' ? 'Order revision saved' : 'Order revision applied'));
      elseif ($p === 'comment') OrderActivity::log($id,'note_added',null,null,mb_substr((string)$r->input('body'),0,300));
      elseif ($p === 'print-log') OrderActivity::log($id,$r->input('type') === 'invoice' ? 'invoice_printed' : 'label_printed',null,null,(string)$r->input('type').' printed');
      elseif ($p === 'schedule') OrderActivity::log($id,'order_scheduled',null,(string)$r->input('sched_date'),(string)$r->input('note'));
      elseif ($p === 'schedule/cancel') OrderActivity::log($id,'schedule_cancelled');
      elseif ($p === 'call') OrderActivity::log($id,'call_started');
    } catch (\Throwable $e) { report($e); }
    return $res;
  }
}
