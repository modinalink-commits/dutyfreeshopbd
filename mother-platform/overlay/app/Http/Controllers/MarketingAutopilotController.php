<?php
namespace App\Http\Controllers;
use App\Support\CustomerReorder;
use App\Support\IncompleteNudge;
use App\Support\IncompleteWa;
use App\Support\IndexNow;
use App\Support\Mailer;
use App\Support\Voice;
use App\Support\Winback;
use Illuminate\Support\Facades\DB;

/** One screen for every automatic marketing channel: is it on, what it sent today, its daily limit and sending hours. Read-only; each row links to the page where it is managed. */
class MarketingAutopilotController extends Controller {
  public function index() {
    $safe = function (callable $f, $d = null) { try { return $f(); } catch (\Throwable $e) { return $d; } };
    $today = now('Asia/Dhaka')->startOfDay()->utc();
    $sms = fn(string $ev) => $safe(fn() => (int)DB::table('sms_logs')->where('event', $ev)->where('created_at', '>=', $today)->whereIn('status', ['sent', 'delivered', 'queued'])->count(), 0);
    $wa = $safe(fn() => IncompleteWa::state(), []); $ix = $safe(fn() => IndexNow::state(), []);
    $waState = ($wa['template'] ?? '') === 'APPROVED' ? (!empty($wa['on']) ? 'on' : 'off') : 'wait';
    $rows = [
      ['Reorder Reminder', 'SMS', 'A short SMS a little before a customer usually runs out. No discount.', $safe(fn() => CustomerReorder::on(), false) ? 'on' : 'off', $safe(fn() => CustomerReorder::sentToday(), 0), $safe(fn() => CustomerReorder::dailyCap(), 0), '10 AM – 10 PM', '/admin/reorder-reminder', ''],
      ['Win-Back Offers', 'SMS', 'A small offer to customers who stopped ordering, timed to each customer\'s own buying rhythm.', $safe(fn() => Winback::on(), false) ? 'on' : 'off', $safe(fn() => Winback::sentToday(), 0), $safe(fn() => Winback::dailyCap(), 0), '10 AM – 10 PM', '/admin/winback', ''],
      ['Unfinished checkout', 'SMS', 'One SMS about 2 minutes after a customer leaves the checkout unfinished.', $safe(fn() => IncompleteNudge::set('on', '1') === '1', false) ? 'on' : 'off', $sms('incomplete_nudge'), null, 'All day', '/admin/incomplete', ''],
      ['Unfinished checkout', 'WhatsApp', 'A WhatsApp reminder 30 minutes later with a Confirm button (no product names).', $waState, (int)($wa['sent_today'] ?? 0), (int)($wa['daily_cap'] ?? 0), '10 AM – 10 PM', '/admin/analytics/cart-wa', $waState === 'wait' ? 'Waiting for Meta to approve the message: ' . ($wa['template'] ?? '') : (string)($wa['last_error'] ?? '')],
      ['Offer email', 'Email', 'Daily offer email through Brevo, only to people who agreed to receive offers.', $safe(fn() => Mailer::mkOn(), false) ? ($safe(fn() => Mailer::offerSmtp(), false) ? 'on' : 'wait') : 'off', $safe(fn() => Mailer::mkToday(), 0), $safe(fn() => Mailer::mkCap(), 0), '10 AM – 9 PM', '/admin/email', $safe(fn() => Mailer::offerSmtp(), false) ? '' : 'Brevo is not connected'],
      ['Offer calls', 'Voice', 'Automatic offer / reminder calls.', $safe(fn() => Voice::on(), false) ? 'on' : 'off', null, null, 'As set', '/admin/voice', ''],
      ['Search engines', 'Bing / IndexNow', 'New and changed pages are sent to Bing every 30 minutes.', empty($ix['last_error']) && !empty($ix['last_run']) ? 'on' : 'wait', (int)($ix['last_sent'] ?? 0), null, 'All day', '/admin/analytics/indexnow', (string)($ix['last_error'] ?? '')],
    ];
    $orders = $safe(fn() => DB::table('orders')->where('created_at', '>=', $today)->whereNotIn('status', ['Cancel'])->selectRaw('count(*) n, coalesce(sum(total),0) v')->first(), null);
    return view('marketing_autopilot', ['rows' => $rows, 'orders' => $orders, 'quiet' => (int)now('Asia/Dhaka')->format('H') >= 22 || (int)now('Asia/Dhaka')->format('H') < 10]);
  }
}
