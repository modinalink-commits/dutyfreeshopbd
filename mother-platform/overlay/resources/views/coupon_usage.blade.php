@extends('layout')@section('title','Coupon usage')
@section('body')
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px"><h1 style="margin:0;flex:1">{{ $c->code }} usage</h1><a class="btn g" href="/admin/coupons/{{ $c->id }}/edit">Edit</a><a class="btn g" href="/admin/coupons">Back</a></div>
<div class="grid" style="margin-bottom:12px"><div class="card k"><b>{{ $n }}</b><span>Orders (cancelled not counted)</span></div><div class="card k"><b>{{ $people }}</b><span>Different customers</span></div><div class="card k"><b>৳{{ number_format($disc) }}</b><span>Discount given</span></div><div class="card k"><b>৳{{ number_format($rev) }}</b><span>Order value</span></div></div>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Mobile</th><th>Status</th><th>Discount</th><th>Total</th><th>Date</th></tr></thead><tbody>
@forelse($orders as $o)<tr><td><a href="/admin/orders/{{ $o->id }}" style="color:#1D4ED8;font-weight:600">{{ $o->invoice }}</a></td><td>{{ $o->customer_name }}</td><td>{{ $o->phone }}</td><td>{{ $o->status }}</td><td>৳{{ number_format($o->discount) }}</td><td>৳{{ number_format($o->total) }}</td><td>{{ \Carbon\Carbon::parse($o->created_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td></tr>
@empty<tr><td colspan="7">No order has used this coupon yet.</td></tr>@endforelse</tbody></table></div>
@endsection
