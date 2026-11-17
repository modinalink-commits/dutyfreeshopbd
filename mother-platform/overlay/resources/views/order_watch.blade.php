@extends('layout')@section('title','Order Watch')
@section('body')
<style>.ow .card{padding:12px 14px;margin-bottom:10px}.ow h2{font-size:14px;margin:0 0 8px}.ow table{width:100%;border-collapse:collapse;font-size:12.5px}.ow th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.ow td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:top}.ow .n{text-align:right;font-variant-numeric:tabular-nums}.ow .f{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.ow label{font-size:12px;color:#374151}.ow input[type=text],.ow input[type=number],.ow select{height:30px}.ow .mu{color:#6B7280}.ow .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:700;background:#EEF2F7;color:#0B1B33;white-space:nowrap}.ow .kp{display:flex;gap:10px;flex-wrap:wrap}.ow .kp div{border:1px solid #E5E7EB;border-radius:10px;padding:8px 12px;min-width:120px;background:#fff}.ow .kp b{display:block;font-size:18px}.ow .kp small{color:#6B7280}.ow pre{white-space:pre-wrap;font-family:inherit;font-size:12.5px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px;margin:6px 0 0}</style>
<div class="ow">
<div style="margin-bottom:8px"><h1 style="font-size:18px;margin:0">Order Watch</h1><small class="mu">Overdue order work by responsible staff, the hourly report each person gets, and who is responsible for what. The person who approves an order is responsible for it until it is delivered or the return is settled.</small></div>
@if($off)<div class="card">This module is installed on the next deploy.</div>@else
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ $errors->first() }}</div>@endif
@php $CATS = \App\Support\OrderWatch::CATS; $dt = fn($x) => $x ? \Carbon\Carbon::parse($x, 'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') : '—'; $grand = array_sum($tot); @endphp
<div class="kp" style="margin-bottom:10px">
<div><b style="color:{{ $c['on'] ? '#067647' : '#B42318' }}">{{ $c['on'] ? 'ON' : 'OFF' }}</b><small>Hourly reports</small></div>
<div><b>{{ $grand }}</b><small>Orders needing attention</small></div>
<div><b style="color:#B54708">{{ count($un['items']) }}</b><small>Nobody responsible</small></div>
<div><b>{{ $inc }}</b><small>Incomplete orders waiting</small></div>
<div><b style="font-size:14px;line-height:26px">{{ $tpl }}</b><small>WhatsApp template</small></div>
<div><b style="font-size:14px;line-height:26px">{{ $s('from','10:00') }}–{{ $s('to','21:00') }}</b><small>Report hours (Dhaka)</small></div>
</div>

<div class="card"><h2>By staff <span class="mu">(calculated from the orders right now)</span></h2>
<div style="overflow-x:auto"><table><tr><th>Staff</th><th>Number</th>@foreach($CATS as $k => $ct)<th class="n" title="{{ $ct[0] }}">{{ $ct[4] }}</th>@endforeach<th class="n">Total</th><th class="n">Waiting for a follow-up time</th><th></th></tr>
@foreach($staff as $st)@php $d = $per[$st->user_id]['d']; @endphp<tr><td><b>{{ $st->name }}</b>@if(!$st->active) <span class="pill" style="background:#FEE4E2;color:#B42318">off</span>@endif</td><td>{{ $st->phone ? '0'.substr($st->phone, 3) : '—' }}</td>
@foreach($CATS as $k => $ct)<td class="n">{{ $d['counts'][$k] ?? '' }}</td>@endforeach<td class="n"><b>{{ $d['total'] }}</b></td><td class="n">{{ $d['parked'] ?: '' }}</td><td><a href="/admin/my-orders?user={{ $st->user_id }}">Open list</a></td></tr>@endforeach
<tr style="background:#FFFAEB"><td><b>Nobody responsible</b><br><small class="mu">not approved yet, or approved by AI</small></td><td>—</td>@foreach($CATS as $k => $ct)<td class="n">{{ $un['counts'][$k] ?? '' }}</td>@endforeach<td class="n"><b>{{ $un['total'] }}</b></td><td class="n">{{ $un['parked'] ?: '' }}</td><td><a href="/admin/my-orders?user=0">Open list</a></td></tr>
@foreach($others as $oid => $n)<tr><td>{{ $names[$oid] ?? 'Staff #'.$oid }} <span class="pill">not on the report list</span></td><td>—</td><td colspan="{{ count($CATS) }}" class="mu">Responsible for {{ $n }} order(s) that need attention, but gets no report. Add a number below.</td><td class="n"><b>{{ $n }}</b></td><td></td><td><a href="/admin/my-orders?user={{ $oid }}">Open list</a></td></tr>@endforeach
<tr><th>All</th><th></th>@foreach($CATS as $k => $ct)<th class="n">{{ $tot[$k] ?? 0 }}</th>@endforeach<th class="n">{{ $grand }}</th><th></th><th></th></tr></table></div>
<small class="mu" style="display:block;margin-top:6px">To compare: Late delivery = orders in status Late Delivery (less those on courier hold or failed delivery, which are counted in the first column). Orders with a courier hold or failed delivery come from the courier's last status.</small></div>

