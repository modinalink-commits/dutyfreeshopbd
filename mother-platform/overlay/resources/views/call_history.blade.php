@extends('layout')@section('title','Call History')
@section('body')
<h1 style="margin:0 0 4px">{{ $view==='report' ? 'Call Reports' : ($f==='missed' ? 'Missed Calls' : 'Call History') }}</h1>
<span style="font-size:13px;color:#6B7280">Calls made and received from the admin softphone.</span>
@if($view==='report' && $rep)
@php($t=$rep['tot'])
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:12px">
@foreach([['Calls (30 days)',$t->n],['Answered',$t->ans],['Missed',$t->mis],['Incoming',$t->inc],['Talk time',gmdate('H:i:s',(int)$t->dur)],['Avg answered',gmdate('i:s',(int)$t->avgd)]] as $k)<div class="card"><div style="font-size:12px;color:#6B7280">{{ $k[0] }}</div><b style="font-size:22px">{{ $k[1] ?? 0 }}</b></div>@endforeach</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-top:12px">
<div class="card"><b>By staff</b><table style="width:100%;margin-top:6px"><tr><th align=left>Staff</th><th>Calls</th><th>Answered</th><th>Talk time</th></tr>@foreach($rep['staff'] as $s)<tr><td>{{ $s->u }}</td><td align=center>{{ $s->n }}</td><td align=center>{{ $s->ans }}</td><td align=center>{{ gmdate('H:i:s',(int)$s->dur) }}</td></tr>@endforeach</table></div>
<div class="card"><b>By day</b><table style="width:100%;margin-top:6px"><tr><th align=left>Day</th><th>Calls</th><th>Answered</th></tr>@foreach($rep['days'] as $d)<tr><td>{{ $d->d }}</td><td align=center>{{ $d->n }}</td><td align=center>{{ $d->ans }}</td></tr>@endforeach</table></div></div>
@else
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0">
@foreach(['all'=>'All','answered'=>'Answered','missed'=>'Missed','busy'=>'Busy','failed'=>'Failed'] as $k=>$l)<a class="chip" href="?f={{ $k }}" style="padding:6px 12px;border-radius:14px;border:1px solid #D1D5DB;text-decoration:none;{{ $f===$k ? 'background:#0B1220;color:#fff' : 'color:#0B1220' }}">{{ $l }}</a>@endforeach
<input type="hidden" name="f" value="{{ $f }}"><input name="q" value="{{ $q }}" placeholder="Search phone or customer" style="max-width:240px"><button class="btn" type="submit">Search</button></form>
<div class="card" style="overflow:auto"><table style="width:100%;border-collapse:collapse;min-width:760px">
<tr style="text-align:left;font-size:12px;color:#6B7280"><th>Customer</th><th>Phone</th><th>Extension</th><th>Staff</th><th>Direction</th><th>Start time</th><th>Duration</th><th>Status</th><th>Note</th></tr>
@forelse($rows as $r)<tr style="border-top:1px solid #E5E7EB"><td style="padding:8px 4px">{{ $r->customer_name ?: 'Unknown' }}</td><td>{{ $r->phone }} <a href="#" onclick="dfCall('{{ $r->phone }}');return false" title="Call">📞</a></td><td>{{ $r->extension }}</td><td>{{ $r->user_name }}</td><td>{{ $r->direction==='in' ? '↙ Incoming' : '↗ Outgoing' }}</td><td>{{ \Carbon\Carbon::parse($r->started_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td><td>{{ gmdate('H:i:s',(int)$r->duration) }}</td>
<td><span style="font-weight:700;color:{{ ['answered'=>'#15803D','missed'=>'#B91C1C','busy'=>'#B45309','failed'=>'#6B7280','rejected'=>'#6B7280'][$r->status] ?? '#6B7280' }}">{{ ucfirst($r->status) }}</span></td><td style="font-size:12px;max-width:220px">{{ $r->note }}</td></tr>
@empty<tr><td colspan="9" style="padding:20px;text-align:center;color:#6B7280">No calls yet.</td></tr>@endforelse</table></div>
<div style="margin-top:12px">{{ $rows->links('pagination.global') }}</div>
@endif
@endsection
