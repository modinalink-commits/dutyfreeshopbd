<?php
namespace App\Http\Controllers;

use App\Support\CustAuth;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CustAuthAdminController extends Controller
{
  public function index() {
    return view('customer_auth', ['emailOn' => CustAuth::emailOn(), 'fbFlag' => CustAuth::flag('auth_fb_on', '0'), 'hasId' => (bool)Sms::secret('fb_app_id'), 'hasSecret' => (bool)Sms::secret('fb_app_secret'),
      'appId' => Sms::secret('fb_app_id'), 'redirect' => CustAuth::redirectUrl(), 'configured' => CustAuth::fbConfigured(), 'mail' => CustAuth::mailReady()]);
  }
  private function setFlag(string $k, bool $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v ? '1' : '0']); }
  public function save(Request $r) {
    $this->setFlag('auth_email_on', $r->boolean('email_on')); $this->setFlag('auth_fb_on', $r->boolean('fb_on'));
    if (trim((string)$r->input('app_id')) !== '') Sms::setSecret('fb_app_id', trim((string)$r->input('app_id')));
    if (trim((string)$r->input('app_secret')) !== '') Sms::setSecret('fb_app_secret', trim((string)$r->input('app_secret')));
    \App\Support\Procure::audit('customer_auth.settings', 'Customer authentication settings updated');
    return redirect('/admin/customer-auth')->with('ok', 'Saved');
  }
  /** Verifies the App ID + Secret with Facebook (client_credentials) — never echoes the secret. */
  public function test() {
    if (!CustAuth::fbConfigured()) return response()->json(['ok' => false, 'status' => 'Configuration Required']);
    try {
      $j = Http::timeout(10)->get('https://graph.facebook.com/oauth/access_token', ['client_id' => Sms::secret('fb_app_id'), 'client_secret' => Sms::secret('fb_app_secret'), 'grant_type' => 'client_credentials'])->json();
      return response()->json(!empty($j['access_token']) ? ['ok' => true, 'status' => 'Connected'] : ['ok' => false, 'status' => 'Configuration Required']);
    } catch (\Throwable $e) { return response()->json(['ok' => false, 'status' => 'Configuration Required']); }
  }
}
