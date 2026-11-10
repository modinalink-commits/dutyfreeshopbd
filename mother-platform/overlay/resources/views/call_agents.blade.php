@extends('layout')@section('title','Agent Scoreboard')
@section('body')
@php($fm=fn($s)=>$s>=3600?floor($s/3600).'h '.floor($s%3600/60).'m':($s>=60?floor($s/60).'m '.($s%60).'s':$s.'s'))
<h1 style="margin:0 0 4px">Agent Scoreboard</h1>
<span style="font-size:13px;color:#6B7280">কে আগে ইনকামিং কল ধরছে, কত কথা বলছে, কয়টা অর্ডার কনফার্ম করছে — Mother Commerce Dialer-এর কল-লগ থেকে।</span>
<div style="margin:12px 0;display:flex;gap:6px;flex-wrap:wrap">@foreach(['today'=>'আজ','7'=>'৭ দিন','30'=>'৩০ দিন','all'=>'সব'] as $k=>$l)<a class="btn {{ $p===(string)$k?'':'g' }}" href="/admin/call-center/agents?p={{ $k }}">{{ $l }}</a>@endforeach</div>
<div class="card" style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280"><th>#</th><th>Agent</th><th>Score</th><th>ইনকামিং ধরেছে</th><th>১০ সেকেন্ডে ধরেছে</th><th>গড় ধরার সময়</th><th>আউটগোয়িং (রিসিভ)</th><th>টকটাইম</th><th>Confirmed</th><th>Cancelled</th></tr>
@forelse($rows as $i=>$x)<tr style="border-top:1px solid #E5E7EB"><td style="padding:7px 6px">{{ $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) }}</td><td><b>{{ $x->name }}</b></td><td><b style="color:#FF5A00">{{ $x->score }}</b></td>
<td>{{ $x->in_ans }} / {{ $x->in_total }}</td><td>{{ $x->fast }}</td><td>{{ $x->avg_ans!==null ? round($x->avg_ans).'s' : '—' }}</td><td>{{ $x->out_total }} ({{ $x->out_ans }})</td><td>{{ $fm((int)$x->talk) }}</td><td>{{ $x->conf }}</td><td>{{ $x->canc }}</td></tr>
@empty<tr><td colspan="10" style="padding:14px;color:#6B7280">এই সময়ে কোনো কল নেই।</td></tr>@endforelse</table>
<div style="margin-top:8px;font-size:12px;color:#6B7280">Score = ধরা ইনকামিং ×৩ + ১০ সেকেন্ডের মধ্যে ধরা ×২ + রিসিভ হওয়া আউটগোয়িং ×১ + Confirmed অর্ডার ×৫। সবার একসাথে রিং হলে যে আগে ধরে সে "ইনকামিং ধরেছে" ও দ্রুততার পয়েন্ট পায়; যারা পরে ধরেনি তাদের সারিতে শুধু মোট সংখ্যা বাড়ে।</div></div>
@endsection
