@extends('layout')@section('title','Courier Income Tracking')
@section('body')
<h1 class="h">Courier Income Tracking</h1>
<p style="color:#6B7280;margin-top:0;max-width:860px">Every courier delivery booked to the Income book, with the courier's tracking link. Read only.</p>
<form method="get" class="card" style="display:flex;gap:8px;align-items:center"><input name="q" value="{{ $q }}" placeholder="Invoice / consignment / phone" style="flex:1;min-width:200px"><button class="btn">Search</button></form>
<div class="card" style="overflow:auto"><table><thead><tr><th>Invoice</th><th>Courier</th><th>Consignment</th><th>Account</th><th>Income</th><th>Date</th><th>Detail</th><th>Track</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x->invoice }}</td><td>{{ $x->courier ?: 'Pathao' }}</td><td>{{ $x->consignment_id }}</td><td>{{ $x->account }}</td><td>৳{{ number_format((float)$x->amount, 2) }}</td><td>{{ $x->entry_date }}</td><td style="font-size:12px;color:#6B7280">{{ \Illuminate\Support\Str::limit(preg_replace('~ — Track: \S+~u', '', (string)$x->comment), 90) }}</td><td>@if($x->url)<a class="btn g" href="{{ $x->url }}" target="_blank" rel="noopener">Track ↗</a>@else — @endif</td></tr>
@empty<tr><td colspan="8">No courier income entries found.</td></tr>@endforelse</tbody></table></div>
@endsection
