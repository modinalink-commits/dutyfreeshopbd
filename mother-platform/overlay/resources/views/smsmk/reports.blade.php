@extends('layout')@section('title','SMS Reports')
@section('body')
<h1 style="margin:0 0 12px">SMS Reports</h1>@include('smsmk.nav')
<form class="card" method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
<div><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div><div><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
<div><label>Campaign</label><select name="campaign"><option value="">All</option>@foreach($camps as $c)<option value="{{ $c->id }}" {{ request('campaign')==$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach</select></div>
<div><label>Status</label><select name="status"><option value="">All</option>@foreach(['queued','sending','sent','failed','cancelled'] as $s)<option {{ request('status')===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select></div>
<div><label>Type</label><select name="type"><option value="">All</option><option {{ request('type')==='marketing'?'selected':'' }}>marketing</option><option {{ request('type')==='transactional'?'selected':'' }}>transactional</option></select></div>
<div><label>Sender ID</label><select name="sender"><option value="">All</option>@foreach($senders as $s)<option {{ request('sender')===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select></div>
<div><label>Search</label><input name="q" value="{{ request('q') }}" placeholder="name / mobile"></div>
<button>Filter</button><a class="btn g" href="/admin/sms-marketing/reports/export/csv?{{ http_build_query(request()->query()) }}">Export CSV</a></form>
<div class="card tw"><table><thead><tr><th>Date</th><th>Campaign</th><th>Customer</th><th>Mobile</th><th>Type</th><th>Sender</th><th>Status</th><th>Cost</th><th>Provider response</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($x->created_at,'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') }}</td><td>{{ $x->campaign ?: $x->event }}</td><td>{{ $x->customer_name }}</td><td>{{ $x->to }}</td><td>{{ $x->type }}</td><td>{{ $x->sender_id }}</td><td><span class="bd {{ $x->status }}">{{ $x->status }}</span></td><td>৳{{ number_format($x->cost,2) }}</td><td style="font-size:12px">{{ $x->provider_response }}</td></tr>@empty<tr><td colspan="9">No messages match.</td></tr>@endforelse</tbody></table>
<div style="margin-top:10px">{{ $rows->links() }}</div><p style="font-size:12px;color:#6B7280">PDF / Excel / Word downloads of the full log: <a href="/admin/m/sms-log/export/pdf" target="_blank">PDF</a> · <a href="/admin/m/sms-log/export/xls">Excel</a> · <a href="/admin/m/sms-log/export/doc">Word</a></p></div>
@endsection
