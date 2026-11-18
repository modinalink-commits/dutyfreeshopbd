@extends('layout')@section('title','Cancel reasons')
@section('body')
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px"><h1 style="margin:0;flex:1">Cancel reasons</h1>
<form method="get" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><input type="date" name="from" value="{{ $from }}"><input type="date" name="to" value="{{ $to }}"><button class="btn">Show</button><a class="btn g" href="?from={{ $from }}&to={{ $to }}&export=csv">CSV</a></form></div>
@php $real = $g['n'] - $g['repeats']; @endphp
<div class="grid" style="margin-bottom:12px"><div class="card k"><b>{{ number_format($g['n']) }}</b><span>Cancelled orders · {{ $all ? round($g['n'] / $all * 100, 1) : 0 }}% of {{ number_format($all) }} orders</span></div><div class="card k"><b>{{ number_format($g['repeats']) }}</b><span>Repeat of another order (same phone, 48 hours)</span></div><div class="card k"><b>{{ number_format($real) }}</b><span>Real lost orders · {{ $all ? round($real / $all * 100, 1) : 0 }}%</span></div><div class="card k"><b>৳{{ number_format($g['total']) }}</b><span>Cancelled order value</span></div></div>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Reason</th><th style="text-align:right">Cancelled</th><th style="text-align:right">Share</th><th style="text-align:right">Repeat of another order</th><th style="text-align:right">From website</th><th style="text-align:right">Order value</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x->label }}</td><td style="text-align:right">{{ number_format($x->n) }}</td><td style="text-align:right">{{ $g['n'] ? round($x->n / $g['n'] * 100, 1) : 0 }}%</td><td style="text-align:right">{{ number_format($x->repeats) }}</td><td style="text-align:right">{{ number_format($x->web) }}</td><td style="text-align:right">৳{{ number_format($x->total) }}</td></tr>
@empty<tr><td colspan="6">No cancelled order in this period.</td></tr>@endforelse</tbody></table></div>
<p style="color:#667085;font-size:12px;margin-top:8px">Dates are Dhaka time, by the day the order was placed. "Repeat" means the same phone has another order that was not cancelled within 48 hours.</p>
@endsection
