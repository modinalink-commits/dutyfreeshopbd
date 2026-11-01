<?php
namespace App\Http\Controllers;

use App\Support\Sms;
use App\Support\Voice as V;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Marketing & Offers -> Voice Campaigns. */
class VoiceCampaignController extends Controller
{
  public function index() {
    $cs = DB::table('voice_campaigns')->orderByDesc('id')->get(); $st = []; foreach ($cs as $c) $st[$c->id] = V::stats($c->id);
    $tot = ['sel' => 0, 'att' => 0, 'ans' => 0, 'noans' => 0, 'intr' => 0, 'tr' => 0, 'used' => 0, 'rev' => 0.0, 'disc' => 0.0]; foreach ($st as $x) foreach ($tot as $k => $_) $tot[$k] += $x[$k];
    $tot['conv'] = $tot['sel'] ? round($tot['used'] * 100 / $tot['sel'], 1) : 0;
    return view('voice_campaigns', ['cs' => $cs, 'st' => $st, 'tot' => $tot, 'ready' => V::readiness(), 's' => $this->settings()]);
  }
  private function settings(): array {
    $o = []; foreach (['on' => '0', 'esl_host' => '', 'esl_port' => '8021', 'gateway' => '', 'caller_id' => '', 'dialplan_ext' => 'voice_offer', 'context' => 'public', 'agent_ext' => '101', 'dial_format' => 'local', 'ring_timeout' => '30', 'base_url' => '', 'tts_provider' => 'none', 'tts_region' => 'southeastasia', 'tts_voice' => '', 'script_ok' => '0'] as $k => $d) $o[$k] = V::set($k, $d);
    $o['has_pw'] = (bool)Sms::secret('vc_esl_pass'); $o['has_key'] = (bool)Sms::secret('vc_tts_key'); $o['hook'] = V::baseUrl() . '/webhooks/voice?k=' . V::hookToken(); return $o;
  }
  public function settingsSave(Request $r) {
    foreach (['esl_host', 'esl_port', 'gateway', 'caller_id', 'dialplan_ext', 'context', 'agent_ext', 'ring_timeout', 'base_url', 'tts_region', 'tts_voice'] as $k) V::put($k, trim((string)$r->input($k)));
    V::put('dial_format', in_array($r->input('dial_format'), ['local', 'intl', 'plus']) ? $r->input('dial_format') : 'local'); V::put('tts_provider', in_array($r->input('tts_provider'), ['none', 'azure', 'google']) ? $r->input('tts_provider') : 'none');
    V::put('on', $r->boolean('on') ? '1' : '0'); V::put('script_ok', $r->boolean('script_ok') ? '1' : '0');
    if ($r->filled('esl_pass')) Sms::setSecret('vc_esl_pass', $r->input('esl_pass')); if ($r->filled('tts_key')) Sms::setSecret('vc_tts_key', $r->input('tts_key'));
    \App\Support\Procure::audit('voice.settings', 'Voice campaign settings', null, $r->except(['_token', 'esl_pass', 'tts_key']));
    return redirect('/admin/voice')->with('ok', 'Voice settings saved');
  }
  public function form($id = null) {
    $c = $id ? DB::table('voice_campaigns')->find($id) : null; if ($id && !$c) abort(404);
    return view('voice_campaign_form', ['c' => $c, 'segs' => V::SEGMENTS, 'script' => V::SCRIPT, 'sms' => V::SMS]);
  }
  public function save(Request $r, $id = null) {
    $d = $r->validate(['name' => 'required|string|max:160', 'segment' => 'required|in:' . implode(',', array_keys(V::SEGMENTS)), 'segment_phones' => 'nullable|string', 'discount_value' => 'required|numeric|min:0.5|max:90', 'validity' => 'required|string', 'expiry_date' => 'nullable|date',
      'start_date' => 'nullable|date', 'end_date' => 'nullable|date', 'call_start_time' => ['required', 'regex:/^\d{2}:\d{2}$/'], 'call_end_time' => ['required', 'regex:/^\d{2}:\d{2}$/'], 'daily_limit' => 'required|integer|min:1|max:5000', 'retry_limit' => 'required|integer|min:1|max:5', 'retry_gap_hours' => 'required|integer|min:1|max:72',
      'cooldown_days' => 'required|integer|min:0|max:365', 'script' => 'nullable|string|max:1500', 'sms_text' => 'nullable|string|max:500', 'min_days' => 'nullable|integer|min:0', 'max_days' => 'nullable|integer|min:0', 'min_orders' => 'nullable|integer|min:0', 'category' => 'nullable|in:,vip,regular', 'audio' => 'nullable|file|max:6000']);
    if ($d['call_end_time'] <= $d['call_start_time']) return back()->withInput()->withErrors(['call_end_time' => 'Call end time must be after the start time']);
    if (($d['segment'] === 'selected') && trim((string)($d['segment_phones'] ?? '')) === '') return back()->withInput()->withErrors(['segment_phones' => 'Enter at least one phone number']);
    $custom = $d['validity'] === 'custom'; if ($custom && empty($d['expiry_date'])) return back()->withInput()->withErrors(['expiry_date' => 'Choose the offer expiry date']);
    $row = ['name' => $d['name'], 'type' => 'last_product', 'segment' => $d['segment'], 'segment_phones' => $d['segment_phones'] ?? null, 'segment_cfg' => json_encode(['min_days' => (int)($d['min_days'] ?? 0), 'max_days' => (int)($d['max_days'] ?? 0), 'min_orders' => (int)($d['min_orders'] ?? 0), 'category' => $d['category'] ?? '']),
      'discount_value' => $d['discount_value'], 'validity_days' => $custom ? 3 : (int)$d['validity'], 'expiry_date' => $custom ? $d['expiry_date'] : null, 'whole_cart' => $r->boolean('whole_cart'), 'reusable' => $r->boolean('reusable'), 'start_date' => $d['start_date'] ?? null, 'end_date' => $d['end_date'] ?? null,
      'call_start_time' => $d['call_start_time'], 'call_end_time' => $d['call_end_time'], 'daily_limit' => $d['daily_limit'], 'retry_on' => $r->boolean('retry_on'), 'retry_limit' => $d['retry_limit'], 'retry_gap_hours' => $d['retry_gap_hours'], 'cooldown_days' => $d['cooldown_days'],
      'dtmf_on' => $r->boolean('dtmf_on'), 'sms_follow' => $r->boolean('sms_follow'), 'wa_follow' => false, 'script' => trim((string)($d['script'] ?? '')) ?: null, 'sms_text' => trim((string)($d['sms_text'] ?? '')) ?: null, 'updated_at' => now()];
    if ($r->hasFile('audio')) { $f = $r->file('audio'); $ext = strtolower($f->getClientOriginalExtension()); if (!in_array($ext, ['wav', 'mp3'])) return back()->withInput()->withErrors(['audio' => 'Upload a .wav or .mp3 file']); $row['audio_path'] = 'voicecamp/' . bin2hex(random_bytes(8)) . '.' . $ext; $f->move(storage_path('app/voicecamp'), basename($row['audio_path'])); }
    if ($r->boolean('remove_audio')) $row['audio_path'] = null;
    if ($id) { DB::table('voice_campaigns')->where('id', $id)->update($row); } else { $id = DB::table('voice_campaigns')->insertGetId($row + ['status' => 'draft', 'created_by' => Auth::id(), 'created_at' => now()]); }
    \App\Support\Procure::audit('voice.campaign.save', $d['name'], null, ['id' => $id]);
    return redirect('/admin/voice/' . $id)->with('ok', 'Campaign saved');
  }
  public function show(Request $r, $id) {
    $c = DB::table('voice_campaigns')->find($id); if (!$c) abort(404); $flt = (string)$r->query('status', ''); $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r->query('date')) ? (string)$r->query('date') : '';
    $prev = null; if (in_array($c->status, ['draft', 'paused'])) { $cd = V::candidates($c, 3000); $prev = ['n' => count($cd['rows']), 'skipped' => count($cd['skipped']), 'reasons' => array_count_values($cd['skipped']), 'rows' => array_slice($cd['rows'], 0, 8)]; foreach ($prev['rows'] as &$x) $x['script'] = V::scriptFor($c, (object)['product_name' => $x['product_name'], 'discount_value' => $c->discount_value, 'name' => $x['name']]); unset($x); }
    $rows = DB::table('voice_campaign_customers')->where('campaign_id', $id)->when($flt !== '', fn($q) => $q->where('call_status', $flt))->when($day !== '', fn($q) => $q->whereRaw("(created_at at time zone 'UTC' at time zone 'Asia/Dhaka')::date = ?", [$day]))->orderByDesc('id')->limit(300)->get();
    $days = DB::table('voice_campaign_customers')->where('campaign_id', $id)->selectRaw("(created_at at time zone 'UTC' at time zone 'Asia/Dhaka')::date as d, count(*) n, count(*) filter (where call_attempts>0) called, count(*) filter (where answered_at is not null) ans, count(*) filter (where interested) intr, count(*) filter (where converted) ord")->groupByRaw('1')->orderByRaw('1 desc')->limit(30)->get();
    $att = DB::table('voice_call_attempts')->where('campaign_id', $id)->orderByDesc('id')->limit(30)->get();
    return view('voice_campaign_show', ['c' => $c, 'st' => V::stats((int)$id), 'prev' => $prev, 'rows' => $rows, 'att' => $att, 'flt' => $flt, 'day' => $day, 'days' => $days, 'ready' => V::readiness(), 'sample' => $prev['rows'][0] ?? null]);
  }
  public function action(Request $r, $id) {
    $c = DB::table('voice_campaigns')->find($id); if (!$c) abort(404); $a = (string)$r->input('a');
    if ($a === 'activate' || $a === 'resume') {
      if (!V::on()) return back()->with('err', 'Voice campaigns are switched OFF in Voice settings. Turn them on first.');
      if ($a === 'activate' && !$c->activated_at) { $n = V::enroll($c); if ($n === 0 && !DB::table('voice_campaign_customers')->where('campaign_id', $id)->exists()) return back()->with('err', 'No eligible customers found for this campaign.'); }
      DB::table('voice_campaigns')->where('id', $id)->update(['status' => 'active', 'activated_at' => $c->activated_at ?: now(), 'updated_at' => now()]); $m = 'Campaign is active';
    } elseif ($a === 'pause') { DB::table('voice_campaigns')->where('id', $id)->update(['status' => 'paused', 'updated_at' => now()]); $m = 'Campaign paused'; }
    elseif ($a === 'stop') { DB::table('voice_campaigns')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]); DB::table('voice_campaign_customers')->where('campaign_id', $id)->where('call_status', 'pending')->update(['offer_status' => 'void', 'updated_at' => now()]); $m = 'Campaign stopped'; }
    elseif ($a === 'delete' && !$c->activated_at) { DB::table('voice_campaigns')->where('id', $id)->delete(); return redirect('/admin/voice')->with('ok', 'Draft deleted'); }
    else return back()->with('err', 'Action not available');
    \App\Support\Procure::audit('voice.campaign.' . $a, $c->name, null, ['id' => $id]); return back()->with('ok', $m);
  }
  public function test(Request $r, $id) {
    $c = DB::table('voice_campaigns')->find($id); if (!$c) abort(404); [$ok, $m] = V::testCall($c, (string)$r->input('phone'), $r->input('product') ?: null);
    return back()->with($ok ? 'ok' : 'err', $m);
  }
  public function prefs(Request $r, $cid) {
    DB::table('customers')->where('id', $cid)->update(['promo_sms' => $r->boolean('promo_sms'), 'promo_whatsapp' => $r->boolean('promo_whatsapp'), 'promo_call' => $r->boolean('promo_call'), 'updated_at' => now()]);
    \App\Support\Procure::audit('customer.promo_prefs', 'Customer #' . $cid, null, $r->only(['promo_sms', 'promo_whatsapp', 'promo_call'])); return back()->with('ok', 'Communication preferences saved');
  }
  /** Public: PBX script reports answered / digit / hangup. */
  public function hook(Request $r) {
    if (!hash_equals(V::hookToken(), (string)$r->query('k', $r->input('k')))) return response()->json(['ok' => false], 403);
    return response()->json(V::hook($r->all()));
  }
  /** Public: the PBX downloads the personalised audio for the call. */
  public function audio(Request $r, $id) {
    if (!hash_equals(V::audioToken((int)$id), (string)$r->query('t'))) abort(403); $f = V::audioFile((int)$id); if (!$f) abort(404);
    return response()->file($f, ['Content-Type' => str_ends_with($f, '.mp3') ? 'audio/mpeg' : 'audio/wav', 'Cache-Control' => 'private, max-age=600']);
  }
}
