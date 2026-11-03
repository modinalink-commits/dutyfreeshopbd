<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\LateDelivery;
use App\Support\OrderActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/** Late Delivery bucket: undelivered shipments older than 72 hours, most overdue first. Following up never changes a courier status. */
class LateDeliveryController extends Controller {
  public function index(Request $r) {
    LateDelivery::auto();
    $q = DB::table('orders')->where('status', 'Late Delivery');
    if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%'; $q->where(fn($w) => $w->where('invoice', 'ilike', $like)->orWhere('phone', 'like', $like)->orWhere('customer_name', 'ilike', $like)->orWhere('consignment_id', 'ilike', $like)); }
    if ($c = $r->query('courier')) $q->where('courier', $c);
    $rows = (clone $q)->orderByRaw('coalesce(shipment_at, courier_sent_at) asc nulls last')->orderBy('id')->paginate(100)->withQueryString();
    $items = DB::table('order_items')->whereIn('order_id', collect($rows->items())->pluck('id'))->get(['order_id', 'name', 'qty'])->groupBy('order_id');
    $intel = []; try { $intel = \App\Support\CustomerIntel::forOrders($rows->items()); } catch (\Throwable $e) {}
    $couriers = DB::table('orders')->where('status', 'Late Delivery')->whereNotNull('courier')->distinct()->pluck('courier');
    $__cr = DB::table('orders')->select('status', DB::raw('count(*) c'), DB::raw('coalesce(sum(total),0) v'))->groupBy('status')->get(); $__v = $__cr->pluck('v', 'status'); try { $__v['Incomplete'] = (float)DB::table('incomplete_orders')->where('status', 'open')->sum('total'); } catch (\Throwable $e) {}
    return view('late_delivery', ['counts' => $__cr->pluck('c', 'status'), 'vals' => $__v, 'incOpen' => (int)DB::table('incomplete_orders')->where('status', 'open')->count(), 'rows' => $rows, 'intel' => $intel, 'items' => $items, 'couriers' => $couriers, 'canAct' => P::can(Auth::user(), 'orders', 'edit')]);
  }
  public function follow(Request $r, $id) {
    abort_unless(P::can(Auth::user(), 'orders', 'edit'), 403);
    $d = $r->validate(['note' => 'required|string|max:500']); $o = DB::table('orders')->find((int)$id); abort_unless($o, 404);
    OrderActivity::log((int)$o->id, 'late_followup', null, null, 'Late Delivery follow-up: '.trim($d['note']), []);
    return back()->with('ok', 'Follow-up saved for '.$o->invoice.'. The courier status is unchanged.');
  }
}
