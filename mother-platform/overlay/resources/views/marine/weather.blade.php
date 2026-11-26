@extends('layout')@section('title','Port Weather')
@section('body')
@php
$bst = fn($iso) => $iso ? \Carbon\Carbon::parse($iso)->setTimezone('Asia/Dhaka')->format('d M H:i') . ' BST' : '-';
$ep = fn($t) => $t ? \Carbon\Carbon::createFromTimestamp($t)->setTimezone('Asia/Dhaka')->format('d M H:i') . ' BST' : '-';
@endphp
<div style="margin-bottom:10px"><h1 style="margin:0">Port Weather</h1><div style="font-size:12px;color:#6B7280">Weather, waves and official BMD warnings for Chattogram, Mongla and Payra. All times are Bangladesh time (UTC+6). Values are model estimates, not station measurements.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The weather tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
@if($review)
<div class="card" style="background:#FFF7ED;border-color:#FDBA74;color:#7C2D12">
<b>Review delivery time</b>
<div style="font-size:13px;margin-top:4px">A new official warning covers a port we serve. Re-confirm delivery times with the local shipping agent, then mark it reviewed.</div>
@foreach($review as $w)
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px solid #FED7AA;font-size:13px">
<span style="flex:1;min-width:220px"><b>{{ implode(', ', array_map(fn($k) => $ports[$k]['name'] ?? $k, $w['ports'])) }}</b>@if($w['signal']) · Signal {{ $w['signal'] }}@endif · {{ $w['title'] }}</span>
<form method="post" action="/admin/marine/weather/warning/{{ $w['id'] }}/ack" style="margin:0">@csrf<button class="btn" style="padding:6px 12px">Delivery time reviewed</button></form>
</div>
@endforeach
</div>
@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;margin-bottom:12px">
@foreach($d['ports'] as $k => $p)
@php $c = $p['current']; $ws = $p['weather_source']; $vs = $p['wave_source']; @endphp
<div class="card" style="margin:0;display:flex;flex-direction:column;gap:6px">
<div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px"><b style="font-size:15px">{{ $p['name'] }}</b><span style="font-size:11px;color:#6B7280">{{ $p['kind'] }}</span></div>
@if($p['warnings'])@foreach($p['warnings'] as $w)<div style="font-size:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;border-radius:6px;padding:6px 8px">@if($w['signal'])<b>Signal {{ $w['signal'] }}</b> · @endif{{ $w['title'] }}<br>Issued {{ $bst($w['issued_at']) }} · <a href="{{ $w['url'] }}" target="_blank" rel="noopener" style="color:#991B1B">Original bulletin</a></div>@endforeach
@elseif($p['no_active_warning'])<div style="font-size:12px;color:#166534">No active BMD warning</div>
@else<div style="font-size:12px;color:#B45309">BMD status: update unavailable</div>@endif
<div style="font-size:13px">@if($c)<b style="font-size:20px">{{ $c['temp_c'] }} °C</b> · Wind {{ $c['wind_kn'] }} knots from {{ $c['wind_dir'] }} · Rain {{ $c['rain_mm'] === null ? 'unavailable' : $c['rain_mm'] . ' mm next hour' }}@else<b>Weather unavailable</b>@endif</div>
<div style="font-size:13px">Waves at anchorage: <b>{{ $p['wave'] ? $p['wave']['hs_m'] . ' m' : 'Unavailable' }}</b></div>
<div style="font-size:11px;color:{{ $ws['ok'] ? '#6B7280' : '#B45309' }}">MET Norway: {{ $ws['ok'] ? 'OK' : 'Update unavailable' }} · model run {{ $bst($ws['data_time']) }} · checked {{ $bst($ws['checked_at']) }}@if(!$ws['ok'] && !empty($recs[$k.'|met']['note'])) · {{ $recs[$k.'|met']['note'] }}@endif</div>
<div style="font-size:11px;color:{{ $vs['ok'] ? '#6B7280' : '#B45309' }}">Copernicus Marine: {{ $vs['ok'] ? 'OK' : 'Update unavailable' }} · step {{ $bst($vs['data_time']) }} · checked {{ $bst($vs['checked_at']) }}@if(!$vs['ok'] && !empty($recs[$k.'|wave']['note'])) · {{ $recs[$k.'|wave']['note'] }}@endif</div>
<div style="font-size:11px;color:#6B7280">Weather point {{ $p['terminal']['lat'] }}N {{ $p['terminal']['lon'] }}E (terminal) · wave point {{ $p['anchorage']['lat'] }}N {{ $p['anchorage']['lon'] }}E (anchorage)</div>
</div>
@endforeach
</div>
<div class="card" style="font-size:13px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
<span>BMD CAP feed: <b style="color:{{ $d['bmd']['ok'] ? '#166534' : '#B45309' }}">{{ $d['bmd']['ok'] ? 'Checked' : 'Update unavailable' }}</b> · last check {{ $bst($d['bmd']['checked_at']) }}@if(!empty($recs['all|cap']['note'])) · {{ $recs['all|cap']['note'] }}@endif</span>
<a href="{{ $d['bmd']['page'] }}" target="_blank" rel="noopener">BMD Marine Warning page</a>
<form method="post" action="/admin/marine/weather/refresh" style="margin:0 0 0 auto">@csrf<button class="btn g" style="padding:6px 12px">Refresh now</button></form>
</div>
<div class="card" style="background:#F8FAFC;font-size:12px;color:#475569">BMD publishes its routine marine warning as a scanned PDF on the Marine Warning page, and that bulletin is not always in the CAP feed. When BMD hoists or changes a signal for a port, add it below with the link to the original bulletin. Marine orders are not built yet, so "Review delivery time" appears on this page and on Marine Overview; it will sit beside each active order once orders exist.</div>

