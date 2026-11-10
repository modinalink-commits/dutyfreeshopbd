@extends('layout')@section('title','Chat Keywords')
@section('body')
<h1>Customer Chat Keywords</h1>
<p style="font-size:13px;color:#6B7280;margin:0 0 12px">Words and two-word phrases customers wrote {{ $min }} or more times in the last {{ $days }} days ({{ number_format($total) }} customer messages checked). "Chats" = how many different conversations used it. "In KB" = how many times the Knowledge Base already mentions it. Read-only.</p>
<form method="get" style="display:flex;gap:8px;align-items:end;margin-bottom:12px"><div><label>Minimum count</label><input type="number" name="min" value="{{ $min }}" min="3" max="200" style="width:90px"></div><div><label>Days</label><input type="number" name="days" value="{{ $days }}" min="7" max="720" style="width:90px"></div><button class="btn" type="submit">Apply</button><a class="btn g" href="/admin/ai-chat">Back to AI Chat</a></form>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:14px">
@foreach([['Words',$uni],['Two-word phrases',$bi]] as [$t,$rows])
<div class="card"><b>{{ $t }} ({{ count($rows) }})</b>
<table style="width:100%;margin-top:8px"><thead><tr><th style="text-align:left">Keyword</th><th style="text-align:right">Times</th><th style="text-align:right">Chats</th><th style="text-align:right">In KB</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x['w'] }}</td><td style="text-align:right">{{ $x['n'] }}</td><td style="text-align:right">{{ $x['c'] }}</td><td style="text-align:right;{{ $x['kb'] ? '' : 'color:#B91C1C' }}">{{ $x['kb'] }}</td></tr>@empty<tr><td colspan="4" style="color:#6B7280">Nothing reaches {{ $min }} yet.</td></tr>@endforelse
</tbody></table></div>
@endforeach
</div>
@endsection
