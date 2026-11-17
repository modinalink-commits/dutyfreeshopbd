@extends('layout')@section('title','Supplier Conversations')
@section('body')
<style>.scm .card{padding:12px 14px;margin-bottom:10px}.scm table{width:100%;border-collapse:collapse;font-size:12.5px}.scm th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.scm td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:top}.scm .f{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.scm label{font-size:12px;color:#374151}.scm input,.scm select{height:30px}.scm .mu{color:#6B7280}.scm .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:700;background:#EEF2F7;color:#0B1B33;white-space:nowrap}</style>
<div class="scm">
<div style="margin-bottom:8px"><h1 style="font-size:18px;margin:0">Supplier Conversations</h1><small class="mu">Each supplier's contact details and the messages exchanged with them (WhatsApp and Supplier Portal), with who is responsible for the conversation.</small></div>
@if($off)<div class="card">This module is installed on the next deploy.</div>@else
<div class="card"><form method="get" action="/admin/purchase/supplier-chats"><div class="f">
<label>Name or number<br><input type="text" name="q" value="{{ $f['q'] }}" placeholder="name, business, 01…" style="width:220px"></label>
<label>Responsible<br><select name="owner"><option value="">Anyone</option><option value="none" @selected($f['owner']==='none')>Nobody yet</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((string)$f['owner']===(string)$u->id)>{{ $u->name }}</option>@endforeach</select></label>
<label>Last message from<br><input type="date" name="from" value="{{ $f['from'] }}"></label>
<label>to<br><input type="date" name="to" value="{{ $f['to'] }}"></label>
<label style="padding-bottom:6px"><input type="checkbox" name="gap" value="1" @checked($f['gap']) style="height:auto"> Incomplete details only</label>
<button class="btn" type="submit">Search</button> <a class="btn g" href="/admin/purchase/supplier-chats">Clear</a></div></form></div>
<div class="card"><div class="mu" style="margin-bottom:6px">{{ count($rows) }} of {{ $total }} suppliers</div>
@if(!$rows)<div class="mu">No supplier matches.</div>@else
<div style="overflow-x:auto"><table><tr><th>Supplier</th><th>Business</th><th>Mobile</th><th>WhatsApp</th><th>Responsible</th><th>Last message</th><th>State</th><th>Details</th></tr>
@foreach($rows as $x)@php $s = $x['s']; $t = $x['t']; @endphp
<tr><td><a href="/admin/purchase/supplier-chats/{{ $s->id }}" style="font-weight:700">{{ $s->name }}</a>@if($s->contact_person)<br><small class="mu">{{ $s->contact_person }}</small>@endif</td><td>{{ $s->business_name ?: '—' }}</td><td>{{ $s->phone ?: '—' }}</td><td>{{ $s->whatsapp ?: '—' }}</td>
<td>{{ $x['owner'] ?: ($t ? 'Nobody yet' : '—') }}</td>
<td>@if($x['last']){{ \Carbon\Carbon::parse($x['last'],'UTC')->timezone('Asia/Dhaka')->format('d M Y h:i A') }}@if($t && $t->last_message)<br><small class="mu">{{ $t->last_dir==='in' ? '← ' : '→ ' }}{{ \Illuminate\Support\Str::limit($t->last_message, 70) }}</small>@endif @else<span class="mu">No message yet</span>@endif</td>
<td>@if($t && $t->awaiting_reply)<span class="pill" style="background:#FEF0C7;color:#93370D">Awaiting reply</span>@elseif($t)<span class="pill">{{ ucfirst($t->status) }}</span>@else<span class="mu">—</span>@endif @if($x['portal'])<br><small class="mu">{{ $x['portal'] }} portal message(s)</small>@endif</td>
<td>@if($x['gaps'])<small style="color:#B54708">Missing: {{ implode(', ', $x['gaps']) }}</small>@else<small class="mu">Complete</small>@endif</td></tr>@endforeach</table></div>@endif</div>
@endif
</div>
@endsection
