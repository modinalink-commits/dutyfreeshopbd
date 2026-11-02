@extends('layout')@section('title','Courier API (Carrybee)')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Courier API (Carrybee)</h1><span style="font-size:13px;color:#6B7280">Send orders to Carrybee with the customer's COD amount; the consignment number and delivery status stay in sync here.</span></div>
<a class="btn g" href="/admin/courier-dashboard">Courier Dashboard</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">
<div class="card k" style="margin:0"><span style="font-size:13px">Carrybee</span><b style="font-size:22px">{{ $ready ? 'Credentials saved' : 'Not set' }}</b><span style="color:#FF5A00;font-weight:600">{{ strtoupper($env) }} mode</span></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Pickup store ID</span><b style="font-size:22px">{{ $storeId ?: '—' }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Orders sent</span><b style="font-size:26px">{{ number_format($sent) }}</b></div></div>
<form class="card" method="post" action="/admin/carrybee" style="max-width:640px">@csrf
<b>Carrybee credentials</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">In the Carrybee merchant panel open API Integration &rarr; API Credentials and copy Client ID, Client Secret and Client Context (Production, or Sandbox for tests). They are stored encrypted and never shown again; leave a field blank to keep the saved value.</p>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="enabled" value="1" style="width:auto" checked> Carrybee enabled</label><label>Mode</label><select name="env"><option value="live" {{ $env==='live'?'selected':'' }}>Live (real parcels)</option><option value="sandbox" {{ $env==='sandbox'?'selected':'' }}>Sandbox (test only)</option></select>
<label>Client ID</label><input name="client_id" value="{{ $cid }}" autocomplete="off">
<label>Client Secret {{ $hasSecret ? '(saved, leave blank to keep)' : '' }}</label><input type="password" name="client_secret" autocomplete="new-password" placeholder="{{ $hasSecret ? '••••••••••••' : 'paste client secret' }}">
<label>Client Context</label><input name="client_context" value="{{ $ctx }}" autocomplete="off">
<label>Pickup store ID (filled automatically after Connect)</label><input name="store_id" value="{{ $storeId }}">
<label>Default parcel weight (kg)</label><input name="weight" value="{{ $weight }}">
<label>Webhook secret {{ $hasHook ? '(saved, leave blank to keep)' : '(optional)' }}</label><input type="password" name="webhook_secret" autocomplete="new-password" placeholder="same value you set on Carrybee's Webhook Integration page">
<div style="margin-top:14px"><button>Save</button></div></form>
<div class="card"><b>Automatic status updates</b><p style="font-size:12px;color:#6B7280;margin:4px 0 0">Statuses refresh automatically every 10 minutes while the dashboard is in use. For instant updates add this address on Carrybee's Webhook Integration page: <code>https://dutyfreeshopbd.com/webhooks/carrybee</code> (use the same secret as above). Delivered / Returned / Cancelled move the order to the same stage automatically.</p></div>
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><form method="post" action="/admin/carrybee/connect" style="margin:0">@csrf<button>Connect &amp; load stores</button></form><span style="font-size:12px;color:#6B7280">Tests the login and lists your Carrybee pickup stores.</span></div>
@if($stores)<div class="card" style="overflow-x:auto"><b>Your Carrybee stores</b><table><thead><tr><th>ID</th><th>Name</th><th>Approved</th><th>Default pickup</th></tr></thead><tbody>@foreach($stores as $s)<tr><td>{{ $s['id'] }}</td><td>{{ $s['name'] }}</td><td>{{ !empty($s['is_approved']) ? 'Yes' : 'No' }}</td><td>{{ !empty($s['is_default_pickup_store']) ? 'Yes' : '' }}</td></tr>@endforeach</tbody></table><p style="font-size:12px;color:#6B7280">To use a different store, type its ID above and press Save.</p></div>@endif
<div class="card" style="overflow-x:auto"><b>Latest parcels sent</b><table><thead><tr><th>Sent</th><th>Invoice</th><th>Customer</th><th>Consignment</th><th>Status</th><th>Fee</th><th></th></tr></thead><tbody>
@forelse($recent as $m)<tr><td>{{ \Carbon\Carbon::parse($m->courier_sent_at)->timezone('Asia/Dhaka')->format('d M H:i') }}</td><td><a href="/admin/orders/{{ $m->id }}">{{ $m->invoice }}</a></td><td>{{ $m->customer_name }}</td><td>{{ $m->consignment_id }}</td><td>{{ $m->courier_status }}</td><td>{{ $m->courier_fee !== null ? '৳'.number_format($m->courier_fee) : '—' }}</td><td><form method="post" action="/admin/orders/{{ $m->id }}/carrybee-refresh" style="margin:0">@csrf<button class="g">Refresh</button></form></td></tr>@empty<tr><td colspan="7">No parcels sent yet.</td></tr>@endforelse</tbody></table></div>
@endsection
