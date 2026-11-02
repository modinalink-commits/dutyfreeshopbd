<?php
namespace App\Http\Controllers;

use App\Support\Capi;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin -> Pixel & Conversions API: what is installed on the site, and the server-side (CAPI) connection. Tokens are typed by the owner and never shown again. */
class PixelController extends Controller
{
  public function index() {
    $s = StoreController::set();
    $log = json_decode((string)DB::table('settings')->where('key', 'capi_log')->value('value'), true) ?: [];
    return view('pixel_admin', ['pixel' => $s['pixel_id'] ?? '', 'gtag' => $s['gtag_id'] ?? '', 'gtm' => $s['gtm_id'] ?? '', 'hasToken' => (bool)Sms::secret('meta_capi_token'), 'hasTest' => (bool)Sms::secret('meta_capi_test'), 'log' => $log]);
  }
  public function save(Request $r) {
    if ($r->filled('token')) Sms::setSecret('meta_capi_token', trim((string)$r->input('token')));
    if ($r->boolean('clear_token')) Sms::setSecret('meta_capi_token', null);
    Sms::setSecret('meta_capi_test', trim((string)$r->input('test_code')) ?: null);
    return redirect('/admin/pixel')->with('ok', 'Saved');
  }
  /** Sends one TEST Purchase (needs the Test Events code so it never counts as a real sale). */
  public function test(Request $r) {
    if (!Capi::enabled()) return redirect('/admin/pixel')->with('ok', 'Add the access token first.');
    if (!Sms::secret('meta_capi_test')) return redirect('/admin/pixel')->with('ok', 'Enter the Test Events code first (Events Manager → Test events), so the test is not counted as a real sale.');
    $u = Capi::user(['phone' => '01700000000', 'name' => 'Test Customer', 'city' => 'Chattogram', 'external_id' => 'test'], null, null, Capi::clientIp($r), (string)$r->userAgent());
    $res = Capi::send('Purchase', 'TEST-' . time(), $u, ['currency' => 'BDT', 'value' => 100, 'content_type' => 'product', 'content_ids' => ['test'], 'order_id' => 'TEST'], url('/'), null, true);
    return redirect('/admin/pixel')->with('ok', ($res['ok'] ? 'Test event sent: ' : 'Failed: ') . $res['msg']);
  }
}
