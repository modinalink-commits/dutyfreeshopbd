@extends('layout')@section('title','Address Check')
@section('body')
<style>.ac input,.ac select{box-sizing:border-box;height:28px;font-size:12px;padding:0 8px;border:1px solid #9CA3AF;border-radius:8px;background:#fff;margin:0}.ac button{height:28px;padding:0 12px;font-size:12px;font-weight:600;border-radius:8px;border:1px solid #F95505;background:#F95505;color:#fff;cursor:pointer}.ac td{vertical-align:top;font-size:12px}.ac .ad{max-width:420px;white-space:normal;color:#0C161F}.ac .mut{color:#6B7280}@media(max-width:700px){.ac form.row{flex-direction:column;align-items:stretch}.ac input,.ac select,.ac button{height:40px;font-size:14px;width:100%}}</style>
<div class="ac">
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Address Check @if($rows)<small style="font-weight:700;color:#B45309">{{ $rows->total() }}</small>@endif</h1><span class="mut" style="font-size:13px">Orders where the customer's address does not clearly name a district. The order is accepted as normal. Read the address (call the customer if needed) and choose the district. The address text and the delivery charge are not changed here.</span></div><a class="btn g" href="/admin/analytics/area-orders/dhaka?view=1">Dhaka areas</a> <a class="btn g" href="/admin/orders">All orders</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
@if(!$ready)<div class="card">This list will start working in a moment (the update is still being applied).</div>@else
<form method="get" class="card" style="display:flex;gap:6px;padding:6px 8px"><input name="q" value="{{ request('q') }}" placeholder="Invoice, phone, address" style="flex:1"><button type="submit" style="background:#F3F4F6;color:#0C161F;border-color:#9CA3AF">Search</button></form>
<datalist id="acd">@foreach($districts as $d)<option value="{{ $d }}">@endforeach</datalist>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Address as written</th><th>Why it needs a check</th><th>Courier price zone</th><th>Set district</th></tr></thead><tbody>
@forelse($rows as $o)<tr>
<td style="white-space:nowrap"><a href="/admin/orders/{{ $o->id }}"><b>{{ \App\Support\Inv::show($o->invoice) }}</b></a><br><span class="mut">{{ $o->status }} · {{ \Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M h:i A') }}</span></td>
<td style="white-space:nowrap">{{ $o->customer_name }}<br><span class="mut">{{ $o->phone }}</span></td>
<td class="ad">{{ $o->address }}</td><td class="mut" style="max-width:220px;white-space:normal">{{ $o->addr_note ?: '—' }}</td><td class="mut">{{ $o->delivery_zone ?: '—' }}</td>
<td>@if($canAct)<form class="row" method="post" action="/admin/address-check/{{ $o->id }}" style="display:flex;gap:4px;margin:0">@csrf<input name="district" list="acd" placeholder="District" required style="width:130px" autocomplete="off"><input name="area" placeholder="Area (optional)" maxlength="80" style="width:130px"><button type="submit">Save</button></form>@else<span class="mut">View only</span>@endif</td>
</tr>@empty<tr><td colspan="6" style="text-align:center;color:#6B7280;padding:24px">No address is waiting for a check.</td></tr>@endforelse</tbody></table>{{ $rows->links('pagination.global') }}</div>@endif
</div>
@endsection
