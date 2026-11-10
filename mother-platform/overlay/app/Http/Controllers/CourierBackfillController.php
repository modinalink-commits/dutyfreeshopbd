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
    if ($r->query('diag')) {
      $out['diag'] = DB::table('orders')->selectRaw("status, coalesce(courier,'-') courier, coalesce(courier_stage,'-') stage, (consignment_id is not null) has_cn, (coalesce(collected_amount,courier_cod,0) > 0) has_cod, count(*) n, to_char(min(coalesce(courier_delivered_at, created_at)),'YYYY-MM-DD') d1, to_char(max(coalesce(courier_delivered_at, created_at)),'YYYY-MM-DD') d2")
        ->whereIn('status', ['Delivered','Partial Delivered','Completed'])->groupBy('status','courier','stage','has_cn','has_cod')->orderByDesc('n')->limit(40)->get();
    }
    if ($r->query('legacy')) {
      $out['legacy_orders'] = DB::table('orders')->where('status','Delivered')->whereNull('courier_stage')->selectRaw("coalesce(courier,'-') c, to_char(created_at at time zone 'Asia/Dhaka','YYYY-MM') m, count(*) n, round(sum(total)) tot, round(sum(coalesce(paid_total,0))) paid")->groupBy('c','m')->orderBy('m')->get();
      $out['ledger_by_prefix'] = DB::table('account_entries')->selectRaw("split_part(reference,'-',1) p, direction, to_char(min(entry_date),'YYYY-MM-DD') d1, to_char(max(entry_date),'YYYY-MM-DD') d2, count(*) n, round(sum(amount)) amt")->groupBy('p','direction')->orderBy('p')->get();
      $out['sample'] = DB::table('account_entries')->where('direction','income')->where('reference','like','OLD-%')->orderByDesc('amount')->limit(5)->get(['reference','amount','entry_date','comment']);
    }
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

  /** Old (imported) Pathao parcels have no courier fields: income per parcel = order total - Pathao fixed charge, dated by the Delivered status log. */
  private function flat(): float { return (float)(\App\Support\Procure::set('pathao_flat_charge', '90') ?? 90); }
  public function legacyPreview(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $f = $this->flat();
    $o = DB::select("select to_char(d,'YYYY-MM') m, count(*) n, round(sum(total)) tot, round(sum(total-?)) inc from (select o.total, (coalesce((select min(l.created_at) from order_status_logs l where l.order_id=o.id and l.to_status='Delivered'), o.created_at) at time zone 'Asia/Dhaka') d from orders o where o.status='Delivered' and o.courier='Pathao' and o.courier_stage is null and o.total > ? and not exists (select 1 from account_entries e where e.reference = 'DEL-'||o.invoice)) x group by m order by m", [$f, $f]);
    $l = DB::select("select to_char(entry_date,'YYYY-MM') m, count(*) n, round(sum(amount)) amt from account_entries where reference like 'OLD-I-%' and direction='income' and comment ilike '%pathao%' group by m order by m");
    $oth = DB::select("select count(*) n, round(coalesce(sum(amount),0)) amt from account_entries where reference like 'OLD-I-%' and direction='income' and comment not ilike '%pathao%'");
    $fe = DB::selectOne("select count(*) n from orders where status='Delivered' and courier='Pathao' and coalesce(courier_fee,0)=0 and total > ? and (created_at at time zone 'Asia/Dhaka') < '2026-11-02'", [$f]);
    return response()->json(['courier_fee_to_fill' => (int)($fe->n ?? 0), 'charge' => $f, 'parcels_to_book' => $o, 'pathao_lumps_to_retire' => $l, 'other_old_income_kept' => $oth]);
  }
  public function legacyApply(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $f = $this->flat(); $by = Auth::user()->name ?? 'Staff'; $out = [];
    if ($r->input('step') === 'book') {
      $m = (string)$r->input('m'); abort_unless(preg_match('/^\d{4}-\d{2}$/', $m), 422);
      $acc = CourierBook::account('Pathao');
      $n = DB::affectingStatement("insert into account_entries (reference, invoice, direction, account_id, amount, comment, entry_date, user_name, created_at, updated_at)
        select 'DEL-'||x.invoice, x.invoice, 'income', ?, round((x.total - ?)::numeric, 2), 'Old Pathao parcel delivered: order total ৳'||round(x.total)||' − Pathao charge ৳'||round(?::numeric)||' — order '||regexp_replace(x.invoice,'^\\s*INV[-\\s]?','','i'), x.d::date, 'System (Old Pathao parcels)', now(), now()
        from (select o.invoice, o.total, (coalesce((select min(l.created_at) from order_status_logs l where l.order_id=o.id and l.to_status='Delivered'), o.created_at) at time zone 'Asia/Dhaka') d from orders o where o.status='Delivered' and o.courier='Pathao' and o.courier_stage is null and o.total > ?) x
        where to_char(x.d,'YYYY-MM') = ? on conflict (reference) do nothing", [$acc, $f, $f, $f, $m]);
      $out = ['booked' => $n, 'month' => $m];
    } elseif ($r->input('step') === 'fee') {
      // Courier COST for old Pathao parcels: orders.courier_fee was empty, so profit ignored it. Fill only empty values, only up to 1 Nov 2026 (Dhaka), only Delivered Pathao parcels.
      $n = DB::affectingStatement("update orders set courier_fee = ?, updated_at = now() where status='Delivered' and courier='Pathao' and coalesce(courier_fee,0) = 0 and total > ? and (created_at at time zone 'Asia/Dhaka') < '2026-11-02'", [$f, $f]);
      $out = ['courier_fee_filled' => $n, 'fee' => $f];
    } elseif ($r->input('step') === 'retire' && $r->input('confirm') === 'yes') {
      DB::statement("create table if not exists account_entries_retired (like account_entries including defaults)");
      DB::statement("alter table account_entries_retired add column if not exists retired_at timestamp default now()");
      $out = DB::transaction(function () {
        $ids = DB::table('account_entries')->where('reference', 'like', 'OLD-I-%')->where('direction', 'income')->whereRaw("comment ilike '%pathao%'")->pluck('id');
        if ($ids->isEmpty()) return ['retired' => 0];
        $cols = implode(',', array_map(fn($c) => '"'.$c.'"', \Illuminate\Support\Facades\Schema::getColumnListing('account_entries')));
        DB::statement("insert into account_entries_retired ($cols) select $cols from account_entries where id in (".$ids->implode(',').") on conflict do nothing");
        $n = DB::table('account_entries')->whereIn('id', $ids)->delete();
        return ['retired' => $n];
      });
    } else abort(422);
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>$by, 'action'=>'courier.income.legacy', 'subject'=>json_encode($out), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json($out);
  }
}
