@extends('layout')@section('title','SMS Marketing')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:240px"><h1 style="margin:0">SMS Marketing</h1><span style="font-size:13px;color:#6B7280">Segments · bulk &amp; scheduled campaigns · automation · delivery tracking · cost</span></div><a class="btn" href="/admin/sms-marketing/send">+ New Bulk SMS</a></div>
@include('smsmk.nav')
@if(!$k['ready'])<div class="card err">The SMS gateway is not connected yet. <a href="/admin/sms">Add the API key and Sender ID</a> — until then messages stay queued.</div>@endif
@if($k['low'])<div class="card err"><b>SMS Balance Low</b> — current balance {{ number_format($k['balance'],2) }}. Recharge at BulkSMSBD.</div>@endif
<div class="sk">
<div class="card"><span>SMS Balance</span><b>{{ $k['balance'] === null ? '—' : '৳'.number_format($k['balance'],2) }}</b></div>
<div class="card"><span>Total SMS</span><b>{{ number_format($k['total']) }}</b></div>
<div class="card"><span>Sent / Delivered</span><b>{{ number_format($k['sent']) }}</b></div>
<div class="card"><span>Failed</span><b>{{ number_format($k['failed']) }}</b></div>
<div class="card"><span>Pending</span><b>{{ number_format($k['queued']) }}</b></div>
<div class="card"><span>Scheduled</span><b>{{ number_format($k['scheduled']) }}</b></div>
<div class="card"><span>Active campaigns</span><b>{{ number_format($k['active']) }}</b></div>
<div class="card"><span>Today</span><b>{{ number_format($k['today']) }}</b></div>
<div class="card"><span>This month</span><b>{{ number_format($k['month']) }}</b></div>
<div class="card"><span>Delivery rate</span><b>{{ $k['rate'] }}%</b></div>
<div class="card"><span>Used credit (est.)</span><b>৳{{ number_format($k['spent'],2) }}</b></div>
<div class="card"><span>Est. remaining SMS</span><b>{{ $k['balance'] !== null && $k['unit'] > 0 ? number_format(floor($k['balance'] / $k['unit'])) : '—' }}</b></div>
</div>
<div class="card tw"><b>Recent campaigns</b> <a href="/admin/sms-marketing/campaigns" style="font-size:12px">all</a><table><thead><tr><th>Campaign</th><th>Status</th><th>Recipients</th><th>Cost</th></tr></thead><tbody>
@forelse($camps as $c)<tr><td><a href="/admin/sms-marketing/campaigns/{{ $c->id }}">{{ $c->name }}</a></td><td><span class="bd {{ $c->status }}">{{ $c->status }}</span></td><td>{{ number_format($c->total) }}</td><td>৳{{ number_format($c->cost,2) }}</td></tr>@empty<tr><td colspan="4">No campaigns yet. <a href="/admin/sms-marketing/send">Create the first one</a>.</td></tr>@endforelse</tbody></table>
<p style="font-size:12px;color:#6B7280;margin:10px 0 0">“Delivered” counts messages accepted by the gateway (BulkSMSBD does not return handset delivery receipts). Last recharge: {{ $k['recharge'] }}.</p></div>
@endsection
