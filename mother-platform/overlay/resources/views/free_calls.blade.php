@extends('layout')@section('title','Free Calls')
@section('body')
@include('settings_ui')
@php $mm = fn($n) => sprintf('%02d:%02d', intdiv((int)$n, 60), (int)$n % 60); @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Customer Free Call</h1><div style="font-size:12px;color:#6B7280">Website visitors call DutyFreeBD from the browser (WebRTC) — no phone charge for the customer.</div></div>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $s['on'] ? '#DCFCE7' : '#FEE2E2' }};color:{{ $s['on'] ? '#166534' : '#B91C1C' }}">{{ $s['on'] ? 'SERVICE ON' : 'SERVICE OFF' }}</span>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $s['turn_ready'] ? '#DCFCE7' : '#FEF3C7' }};color:{{ $s['turn_ready'] ? '#166534' : '#92400E' }}" title="TURN relay lets calls work on mobile data">TURN: {{ $s['turn_ready'] ? 'READY' : ($s['turn_urls'] === '' ? 'NOT SET' : 'SECRET MISSING') }}</span>
<form method="post" action="/admin/free-calls/toggle" style="margin:0">@csrf<button class="g" style="height:32px;font-weight:700" title="Master switch for customers">{{ $s['on'] ? 'Turn OFF' : 'Turn ON' }}</button></form>
<a href="/admin/free-calls/history" style="text-decoration:none;display:inline-block;padding:6px 12px;border:1px solid #E5E7EB;border-radius:8px;font-size:12px;font-weight:600;background:#fff;color:inherit">Call history</a>
<button type="button" class="sbtn" data-sdr="fcset">⚙ Settings</button></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(!$s['turn_ready'])<div class="card" style="border-color:#FDBA74;background:#FFF7ED;font-size:12.5px">⚠ TURN relay is not configured. Calls work on most Wi-Fi/broadband, but many mobile-data (CGNAT) connections will fail without a TURN server. Add it in Settings.</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:12px">
@foreach([['Agents Available',$k['avail']],['Agents Busy',$k['busy']],['Waiting Now',$k['waiting']],['Active Calls',$k['active']],['Calls Today',$k['today']],['Missed Today',$k['missed']],['Avg Wait',$mm($k['avgwait'])],['Avg Duration',$mm($k['avgdur'])]] as [$l,$v])<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:20px">{{ $v }}</b></div>@endforeach
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px">
<div class="card"><h2>Agents</h2>
<table style="width:100%;font-size:13px"><tr><th align="left">Agent</th><th align="left">Status</th><th align="left">Last seen</th></tr>
@forelse($agents as $a)<tr><td>{{ $a->name }}</td><td><span style="font-weight:700;color:{{ $a->eff==='available' ? '#166534' : ($a->eff==='busy' ? '#B45309' : '#6B7280') }}">● {{ ucfirst($a->eff) }}</span></td><td style="color:#6B7280">{{ $a->last_seen_at ? \Carbon\Carbon::parse($a->last_seen_at)->diffForHumans() : '—' }}</td></tr>
@empty<tr><td colspan="3" style="color:#6B7280">No agent has opened the admin yet. Set your status with the "Free Call" pill at the bottom-left of any admin page.</td></tr>@endforelse</table></div>
<div class="card"><h2>Queue ({{ $queue->count() }})</h2>
<table style="width:100%;font-size:13px"><tr><th align="left">#</th><th align="left">Customer</th><th align="left">Waiting</th><th align="left">Status</th></tr>
@forelse($queue as $i => $c)<tr><td>{{ $i+1 }}</td><td>{{ $c['name'] ?: 'Guest' }}<div style="font-size:11.5px;color:#6B7280">{{ $c['mobile'] }}</div></td><td>{{ $mm($c['waiting']) }}</td><td>{{ ucfirst($c['status']) }}</td></tr>
@empty<tr><td colspan="4" style="color:#6B7280">Queue is empty.</td></tr>@endforelse</table></div>
<div class="card"><h2>Active calls ({{ $active->count() }})</h2>
<table style="width:100%;font-size:13px"><tr><th align="left">Customer</th><th align="left">Agent</th><th align="left">Since</th></tr>
@forelse($active as $c)<tr><td>{{ $c->guest_name ?: 'Guest' }}<div style="font-size:11.5px;color:#6B7280">{{ $c->mobile }}</div></td><td>{{ $c->agent }}</td><td>{{ $mm(max(0, time() - strtotime(($c->connected_at ?: $c->answered_at) . ' UTC'))) }}</td></tr>
@empty<tr><td colspan="3" style="color:#6B7280">No active call.</td></tr>@endforelse</table></div>
</div>
<p style="font-size:12px;color:#6B7280;margin-top:10px">Staff with this admin page open and status <b>Available</b> receive the popup. Calls are offered to the longest-waiting available agent, one at a time, with a {{ $s['ring_timeout'] }}s ring timeout, then to the next. This page refreshes every few seconds.</p>
<div class="sdr" id="fcset"><div class="sdr-p"><div class="sdr-h"><b>Free Call settings</b><button type="button" data-sdx aria-label="Close">×</button></div><div class="sdr-b"><div class="card"><h2>Settings</h2>
<form method="post" action="/admin/free-calls/settings">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;align-items:end">
<div><label>Free Call service</label><select name="on"><option value="1" @selected($s['on'])>ON</option><option value="0" @selected(!$s['on'])>OFF</option></select></div>
<div><label>Ring timeout (seconds)</label><input type="number" min="5" max="60" name="ring_timeout" value="{{ $s['ring_timeout'] }}"></div>
<div><label>Max queue size</label><input type="number" min="1" max="200" name="max_queue" value="{{ $s['max_queue'] }}"></div>
<div><label>Disconnect detection (seconds)</label><input type="number" min="8" max="120" name="heartbeat" value="{{ $s['heartbeat'] }}"></div>
<div><label>Operating hours</label><select name="hours_on"><option value="0" @selected($s['hours_on']!=='1')>Always (agents decide)</option><option value="1" @selected($s['hours_on']==='1')>Only between…</option></select></div>
<div><label>From (Dhaka)</label><input type="time" name="hours_from" value="{{ $s['hours_from'] }}"></div>
<div><label>To (Dhaka)</label><input type="time" name="hours_to" value="{{ $s['hours_to'] }}"></div></div>
<label style="margin-top:12px">STUN servers (comma separated)</label><input name="stun" value="{{ $s['stun'] }}">
<label>TURN server URLs (comma separated, e.g. turn:turn.dutyfreebd.com:3478?transport=udp,turns:turn.dutyfreebd.com:5349)</label><input name="turn_urls" value="{{ $s['turn_urls'] }}" placeholder="turn:…">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:6px">
<div><label>TURN shared secret (time-limited credentials, recommended){!! $s['has_secret'] ? ' — <b style="color:#166534">saved</b>' : '' !!}</label><input type="password" name="turn_secret" autocomplete="new-password" placeholder="{{ $s['has_secret'] ? '•••••• (leave blank to keep)' : 'coturn static-auth-secret' }}"></div>
<div><label>…or static TURN username{!! $s['has_user'] ? ' — <b style="color:#166534">saved</b>' : '' !!}</label><input name="turn_user" autocomplete="off" placeholder="{{ $s['has_user'] ? '•••• (leave blank to keep)' : '' }}"></div>
<div><label>…and static password</label><input type="password" name="turn_cred" autocomplete="new-password" placeholder="leave blank to keep"></div></div>
<div style="display:flex;gap:14px;font-size:12.5px;margin-top:8px"><label style="display:flex;gap:5px;align-items:center"><input type="checkbox" name="clear_turn_secret" value="1">Clear secret</label><label style="display:flex;gap:5px;align-items:center"><input type="checkbox" name="clear_turn_user" value="1">Clear static user</label><label style="display:flex;gap:5px;align-items:center"><input type="checkbox" name="clear_turn_cred" value="1">Clear static password</label></div>
<div style="margin-top:10px"><button>Save settings</button></div></form>
<p style="font-size:12px;color:#6B7280;margin:10px 0 0">TURN credentials are never sent to the browser as permanent secrets: with a shared secret, every call gets its own credential that expires in 2 hours. While the service is OFF, only signed-in staff see the Free Call button (preview).</p></div></div></div></div>
<script>setTimeout(function(){if(!document.querySelector('.sdr.on')&&!document.querySelector('.fca-c.on'))location.reload()},6000)</script>
@endsection
