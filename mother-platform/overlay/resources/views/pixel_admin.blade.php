@extends('layout')@section('title','Pixel & Conversions API')
@section('body')
<h1 style="margin:0 0 4px">Pixel &amp; Conversions API</h1><div style="font-size:12px;color:#6B7280;margin-bottom:10px">Tracking installed on the store, plus the server-side Meta connection (CAPI) that sends each order straight from the server.</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div class="card"><h2>Installed on the website</h2><table><tbody>
<tr><td>Meta Pixel ID</td><td>{{ $pixel ?: '— not set' }}</td><td>{{ $pixel ? ($gtm ? 'Loaded through GTM' : 'Direct on site') : '' }}</td></tr>
<tr><td>Google Analytics 4</td><td>{{ $gtag ?: '— not set' }}</td><td></td></tr>
<tr><td>Google Tag Manager</td><td>{{ $gtm ?: 'not used' }}</td><td></td></tr>
<tr><td>Browser events</td><td colspan="2">PageView, ViewContent, AddToCart, InitiateCheckout, Purchase (with product ids, value, order id, event id)</td></tr>
<tr><td>Advanced Matching</td><td colspan="2">Purchase page sends the customer's email, phone, name, city and customer id (hashed by Meta's pixel)</td></tr>
</tbody></table><div style="font-size:12px;color:#6B7280;margin-top:6px">Pixel / GA4 / GTM IDs are edited in Site Settings.</div></div>
<div class="card"><h2>Conversions API (server-side)</h2>
<div style="font-size:12px;margin-bottom:8px">Status: <b style="color:{{ $hasToken ? '#166534' : '#B91C1C' }}">{{ $hasToken ? 'CONNECTED — every new order is also sent from the server' : 'NOT CONNECTED — add the access token' }}</b></div>
<form method="post" action="/admin/pixel/save">@csrf
<label>Access token (Events Manager → Settings → Conversions API → Generate access token). Stored encrypted, never shown again.</label><input type="password" name="token" autocomplete="off" placeholder="{{ $hasToken ? '•••••••• saved — type a new one to replace' : 'Paste token' }}">
<label>Test Events code (optional, e.g. TEST12345)</label><input type="text" name="test_code" placeholder="{{ $hasTest ? 'saved' : '' }}">
@if($hasToken)<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="clear_token" value="1" style="width:auto"> Remove saved token</label>@endif
<button>Save</button></form>
@if($hasToken)<form method="post" action="/admin/pixel/test" style="margin-top:8px">@csrf<button class="g">Send a test Purchase</button></form>@endif</div>
<div class="card"><h2>Last server events</h2><div style="overflow:auto"><table><thead><tr><th>Time</th><th>Event</th><th>Event id</th><th>Result</th></tr></thead><tbody>
@forelse($log as $l)<tr><td>{{ $l['t'] }}</td><td>{{ $l['e'] }}</td><td>{{ $l['id'] }}</td><td style="color:{{ $l['ok'] ? '#166534' : '#B91C1C' }}">{{ $l['ok'] ? 'OK' : 'Failed' }} · {{ $l['m'] }}</td></tr>@empty<tr><td colspan="4" style="color:#6B7280">Nothing sent yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
