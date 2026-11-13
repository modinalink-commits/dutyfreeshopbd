@extends('layout')@section('title','Vendor Orders')
@section('body')
<h1>Vendor Orders</h1>
<p style="color:#6B7280;font-size:13px;margin:0 0 10px">Order lines sold from source shops. Staff only: customers never see the vendor or the link.</p>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">@foreach(['todo'=>'To order','ordered'=>'Ordered','shipped'=>'Shipped by vendor','received'=>'Received','all'=>'All'] as $k=>$l)<a class="btn {{ $st===$k ? '' : 'g' }}" href="?status={{ $k }}">{{ $l }}@if($k!=='all') ({{ (int)($counts[$k] ?? 0) }})@endif</a>@endforeach</div>
@forelse($rows as $vendor => $lines)
<div class="card" style="overflow-x:auto"><b>{{ $vendor }}</b> <span style="color:#6B7280;font-size:12px">· {{ $lines->count() }} line(s) · vendor cost ৳{{ number_format($lines->sum(fn($l) => (float)$l->src_price * (int)$l->qty)) }}</span>
<table style="margin-top:8px"><thead><tr><th>Order</th><th>Product</th><th>Qty</th><th>Vendor price</th><th>Our price</th><th>Customer</th><th>Status</th></tr></thead><tbody>
@foreach($lines as $l)<tr>
<td><a href="/admin/orders/{{ $l->order_id }}">{{ $l->invoice }}</a><br><small style="color:#6B7280">{{ $l->ostatus }}</small></td>
<td><b>{{ $l->name }}</b>@if($l->src_url)<br><a href="{{ $l->src_url }}" target="_blank" rel="noopener noreferrer" style="font-size:12px">Open vendor link ↗</a>@endif @if($l->src_sku)<small style="color:#6B7280"> · {{ $l->src_sku }}</small>@endif</td>
<td>{{ $l->qty }}</td><td>৳{{ number_format((float)$l->src_price) }}</td><td>৳{{ number_format((float)$l->price) }}@if((float)$l->src_price > (float)$l->price)<br><span style="color:#B91C1C;font-size:11px;font-weight:700">below vendor cost</span>@endif</td>
<td style="font-size:12px">{{ $l->cname }}<br>{{ $l->cphone }}</td>
<td><form method="post" action="/admin/vendor-orders/{{ $l->id }}" style="display:flex;gap:4px;flex-wrap:wrap">@csrf
<select name="vendor_status" style="max-width:120px"><option value="">To order</option>@foreach(['ordered'=>'Ordered','shipped'=>'Shipped','received'=>'Received'] as $k=>$t)<option value="{{ $k }}" {{ $l->vendor_status===$k?'selected':'' }}>{{ $t }}</option>@endforeach</select>
<input name="vendor_ref" value="{{ $l->vendor_ref }}" placeholder="Vendor order / tracking" maxlength="160" style="max-width:160px">
@if(!empty($src['on']) && $src['users']->count())@php $__sb = $l->sourced_by ?: ($src['byStore'][(int)$l->src_store_id] ?? null); $__lock = ($l->sourced_status ?? null) === 'approved' && !$src['checker']; @endphp
<span style="flex-basis:100%;display:flex;gap:4px;flex-wrap:wrap;align-items:center;font-size:12px;color:#6B7280">Sourced by
<select name="sourced_by" style="max-width:130px" {{ $__lock ? 'disabled' : '' }}><option value="">Company</option>@foreach($src['users'] as $uid => $un)@if($src['checker'] || (int)$uid === (int)$src['me'] || (int)$uid === (int)$l->sourced_by)<option value="{{ $uid }}" {{ (int)$__sb === (int)$uid ? 'selected' : '' }}>{{ $un }}</option>@endif @endforeach</select>
<input name="sourced_cost" type="number" step="0.01" min="0" value="{{ $l->sourced_cost !== null ? (float)$l->sourced_cost : '' }}" placeholder="Buy price / unit" style="max-width:120px" {{ $__lock ? 'disabled' : '' }}>
<input name="sourced_vendor" value="{{ $l->sourced_vendor ?? $l->src_store }}" placeholder="Supplier bought from" maxlength="160" style="max-width:150px" aria-label="Supplier bought from" {{ $__lock ? 'disabled' : '' }}>
@if($l->sourced_by)<b style="color:{{ ['approved'=>'#15803D','rejected'=>'#B91C1C'][$l->sourced_status] ?? '#B45309' }}">{{ ucfirst($l->sourced_status ?: 'pending') }}</b>@elseif($__sb)<span>(suggested, press Save)</span>@endif</span>@endif
<button>Save</button></form></td></tr>@endforeach
</tbody></table></div>
@empty<div class="card">No lines here yet.</div>@endforelse
@endsection
