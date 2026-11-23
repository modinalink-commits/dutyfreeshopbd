<?php
namespace App\Http\Controllers;

use App\Support\CustomerReorder as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marketing & Offers -> Reorder Reminder (customer's own buying rhythm, one short reminder before the Win-Back offers). */
class ReorderReminderController extends Controller
{
  public function index() {
    $due = R::due(); $total = count($due); $rows = array_slice($due, 0, 50); $names = [];
    if ($rows) foreach (DB::table('customers')->whereRaw('right(phone,11) in (' . implode(',', array_fill(0, count($rows), '?')) . ')', array_column($rows, 'p'))->selectRaw('right(phone,11) as k, name')->get() as $cu) $names[$cu->k] = $cu->name;
    $hist = DB::table('reorder_events as e')->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')->orderByDesc('e.id')->limit(50)->get(['e.*', 'c.name']);
    return view('reorder_reminder', ['st' => R::stats(), 'due' => $rows, 'dueTotal' => $total, 'names' => $names, 'hist' => $hist,
      's' => ['on' => R::on(), 'cap' => R::dailyCap(), 'slot' => R::slotSize(), 'gap' => R::slotGap(), 'min' => R::minDays(), 'max' => R::maxDays(), 'def' => R::defaultDays(), 'mingap' => R::gapDays(), 'qf' => R::set('quiet_from', '22'), 'qt' => R::set('quiet_to', '10'), 'sms' => R::set('sms', R::SMS), 'quiet' => R::quiet(), 'today' => R::sentToday(), 'last' => R::set('last_run')]]);
  }
  public function save(Request $r) {
    R::put('daily_cap', (string)max(1, min(5000, (int)$r->input('daily_cap', 100))));
    R::put('slot_size', (string)max(1, min(20, (int)$r->input('slot_size', 10)))); R::put('slot_gap', (string)max(1, min(240, (int)$r->input('slot_gap', 10))));
    $mn = max(3, min(30, (int)$r->input('min_days', 7))); $mx = max($mn, min(14, (int)$r->input('max_days', 13)));
    R::put('min_days', (string)$mn); R::put('max_days', (string)$mx); R::put('default_days', (string)max($mn, min($mx, (int)$r->input('default_days', 10))));
    R::put('min_gap', (string)max(1, min(90, (int)$r->input('min_gap', 15))));
    R::put('quiet_from', (string)max(0, min(23, (int)$r->input('quiet_from', 22)))); R::put('quiet_to', (string)max(0, min(23, (int)$r->input('quiet_to', 10))));
    $t = trim((string)$r->input('sms', '')); R::put('sms', mb_substr($t !== '' ? $t : R::SMS, 0, 300));
    \App\Support\Procure::audit('reorder_reminder.save', 'Reorder reminder settings saved', null, []);
    return redirect('/admin/reorder-reminder')->with('ok', 'Saved');
  }
  public function toggle() { $on = !R::on(); R::put('on', $on ? '1' : '0'); \App\Support\Procure::audit('reorder_reminder.toggle', 'Reorder reminder ' . ($on ? 'ON' : 'OFF'), null, []); return redirect('/admin/reorder-reminder')->with('ok', $on ? 'Reorder reminder is ON' : 'Reorder reminder is OFF'); }
}
