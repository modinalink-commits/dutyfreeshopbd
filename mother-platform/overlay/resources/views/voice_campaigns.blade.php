@extends('layout')@section('title','Voice Campaigns')
@section('body')
@include('settings_ui')
@php $T = fn($v) => number_format($v); $pill = ['draft'=>['#F3F4F6','#4B5563'],'active'=>['#DCFCE7','#166534'],'paused'=>['#FEF3C7','#92400E'],'completed'=>['#E0E7FF','#3730A3']]; @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Voice Campaigns</h1><div style="font-size:12px;color:#6B7280">Personalised promotional calls — last ordered product, scheduled voice offer, conversion tracking</div></div>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $s['on']==='1' ? '#DCFCE7' : '#FEE2E2' }};color:{{ $s['on']==='1' ? '#166534' : '#B91C1C' }}">{{ $s['on']==='1' ? 'CALLING ON' : 'CALLING OFF' }}</span>
<button type="button" class="sbtn" data-sdr="vcset">⚙ Settings</button><a class="btn" href="/admin/voice/new" style="text-decoration:none">+ New Campaign</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('err'))<div class="card" style="color:#B91C1C">{{ session('err') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(125px,1fr));gap:10px;margin-bottom:12px">
@foreach([['Customers Selected',$T($tot['sel'])],['Calls Attempted',$T($tot['att'])],['Calls Answered',$T($tot['ans'])],['No Answer',$T($tot['noans'])],['Interested',$T($tot['intr'])],['Agent Transfers',$T($tot['tr'])],['Discounts Used',$T($tot['used'])],['Orders Generated',$T($tot['used'])],['Revenue Generated','৳'.$T($tot['rev'])],['Conversion Rate',$tot['conv'].'%']] as [$l,$v])<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:19px">{{ $v }}</b></div>@endforeach</div>
<div class="card"><h2>Campaigns</h2><div style="overflow-x:auto"><table style="width:100%;font-size:12.5px"><tr style="text-align:left;color:#6B7280"><th>Name</th><th>Audience</th><th>Offer</th><th>Hours</th><th>Selected</th><th>Answered</th><th>Orders</th><th>Conv.</th><th>Status</th></tr>
@forelse($cs as $c)@php $x=$st[$c->id]; $pc=$pill[$c->status]??$pill['draft']; @endphp<tr><td><a href="/admin/voice/{{ $c->id }}"><b>{{ $c->name }}</b></a></td><td>{{ \App\Support\Voice::SEGMENTS[$c->segment] ?? $c->segment }}</td><td>{{ \App\Support\Voice::pct((float)$c->discount_value) }}% last product</td><td>{{ $c->call_start_time }}–{{ $c->call_end_time }}</td><td>{{ $x['sel'] }}</td><td>{{ $x['ans'] }}</td><td>{{ $x['used'] }}</td><td>{{ $x['conv'] }}%</td><td><span style="font-size:11px;font-weight:700;border-radius:999px;padding:3px 10px;background:{{ $pc[0] }};color:{{ $pc[1] }}">{{ strtoupper($c->status) }}</span></td></tr>
@empty<tr><td colspan="9" style="color:#6B7280;padding:14px 0">No campaign yet. Create one: Last Ordered Product → 5% offer → scheduled call.</td></tr>@endforelse</table></div></div>
<div class="card"><h2>Readiness</h2>@foreach($ready as [$l,$ok,$h])<div style="font-size:12.5px;padding:3px 0">{!! $ok ? '<b style="color:#166534">✔</b>' : '<b style="color:#B91C1C">✘</b>' !!} {{ $l }} <span style="color:#6B7280">— {{ $ok ? 'ready' : $h }}</span></div>@endforeach
<div style="font-size:11.5px;color:#6B7280;margin-top:6px">Automated calls are placed only when every item above is ready and Calling is ON. Until then campaigns can be prepared, previewed and activated safely — nothing is dialled.</div></div>
<div class="sdr" id="vcset"><div class="sdr-p"><div class="sdr-h"><b>Voice campaign settings</b><button type="button" data-sdx aria-label="Close">×</button></div><div class="sdr-b"><div class="card"><h2>Voice settings</h2><form method="post" action="/admin/voice/settings" enctype="multipart/form-data">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;align-items:end">
<div><label>Automated calling</label><select name="on"><option value="1" @selected($s['on']==='1')>ON</option><option value="0" @selected($s['on']!=='1')>OFF</option></select></div>
<div><label>PBX host (Event Socket)</label><input name="esl_host" value="{{ $s['esl_host'] }}" placeholder="childcity.pbx.com.bd"></div>
<div><label>Event Socket port</label><input name="esl_port" value="{{ $s['esl_port'] }}"></div>
<div><label>Event Socket password {{ $s['has_pw'] ? '(saved)' : '' }}</label><input type="password" name="esl_pass" autocomplete="new-password" placeholder="{{ $s['has_pw'] ? '•••••• leave blank to keep' : '' }}"></div>
<div><label>Outbound gateway name</label><input name="gateway" value="{{ $s['gateway'] }}" placeholder="e.g. mobile_gw"></div>
<div><label>Caller ID shown</label><input name="caller_id" value="{{ $s['caller_id'] }}" placeholder="{{ \App\Support\Biz::hotline() }}"></div>
<div><label>Number format to dial</label><select name="dial_format"><option value="local" @selected($s['dial_format']==='local')>01XXXXXXXXX</option><option value="intl" @selected($s['dial_format']==='intl')>8801XXXXXXXXX</option><option value="plus" @selected($s['dial_format']==='plus')>+8801XXXXXXXXX</option></select></div>
<div><label><input type="checkbox" name="direct" value="1" @checked(($s['direct'] ?? '0')==='1')> Direct play (no PBX script): the PBX plays the Google-voice audio straight into the call. No key presses, no dialplan needed.</label></div><div><label>Dialplan extension</label><input name="dialplan_ext" value="{{ $s['dialplan_ext'] }}"></div>
<div><label>Dialplan context</label><input name="context" value="{{ $s['context'] }}"></div>
<div><label>Agent extension (key 2)</label><input name="agent_ext" value="{{ $s['agent_ext'] }}"></div>
<div><label>Ring timeout (sec)</label><input name="ring_timeout" value="{{ $s['ring_timeout'] }}"></div>
<div><label>PBX recordings folder (voices saved in the PBX panel)</label><input name="pbx_rec_dir" value="{{ $s['pbx_rec_dir'] }}" placeholder="/var/lib/freeswitch/recordings/childcity.pbx.com.bd"></div>
<div><label>Public site URL (PBX fetches audio)</label><input name="base_url" value="{{ $s['base_url'] }}" placeholder="{{ config('app.url') }}"></div>
<div><label>Bangla voice engine</label><select name="tts_provider"><option value="none" @selected($s['tts_provider']==='none')>None (upload audio)</option><option value="azure" @selected($s['tts_provider']==='azure')>Microsoft Azure Speech</option><option value="google" @selected($s['tts_provider']==='google')>Google Cloud Text-to-Speech</option></select></div>
<div><label>Voice engine key {{ $s['has_key'] ? '(saved)' : '' }}</label><input type="password" name="tts_key" autocomplete="new-password" placeholder="{{ $s['has_key'] ? '•••••• leave blank to keep' : '' }}"></div>
<div><label>Azure region</label><input name="tts_region" value="{{ $s['tts_region'] }}"></div>
<div><label>Voice name</label><input name="tts_voice" value="{{ $s['tts_voice'] }}" placeholder="bn-BD-NabanitaNeural"></div>
<div><label><input type="checkbox" name="script_ok" value="1" @checked($s['script_ok']==='1')> PBX script installed</label></div></div>
<p><button>Save settings</button></p></form>
<details style="margin-top:8px"><summary style="cursor:pointer;font-weight:600;font-size:12.5px">PBX one-time setup (voice_offer.lua)</summary>
<div style="font-size:12px;color:#6B7280;margin:6px 0">1) Allow this server to reach the Event Socket (port {{ $s['esl_port'] }}). 2) FusionPBX → Advanced → Script Manager: create <code>voice_offer.lua</code> with the code below. 3) Dialplan: destination <code>^voice_offer$</code> → action <code>lua voice_offer.lua</code>. 4) Tick "PBX script installed" above.<br>Webhook used by the script: <code>{{ $s['hook'] }}</code></div>
<pre style="background:#0B1220;color:#E5E7EB;padding:10px;border-radius:8px;font-size:11.5px;overflow:auto;white-space:pre">{{ file_get_contents(resource_path('views/voice_offer_lua.txt')) }}</pre></details></div>
</div></div></div>
@endsection
