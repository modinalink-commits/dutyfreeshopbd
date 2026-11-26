@extends('mp.layout')@section('title','Port calls')
@section('body')
@php $bt = fn($t) => $t ? \Carbon\Carbon::parse($t)->timezone('Asia/Dhaka')->format('d M Y, H:i') : '-'; @endphp
<h1>Port calls</h1><p class="sub">Calls at Chattogram, Mongla and Payra for {{ $u->type === 'owner' ? 'your fleet' : ($u->type === 'agency' ? 'the vessels you act for' : 'your vessel') }}. Times are Bangladesh time (UTC+6).</p>
<div class="card tw" style="padding:0"><table style="min-width:680px"><tr><th style="padding-left:16px">Vessel</th><th>Port</th><th>ETA</th><th>ETD</th><th>Status</th><th style="padding-right:16px"></th></tr>
@forelse($rows as $c)<tr><td style="padding-left:16px"><b>{{ $c->vessel ?: '-' }}</b><div class="hint" style="margin:0">IMO {{ $c->imo }}</div></td><td>{{ $ports[$c->port] ?? $c->port }}<div class="hint" style="margin:0">{{ $places[$c->place] ?? '' }}{{ $c->berth ? ' · ' . $c->berth : '' }}</div></td><td style="white-space:nowrap">{{ $bt($c->eta) }}</td><td style="white-space:nowrap">{{ $bt($c->etd) }}</td><td><span class="st st-{{ $c->status }}">{{ $call[$c->status] ?? $c->status }}</span></td>
<td style="padding-right:16px"><div class="row" style="justify-content:flex-end;flex-wrap:nowrap">@if($canOrder && in_array($c->status, ['expected', 'arrived'], true))<a class="btn" style="min-height:38px;padding:0 12px;font-size:13px" href="/marine-portal/orders/new?pc={{ $c->id }}">Requisition</a>@endif @if((int)$c->account_id === (int)$u->id && $c->status === 'expected')<form method="post" action="/marine-portal/port-calls/{{ $c->id }}/cancel" style="margin:0">@csrf<button class="g" style="min-height:38px;padding:0 12px;font-size:13px">Cancel</button></form>@endif</div></td></tr>
@empty<tr><td colspan="6" style="padding:20px 16px;color:#64748B">No port call yet.</td></tr>@endforelse</table></div>
{{ $rows->links('pagination.global') }}
@if($canAdd)
<form class="card" method="post" action="/marine-portal/port-calls">@csrf<h2>Add a port call</h2><div class="f2">
@if($u->type === 'vessel')<div><label>Vessel</label><input type="text" value="{{ $u->vessel ?: '-' }}{{ $u->imo ? ' · IMO ' . $u->imo : '' }}" disabled></div>
@else<div><label for="imo">Vessel IMO number (7 digits)</label><input id="imo" type="text" name="imo" inputmode="numeric" maxlength="7" list="vl" value="{{ old('imo') }}" required><datalist id="vl">@foreach($vessels as $v)<option value="{{ $v->imo }}">{{ $v->name }}</option>@endforeach</datalist></div><div><label for="vn">Vessel name (for a new IMO number)</label><input id="vn" type="text" name="vessel" maxlength="120" value="{{ old('vessel') }}"></div>@endif
<div><label for="port">Port</label><select id="port" name="port" required>@foreach($ports as $k => $l)<option value="{{ $k }}" @selected(old('port', strtolower((string)$u->port)) === $k)>{{ $l }}</option>@endforeach</select></div>
<div><label for="place">Position</label><select id="place" name="place">@foreach($places as $k => $l)<option value="{{ $k }}" @selected(old('place', 'unknown') === $k)>{{ $l }}</option>@endforeach</select></div>
<div><label for="berth">Berth or anchorage name (optional)</label><input id="berth" type="text" name="berth" maxlength="80" value="{{ old('berth') }}"></div>
<div><label for="eta">ETA</label><input id="eta" type="datetime-local" name="eta" value="{{ old('eta') }}" required></div>
<div><label for="etd">ETD (optional)</label><input id="etd" type="datetime-local" name="etd" value="{{ old('etd') }}"></div>
<div><label for="an">Local agent (optional)</label><input id="an" type="text" name="agent_name" maxlength="120" value="{{ old('agent_name') }}"></div>
<div><label for="ac">Agent phone or email (optional)</label><input id="ac" type="text" name="agent_contact" maxlength="120" value="{{ old('agent_contact') }}"></div>
</div><label for="nt">Note (optional)</label><textarea id="nt" name="note" maxlength="400">{{ old('note') }}</textarea>
<div class="row" style="margin-top:16px"><button>Add port call</button></div></form>
@endif
@endsection
