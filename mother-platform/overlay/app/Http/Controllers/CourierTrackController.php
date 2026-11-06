<?php
namespace App\Http\Controllers;
use App\Support\CourierBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Courier income with tracking links. Read-only: joins the DEL-* income entries to their orders, so old entries get links too without changing any data. */
class CourierTrackController extends Controller {
  public function index(Request $r) {
    $q = trim((string)$r->query('q', '')); $rows = [];
    try {
      $qq = DB::table('account_entries as e')->leftJoin('orders as o', DB::raw("o.invoice"), '=', DB::raw("substr(e.reference, 5)"))->leftJoin('money_accounts as a', 'a.id', '=', 'e.account_id')
        ->where('e.reference', 'like', 'DEL-%')->where('e.direction', 'income')
        ->select('e.id', 'e.reference', 'e.invoice', 'e.amount', 'e.entry_date', 'e.comment', 'a.name as account', 'o.courier', 'o.consignment_id', 'o.phone', 'o.tracking_code', 'o.collected_amount', 'o.courier_cod')
        ->orderByDesc('e.id')->limit(300);
      if ($q !== '') $qq->where(fn($x) => $x->where('e.invoice', 'like', '%'.$q.'%')->orWhere('o.consignment_id', 'like', '%'.$q.'%')->orWhere('o.phone', 'like', '%'.$q.'%'));
      foreach ($qq->get() as $x) { $x->url = CourierBook::trackUrl(CourierBook::track($x)); $rows[] = $x; }
    } catch (\Throwable $e) { report($e); }
    return view('courier_track', ['rows' => $rows, 'q' => $q]);
  }
}
