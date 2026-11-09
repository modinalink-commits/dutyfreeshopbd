@extends('layout')@section('title','Pathao Status Map')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Pathao Status Map</h1><span style="font-size:13px;color:#6B7280">Every status Pathao sends, and what it does here. Change a row and save; no code change needed. A status we have not seen before is added automatically and waits for your decision, and its orders are flagged "Courier Status Mapping Required".</span></div>
<form method="post" action="/admin/courier/status-map/recheck" style="margin:0">@csrf<button class="g" type="submit">Re-check flagged parcels ({{ $flagged }})</button></form><a class="btn g" href="/admin/courier-dashboard">Courier Dashboard</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form method="post" action="/admin/courier/status-map" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">@csrf<div style="flex:1;min-width:240px"><label>Add a Pathao status by hand</label><input name="raw_label" placeholder="exactly as Pathao shows it" maxlength="160"></div><button>Add</button></form>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Pathao status</th><th>Seen</th><th>Pathao Outcome</th><th>What the order does</th><th>Courier stage</th><th>Note</th><th></th></tr></thead><tbody>
@forelse($rows as $r)<tr @if($r->needs_mapping) style="background:#FEF3C7" @endif><form method="post" action="/admin/courier/status-map/{{ $r->id }}">@csrf
<td style="min-width:170px"><b>{{ $r->raw_label }}</b>@if($r->needs_mapping)<br><small style="color:#B45309;font-weight:800">Needs mapping</small>@elseif(!$r->confirmed)<br><small style="color:#6B7280">assumed</small>@endif</td>
<td style="font-size:12px;white-space:nowrap">{{ number_format($r->seen_count) }}@if($r->last_seen_at)<br><span style="color:#6B7280">{{ \Carbon\Carbon::parse($r->last_seen_at)->timezone('Asia/Dhaka')->format('d M H:i') }}</span>@endif</td>
<td><select name="outcome"><option value="">—</option>@foreach($outcomes as $o)<option {{ $r->outcome===$o?'selected':'' }}>{{ $o }}</option>@endforeach</select></td>
<td><select name="action">@foreach($actions as $k=>$l)<option value="{{ $k }}" {{ $r->action===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></td>
<td><select name="stage"><option value="">auto</option>@foreach($stages as $s)<option {{ $r->stage===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select></td>
<td><input name="note" value="{{ $r->note }}" maxlength="500" style="min-width:180px"></td>
<td style="white-space:nowrap"><button type="submit">Save</button></form><form method="post" action="/admin/courier/status-map/{{ $r->id }}/delete" style="display:inline" onsubmit="return confirm('Remove this status from the map?')">@csrf<button class="g" type="submit">✕</button></form></td></tr>
@empty<tr><td colspan="7">No statuses yet.</td></tr>@endforelse</tbody></table></div>
<div class="card" style="font-size:13px;color:#4B5563;line-height:1.7"><b>How it works</b><br>Order stages are set only by what you choose here. After shipment the order status is only Shipment, Partial Delivered, Delivered, Late Delivery, Return Pending, or Returned. Partial Return and Regular Return set Return Pending; Exchange changes nothing and only flags the parcel for manual review; stock goes back only when the goods are confirmed received at the warehouse. Pathao Outcome (Partial Return, Exchange, Regular Return) is Pathao's own label and is kept separate from the order status.</div>
@endsection
