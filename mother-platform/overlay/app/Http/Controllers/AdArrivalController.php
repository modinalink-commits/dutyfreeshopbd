<?php
namespace App\Http\Controllers;
use App\Support\ArrivalCount;

/** Read-only: how many people who clicked a Facebook / Instagram ad actually reached the site (counted on our server), per day / hour / ad id. Compare with Meta link clicks; the gap is visitors who never arrived (blocked, slow, left). Admin only. */
class AdArrivalController extends Controller {
  public function index() {
    abort_unless(auth()->check() && in_array(auth()->user()->role, ['super', 'admin'], true), 403);
    return response()->json(['since_note' => 'Counting starts when this version is live; earlier days read 0.', 'days' => ArrivalCount::report((int)request('days', 7))])->header('Cache-Control', 'no-store');
  }
}