<div class="card"><h2>Report preview <span class="mu">(what each person would get now; nothing is sent from here)</span></h2>
@forelse($staff as $st)@php $m = $per[$st->user_id]['msg']; @endphp<div style="margin-bottom:10px"><b>{{ $st->name }}</b> <span class="mu">→ {{ $st->phone ? '0'.substr($st->phone, 3) : 'no number' }}</span>
@if($m)<pre>{{ $m[0] }}</pre><small class="mu">Outside the 24-hour window the template is used with this line: {{ $m[1] }}<br>SMS version ({{ \App\Support\SmsText::count($m[2])[1] }} characters, {{ \App\Support\SmsText::count($m[2])[0] }} part(s)): {{ $m[2] }}</small>
<form method="post" action="/admin/order-watch/act/test" class="f" style="margin-top:6px">@csrf<input type="hidden" name="user_id" value="{{ $st->user_id }}"><label>Send this report to a test number<br><input type="text" name="phone" value="01884808080" style="width:140px"></label><button class="btn g" type="submit">Send test</button></form>
@else<div class="mu">Nothing to report, so no message would be sent.</div>@endif</div>@empty<div class="mu">No staff on the report list yet. Add them below.</div>@endforelse</div>

<div class="card"><h2>Settings</h2>
<form method="post" action="/admin/order-watch/settings">@csrf<div class="f">
<label><input type="checkbox" name="on" value="1" @checked($c['on'])> Hourly reports on</label>
<label>From<br><input type="text" name="from" value="{{ $s('from','10:00') }}" style="width:70px"></label>
<label>Until<br><input type="text" name="to" value="{{ $s('to','21:00') }}" style="width:70px"></label>
<label>Channel<br><select name="channel"><option value="whatsapp" @selected($c['channel']==='whatsapp')>WhatsApp</option><option value="sms" @selected($c['channel']==='sms')>SMS</option></select></label>
<label style="padding-bottom:6px"><input type="checkbox" name="sms_fallback" value="1" @checked($c['sms_fallback'])> Send by SMS when WhatsApp fails</label>
<label>Retries of a failed report<br><input type="number" name="retries" min="0" max="3" value="{{ $c['retries'] }}" style="width:70px"></label></div>
<div style="margin-top:8px;font-size:12px;color:#374151;font-weight:600">An order is listed after this many hours in the state (0 = at once):</div>
<div class="f" style="margin-top:4px">@foreach($CATS as $k => $ct)<label>{{ $ct[4] }}<br><input type="number" step="0.5" min="0" max="720" name="lim_{{ $k }}" value="{{ rtrim(rtrim(number_format($c['lim'][$k], 1, '.', ''), '0'), '.') }}" style="width:80px"></label>@endforeach
<label>Incomplete order<br><input type="number" step="0.5" min="0" max="720" name="lim_incomplete" value="{{ rtrim(rtrim(number_format($c['inc'], 1, '.', ''), '0'), '.') }}" style="width:80px"></label></div>
<button class="btn" type="submit" style="margin-top:8px">Save</button></form>
<div style="border-top:1px solid #E5E7EB;margin-top:10px;padding-top:8px">Template <code>{{ \App\Support\OrderWatch::TPL }}</code> · status: <b>{{ $tpl }}</b> <span class="mu">(used when the person has not written to the business number in the last 24 hours)</span><pre>{{ \App\Support\OrderWatch::BODY }}</pre>
<div style="display:flex;gap:8px;margin-top:6px"><form method="post" action="/admin/order-watch/act/submit">@csrf<button class="btn" type="submit">Submit to Meta</button></form><form method="post" action="/admin/order-watch/act/sync">@csrf<button class="btn g" type="submit">Refresh status</button></form></div></div></div>

