@extends('layout')@section('title','Orders')
@section('body')<div style="display:flex;gap:10px;align-items:center;margin-bottom:12px"><h1 style="margin:0;flex:1">Order Management</h1><a class="btn g" href="/admin/orders-export.csv">Download CSV</a><a class="btn" href="/admin/orders/new">Create Order</a></div>
<div class="chips"><a class="chip {{ request('status')?'':'on' }}" href="/admin/orders">All · {{ $counts->sum() }}</a>
@foreach(\App\Models\Mother::STAGES as $s)<a class="chip {{ in_array($s,\App\Models\Mother::RED)?'red':'' }} {{ request('status')==$s?'on':'' }}" href="/admin/orders?status={{ urlencode($s) }}">{{ $s }} · {{ $counts[$s] ?? 0 }}</a>@endforeach</div>
<form class="card" style="display:flex;gap:8px"><input name="q" value="{{ request('q') }}" placeholder="type invoice or customer phone"><button>Search</button></form>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Shipping</th><th>Status</th><th>Courier</th><th>Date</th></tr></thead><tbody>
@forelse($orders as $o)<tr><td><a href="/admin/orders/{{ $o->id }}">{{ $o->invoice }}</a></td><td>{{ $o->customer_name }}<br><small>{{ $o->phone }}</small></td><td>৳{{ number_format($o->total) }}</td><td>৳{{ number_format($o->shipping) }}</td><td><span class="pill {{ in_array($o->status,\App\Models\Mother::RED)?'red':'' }}">{{ $o->status }}</span></td><td>{{ $o->courier ?: '—' }}</td><td>{{ $o->created_at }}</td></tr>
@empty<tr><td colspan="7">No orders yet.</td></tr>@endforelse</tbody></table>{{ $orders->links() }}</div>@endsection
