@extends('layout')@section('title','Order History')
@section('body')
@include('report_grid_assets')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Order History</h1><span style="font-size:13px;color:#6B7280">Orders grouped by status (delivered, returned, cancelled and more) with totals.</span></div>
<a class="btn g" href="?from={{ $from }}&to={{ $to }}&status={{ urlencode($status) }}&q={{ urlencode($q) }}&export=csv">Export Excel (CSV)</a><a class="btn g" href="?from={{ $from }}&to={{ $to }}&status={{ urlencode($status) }}&q={{ urlencode($q) }}&export=doc">Export Word</a><button class="g" onclick="window.print()">Print / PDF</button></div>
<form class="bar" method="get"><div><label>From</label><input type="date" name="from" value="{{ $from }}"></div><div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
<div><label>Status</label><select name="status"><option value="all">All</option>@foreach($statuses as $s)<option {{ $status===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select></div>
<div><label>Search invoice, name, phone</label><input name="q" value="{{ $q }}" placeholder="search" style="min-width:200px"></div>
<div><label>Rows</label><select name="per">@foreach([500,1000,2000,3000] as $n)<option {{ $per==$n?'selected':'' }}>{{ $n }}</option>@endforeach</select></div><div><button>Search</button> <a class="btn g" href="/admin/order-history-report">Reset</a></div></form>
<div class="rp-body"><div class="noprint" style="font-size:12px;color:#6B7280;margin-bottom:6px">Showing {{ number_format($g['n']) }} of {{ number_format($total) }} orders &middot; {{ $from }} &rarr; {{ $to }} &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.add('hide'));return false">collapse all</a> &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.remove('hide'));return false">expand all</a></div>
<div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Phone</th><th>Payment</th><th class="n">Discount</th><th class="n">Delivery</th><th class="n">Total</th></tr></thead><tbody>
@forelse($groups as $i => $gr)
<tr class="gh" onclick="document.querySelectorAll('.d{{ $i }}').forEach(e=>e.classList.toggle('hide'))"><td colspan="8">&#9660; Status: {{ $gr['name'] }} &nbsp;|&nbsp; Orders: {{ number_format($gr['n']) }} &nbsp;|&nbsp; Total: {{ number_format($gr['total'],2) }}</td></tr>
@foreach($gr['rows'] as $x)<tr class="det d{{ $i }}"><td>{{ $x->invoice }}</td><td>{{ substr((string)$x->created_at,0,10) }}</td><td>{{ $x->customer_name }}</td><td>{{ $x->phone }}</td><td>{{ $x->payment_method }}</td><td class="n">{{ number_format((float)$x->discount,2) }}</td><td class="n">{{ number_format((float)$x->shipping,2) }}</td><td class="n">{{ number_format((float)$x->total,2) }}</td></tr>@endforeach
<tr class="st det d{{ $i }}"><td colspan="5" class="n">Total ({{ $gr['name'] }}, {{ number_format($gr['n']) }} orders)</td><td class="n">{{ number_format($gr['disc'],2) }}</td><td class="n">{{ number_format($gr['ship'],2) }}</td><td class="n">{{ number_format($gr['total'],2) }}</td></tr>
@empty<tr><td colspan="8" style="padding:14px">No orders for this filter.</td></tr>@endforelse
<tr class="gt"><td colspan="5">Grand Total ({{ number_format($g['n']) }} orders)</td><td class="n">{{ number_format($g['disc'],2) }}</td><td class="n">{{ number_format($g['ship'],2) }}</td><td class="n">{{ number_format($g['total'],2) }}</td></tr>
</tbody></table></div></div>
@include('report_grid_js')
@endsection
