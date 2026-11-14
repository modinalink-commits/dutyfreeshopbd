@extends('layout')@section('title','Agent Scoreboard')
@section('body')
@include('cc_ui')
@php($fm=fn($s)=>$s>=3600?floor($s/3600).'h '.floor($s%3600/60).'m':($s>=60?floor($s/60).'m '.($s%60).'s':$s.'s'))
@php($top=max(1, (int)collect($rows)->max('score')))
<div class="cc-h"><h1>Agent Scoreboard</h1><span>Who answers incoming calls first, talks most and confirms most orders. From the DutyFree Dialer call log.</span></div>
<div class="cc-f">@foreach(['today'=>'Today','7'=>'7 days','30'=>'30 days','all'=>'All'] as $k=>$l)<a class="c {{ $p===(string)$k?'on':'' }}" href="/admin/call-center/agents?p={{ $k }}">{{ $l }}</a>@endforeach</div>
<div class="cc-box"><table class="cc-t">
<tr><th style="width:44px">#</th><th style="width:15%">Agent</th><th style="width:17%">Score</th><th>Incoming answered</th><th class="hm">Answered in 10 s</th><th class="hm">Avg answer time</th><th>Outgoing (answered)</th><th class="hm">Talk time</th><th>Confirmed</th><th>Cancelled</th></tr>
@forelse($rows as $i=>$x)<tr><td><span class="cc-rk {{ $i===0?'g':'' }}">{{ $i+1 }}</span></td><td><b>{{ $x->name }}</b></td>
<td><div style="display:flex;align-items:center;gap:8px"><div class="cc-bar" style="flex:1"><i class="{{ $x->score*2 < $top ? 'w' : '' }}" style="width:{{ round($x->score*100/$top) }}%"></i></div><b>{{ $x->score }}</b></div></td>
<td>{{ $x->in_ans }} / {{ $x->in_total }}</td><td class="hm">{{ $x->fast }}</td><td class="hm">{{ $x->avg_ans!==null ? round($x->avg_ans).' s' : '—' }}</td><td>{{ $x->out_total }} ({{ $x->out_ans }})</td><td class="hm">{{ $fm((int)$x->talk) }}</td><td><b style="color:#067647">{{ $x->conf }}</b></td><td>{{ $x->canc }}</td></tr>
@empty<tr><td colspan="10" style="padding:14px;color:#475467">No calls in this period.</td></tr>@endforelse</table>
<div class="cc-note">Score = answered incoming ×3 + answered within 10 seconds ×2 + answered outgoing ×1 + confirmed orders ×5. When everyone rings together, the person who picks up first gets the "incoming answered" and speed points; for the others only the total goes up.</div></div>
@endsection