<h2 style="font-size:15px;margin:16px 0 8px">Active official warnings</h2>
<div class="card" style="padding:0;overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:8px 10px">Ports</th><th style="padding:8px 10px">Signal</th><th style="padding:8px 10px">Warning</th><th style="padding:8px 10px">Issued</th><th style="padding:8px 10px">Valid until</th><th style="padding:8px 10px">Source</th><th style="padding:8px 10px"></th></tr>
@forelse($warns as $w)
<tr style="border-top:1px solid #E5E7EB"><td style="padding:8px 10px">{{ $w['ports'] ? implode(', ', array_map(fn($k) => $ports[$k]['name'] ?? $k, $w['ports'])) : 'Not our ports' }}</td><td style="padding:8px 10px">{{ $w['signal'] ?? '-' }}</td><td style="padding:8px 10px"><a href="{{ $w['url'] }}" target="_blank" rel="noopener">{{ $w['title'] }}</a></td><td style="padding:8px 10px;white-space:nowrap">{{ $ep($w['issued']) }}</td><td style="padding:8px 10px;white-space:nowrap">{{ $ep($w['expires']) }}</td><td style="padding:8px 10px">{{ $w['source'] === 'cap' ? 'CAP feed' : 'Desk: ' . $w['by'] }}</td>
<td style="padding:8px 10px;text-align:right"><form method="post" action="/admin/marine/weather/warning/{{ $w['id'] }}/end" style="margin:0" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn g" style="padding:4px 10px;font-size:12px">End now</button></form></td></tr>
@empty
<tr style="border-top:1px solid #E5E7EB"><td colspan="7" style="padding:14px 10px;color:#6B7280">No active warning stored.</td></tr>
@endforelse
</table></div>

<h2 style="font-size:15px;margin:16px 0 8px">Add an official BMD warning</h2>
<form method="post" action="/admin/marine/weather/warning" class="card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:820px">@csrf
<div style="grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap;font-size:13px"><span style="color:#6B7280">Ports covered</span>@foreach($ports as $k => $p)<label style="display:inline-flex;align-items:center;gap:6px;width:auto"><input type="checkbox" name="ports[]" value="{{ $k }}" style="width:auto" @checked(in_array($k, (array)old('ports', [])))> {{ $p['name'] }}</label>@endforeach</div>
<label style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px;font-size:13px">Warning, as BMD words it<input name="title" maxlength="200" value="{{ old('title') }}" placeholder="e.g. Maritime ports advised to hoist local cautionary signal no. 3" required></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Signal number (only if the bulletin states one)<input name="signal" type="number" min="1" max="11" value="{{ old('signal') }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Link to the original bulletin (bmd.gov.bd)<input name="url" type="url" maxlength="300" value="{{ old('url') }}" placeholder="https://..." required></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Issued (Bangladesh time)<input name="issued" type="datetime-local" value="{{ old('issued') }}" required></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Valid until (Bangladesh time)<input name="expires" type="datetime-local" value="{{ old('expires') }}" required></label>
<div style="grid-column:1/-1"><button class="btn">Add warning</button></div>
</form>

@if($admin)
<form method="post" action="/admin/marine/weather" class="card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;max-width:820px">@csrf
<label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;width:auto"><input type="checkbox" name="on" value="1" @checked($on) style="width:auto"> Collect weather data in the background</label><label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;width:auto"><input type="checkbox" name="live" value="1" @checked($live) style="width:auto"> Show Port Weather in the staff menu and on Marine Overview (after approval)</label><button class="btn g" style="padding:6px 12px">Save</button>
</form>
@endif
<div style="font-size:12px;color:#6B7280">Credits shown on the site: {{ implode(' · ', $d['credits']) }}.</div>
@endif
@endsection
