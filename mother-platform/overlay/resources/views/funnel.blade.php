@extends('layout')@section('title','Sales Funnel')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Sales Funnel</h1><div style="font-size:12px;color:#6B7280">Where visitors drop off: viewed a product → added to cart → started checkout → ordered. Counts are unique visitors (anonymous, bots skipped).</div></div>
<form method="get" style="display:flex;gap:6px;align-items:center;margin:0"><input type="date" name="from" value="{{ $from }}"><input type="date" name="to" value="{{ $to }}"><button class="g">Apply</button></form></div>
@php $pv=$v['product_view'] ?? 0; $cr=$v['add_to_cart'] ?? 0; $ck=$v['checkout_started'] ?? 0; $pct=fn($a,$b)=>$b>0?round($a/$b*100,1).'%':'—'; @endphp
<div class="card"><h2>Funnel</h2>
@foreach([[ 'Viewed a product',$pv,null ],['Added to cart',$cr,$pct($cr,$pv).' of viewers'],['Started checkout',$ck,$pct($ck,$cr).' of cart adders'],['Orders (not cancelled/returned)',$orders,$pct($orders,$ck).' of checkout starters']] as [$l,$n,$s])
<div style="display:flex;align-items:center;gap:8px;margin:7px 0;font-size:12.5px"><span style="width:190px;color:#6B7280">{{ $l }}</span><span style="height:12px;border-radius:6px;background:#F95505;opacity:.8;width:{{ $pv>0 ? max(1,round(min($n,$pv)/$pv*55)) : 1 }}%;min-width:2px"></span><b>{{ number_format($n) }}</b><span style="color:#6B7280">{{ $s }}</span></div>@endforeach
<div style="font-size:11.5px;color:#6B7280;margin-top:6px">Overall: {{ $pct($orders,$pv) }} of product viewers ended in an order. "Orders" counts every channel in these dates ({{ number_format($all) }} including cancelled/returned), so it also includes orders taken by phone or chat; checkout starters are website visitors only. Read the last step as a guide, not an exact figure.</div></div>
@foreach(['dev'=>'By device','src'=>'By source (where the visitor came from)'] as $k=>$t)
<div class="card"><h2>{{ $t }}</h2><div style="overflow-x:auto"><table><thead><tr><th>{{ $k==='dev' ? 'Device' : 'Source' }}</th><th>Viewers</th><th>Added to cart</th><th>Cart rate</th><th>Started checkout</th><th>Checkout rate</th></tr></thead><tbody>
@forelse(($k==='dev' ? $dev : $src) as $name=>$x)@php $a=$x['product_view'] ?? 0; $b=$x['add_to_cart'] ?? 0; $c=$x['checkout_started'] ?? 0; @endphp<tr><td>{{ $name }}</td><td>{{ number_format($a) }}</td><td>{{ number_format($b) }}</td><td>{{ $pct($b,$a) }}</td><td>{{ number_format($c) }}</td><td>{{ $pct($c,$b) }}</td></tr>@empty<tr><td colspan="6" style="color:#6B7280">No events in these dates.</td></tr>@endforelse</tbody></table></div></div>
@endforeach
@endsection
