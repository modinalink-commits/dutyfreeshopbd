@extends('layout')@section('title','Black Friday plan')
@section('body')
<?php $n = fn($v) => number_format((float)$v); $avg = $tot['price_sum'] > 0 ? round($tot['off_sum'] / $tot['price_sum'] * 100, 1) : 0; $link = fn($s, $c = null) => '/admin/black-friday?show=' . $s . (($c ?? $cat) !== '' ? '&cat=' . urlencode($c ?? $cat) : ''); ?>
<div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;flex-wrap:wrap"><h1 style="margin:0;flex:1">Black Friday plan</h1><a class="btn g" href="/admin/black-friday.csv">Download full list (CSV)</a></div>
<p style="margin:0 0 12px;font-size:13px;color:#6B7280">27 to 30 November 2026. Black Friday price = purchase cost + {{ rtrim(rtrim(number_format($pct, 2), '0'), '.') }}% profit, rounded up to a whole taka. The normal price is kept and comes back by itself after the end time.</p>
@if(session('ok'))
<div class="card" style="color:#166534;margin-bottom:12px">{{ session('ok') }}</div>
@endif
<div class="card" style="margin-bottom:12px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
<div style="flex:1 1 320px"><b style="font-size:15px">Campaign: {{ $running ? 'RUNNING' : ($on ? 'ON, waiting for the start time' : 'OFF') }}</b>
<div style="font-size:13px;color:#6B7280;margin-top:4px">{{ $from->format('j M Y, g:i A') }} to {{ $to->format('j M Y, g:i A') }} (Dhaka). Takes part: Cigarette and Vape only, every product with a saving{{ $minOff > 0 ? ' of ' . rtrim(rtrim(number_format($minOff, 1), '0'), '.') . '% or more' : '' }}, except the Best Sellers (own pack offer): <b style="color:#0C161F">{{ number_format($eligible) }} products</b> ({{ number_format($elSmoke) }} cigarette/vape). At Black Friday price now: <b style="color:#0C161F">{{ number_format($cnt['applied']) }}</b>. Put back: {{ number_format($cnt['restored']) }}. Left as changed by staff: {{ number_format($cnt['kept']) }}.</div></div>
<form method="post" action="/admin/black-friday/switch">@csrf<input type="hidden" name="on" value="{{ $on ? '0' : '1' }}"><button class="btn {{ $on ? 'g' : '' }}">{{ $on ? 'Switch off and put normal prices back' : 'Switch on' }}</button></form>
<a class="btn g" href="/black-friday" target="_blank">Offer page</a>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:12px">
<div class="card"><div style="font-size:12px;color:#6B7280">Products in the campaign</div><div style="font-size:26px;font-weight:800">{{ $n($tot['in']) }} <small style="font-size:12px;font-weight:500">of {{ $n($tot['all']) }}</small></div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Average saving</div><div style="font-size:26px;font-weight:800">{{ $avg }}%</div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Cost to check first</div><div style="font-size:26px;font-weight:800;color:{{ $tot['check'] ? '#B45309' : '#0C161F' }}">{{ $n($tot['check']) }}</div><div style="font-size:11.5px;color:#6B7280">saving of 50% or more</div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Left out: no purchase cost</div><div style="font-size:26px;font-weight:800">{{ $n($tot['no_cost']) }}</div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Left out: already at minimum</div><div style="font-size:26px;font-weight:800">{{ $n($tot['at_floor']) }}</div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Left out: out of stock</div><div style="font-size:26px;font-weight:800">{{ $n($tot['no_stock']) }}</div></div>
</div>
<div class="card" style="margin-bottom:12px;overflow-x:auto"><b>By category</b>
<table style="width:100%;font-size:13px;margin-top:8px"><thead><tr><th style="text-align:left">Category</th><th>In the campaign</th><th>Average saving</th><th>Largest saving</th><th>Under 5%</th><th>5 to 10%</th><th>10 to 20%</th><th>20% and more</th><th>Sourced on order</th><th>No cost</th><th>At minimum</th><th>Out of stock</th></tr></thead><tbody>
@foreach($by as $c)
<tr><td><a href="{{ $link('in', $c['cat']) }}">{{ $c['cat'] }}</a>{{ $c['smoke'] ? ' (cigarette/vape)' : '' }}</td><td style="text-align:center"><b>{{ $n($c['in']) }}</b> / {{ $n($c['all']) }}</td><td style="text-align:center">{{ $c['price_sum'] > 0 ? round($c['off_sum'] / $c['price_sum'] * 100, 1) : 0 }}%</td><td style="text-align:center">{{ $c['max'] }}%</td><td style="text-align:center">{{ $n($c['b'][0]) }}</td><td style="text-align:center">{{ $n($c['b'][1]) }}</td><td style="text-align:center">{{ $n($c['b'][2]) }}</td><td style="text-align:center">{{ $n($c['b'][3]) }}</td><td style="text-align:center">{{ $n($c['sourced']) }}</td><td style="text-align:center">{{ $n($c['no_cost']) }}</td><td style="text-align:center">{{ $n($c['at_floor']) }}</td><td style="text-align:center">{{ $n($c['no_stock']) }}</td></tr>
@endforeach
</tbody></table></div>
<div class="card" style="overflow-x:auto">
<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:8px"><b style="flex:1">{{ $cat !== '' ? $cat : 'All categories' }}: {{ $n($count) }} products{{ $count > 300 ? ' (first 300 shown, all are in the CSV)' : '' }}</b>
@foreach(['in' => 'In the campaign', 'check' => 'Cost to check', 'no_cost' => 'No cost', 'at_floor' => 'At minimum', 'no_stock' => 'Out of stock'] as $k => $lb)
<a class="btn {{ $show === $k ? '' : 'g' }}" href="{{ $link($k) }}">{{ $lb }}</a>
@endforeach
@if($cat !== '')
<a class="btn g" href="/admin/black-friday?show={{ $show }}">All categories</a>
@endif
</div>
<table style="width:100%;font-size:13px"><thead><tr><th style="text-align:left">Product</th><th style="text-align:left">Category</th><th>Stock</th><th>Purchase cost</th><th>Price now</th><th>Black Friday price</th><th>Customer saves</th><th>Profit per piece</th></tr></thead><tbody>
@forelse($list as $x)
<tr><td>{{ $x['name'] }}<br><span style="font-size:11.5px;color:#6B7280">{{ $x['sku'] }}</span></td><td>{{ $x['cat'] }}</td><td style="text-align:center">{{ $x['stock'] === null ? '-' : $n($x['stock']) }}</td><td style="text-align:right">{{ $x['cost'] > 0 ? number_format($x['cost'], 2) : '-' }}</td><td style="text-align:right">{{ $n($x['price']) }}</td><td style="text-align:right"><b>{{ $x['bf'] === null ? '-' : $n($x['bf']) }}</b></td><td style="text-align:right;color:{{ $x['check'] ? '#B45309' : '#166534' }}">{{ $x['bf'] === null ? '-' : $n($x['off']) . ' (' . $x['off_pct'] . '%)' }}</td><td style="text-align:right">{{ $x['profit'] === null ? '-' : number_format($x['profit'], 2) }}</td></tr>
@empty
<tr><td colspan="8" style="color:#6B7280;padding:14px">No product in this list.</td></tr>
@endforelse
</tbody></table></div>
@endsection
