@extends('layout')@section('title','Checkout Discount Performance')
@section('body')
<h1 class="h">Checkout Discount Performance</h1>
<p style="color:#6B7280;margin-top:0;max-width:860px">From the discount snapshot saved with each order at checkout. Only orders placed after this feature went live are counted ({{ $n }} order(s) in the period). Eligible = distinct customers who were offered the discount; Conversion = applied ÷ offered.</p>
<form method="get" class="card" style="display:flex;gap:8px;align-items:center"><select name="days">@foreach([7,30,90,180,365] as $d)<option value="{{ $d }}" @selected($days==$d)>Last {{ $d }} days</option>@endforeach</select><button class="btn">Filter</button> <a href="?days={{ $days }}&csv=1">Export CSV</a> <button type="button" onclick="window.print()">Print</button></form>
<div class="card" style="overflow:auto"><table><thead><tr><th>Discount type</th><th>Eligible customers</th><th>Offered</th><th>Applied</th><th>Orders</th><th>Revenue</th><th>Average discount</th><th>Total discount</th><th>Conversion</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x['type'] }}</td><td>{{ $x['eligible'] }}</td><td>{{ $x['offered'] }}</td><td>{{ $x['applied'] }}</td><td>{{ $x['orders'] }}</td><td>৳{{ number_format($x['revenue']) }}</td><td>৳{{ number_format($x['avg']) }}</td><td>৳{{ number_format($x['total']) }}</td><td>{{ $x['offered'] ? round($x['applied'] / $x['offered'] * 100, 1).'%' : '—' }}</td></tr>
@empty<tr><td colspan="9">No checkout discount data yet. It fills as new orders are placed.</td></tr>@endforelse</tbody></table></div>
@endsection
