<?php
namespace App\Http\Controllers;
use App\Support\CourierBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Courier income with tracking links. Read-only: joins the DEL-* income entries to their orders, so old entries get links too without changing any data. */
class CourierTrackController extends Controller {
  public function index(Request $r) { \App\Support\DateDefault::month($r);
    $q = trim((string)$r->query('q', '')); $from = $this->d($r->query('from')); $to = $this->d($r->query('to')); $rows = null; $sum = 0.0; $cnt = 0;
    try {
      $base = DB::table('account_entries as e')->leftJoin('orders as o', DB::raw("o.invoice"), '=', DB::raw("substr(e.reference, 5)"))->leftJoin('money_accounts as a', 'a.id', '=', 'e.account_id')
        ->where('e.reference', 'like', 'DEL-%')->where('e.direction', 'income');
      if ($from) $base->where('e.entry_date', '>=', $from); if ($to) $base->where('e.entry_date', '<=', $to);
      if ($q !== '') $base->where(fn($x) => $x->where('e.invoice', 'like', '%'.$q.'%')->orWhere('e.reference', 'like', '%'.$q.'%')->orWhere('o.consignment_id', 'like', '%'.$q.'%')->orWhere('o.tracking_code', 'like', '%'.$q.'%')->orWhere('o.phone', 'like', '%'.$q.'%'));
      $sum = (float)(clone $base)->sum('e.amount'); $cnt = (int)(clone $base)->count();
      $rows = $base->select('e.id', 'e.reference', 'e.invoice', 'e.amount', 'e.entry_date', 'e.created_at as booked_at', 'e.comment', 'a.name as account', 'o.courier', 'o.consignment_id', 'o.tracking_code', 'o.phone', 'o.collected_amount', 'o.courier_cod')
        ->orderByDesc('e.entry_date')->orderByDesc('e.id')->paginate(100)->withQueryString();
      $rows->getCollection()->transform(function ($x) {
        $x->shown_consignment = trim((string)($x->consignment_id ?: $x->tracking_code ?: ''));
        $o = clone $x; $o->consignment_id = $x->shown_consignment;
        $x->url = $x->shown_consignment !== '' ? CourierBook::trackUrl(CourierBook::track($o)) : null;
        if (!$x->url) $x->url = CourierBook::trackUrl($x->comment);
        return $x;
      });
    } catch (\Throwable $e) { report($e); }
    return view('courier_track', ['rows' => $rows, 'q' => $q, 'from' => $from, 'to' => $to, 'sum' => $sum, 'cnt' => $cnt, 'today' => now('Asia/Dhaka')->toDateString()]);
  }
  private function d($v): ?string { try { return $v ? \Carbon\Carbon::parse((string)$v)->toDateString() : null; } catch (\Throwable $e) { return null; } }
}
