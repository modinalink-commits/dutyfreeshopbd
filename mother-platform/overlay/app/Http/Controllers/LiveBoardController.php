<?php
namespace App\Http\Controllers;

use App\Support\Deployer;
use App\Support\GoogleSeo;
use App\Support\LiveBoard;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Live Dashboard: a read-only wall screen of everything running in the background. Settings-level permission (ModulePermission). */
class LiveBoardController extends Controller
{
  public function index() { return view('live_board'); }
  public function status() {
    try { return response()->json(LiveBoard::data())->header('Cache-Control', 'no-store'); }
    catch (\Throwable $e) { report($e); return response()->json(['error' => 'Live data could not be read'], 500); }
  }

  public function pulse() {
    try { return response()->json(LiveBoard::pulse())->header('Cache-Control', 'no-store'); }
    catch (\Throwable $e) { return response()->json(['error' => 'pulse'], 500); }
  }

  /** Google Search Console connection: the owner pastes the service-account key here once. The key is stored encrypted and never shown again. */
  public function google() { return view('live_board_google', ['email' => GoogleSeo::email(), 'property' => GoogleSeo::property(), 'g' => GoogleSeo::key() ? GoogleSeo::data() : null]); }
  public function googleSave(Request $r) {
    $d = $r->validate(['property' => 'nullable|string|max:120', 'key' => 'nullable|string|max:8000', 'act' => 'nullable|in:save,test,remove']);
    $act = $d['act'] ?? 'save';
    if ($act === 'remove') { Sms::setSecret(GoogleSeo::SECRET, null); Cache::forget('gseo.data'); Deployer::audit('Google SEO key removed', 'Search Console connection', $r->user()->name ?? null); return back()->with('ok', 'Google key removed.'); }
    if (!empty($d['property'])) DB::table('settings')->updateOrInsert(['key' => 'gsc_property'], ['value' => trim($d['property']), 'updated_at' => now(), 'created_at' => now()]);
    if ($act === 'save' && trim((string)($d['key'] ?? '')) !== '') {
      $j = json_decode(trim($d['key']), true);
      if (!is_array($j) || empty($j['client_email']) || empty($j['private_key']) || ($j['type'] ?? '') !== 'service_account') return back()->with('err', 'This is not a Google service-account key file. Paste the whole JSON file (it starts with { and contains "type": "service_account").');
      Sms::setSecret(GoogleSeo::SECRET, json_encode(['client_email' => $j['client_email'], 'private_key' => $j['private_key']]));
      Deployer::audit('Google SEO key saved', 'Service account ' . $j['client_email'], $r->user()->name ?? null);
    }
    Cache::forget('gseo.data'); Cache::forget('lb.data'); $g = GoogleSeo::key() ? GoogleSeo::data(true) : ['state' => 'none', 'msg' => 'No key saved yet'];
    return back()->with($g['state'] === 'ok' ? 'ok' : 'err', $g['state'] === 'ok' ? 'Connected. Last 7 days: ' . $g['clicks'] . ' clicks, ' . $g['impressions'] . ' impressions.' : $g['msg']);
  }
}
