@extends('layout')@section('title','Courier API (Pathao)')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Courier API (Pathao)</h1><span style="font-size:13px;color:#6B7280">Send orders to Pathao in one click, with the customer's COD amount, and keep the consignment number and delivery status here.</span></div>
<a class="btn g" href="/admin/m/couriers">Courier list</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">
<div class="card k" style="margin:0"><span style="font-size:13px">Pathao</span><b style="font-size:22px">{{ $ready ? 'Credentials saved' : 'Not set' }}</b><span style="color:#FF5A00;font-weight:600">{{ strtoupper($env) }} mode</span></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Pickup store ID</span><b style="font-size:22px">{{ $storeId ?: '—' }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Orders sent</span><b style="font-size:26px">{{ number_format($sent) }}</b></div></div>
<form class="card" method="post" action="/admin/courier" style="max-width:640px">@csrf
<b>Pathao credentials</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">In the Pathao merchant panel open Developer's API: copy Client ID and Client Secret; use the email and password you log in with. They are stored encrypted and never shown again; leave a field blank to keep the saved value. Use Sandbox only for tests.</p>
<label>Mode</label><select name="env"><option value="live" {{ $env==='live'?'selected':'' }}>Live (real parcels)</option><option value="sandbox" {{ $env==='sandbox'?'selected':'' }}>Sandbox (test only)</option></select>
<label>Client ID</label><input name="client_id" value="{{ $cid }}" autocomplete="off">
<label>Client Secret {{ $hasSecret ? '(saved, leave blank to keep)' : '' }}</label><input type="password" name="client_secret" autocomplete="new-password" placeholder="{{ $hasSecret ? '••••••••••••' : 'paste client secret' }}">
<label>Pathao login email</label><input name="username" value="{{ $user }}" autocomplete="off">
<label>Pathao password {{ $hasPass ? '(saved, leave blank to keep)' : '' }}</label><input type="password" name="password" autocomplete="new-password" placeholder="{{ $hasPass ? '••••••••••••' : 'Pathao password' }}">
<label>Pickup store ID (filled automatically after Connect)</label><input name="store_id" value="{{ $storeId }}" inputmode="numeric">
<label>Default parcel weight (kg, 0.5 to 10)</label><input name="weight" value="{{ $weight }}">
<div style="margin-top:14px"><button>Save</button></div></form>
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><form method="post" action="/admin/courier/connect" style="margin:0">@csrf<button>Connect &amp; load stores</button></form><span style="font-size:12px;color:#6B7280">Tests the login and lists your Pathao pickup stores.</span></div>
@if($stores)<div class="card" style="overflow-x:auto"><b>Your Pathao stores</b><table><thead><tr><th>ID</th><th>Name</th><th>Active</th><th>Default</th></tr></thead><tbody>@foreach($stores as $s)<tr><td>{{ $s['store_id'] }}</td><td>{{ $s['store_name'] }}</td><td>{{ !empty($s['is_active']) ? 'Yes' : 'No' }}</td><td>{{ !empty($s['is_default_store']) ? 'Yes' : '' }}</td></tr>@endforeach</tbody></table><p style="font-size:12px;color:#6B7280">To use a different store, type its ID above and press Save.</p></div>@endif
<div class="card" style="overflow-x:auto"><b>Latest parcels sent</b><table><thead><tr><th>Sent</th><th>Invoice</th><th>Customer</th><th>Consignment</th><th>Status</th><th>Fee</th><th></th></tr></thead><tbody>
@forelse($recent as $m)<tr><td>{{ \Carbon\Carbon::parse($m->courier_sent_at)->timezone('Asia/Dhaka')->format('d M H:i') }}</td><td><a href="/admin/orders/{{ $m->id }}">{{ $m->invoice }}</a></td><td>{{ $m->customer_name }}</td><td>{{ $m->consignment_id }}</td><td>{{ $m->courier_status }}</td><td>{{ $m->courier_fee !== null ? '৳'.number_format($m->courier_fee) : '—' }}</td><td><form method="post" action="/admin/orders/{{ $m->id }}/courier-refresh" style="margin:0">@csrf<button class="g">Refresh</button></form></td></tr>@empty<tr><td colspan="7">No parcels sent yet.</td></tr>@endforelse</tbody></table></div>
@endsection
