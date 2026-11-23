@extends('layout')@section('title','Reorder Reminder')
@section('body')
@include('settings_ui')
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Reorder Reminder</h1><div style="font-size:12px;color:#6B7280">Each customer's own buying rhythm is measured from their past orders. A little before it usually runs out, one short SMS with the website link goes out. No discount is offered. It runs before the Win-Back offers.</div></div>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $s['on'] ? '#DCFCE7' : '#FEE2E2' }};color:{{ $s['on'] ? '#166534' : '#B91C1C' }}">{{ $s['on'] ? 'AUTOMATION ON' : 'AUTOMATION PAUSED' }}</span>
<form method="post" action="/admin/reorder-reminder/toggle" style="margin:0">@csrf<button class="g" style="height:32px;font-weight:700" title="Master switch">{{ $s['on'] ? 'Turn OFF' : 'Turn ON' }}</button></form><button type="button" class="sbtn" data-sdr="rrset">⚙ Settings</button></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:10px">
@foreach([['Customers',number_format($st['cust'])],['With 2+ orders',number_format($st['multi'])],['Typical gap',$st['med'] !== null ? $st['med'].' days' : '—'],['Due now',$dueTotal],['Sent today',$s['today'].' / '.$s['cap']],['Sent (all)',$st['sent']],['Ordered after SMS',$st['conv']]] as [$l,$v])<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:17px">{{ $v }}</b></div>@endforeach</div>
<div class="card"><h2>Buying rhythm (days between a customer's last orders)</h2>
@php $bl=['Up to 7 days','8–14 days','15–21 days','22–30 days','Over 30 days']; $mx=max(1,max($st['b'])); @endphp
@foreach($bl as $i=>$l)<div style="display:flex;align-items:center;gap:8px;margin:5px 0;font-size:12.5px"><span style="width:96px;color:#6B7280">{{ $l }}</span><span style="height:10px;border-radius:5px;background:#F95505;opacity:.8;width:{{ round($st['b'][$i]/$mx*60) }}%;min-width:2px"></span><b>{{ number_format($st['b'][$i]) }}</b></div>@endforeach
<div style="font-size:11.5px;color:#6B7280;margin-top:6px">The reminder day is 85% of a customer's own typical gap, kept between {{ $s['min'] }} and {{ $s['max'] }} days after the last order (customers with a single order: day {{ $s['def'] }}). Customers who already got a Win-Back SMS in the last {{ $s['mingap'] }} days are skipped.</div></div>
<div class="sdr" id="rrset"><div class="sdr-p"><div class="sdr-h"><b>Reorder reminder settings</b><button type="button" data-sdx aria-label="Close">×</button></div><div class="sdr-b"><div class="card"><h2>Settings</h2><form method="post" action="/admin/reorder-reminder/settings">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;align-items:end">
<div><label>Max reminders per day (SMS cost cap)</label><input type="number" min="1" name="daily_cap" value="{{ $s['cap'] }}"></div>
<div><label>SMS per slot</label><input type="number" min="1" max="20" name="slot_size" value="{{ $s['slot'] }}"></div>
<div><label>Minutes between slots</label><input type="number" min="1" max="240" name="slot_gap" value="{{ $s['gap'] }}"></div>
<div><label>Earliest reminder day</label><input type="number" min="3" max="30" name="min_days" value="{{ $s['min'] }}"></div>
<div><label>Latest reminder day</label><input type="number" min="3" max="14" name="max_days" value="{{ $s['max'] }}"></div>
<div><label>Day for customers with one order</label><input type="number" min="3" max="14" name="default_days" value="{{ $s['def'] }}"></div>
<div><label>Days between two messages to a number</label><input type="number" min="1" max="90" name="min_gap" value="{{ $s['mingap'] }}"></div>
<div><label>No SMS from (hour, Dhaka)</label><input type="number" min="0" max="23" name="quiet_from" value="{{ $s['qf'] }}"></div>
<div><label>No SMS until (hour, Dhaka)</label><input type="number" min="0" max="23" name="quiet_to" value="{{ $s['qt'] }}"></div></div>
<label style="margin-top:12px">SMS template — variables {name} {link}</label><textarea name="sms" rows="2">{{ $s['sms'] }}</textarea>
<div style="margin-top:10px"><button>Save settings</button></div></form></div></div></div></div>
<div class="card"><h2>Due now · {{ $dueTotal }}</h2><div style="font-size:12px;color:#6B7280;margin:-4px 0 8px">Customers whose reminder day has come and who have not ordered again. Showing the first 50, longest waiting first.</div>
<div style="overflow-x:auto"><table><thead><tr><th>#</th><th>Customer</th><th>Phone</th><th>Orders</th><th>Own gap</th><th>Reminder day</th><th>Days since last order</th></tr></thead><tbody>
@forelse($due as $x)<tr><td>{{ $loop->iteration }}</td><td>{{ $names[$x['p']] ?? '—' }}</td><td>{{ $x['p'] }}</td><td>{{ $x['n'] }}</td><td>{{ $x['cycle'] !== null ? $x['cycle'].' d' : '—' }}</td><td>{{ $x['due'] }}</td><td>{{ $x['d'] }}</td></tr>@empty<tr><td colspan="7" style="color:#6B7280">Nobody is due right now.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><h2>Recent reminders</h2><div style="overflow-x:auto"><table><thead><tr><th>Sent</th><th>Customer</th><th>Phone</th><th>Own gap</th><th>Day sent</th></tr></thead><tbody>
@forelse($hist as $e)<tr><td>{{ \Carbon\Carbon::parse($e->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td><td>{{ $e->name ?: '—' }}</td><td>{{ $e->phone }}</td><td>{{ $e->cycle_days !== null ? $e->cycle_days.' d' : '—' }}</td><td>{{ $e->days_since }}</td></tr>@empty<tr><td colspan="5" style="color:#6B7280">No reminder sent yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
