@extends('layout')@section('title','Late Delivery')
@section('body')
<style>
.ld .btn,.ld button{height:26px;min-height:0!important;padding:0 10px!important;font-size:11px!important;font-weight:600;border-radius:8px!important;line-height:1;border:1.5px solid #94A3B8!important;background:#F1F5F9!important;color:#0B1220!important;box-shadow:none}
.ld .ldbar{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;padding:4px 8px;background:#F1F5F9;border:1px solid #CBD5E1;border-radius:10px;overflow-x:auto}.ld .ldbar label{display:none}.ld .ldbar>div{margin:0}.ld .ldbar input,.ld .ldbar select{height:26px!important;font-size:11px!important;padding:0 8px!important;border:1px solid #CED4DA;border-radius:8px;width:auto;margin:0}.ld .ldbar input[name=q]{min-width:260px;width:100%}.ld .ldbar>div:first-child{flex:1 1 260px;min-width:200px}
.ld .ldbar button[type=submit]{background:#DBEAFE!important;color:#1E40AF!important;border-color:#2563EB!important}
.ld td form button{background:#DCFCE7!important;color:#166534!important;border-color:#16A34A!important}.ld td form input{height:26px!important;font-size:11px!important;padding:0 8px!important;border:1px solid #CED4DA;border-radius:8px}
.ld .btn:hover,.ld button:hover{filter:brightness(.95);border-color:#64748B!important}.ld td form button:hover{border-color:#15803D!important}.ld .ldbar button[type=submit]:hover{border-color:#1D4ED8!important}
.ld .dfx-btn{height:26px!important;font-size:11px!important;padding:0 10px!important;border:1.5px solid #94A3B8!important;border-radius:8px!important;background:#F1F5F9!important}
</style><div class="ld">
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Late Delivery <small style="font-weight:700;color:#B45309">{{ $rows->total() }}</small></h1><span style="font-size:13px;color:#6B7280">Shipments not delivered 72 hours after they went to the courier. Most overdue first. This list is automatic: a parcel leaves it when the courier delivers it.</span></div>
<a class="btn g" href="/admin/orders">All orders</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form method="get" class="card ldbar" style="padding:4px 8px"><div style="flex:1;min-width:220px"><label>Search</label><input name="q" value="{{ request('q') }}" placeholder="Invoice, phone, name, consignment"></div><div><label>Courier</label><select name="courier" title="Courier"><option value="">All</option>@foreach($couriers as $c)<option {{ request('courier')===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select></div><button type="submit">Filter</button></form>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Phone</th><th>Product</th><th>Courier</th><th>Tracking / Consignment</th><th>Shipment date/time</th><th>Hours in shipment</th><th>Courier status</th><th>COD</th><th>Last courier sync</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $o)@php $h = \App\Support\LateDelivery::hours($o); $sa = \App\Support\LateDelivery::since($o); $its = $items[$o->id] ?? collect(); @endphp
<tr style="background:#FFFBEB"><td style="white-space:nowrap"><a href="/admin/orders/{{ $o->id }}"><b>{{ \App\Support\Inv::show($o->invoice) }}</b></a></td><td>{{ $o->customer_name }}</td><td style="white-space:nowrap">{{ $o->phone }}</td>
<td style="font-size:12px;min-width:160px">@foreach($its->take(2) as $it){{ $it->name }} ×{{ $it->qty }}<br>@endforeach @if($its->count() > 2)<span style="color:#6B7280">+{{ $its->count() - 2 }} more</span>@endif</td>
<td>{{ $o->courier ?: '—' }}</td><td style="white-space:nowrap;font-size:12px">{{ $o->consignment_id ?: '—' }}</td>
<td style="white-space:nowrap;font-size:12px">{{ $sa ? $sa->timezone('Asia/Dhaka')->format('d M Y h:i A') : '—' }}</td>
<td style="white-space:nowrap">@if($h !== null)<span class="pill" style="background:#FEF3C7;color:#92400E;font-weight:700">{{ $h }}h since shipment</span><br><small style="color:#B45309;font-weight:700">{{ \App\Support\LateDelivery::lateBy($h) }}</small>@else — @endif</td>
<td style="font-size:12px">{{ $o->pathao_raw_status ?: ($o->courier_status ?: '—') }}</td><td style="white-space:nowrap">৳{{ number_format((float)($o->courier_cod ?? 0)) }}</td>
<td style="white-space:nowrap;font-size:12px">{{ $o->courier_synced_at ? \Carbon\Carbon::parse($o->courier_synced_at)->timezone('Asia/Dhaka')->format('d M h:i A') : '—' }}</td>
<td style="min-width:200px"><a class="btn g" href="/admin/orders/{{ $o->id }}" style="font-size:12px">Open</a>@if($canAct)<form method="post" action="/admin/late-delivery/{{ $o->id }}/follow" style="display:flex;gap:4px;margin-top:4px">@csrf<input name="note" placeholder="Follow-up note" maxlength="500" required style="font-size:12px"><button type="submit" style="font-size:12px">Save</button></form>@endif</td></tr>
@empty<tr><td colspan="12" style="text-align:center;color:#6B7280;padding:24px">No late shipments. Everything is within 72 hours or delivered.</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div>
</div>
@endsection
