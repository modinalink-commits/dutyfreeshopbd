<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\CourierBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Old Pathao parcels: book COD collected minus the courier charge as Income for every Delivered parcel that has no income entry yet.
 *  GET = read-only preview (per month). POST = apply (idempotent via CourierBook::deliver, existing entries are never changed). */
class CourierBackfillController extends Controller {
  private function base() {
    return DB::table('orders as o')->where('o.courier_stage', 'Delivered')->whereRaw("coalesce(o.courier,'Pathao') = 'Pathao'")
      ->whereRaw("coalesce(o.collected_amount, o.courier_cod, 0) > 0")
      ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('account_entries as e')->whereRaw("e.reference = 'DEL-' || o.invoice"))
      ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('account_entries as e')->whereRaw("o.consignment_id is not null and e.reference like 'PAY-%-' || o.consignment_id"));
  }
  public function preview(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $rows = $this->base()->selectRaw("to_char(o.courier_delivered_at at time zone 'Asia/Dhaka','YYYY-MM') m, count(*) n, coalesce(sum(coalesce(o.collected_amount,o.courier_cod)),0) cod, count(*) filter (where o.courier_delivered_at is null) nodate")->groupBy('m')->orderBy('m')->get();
    $have = DB::table('account_entries')->where('reference', 'like', 'DEL-%')->count();
    $out = ['already_booked_DEL_entries' => $have, 'flat_charge' => (float)(\App\Support\Procure::set('pathao_flat_charge', '90') ?? 90), 'months' => $rows, 'total_parcels' => $rows->sum('n'), 'total_cod' => $rows->sum('cod')];
    return response()->json($out);
  }
  public function apply(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $from = (string)$r->input('from', '2000-01-01'); $to = (string)$r->input('to', '2100-01-01'); $limit = min(2000, max(1, (int)$r->input('limit', 500)));
    $ids = $this->base()->whereRaw("coalesce(o.courier_delivered_at, '2000-01-01') >= ?", [$from])->whereRaw("coalesce(o.courier_delivered_at, '2000-01-01') < ?", [$to])->orderBy('o.id')->limit($limit)->pluck('o.id');
    $n = 0; $sum = 0.0; $fail = 0;
    foreach ($ids as $id) { try { $a = CourierBook::deliver((int)$id); if ($a > 0) { $n++; $sum += $a; } } catch (\Throwable $e) { $fail++; report($e); } }
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'Staff', 'action'=>'courier.income.backfill', 'subject'=>'booked '.$n.' parcels, BDT '.round($sum,2), 'meta'=>json_encode(['from'=>$from,'to'=>$to,'failed'=>$fail]), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json(['booked' => $n, 'amount' => round($sum, 2), 'failed' => $fail, 'picked' => count($ids)]);
  }
}
