<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\AddressParse;
use App\Support\AreaOrders;
use App\Support\OrderActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/** Orders whose address did not clearly name a district. Staff set the district; the customer's own address text is never changed here. */
class AddressCheckController extends Controller {
  public function index(Request $r) {
    $ready = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'addr_check'); $rows = null;
    if ($ready) {
      $q = DB::table('orders')->where('addr_check', 1)->whereNotIn('status', ['Cancel', 'Delivered', 'Returned', 'Partial Delivered']);
      if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%'; $q->where(fn($w) => $w->where('invoice', 'ilike', $like)->orWhere('phone', 'like', $like)->orWhere('address', 'ilike', $like)); }
      $rows = $q->orderByDesc('id')->paginate(50, ['id', 'invoice', 'customer_name', 'phone', 'address', 'district', 'delivery_zone', 'status', 'addr_note', 'created_at'])->withQueryString();
    }
    return view('address_check', ['ready' => $ready, 'rows' => $rows, 'districts' => collect(AreaOrders::geo())->pluck('en')->sort()->values()->all(), 'canAct' => P::can(Auth::user(), 'orders', 'edit')]);
  }
  public function save(Request $r, $id) {
    abort_unless(P::can(Auth::user(), 'orders', 'edit'), 403);
    $d = $r->validate(['district' => 'required|string|max:60', 'area' => 'nullable|string|max:80']);
    $dist = AreaOrders::match($d['district'], false); if (!$dist) return back()->with('bad', 'Choose a district from the list.');
    $o = DB::table('orders')->where('id', (int)$id)->first(['id', 'invoice', 'district', 'addr_check']); abort_unless($o && $o->addr_check !== null, 404); // only orders the reader has flagged; old records are not edited here
    $area = trim((string)($d['area'] ?? '')) ?: null;
    DB::table('orders')->where('id', $o->id)->update(['district' => $dist, 'addr_district' => $dist, 'addr_division' => AddressParse::division($dist), 'addr_city' => $dist, 'addr_area' => $area, 'addr_check' => 2, 'addr_note' => null, 'updated_at' => now()]);
    OrderActivity::log((int)$o->id, 'order_edited', (string)$o->district, $dist, 'Address checked by staff: district set to '.$dist.($area ? ', area '.$area : '').'. The address text and the delivery charge are unchanged.', []);
    return back()->with('ok', \App\Support\Inv::show($o->invoice).': district saved as '.$dist.'.');
  }
}