<div class="card"><h2>Staff who get a report</h2>
<div style="overflow-x:auto"><table><tr><th>Staff</th><th>WhatsApp / SMS number</th><th>Report</th><th></th></tr>
@foreach($staff as $st)<tr><td>{{ $st->name }}</td><td colspan="3"><form method="post" action="/admin/order-watch/staff" class="f">@csrf<input type="hidden" name="user_id" value="{{ $st->user_id }}"><input type="text" name="phone" value="{{ $st->phone ? '0'.substr($st->phone, 3) : '' }}" style="width:140px"><label><input type="checkbox" name="active" value="1" @checked($st->active)> on</label><button class="btn g" name="do" value="save" type="submit">Save</button><button class="btn g" name="do" value="remove" type="submit">Remove</button></form></td></tr>@endforeach
<tr><td colspan="4"><form method="post" action="/admin/order-watch/staff" class="f">@csrf<label>Add staff<br><select name="user_id">@foreach($users as $u)@if(!isset($staff[$u->id]))<option value="{{ $u->id }}">{{ $u->name }}</option>@endif @endforeach</select></label><label>Number<br><input type="text" name="phone" placeholder="01XXXXXXXXX" style="width:140px"></label><input type="hidden" name="active" value="1"><button class="btn" name="do" value="save" type="submit">Add</button></form></td></tr></table></div></div>

<div class="card"><h2>Change who is responsible</h2>
<form method="post" action="/admin/order-watch/assign" class="f">@csrf<label>Order (invoice number)<br><input type="text" name="order" style="width:150px" required></label><label>Give to<br><select name="user_id"><option value="">Nobody (unassigned list)</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label><label>Reason<br><input type="text" name="reason" style="width:300px" required minlength="3"></label><button class="btn" type="submit">Change</button></form>
@if($olog->count())<details style="margin-top:8px"><summary style="cursor:pointer;font-size:12.5px;font-weight:600">History of changes ({{ $olog->count() }})</summary><table><tr><th>When</th><th>Order</th><th>From</th><th>To</th><th>By</th><th>Reason</th></tr>
@foreach($olog as $l)<tr><td style="white-space:nowrap">{{ $dt($l->created_at) }}</td><td>{{ \App\Support\Inv::show($l->invoice) }}</td><td>{{ $l->from_id ? ($names[$l->from_id] ?? '#'.$l->from_id) : 'Nobody' }}</td><td>{{ $l->to_id ? ($names[$l->to_id] ?? '#'.$l->to_id) : 'Nobody' }}</td><td>{{ $l->by_name }}</td><td>{{ $l->reason }}</td></tr>@endforeach</table></details>@endif</div>

<div class="card"><h2>Reports sent <span class="mu">(last 80)</span></h2>@if($reports->isEmpty())<div class="mu">No report has been sent yet.</div>@else
<div style="overflow-x:auto;max-height:480px"><table><tr><th>Hour</th><th>Staff</th><th>Number</th><th>Channel</th><th>Result</th><th>Delivery</th><th class="n">Items</th><th>Text</th></tr>
@foreach($reports as $x)<tr><td style="white-space:nowrap">{{ $dt($x->created_at) }}</td><td>{{ $x->name }}</td><td>{{ $x->phone ? '0'.substr($x->phone, 3) : '—' }}</td><td>{{ $x->channel ?: '—' }}</td>
<td><span class="pill" style="{{ $x->status==='failed' ? 'background:#FEE4E2;color:#B42318' : ($x->status==='sent' ? 'background:#E7F8EE;color:#0A6B3B' : '') }}">{{ ucfirst($x->status) }}</span>@if($x->error)<br><small style="color:#B42318">{{ $x->error }}</small>@endif @if($x->attempts > 1)<br><small class="mu">{{ $x->attempts }} tries</small>@endif</td>
<td>{{ $x->wamid ? ucfirst($rcpt[$x->wamid] ?? 'sent') : '—' }}</td><td class="n">{{ $x->total }}</td><td style="max-width:420px;white-space:pre-line"><details><summary style="cursor:pointer">{{ \Illuminate\Support\Str::limit($x->body, 60) }}</summary>{{ $x->body }}</details></td></tr>@endforeach</table></div>@endif</div>
@endif
</div>
@endsection
