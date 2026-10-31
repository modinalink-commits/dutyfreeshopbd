@extends('layout')@section('title','SMS Setup')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">SMS Setup (BulkSMSBD)</h1><span style="font-size:13px;color:#6B7280">Order confirmation SMS to customers and publish / unpublish alerts to the owner and managers.</span></div>
<span style="display:inline-flex;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download log: <a target="_blank" href="/admin/m/sms-log/export/pdf">PDF</a> · <a href="/admin/m/sms-log/export/xls">Excel</a> · <a href="/admin/m/sms-log/export/doc">Word</a> · <a href="/admin/m/sms-log/export/csv">CSV</a></span><a class="btn g" href="/admin/m/sms-log">SMS log</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">
<div class="card k" style="margin:0"><span style="font-size:13px">Gateway</span><b style="font-size:22px">{{ $ready ? 'Connected' : 'Not set' }}</b><span style="color:#FF5A00;font-weight:600">{{ $ready ? 'ready to send' : 'add key + sender' }}</span></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Sent</span><b style="font-size:26px">{{ number_format($counts['sent'] ?? 0) }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Queued</span><b style="font-size:26px">{{ number_format($counts['queued'] ?? 0) }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Failed</span><b style="font-size:26px">{{ number_format($counts['failed'] ?? 0) }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Balance</span><b style="font-size:22px">{{ $balance ?? '—' }}</b></div></div>
<form class="card" method="post" action="/admin/sms" style="max-width:640px">@csrf
<b>Gateway</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">Find both in your BulkSMSBD dashboard (API key under API settings, Sender ID under Sender ID management). The key is stored encrypted and is never shown again; leave it blank to keep the saved one. In the BulkSMSBD dashboard, turn IP whitelisting off or add this server's IP, otherwise the gateway answers 1032.</p>
<label>API key {{ $hasKey ? '(saved, leave blank to keep)' : '' }}</label><input type="password" name="api_key" autocomplete="new-password" placeholder="{{ $hasKey ? '••••••••••••' : 'paste API key' }}">
<label>Sender ID</label><input name="sender_id" value="{{ $sender }}" maxlength="30" placeholder="approved sender ID">
<label>Owner phone (publish / unpublish alerts)</label><input name="owner_phone" value="{{ $set['owner_phone'] ?? '' }}" placeholder="01XXXXXXXXX">
<label>Manager phones (comma separated)</label><input name="manager_phones" value="{{ $set['manager_phones'] ?? '' }}" placeholder="01XXXXXXXXX, 01XXXXXXXXX">
<label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="order_confirm" value="1" style="width:auto" {{ ($set['sms_order_confirm'] ?? '1') === '1' ? 'checked' : '' }}> Send an order confirmation SMS to the customer</label>
<div style="margin-top:14px"><button>Save</button></div></form>
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
<form method="post" action="/admin/sms/test" style="display:flex;gap:8px;align-items:flex-end;margin:0">@csrf<div><label>Send a test SMS to</label><input name="to" placeholder="01XXXXXXXXX" required></div><button>Send test</button></form>
<form method="post" action="/admin/sms/balance" style="margin:0">@csrf<button class="g">Check balance</button></form>
<form method="post" action="/admin/sms/flush" style="margin:0">@csrf<button class="g">Send queued now</button></form></div>
<div class="card" style="overflow-x:auto"><b>Latest messages</b><table><thead><tr><th>Time</th><th>To</th><th>Event</th><th>Status</th><th>Gateway answer</th></tr></thead><tbody>
@forelse($recent as $m)<tr><td>{{ \Carbon\Carbon::parse($m->created_at)->timezone('Asia/Dhaka')->format('d M H:i') }}</td><td>{{ $m->to }}</td><td>{{ $m->event }}</td><td><span class="pill {{ $m->status==='sent'?'ok':($m->status==='failed'?'red':'') }}">{{ $m->status }}</span></td><td style="font-size:12px">{{ $m->provider_response }}</td></tr>@empty<tr><td colspan="5">No messages yet.</td></tr>@endforelse</tbody></table></div>
@endsection
