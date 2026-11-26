@extends('layout')@section('title','Marine Port Calls')
@section('body')
@php $bt = fn($t) => $t ? \Carbon\Carbon::parse($t)->timezone('Asia/Dhaka')->format('d M, H:i') : '-'; @endphp
<div style="margin-bottom:10px"><h1 style="margin:0">Port Calls</h1><div style="font-size:12px;color:#6B7280">Vessel calls at Chattogram, Mongla and Payra added by owners, agencies and captains. Times are Bangladesh time.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">@foreach(['open' => 'Expected and in port'] + $call + ['all' => 'All'] as $k => $l)<a href="/admin/marine/port-calls?status={{ $k }}" style="font-size:12px;padding:5px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $st === $k ? '#0C161F' : '#D1D5DB' }};background:{{ $st === $k ? '#0C161F' : '#FFFFFF' }};color:{{ $st === $k ? '#FFFFFF' : '#0C161F' }}">{{ $l }}</a>@endforeach</div>
<div class="card" style="padding:0;overflow:auto"><table style="width:100%;min-width:940px;font-size:13px;border-collapse:collapse">
<thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:8px 10px">Vessel</th><th style="padding:8px 10px">Port and position</th><th style="padding:8px 10px">ETA</th><th style="padding:8px 10px">ETD</th><th style="padding:8px 10px">Added by / agent</th><th style="padding:8px 10px">Requisitions</th><th style="padding:8px 10px;width:210px">Status</th></tr></thead><tbody>
@forelse($rows as $c)<tr style="border-top:1px solid #E5E7EB;vertical-align:top"><td style="padding:8px 10px"><b>{{ $c->vessel ?: '-' }}</b><div style="font-size:11.5px;color:#6B7280">IMO {{ $c->imo }}</div></td>
<td style="padding:8px 10px">{{ $ports[$c->port] ?? $c->port }}<div style="font-size:11.5px;color:#6B7280">{{ $places[$c->place] ?? '' }}{{ $c->berth ? ' · ' . $c->berth : '' }}</div>@if($c->note)<div style="font-size:11.5px;color:#374151;max-width:220px">{{ $c->note }}</div>@endif</td>
<td style="padding:8px 10px;white-space:nowrap">{{ $bt($c->eta) }}</td><td style="padding:8px 10px;white-space:nowrap">{{ $bt($c->etd) }}</td>
<td style="padding:8px 10px">{{ $c->company ?: $c->person }}<div style="font-size:11.5px;color:#6B7280">{{ $c->username }}</div>@if($c->agent_name || $c->agent_contact)<div style="font-size:11.5px;color:#374151">Agent: {{ $c->agent_name }} {{ $c->agent_contact }}</div>@endif</td>
<td style="padding:8px 10px">@forelse($ord[$c->id] ?? [] as $x)<div style="font-size:12px"><a href="/admin/marine/orders/{{ $x->id }}">{{ $x->number }}</a> <span style="color:#6B7280">{{ $status[$x->status] ?? $x->status }}</span></div>@empty<span style="color:#6B7280;font-size:12px">None</span>@endforelse</td>
<td style="padding:8px 10px"><form method="post" action="/admin/marine/port-calls/{{ $c->id }}" style="display:flex;gap:6px;margin:0">@csrf<select name="to" style="font-size:12px">@foreach($call as $k => $l)<option value="{{ $k }}" @selected($c->status === $k)>{{ $l }}</option>@endforeach</select><button class="btn g" style="padding:4px 10px;font-size:12px">Save</button></form></td></tr>
@empty<tr><td colspan="7" style="padding:16px 10px;color:#6B7280">No port call.</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>
@endif
@endsection
