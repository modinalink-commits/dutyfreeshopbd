<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Staff-only: order lines sold from source shops, grouped by vendor, with the vendor link and a to-order / ordered / shipped / received status. Customers never see any of it. */
class VendorOrderController extends Controller {
  const OPEN = ['Cancel', 'Cancelled', 'Returned', 'Return Pending', 'Draft'];
  public function index(Request $r) {
    abort_unless(Schema::hasColumn('order_items', 'src_store_id'), 404);
    $st = (string)$r->query('status', 'todo');
    $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.src_store_id')->whereNotIn('o.status', self::OPEN)
      ->select('i.*', 'o.invoice', 'o.status as ostatus', 'o.created_at as odate', 'o.customer_name as cname', 'o.phone as cphone', 'o.address as caddr')->orderBy('i.src_store')->orderByDesc('i.id');
    if ($st === 'todo') $q->whereNull('i.vendor_status'); elseif (in_array($st, ['ordered', 'shipped', 'received'], true)) $q->where('i.vendor_status', $st);
    $rows = $q->limit(500)->get()->groupBy('src_store');
    $counts = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.src_store_id')->whereNotIn('o.status', self::OPEN)->selectRaw("coalesce(i.vendor_status,'todo') s, count(*) n")->groupBy('s')->pluck('n', 's');
    return view('vendor_orders', compact('rows', 'st', 'counts'));
  }
  public function update(Request $r, $id) {
    $d = $r->validate(['vendor_status' => 'nullable|in:ordered,shipped,received', 'vendor_ref' => 'nullable|string|max:160']);
    DB::table('order_items')->where('id', $id)->whereNotNull('src_store_id')->update(['vendor_status' => $d['vendor_status'] ?? null, 'vendor_ref' => $d['vendor_ref'] ?? null]);
    return back();
  }
}
