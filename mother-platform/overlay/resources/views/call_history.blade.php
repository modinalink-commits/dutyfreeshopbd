@extends('layout')@section('title', $view==='report' ? 'Call Reports' : ($f==='missed' || $f==='noback' ? 'Missed Calls' : 'Call History'))
@section('body')
@include('cc_ui')
@php($OC=['confirmed'=>'Order confirmed','pending'=>'Pending','call_later'=>'Call later','no_answer'=>'No answer','cancelled'=>'Order cancelled'])
@php($qs=fn($a)=>'?'.http_build_query(array_filter(array_merge(['f'=>$f,'d'=>$d,'q'=>$q], $a), fn($v)=>$v!==null && $v!=='')))
<div class="cc-h"><h1>{{ $view==='report' ? 'Call Reports' : ($f==='missed' || $f==='noback' ? 'Missed Calls' : 'Call History') }}</h1><span>{{ $view==='report' ? 'Last 30 days, from the DutyFree Dialer call log.' : 'Calls made and received from the DutyFree Dialer. Numbers above are for this month.' }}</span></div>
@if($view==='report' && $rep)
@php($t=$rep['tot'])
<div class="cc-k">
<div><span>Calls (30 days)</span><b>{{ $t->n ?? 0 }}</b></div><div class="ok"><span>Answered</span><b>{{ $t->ans ?? 0 }}</b></div><div class="bad"><span>Missed</span><b>{{ $t->mis ?? 0 }}</b></div><div><span>Incoming</span><b>{{ $t->inc ?? 0 }}</b></div><div><span>Talk time</span><b>{{ gmdate('H:i:s',(int)$t->dur) }}</b></div><div><span>Avg answered</span><b>{{ gmdate('i:s',(int)$t->avgd) }}</b></div></div>
<div class="cc-two">
<div class="cc-box"><b class="t">By staff</b><table class="cc-t"><tr><th style="width:34%">Staff</th><th>Answered share</th><th style="width:14%">Calls</th><th style="width:16%">Answered</th><th style="width:18%">Talk time</th></tr>@foreach($rep['staff'] as $s)<tr><td><b>{{ $s->u }}</b></td><td><div class="cc-bar"><i style="width:{{ $s->n ? round($s->ans*100/$s->n) : 0 }}%"></i></div></td><td>{{ $s->n }}</td><td>{{ $s->ans }}</td><td>{{ gmdate('H:i:s',(int)$s->dur) }}</td></tr>@endforeach</table></div>
<div class="cc-box"><b class="t">By day</b><table class="cc-t"><tr><th style="width:24%">Day</th><th>Answered share</th><th style="width:16%">Calls</th><th style="width:18%">Answered</th></tr>@foreach($rep['days'] as $x)<tr><td><b>{{ $x->d }}</b></td><td><div class="cc-bar"><i style="width:{{ $x->n ? round($x->ans*100/$x->n) : 0 }}%"></i></div></td><td>{{ $x->n }}</td><td>{{ $x->ans }}</td></tr>@endforeach</table></div></div>
@else
<div class="cc-k">
<div><span>Calls</span><b>{{ $kpi->n ?? 0 }}</b></div><div class="ok"><span>Answered</span><b>{{ $kpi->ans ?? 0 }}</b></div><a class="bad" href="?f=missed"><span>Missed</span><b>{{ $kpi->mis ?? 0 }}</b></a><a class="{{ $noback ? 'bad' : '' }}" href="?f=noback" title="Missed incoming calls that nobody has called back yet"><span>Not called back</span><b>{{ $noback }}</b></a><div><span>Incoming</span><b>{{ $kpi->inc ?? 0 }}</b></div><div><span>Talk time</span><b>{{ gmdate('H:i:s',(int)($kpi->dur ?? 0)) }}</b></div><div><span>Avg answered</span><b>{{ gmdate('i:s',(int)($kpi->avgd ?? 0)) }}</b></div></div>
<form method="get" class="cc-f">
@foreach(['all'=>'All','answered'=>'Answered','missed'=>'Missed','noback'=>'Not called back','busy'=>'Busy','failed'=>'Failed'] as $k=>$l)<a class="c {{ $f===$k ? 'on' : '' }}" href="{{ $qs(['f'=>$k]) }}">{{ $l }}</a>@endforeach
<span style="width:1px;height:20px;background:#E5E7EB"></span>
@foreach([''=>'Both ways','in'=>'Incoming','out'=>'Outgoing'] as $k=>$l)<a class="c {{ $d===$k ? 'on' : '' }}" href="{{ $qs(['d'=>$k]) }}">{{ $l }}</a>@endforeach
<input type="hidden" name="f" value="{{ $f }}"><input type="hidden" name="d" value="{{ $d }}"><input name="q" value="{{ $q }}" placeholder="Search customer, phone, staff or note"><button class="btn" type="submit">Search</button></form>
<div class="cc-box"><table class="cc-t">
<tr><th style="width:50px"></th><th style="width:19%">Customer</th><th style="width:12%">Staff</th><th class="hm" style="width:9%">Direction</th><th style="width:15%">Start time</th><th class="hm" style="width:8%">Duration</th><th style="width:10%">Status</th><th style="width:12%">Outcome</th><th>Note</th></tr>
@forelse($rows as $r)
@php($miss=$r->direction==='in' && $r->status==='missed') @php($bk=$back[$r->id] ?? null)
<tr class="{{ $miss && !$bk ? 'hot' : '' }}"><td><button type="button" class="cc-call {{ $miss && !$bk ? 'go' : '' }}" onclick="dfCall('{{ $r->phone }}')" title="Call {{ $r->phone }}" aria-label="Call {{ $r->phone }}"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg></button></td>
<td><b>{{ $r->customer_name ?: 'Unknown' }}</b><small>{{ $r->phone }}</small></td>
<td>{{ $r->user_name ?: '—' }}<small>Ext {{ $r->extension ?: '—' }}</small></td>
<td class="hm">{{ $r->direction==='in' ? 'Incoming' : 'Outgoing' }}</td>
<td>{{ \Carbon\Carbon::parse($r->started_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td>
<td class="hm">{{ gmdate('H:i:s',(int)$r->duration) }}</td>
<td><span class="cc-p {{ ['answered'=>'ok','missed'=>'bad','busy'=>'warn'][$r->status] ?? '' }}">{{ ucfirst($r->status) }}</span></td>
<td>@if($miss)@if($bk)<span class="cc-p ok">Handled</span><small>{{ $bk }}</small>@else<span class="cc-p bad">Not called back</span><small>waiting {{ \Carbon\Carbon::parse($r->started_at)->diffForHumans(null, true) }}</small>@endif @else{{ $OC[$r->outcome] ?? '—' }}@endif</td>
<td>@if(!empty($r->recording_url))<a href="/admin/call-center/play/{{ $r->id }}" target="_blank" rel="noopener" style="font-weight:600" title="Opens the recording. The play is written to the listening log.">Play</a> @endif<span style="font-size:12px;color:#475467">{{ $r->note }}</span></td></tr>
@empty<tr><td colspan="9" style="padding:20px;text-align:center;color:#475467">No calls here yet.</td></tr>@endforelse</table></div>
<div style="margin-top:12px">{{ $rows->links('pagination.global') }}</div>
@endif
@endsection
