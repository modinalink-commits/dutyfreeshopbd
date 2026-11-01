@extends('layout')@section('title','SMS Campaigns')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><h1 style="margin:0;flex:1">{{ $only ? 'Scheduled SMS' : 'SMS Campaigns' }}</h1><a class="btn" href="/admin/sms-marketing/send">+ New Campaign</a></div>@include('smsmk.nav')
<div class="card tw"><table><thead><tr><th>Campaign</th><th>Status</th><th>Schedule (Asia/Dhaka)</th><th>Repeat</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Cost</th><th></th></tr></thead><tbody>
@forelse($rows as $c)@php $s = collect($st[$c->id] ?? [])->pluck('n','status'); $at = $tz($c->schedule_at); @endphp
<tr><td><a href="/admin/sms-marketing/campaigns/{{ $c->id }}"><b>{{ $c->name }}</b></a><div style="font-size:11px;color:#6B7280">{{ $c->kind }}</div></td><td><span class="bd {{ $c->status }}">{{ $c->status }}</span></td><td>{{ $at ? $at->format('d M Y · h:i A') : '—' }}</td><td>{{ $c->repeat }}</td><td>{{ number_format($c->total) }}</td><td>{{ number_format($s['sent'] ?? 0) }}</td><td>{{ number_format($s['failed'] ?? 0) }}</td><td>৳{{ number_format($c->cost,2) }}</td>
<td style="white-space:nowrap">@if(in_array($c->status,['draft','scheduled','paused']))<a class="btn g" href="/admin/sms-marketing/send?edit={{ $c->id }}">Edit</a>@endif
@foreach([['pause',['scheduled','sending']],['resume',['paused']],['cancel',['draft','scheduled','sending','paused']]] as [$a,$ok])@if(in_array($c->status,$ok))<form method="post" action="/admin/sms-marketing/campaigns/{{ $c->id }}/action" style="display:inline" @if($a==='cancel')onsubmit="return confirm('Cancel this campaign? Unsent messages are dropped.')"@endif>@csrf<input type="hidden" name="do" value="{{ $a }}"><button class="g">{{ ucfirst($a) }}</button></form> @endif @endforeach</td></tr>
@empty<tr><td colspan="9">Nothing here yet.</td></tr>@endforelse</tbody></table></div>
@endsection
