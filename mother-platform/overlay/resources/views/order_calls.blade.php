@extends('layout')@section('title','Order Confirmation Calls')
@section('body')
@php
  $pill = ['confirmed'=>['#DCFCE7','#166534','Pressed 1 · confirmed'],'pending_requested'=>['#FEF3C7','#92400E','Pressed 2 · keep pending'],'calling'=>['#E0E7FF','#1D5FA8','Calling'],'answered'=>['#E0E7FF','#1D5FA8','Answered, waiting for key'],'no_input'=>['#FEF3C7','#92400E','Answered, no valid key'],'no_answer'=>['#F3F4F6','#4B5563','No answer'],'busy'=>['#F3F4F6','#4B5563','Busy'],'failed'=>['#FEE2E2','#B91C1C','Failed'],'stale'=>['#FEE2E2','#B91C1C','Order changed']];
  $modeTxt = ['off'=>['OFF','#FEE2E2','#B91C1C'],'assist'=>['ASSIST · evidence only','#E0E7FF','#1D5FA8'],'gate'=>['GATE · key 1 needed before auto-approve','#DCFCE7','#166534']][$mode];
@endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Order Confirmation Calls</h1><div style="font-size:12px;color:#6B7280">AI call to the customer after an order: press 1 to confirm, 2 to keep it pending. Shown in AI Verification → Communication → Call verification (source "AI call").</div></div>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $modeTxt[1] }};color:{{ $modeTxt[2] }}">{{ $modeTxt[0] }}</span>
<a href="/admin/voice" style="font-size:12px">← Voice campaigns</a></div>
@if(!$ok)<div class="card" style="border-left:3px solid #B91C1C"><b>Database update pending.</b> The call table is created by the next deploy migration. Reload in a minute.</div>@endif
<div class="card"><h2 style="margin-top:0">Readiness</h2>
@foreach($ready as $r)<div style="display:flex;gap:8px;font-size:12.5px;padding:3px 0"><span style="color:{{ $r[1] ? '#166534' : '#B91C1C' }};font-weight:700">{{ $r[1] ? '✓' : '✗' }}</span><span>{{ $r[0] }} @if(!$r[1])<span style="color:#6B7280">— {{ $r[2] }}</span>@endif</span></div>@endforeach</div>
<div class="card"><h2 style="margin-top:0">Settings</h2>
<form method="post" action="/admin/voice/order-calls/settings">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;font-size:12.5px">
<label>Mode<select name="mode" style="width:100%"><option value="off" @selected($s['mode']==='off')>Off</option><option value="assist" @selected($s['mode']==='assist')>Assist: call and record only</option><option value="gate" @selected($s['mode']==='gate')>Gate: key 1 (or staff call) before auto-approve</option></select></label>
<label>Test numbers only<br><input type="checkbox" name="test_only" value="1" @checked($s['test_only']!=='0')> call only the test list below</label>
<label>Test numbers (comma separated)<input name="test_phones" value="{{ $s['test_phones'] }}" style="width:100%"></label>
<label>Order source to call<input name="source" value="{{ $s['source'] }}" style="width:100%" placeholder="Web Site (empty = any)"></label>
<label>Call from<input name="start" value="{{ $s['start'] }}" style="width:100%"></label><label>Call until<input name="end" value="{{ $s['end'] }}" style="width:100%"></label>
<label>Attempts per order<input type="number" min="1" max="3" name="max_attempts" value="{{ $s['max_attempts'] }}" style="width:100%"></label>
<label>Retry gap (minutes)<input type="number" min="10" name="retry_gap" value="{{ $s['retry_gap'] }}" style="width:100%"></label>
<label>Calls at the same time<input type="number" min="1" max="10" name="concurrency" value="{{ $s['concurrency'] }}" style="width:100%"></label>
<label>Only orders newer than (hours)<input type="number" min="1" max="72" name="hours_back" value="{{ $s['hours_back'] }}" style="width:100%"></label></div>
<p style="font-size:12px;color:#6B7280;margin:8px 0">Safe start: Assist + "test numbers only". Real customers are never called while "test numbers only" is on. Gate mode only affects orders that can be called; new customers still go to staff by the existing manual rule.</p>
<button>Save settings</button></form></div>
<div class="card"><h2 style="margin-top:0">Test call</h2>
<form method="post" action="/admin/voice/order-calls/test" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">@csrf<input name="phone" value="{{ explode(',', $s['test_phones'])[0] }}" style="width:180px"><button>Place a sample call</button><span style="font-size:12px;color:#6B7280">Only numbers in the test list. No order is touched.</span></form></div>
@if($exc->count())<div class="card" style="border-left:3px solid #92400E"><h2 style="margin-top:0">Staff follow-up ({{ $exc->count() }})</h2><div style="font-size:12px;color:#6B7280;margin-bottom:6px">Orders still waiting for approval where the AI call did not confirm.</div>
<div style="overflow-x:auto"><table class="tbl" style="width:100%;font-size:12.5px"><tr><th>Order</th><th>Customer</th><th>Total</th><th>Result</th><th>When</th></tr>
@foreach($exc as $e)<tr><td><a href="/admin/orders/{{ $e->order_id }}">{{ $e->invoice }}</a></td><td>{{ $e->customer_name }} · {{ $e->phone }}</td><td>৳{{ number_format($e->total) }}</td><td>{{ ($pill[$e->status][2] ?? $e->status) }}</td><td>{{ \Carbon\Carbon::parse($e->updated_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td></tr>@endforeach</table></div></div>@endif
<div class="card"><h2 style="margin-top:0">Last 7 days (real orders)</h2><div style="display:flex;flex-wrap:wrap;gap:8px">@forelse($cnt as $k => $n)<span style="font-size:12px;border-radius:999px;padding:3px 10px;background:{{ ($pill[$k][0] ?? '#F3F4F6') }};color:{{ ($pill[$k][1] ?? '#4B5563') }}">{{ ($pill[$k][2] ?? $k) }}: <b>{{ $n }}</b></span>@empty<span style="font-size:12px;color:#6B7280">No calls yet.</span>@endforelse</div></div>
<div class="card"><h2 style="margin-top:0">Recent calls</h2><div style="overflow-x:auto"><table class="tbl" style="width:100%;font-size:12.5px"><tr><th>When</th><th>Order</th><th>Phone</th><th>Attempt</th><th>Result</th><th>Key</th><th>Test</th><th>Note</th></tr>
@forelse($recent as $r)<tr><td>{{ $r->started_at ? \Carbon\Carbon::parse($r->started_at)->timezone('Asia/Dhaka')->format('d M, h:i A') : '' }}</td><td>@if($r->order_id)<a href="/admin/orders/{{ $r->order_id }}">{{ $r->invoice }}</a>@else sample @endif</td><td>{{ $r->phone }}</td><td>{{ $r->attempt }}</td>
<td><span style="font-size:11px;border-radius:999px;padding:2px 8px;background:{{ ($pill[$r->status][0] ?? '#F3F4F6') }};color:{{ ($pill[$r->status][1] ?? '#4B5563') }}">{{ ($pill[$r->status][2] ?? $r->status) }}</span></td><td>{{ $r->digit }}</td><td>{{ $r->test ? 'test' : '' }}</td><td style="color:#6B7280">{{ $r->cause }}</td></tr>@empty<tr><td colspan="8" style="color:#6B7280">No calls yet.</td></tr>@endforelse</table></div></div>
@endsection
