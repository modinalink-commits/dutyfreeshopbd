<?php
namespace App\Support;
use Illuminate\Http\Request;
/** Month-to-date default for date-range filters: From = 1st of the current month, To = today (Dhaka). Applies only when the page is opened without the fields; a user who clears them and submits still gets all dates. */
class DateDefault {
  public static function month(Request $r, string $from = 'from', string $to = 'to'): void {
    foreach ($r->query() as $k => $v) if (!in_array($k, [$from, $to, 'page', 'per', 'per_page', 'rows', 'sort', 'dir', 'view', 'tab'], true) && $v !== null && $v !== '' && $v !== []) return; // searching or filtering by something else: never hide older records behind a date default
    $n = now('Asia/Dhaka');
    if (!$r->query->has($from)) $r->query->set($from, $n->copy()->startOfMonth()->toDateString());
    if (!$r->query->has($to)) $r->query->set($to, $n->toDateString());
  }
}
