@extends('layout')@section('title','Courier Report')
@section('body')
@include('report_grid_assets')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Courier-wise Delivery &amp; COD</h1><span style="font-size:13px;color:#6B7280">Parcels sent to each courier with COD amount, fee and delivery outcome.</span></div>
<a class="btn g" href="?from={{ $from }}&to={{ $to }}&courier={{ urlencode($courier) }}&q={{ urlencode($q) }}&export=csv">Export Excel (CSV)</a><a class="btn g" href="?from={{ $from }}&to={{ $to }}&courier={{ urlencode($courier) }}&q={{ urlencode($q) }}&export=doc">Export Word</a><button class="g" onclick="window.print()">Print / PDF</button></div>
<form class="bar" method="get"><div><label>Sent from</label><input type="date" name="from" value="{{ $from }}"></div><div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
<div><label>Courier</label><select name="courier"><option value="">All couriers</option>@foreach($couriers as $c)<option {{ $courier===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select></div>
<div><label>Search invoice, name, mobile, consignment</label><input name="q" value="{{ $q }}" placeholder="search" style="min-width:220px"></div><div><button>Search</button> <a class="btn g" href="/admin/courier-report">Reset</a></div></form>
<div class="rp-body"><div class="noprint" style="font-size:12px;color:#6B7280;margin-bottom:6px">{{ $g['n'] }} parcels &middot; {{ $from }} &rarr; {{ $to }}@if($capped) &middot; showing the first 3,000 only, narrow the dates @endif &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.add('hide'));return false">collapse all</a> &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.remove('hide'));return false">expand all</a></div>
<div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Invoice</th><th>Sent</th><th>Customer</th><th>Consignment</th><th>Order status</th><th>Courier status</th><th class="n">COD amount</th><th class="n">Courier fee</th></tr></thead><tbody>
@forelse($groups as $i => $gr)
<tr class="gh" onclick="document.querySelectorAll('.d{{ $i }}').forEach(e=>e.classList.toggle('hide'))"><td colspan="8">&#9660; Courier: {{ $gr['name'] }} &nbsp;|&nbsp; Parcels: {{ $gr['n'] }} &nbsp;|&nbsp; Delivered: <span class="p">{{ $gr['del'] }}</span> &nbsp;|&nbsp; Returned/cancelled: <span class="l">{{ $gr['ret'] }}</span> &nbsp;|&nbsp; On the way: {{ $gr['pend'] }}</td></tr>
@foreach($gr['rows'] as $x)<tr class="det d{{ $i }}"><td>{{ $x['inv'] }}</td><td>{{ $x['d'] }}</td><td>{{ $x['c'] }} {{ $x['ph'] }}</td><td>{{ $x['cid'] }}</td><td>{{ $x['st'] }}</td><td>{{ $x['cs'] }}</td><td class="n">{{ number_format($x['cod'],2) }}</td><td class="n">{{ number_format($x['fee'],2) }}</td></tr>@endforeach
<tr class="st det d{{ $i }}"><td colspan="6" class="n">Total (delivered COD {{ number_format($gr['delcod'],2) }})</td><td class="n">{{ number_format($gr['cod'],2) }}</td><td class="n">{{ number_format($gr['fee'],2) }}</td></tr>
@empty<tr><td colspan="8" style="padding:14px">No parcels for this filter.</td></tr>@endforelse
<tr class="gt"><td colspan="6">Grand Total ({{ $g['n'] }} parcels)</td><td class="n">{{ number_format($g['cod'],2) }}</td><td class="n">{{ number_format($g['fee'],2) }}</td></tr>
<tr class="gt"><td colspan="8">Delivered: <span class="p">{{ $g['del'] }}</span> (COD {{ number_format($g['delcod'],2) }}) &nbsp;|&nbsp; Returned/cancelled: <span class="l">{{ $g['ret'] }}</span> &nbsp;|&nbsp; On the way: {{ $g['pend'] }} &nbsp;|&nbsp; Total courier fee: {{ number_format($g['fee'],2) }}</td></tr>
</tbody></table></div></div>
@include('report_grid_js')
@endsection
