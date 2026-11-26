<?php
namespace App\Http\Controllers;

use App\Support\MarineWeather as W;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Marine Supply: Port Weather. Public JSON for the marine site (stored rows only) and the marine desk page (source health, official warnings, "Review delivery time"). */
class MarineWeatherController extends Controller
{
  /** Public: latest stored weather for the three ports. Never calls an outside source. */
  public function publicWeather() {
    if (!W::ready()) return response()->json(['ports' => (object)[], 'bmd' => ['ok' => false]], 200)->header('Access-Control-Allow-Origin', MarineController::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'no-store');
    return response()->json(W::api())->header('Access-Control-Allow-Origin', MarineController::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=60');
  }

  public function index() {
    if (!W::ready()) return view('marine.weather', ['ready' => false]);
    $recs = W::recs(); $warns = W::warns(); $now = time();
    return view('marine.weather', ['ready' => true, 'on' => W::on(), 'live' => W::live(), 'd' => W::build($recs, $warns, $now), 'recs' => $recs, 'warns' => array_values(array_filter($warns, fn($w) => W::active($w, $now))), 'review' => W::review(), 'ports' => W::PORTS, 'site' => MarineController::SITE, 'admin' => \App\Support\ProfitFloor::admin()]);
  }

  public function save(Request $r) {
    if (!\App\Support\ProfitFloor::admin()) return back()->with('ok', 'Only an admin can change this.');
    DB::table('settings')->updateOrInsert(['key' => 'marine_weather_on'], ['value' => $r->input('on') ? '1' : '0', 'updated_at' => now()]);
    DB::table('settings')->updateOrInsert(['key' => 'marine_weather_live'], ['value' => $r->input('live') ? '1' : '0', 'updated_at' => now()]); Cache::forget('marine_wx_live');
    try { \App\Support\Procure::audit('marine.weather', 'Marine Port Weather: collection ' . ($r->input('on') ? 'on' : 'off') . ', staff menu ' . ($r->input('live') ? 'on' : 'off'), null, null); } catch (\Throwable $e) {}
    return redirect('/admin/marine/weather')->with('ok', 'Saved.');
  }

  /** Asks the background run to start now. MET Norway is still only asked again after its own Expires time. */
  public function refresh() {
    if (!W::ready()) return back();
    Cache::forget('marine_wx_next'); @exec('nohup nice -n 15 php ' . escapeshellarg(base_path('artisan')) . ' marine:weather --force > /dev/null 2>&1 &');
    return redirect('/admin/marine/weather')->with('ok', 'Refresh started. Open this page again in about a minute.');
  }

  /** The marine desk adds an official BMD warning by hand, always with the link to the original bulletin. */
  public function addWarning(Request $r) {
    if (!W::ready()) return back();
    $ports = array_values(array_intersect(array_keys(W::PORTS), (array)$r->input('ports', []))); $title = mb_substr(trim((string)$r->input('title')), 0, 200); $url = trim((string)$r->input('url'));
    $sig = $r->input('signal') !== null && $r->input('signal') !== '' ? (int)$r->input('signal') : null;
    $tz = 'Asia/Dhaka'; $iss = null; $exp = null;
    try { $iss = $r->input('issued') ? \Carbon\Carbon::parse((string)$r->input('issued'), $tz) : null; $exp = $r->input('expires') ? \Carbon\Carbon::parse((string)$r->input('expires'), $tz) : null; } catch (\Throwable $e) { return back()->withInput()->with('ok', 'The date and time could not be read.'); }
    $host = (string)parse_url($url, PHP_URL_HOST);
    if (!$ports) return back()->withInput()->with('ok', 'Choose at least one port.');
    if ($title === '') return back()->withInput()->with('ok', 'Write the warning as BMD words it.');
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !preg_match('/(^|\.)bmd\.gov\.bd$/i', $host) || mb_strlen($url) > 300) return back()->withInput()->with('ok', 'The bulletin link must be an https link on bmd.gov.bd.');
    if ($sig !== null && ($sig < 1 || $sig > 11)) return back()->withInput()->with('ok', 'Signal number must be 1 to 11, or empty when the bulletin states none.');
    if (!$iss || $iss->isFuture() && $iss->diffInHours(now()) > 1) return back()->withInput()->with('ok', 'Enter the time the bulletin was issued (Bangladesh time).');
    if (!$exp || $exp->lessThanOrEqualTo($iss) || $exp->diffInDays($iss, true) > 7) return back()->withInput()->with('ok', 'Enter until when the warning is valid (Bangladesh time, at most 7 days). Add it again if BMD extends it.');
    $who = (string)(auth()->user()->name ?? 'staff');
    $id = DB::table('marine_weather_warnings')->insertGetId(['source' => 'desk', 'ports' => implode(',', $ports), 'signal' => $sig, 'title' => $title, 'bulletin_url' => $url, 'issued_at' => $iss->copy()->setTimezone(config('app.timezone')), 'expires_at' => $exp->copy()->setTimezone(config('app.timezone')), 'created_by' => mb_substr($who, 0, 80), 'created_at' => now(), 'updated_at' => now()]);
    Cache::forget('marine.wx');
    try { \App\Support\Procure::audit('marine.weather.warning', 'BMD warning added for ' . implode(', ', $ports) . ': ' . $title, null, ['id' => $id, 'signal' => $sig, 'url' => $url, 'expires' => $exp->toIso8601String()]); } catch (\Throwable $e) {}
    return redirect('/admin/marine/weather')->with('ok', 'Warning added. It shows on the port cards until its end time.');
  }

  public function endWarning($id) {
    $w = DB::table('marine_weather_warnings')->where('id', (int)$id)->whereNull('ended_at')->first(); if (!$w) return back();
    DB::table('marine_weather_warnings')->where('id', $w->id)->update(['ended_at' => now(), 'ended_by' => mb_substr((string)(auth()->user()->name ?? 'staff'), 0, 80), 'updated_at' => now()]); Cache::forget('marine.wx');
    try { \App\Support\Procure::audit('marine.weather.warning', 'BMD warning ended by the desk: ' . $w->title, null, ['id' => (int)$w->id]); } catch (\Throwable $e) {}
    return redirect('/admin/marine/weather')->with('ok', 'Warning ended.');
  }

  /** "Review delivery time" done: the desk has re-confirmed delivery times with the local shipping agent for this warning. */
  public function ackWarning($id) {
    $w = DB::table('marine_weather_warnings')->where('id', (int)$id)->whereNull('ack_at')->first(); if (!$w) return back();
    DB::table('marine_weather_warnings')->where('id', $w->id)->update(['ack_at' => now(), 'ack_by' => mb_substr((string)(auth()->user()->name ?? 'staff'), 0, 80), 'updated_at' => now()]);
    try { \App\Support\Procure::audit('marine.weather.review', 'Delivery time reviewed for BMD warning: ' . $w->title, null, ['id' => (int)$w->id, 'ports' => $w->ports]); } catch (\Throwable $e) {}
    return redirect('/admin/marine/weather')->with('ok', 'Marked as reviewed.');
  }
}
