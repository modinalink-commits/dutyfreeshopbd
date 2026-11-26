@extends('layout')@section('title','Vessel Tracking')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Vessel Tracking</h1><div style="font-size:12px;color:#6B7280">Data source for the free ship tracking on <a href="{{ $site }}/track" target="_blank" rel="noopener">the marine site</a>: vessel names, details and positions collected on our own server.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:12px">
<div class="card" style="margin:0"><div style="font-size:12px;color:#6B7280">Key</div><b style="color:{{ $has ? '#166534' : '#B45309' }}">{{ $has ? 'Saved' : 'Not saved yet' }}</b></div>
<div class="card" style="margin:0"><div style="font-size:12px;color:#6B7280">Collector</div><b style="color:{{ $hb && time() - (int)$hb < 180 ? '#166534' : '#6B7280' }}">{{ $hb && time() - (int)$hb < 180 ? 'Running' : ($on && $has ? 'Starting / waiting' : 'Off') }}</b></div>
<div class="card" style="margin:0"><div style="font-size:12px;color:#6B7280">Vessels seen</div><b>{{ number_format($st['vessels']) }}</b> <span style="font-size:12px;color:#6B7280">({{ number_format($st['named']) }} with IMO)</span></div>
<div class="card" style="margin:0"><div style="font-size:12px;color:#6B7280">Last position received</div><b>{{ $st['last'] ? \Carbon\Carbon::parse($st['last'])->diffForHumans() : '-' }}</b></div>
</div>
@if(is_array($stat ?? null))<div style="font-size:12px;color:#6B7280;margin:-4px 0 12px">Bay of Bengal positions: connection opened {{ \Carbon\Carbon::createFromTimestamp($stat['connected'])->diffForHumans() }}, {{ number_format($stat['msgs']) }} vessel messages received so far.</div>@endif
@if(is_array($statw ?? null))<div style="font-size:12px;color:#6B7280;margin:-4px 0 12px">Vessel register (worldwide, names and details only): connection opened {{ \Carbon\Carbon::createFromTimestamp($statw['connected'])->diffForHumans() }}, {{ number_format($statw['msgs']) }} messages read.</div>@endif
@if($err)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#92400E;font-size:13px">Last collector message: {{ $err }}</div>@endif
<form method="post" action="/admin/marine/tracking" class="card" style="display:flex;flex-direction:column;gap:12px;max-width:560px" autocomplete="off">@csrf
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">AISStream API key<input type="password" name="key" autocomplete="new-password" placeholder="{{ $has ? 'A key is saved. Paste a new one only to replace it.' : 'Paste the key from your AISStream account page' }}"></label>
<label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;width:auto"><input type="checkbox" name="on" value="1" @checked($on || !$has) style="width:auto"> Collect vessel data (Bay of Bengal and the approaches to Chattogram, Mongla and Payra)</label>
@if($has)<label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;width:auto;color:#991B1B"><input type="checkbox" name="remove" value="1" style="width:auto"> Remove the saved key</label>@endif
<div><button class="btn">Save</button></div>
<div style="font-size:12px;color:#6B7280">The key is stored encrypted and is never shown again on this page. Collected data is public AIS information broadcast by ships.</div>
</form>
@if($has)<form method="post" action="/admin/marine/tracking-test" class="card" style="max-width:560px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">@csrf<button class="btn g">Test connection</button><span style="font-size:12px;color:#6B7280">Listens for about 25 seconds: a busy strait (checks the key) and our own area (checks coverage near Bangladesh).</span></form>@endif
@endsection
