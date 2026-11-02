@extends('layout')@section('title','Courier API (Steadfast)')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Courier API (Steadfast)</h1><span style="font-size:13px;color:#6B7280">Send orders to Steadfast with the customer's COD amount; the consignment number and delivery status stay in sync here.</span></div>
<a class="btn g" href="/admin/courier-dashboard">Courier Dashboard</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">
<div class="card k" style="margin:0"><span style="font-size:13px">Steadfast keys</span><b style="font-size:22px">{{ $ready ? 'Saved' : 'Not set' }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Orders sent</span><b style="font-size:26px">{{ number_format($sent) }}</b></div></div>
<form class="card" method="post" action="/admin/steadfast" style="max-width:640px">@csrf
<b>Steadfast settings</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">The Api-Key and Secret-Key are the same ones saved on the <a href="/admin/fraud">Fraud Check</a> page ({{ $ready ? 'saved' : 'not saved yet' }}). Steadfast must have approved your ownership (KYC) before the API works.</p>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="enabled" value="1" style="width:auto" checked> Steadfast enabled</label>
<label>Webhook token {{ $hasHook ? '(saved, leave blank to keep)' : '(optional)' }}</label><input type="password" name="webhook_secret" autocomplete="new-password" placeholder="any secret word; give the same to Steadfast as Bearer token">
<div style="margin-top:14px"><button>Save</button></div></form>
<div class="card"><b>Automatic status updates</b><p style="font-size:12px;color:#6B7280;margin:4px 0 0">Statuses refresh automatically every 10 minutes. For instant updates give Steadfast this callback URL and the token above as Bearer token: <code>https://dutyfreeshopbd.com/webhooks/steadfast</code>.</p></div>
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><form method="post" action="/admin/steadfast/connect" style="margin:0">@csrf<button>Test connection</button></form><span style="font-size:12px;color:#6B7280">Checks the keys and shows your Steadfast balance.</span></div>
<div class="card" style="overflow-x:auto"><b>Latest parcels sent</b><table><thead><tr><th>Sent</th><th>Invoice</th><th>Customer</th><th>Consignment</th><th>Status</th><th>Fee</th><th></th></tr></thead><tbody>
@forelse($recent as $m)<tr><td>{{ \Carbon\Carbon::parse($m->courier_sent_at)->timezone('Asia/Dhaka')->format('d M H:i') }}</td><td><a href="/admin/orders/{{ $m->id }}">{{ $m->invoice }}</a></td><td>{{ $m->customer_name }}</td><td>{{ $m->consignment_id }}</td><td>{{ $m->courier_status }}</td><td>{{ $m->courier_fee !== null ? '৳'.number_format($m->courier_fee) : '—' }}</td><td><form method="post" action="/admin/orders/{{ $m->id }}/steadfast-refresh" style="margin:0">@csrf<button class="g">Refresh</button></form></td></tr>@empty<tr><td colspan="7">No parcels sent yet.</td></tr>@endforelse</tbody></table></div>
@endsection
