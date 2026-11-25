<?php
namespace App\Http\Controllers;

use App\Support\FraudCheck as F;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FraudController extends Controller
{
  public function index(Request $r) {
    $phone = trim((string)$r->query('phone')); $res = $phone !== '' ? F::check($phone, $r->boolean('fresh')) : null;
    $k = DB::table('fraud_checks')->selectRaw("count(*) n, count(*) filter (where risk_level in ('high','very_high')) hi, count(*) filter (where risk_level = 'unknown') un, count(*) filter (where risk_level in ('very_low','low')) lo")->first();
    return view('fraud', ['phone' => $phone, 'res' => $res, 'k' => $k, 'cfg' => F::configured(), 'primary' => Sms::secret('fc_primary') === 'smartpos' ? 'smartpos' : 'bdcourier', 'internal' => Sms::secret('fc_internal') === '1',
      'recent' => DB::table('fraud_checks')->orderByDesc('id')->limit(40)->selectRaw("phone, risk_level, risk_score, total, delivered, returned, success_rate, checked_by, created_at, substring(data::text from '\"source\": ?\"([a-z]+)\"') as src")->get()]);
  }
  public function check(Request $r) { return response()->json(F::check((string)$r->query('phone'), $r->boolean('fresh')))->header('Cache-Control', 'no-store'); }
  public function save(Request $r) {
    foreach (['fc_steadfast_key' => 'steadfast_key', 'fc_steadfast_secret' => 'steadfast_secret', 'fc_redx_token' => 'redx_token', 'fc_pathao_token' => 'pathao_token', 'fc_bdcourier_key' => 'bdcourier_key', 'fc_smartpos_key' => 'smartpos_key'] as $k => $f) if (trim((string)$r->input($f)) !== '') Sms::setSecret($k, trim((string)$r->input($f)));
    foreach (['steadfast_key', 'steadfast_secret', 'redx_token', 'pathao_token', 'bdcourier_key', 'smartpos_key'] as $f) if ($r->boolean('clear_' . $f)) Sms::setSecret('fc_' . $f, null);
    if (in_array($r->input('primary'), ['bdcourier', 'smartpos'], true)) Sms::setSecret('fc_primary', $r->input('primary'));
    Sms::setSecret('fc_internal', $r->boolean('internal') ? '1' : '0');
    \App\Support\Procure::audit('fraud.settings', 'Fraud check API settings updated');
    return redirect('/admin/fraud')->with('ok', 'Saved');
  }
}
