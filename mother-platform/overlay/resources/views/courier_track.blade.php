@extends('layout')@section('title','Courier Income Tracking')
@section('body')
@php $t = $today; $d7 = \Carbon\Carbon::parse($t)->subDays(6)->toDateString(); $m0 = \Carbon\Carbon::parse($t)->startOfMonth()->toDateString(); @endphp
<h1 class="h">Courier Income Tracking</h1>
<p style="color:#6B7280;margin-top:0;max-width:860px">Every courier delivery booked to the Income book, newest first, with the courier's tracking link. Read only.</p>
<form method="get" class="card" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
<div style="flex:1;min-width:200px"><label>Search</label><input name="q" value="{{ $q }}" placeholder="Invoice / consignment / phone"></div>
<div><label>From date</label><input type="date" name="from" value="{{ $from }}"></div>
<div><label>To date</label><input type="date" name="to" value="{{ $to }}"></div>
<button class="btn">Filter</button>
<a class="btn g" href="/admin/courier-income-tracking?from={{ $t }}&to={{ $t }}">Today</a>
<a class="btn g" href="/admin/courier-income-tracking?from={{ $d7 }}&to={{ $t }}">Last 7 days</a>
<a class="btn g" href="/admin/courier-income-tracking?from={{ $m0 }}&to={{ $t }}">This month</a>
<a class="btn g" href="/admin/courier-income-tracking">Reset</a>
</form>
<div class="card" style="display:flex;gap:24px;flex-wrap:wrap"><div><small>Deliveries shown</small><div style="font-size:20px;font-weight:800">{{ number_format($cnt) }}</div></div><div><small>Total income in this filter</small><div style="font-size:20px;font-weight:800;color:#166534">৳{{ number_format($sum, 2) }}</div></div></div>
<div class="card" style="overflow:auto"><table><thead><tr><th>Invoice</th><th>Courier</th><th>Consignment</th><th>Account</th><th>Income</th><th>Date</th><th>Booked at</th><th>Detail</th><th>Track</th></tr></thead><tbody>
@forelse(($rows ?? []) as $x)<tr><td>{{ $x->invoice }}</td><td>{{ $x->courier ?: 'Pathao' }}</td><td>{{ $x->shown_consignment }}</td><td>{{ $x->account }}</td><td>৳{{ number_format((float)$x->amount, 2) }}</td><td>{{ $x->entry_date }}</td><td style="font-size:12px;white-space:nowrap">{{ $x->booked_at ? \Carbon\Carbon::parse($x->booked_at, 'UTC')->setTimezone('Asia/Dhaka')->format('d M Y, h:i A') : '' }}</td><td style="font-size:12px;color:#6B7280">{{ \Illuminate\Support\Str::limit(preg_replace('~ — Track: \S+~u', '', (string)$x->comment), 90) }}</td><td>@if($x->url)<a class="btn g" href="{{ $x->url }}" target="_blank" rel="noopener">Track ↗</a>@else — @endif</td></tr>
@empty<tr><td colspan="9">No courier income entries found for this filter.</td></tr>@endforelse</tbody></table>
@if($rows && method_exists($rows, 'links')){{ $rows->links('pagination.global') }}@endif</div>
@endsection
