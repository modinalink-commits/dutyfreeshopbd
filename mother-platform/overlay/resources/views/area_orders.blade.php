@extends('layout')@section('title','Area-wise orders · Reports & Analytics')
@section('body')
@php
$key = ['customers'=>'Customers','orders'=>'Orders','sales'=>'Sales'];
$q = fn($a = []) => '?'.http_build_query(array_filter(array_merge(['days'=>$days ?: null, 'by'=>$by !== 'customers' ? $by : null], $a), fn($v) => $v !== null));
$max = max(1, collect($rows)->max($by));
$periods = [0=>'All time', 30=>'30 days', 90=>'90 days', 365=>'1 year'];
$heat = [];
foreach ($rows as $x) $heat[$x['name']] = ['bn'=>$x['bn'], 'c'=>$x['customers'], 'o'=>$x['orders'], 's'=>$x['sales'], 'v'=>$x[$by]];
@endphp
<style>
.ar-h{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px}
.ar-h h1{margin:0;font-size:20px}
.ar-seg{display:inline-flex;border:1px solid #E5E7EB;border-radius:9px;overflow:hidden;background:#fff}
.ar-seg a{padding:6px 11px;font-size:12.5px;font-weight:600;color:#475569;text-decoration:none;border-left:1px solid #E5E7EB}.ar-seg a:first-child{border-left:0}
.ar-seg a.on{background:#FFF1E8;color:#C2410C}
.ar-t{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:12px}
.ar-t>div{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:10px 12px}
.ar-t small{display:block;color:#6B7280;font-size:11.5px;font-weight:600}.ar-t b{font-size:20px;display:block;margin-top:2px}
.ar-g{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:12px;align-items:start}
.ar-b{display:flex;align-items:center;gap:9px;padding:7px 0;border-top:1px solid #F1F3F6}.ar-b:first-of-type{border-top:0}
.ar-rk{width:22px;height:22px;border-radius:50%;background:#FFF1E8;color:#C2410C;font-size:11.5px;font-weight:800;display:flex;align-items:center;justify-content:center;flex:none}
.ar-bar{height:9px;background:#EEF1F5;border-radius:5px;overflow:hidden;margin-top:4px}.ar-bar i{display:block;height:100%;background:linear-gradient(90deg,#FFB27A,#FF5A00);border-radius:5px}
.ar-m{display:flex;gap:10px;font-size:11.5px;color:#6B7280;flex-wrap:wrap;margin-top:2px}
.ar-map{width:100%;max-height:520px;display:block;margin:0 auto}
.ar-map path{stroke:#fff;stroke-width:.6;transition:opacity .15s}.ar-map path:hover{opacity:.75;stroke:#0B1220;stroke-width:1}
.ar-lg{display:flex;align-items:center;gap:6px;font-size:11px;color:#6B7280;justify-content:center;margin-top:6px}.ar-lg i{display:block;width:120px;height:8px;border-radius:4px;background:linear-gradient(90deg,#FFE9D9,#FF5A00)}
table.ar-tb{width:100%;border-collapse:collapse;font-size:13px}.ar-tb th{text-align:left;font-size:11.5px;color:#6B7280;padding:6px 8px;border-bottom:1px solid #E5E7EB}.ar-tb td{padding:6px 8px;border-bottom:1px solid #F1F3F6}.ar-tb td.n,.ar-tb th.n{text-align:right}
.ar-dl{font-size:11.5px;font-weight:700;color:#1D5FA8;text-decoration:none;white-space:nowrap}
@media (max-width:900px){.ar-g{grid-template-columns:minmax(0,1fr)}.ar-t{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="ar-h"><div><h1>Area-wise orders</h1><small style="color:#6B7280">Where our customers are — district-wise customers, orders and sales (cancelled and incomplete orders excluded)</small></div>
<div style="display:flex;gap:8px;flex-wrap:wrap"><span class="ar-seg">@foreach($periods as $d=>$l)<a class="{{ $days === $d ? 'on' : '' }}" href="{{ $q(['days'=>$d ?: null]) }}">{{ $l }}</a>@endforeach</span><span class="ar-seg">@foreach($key as $k=>$l)<a class="{{ $by === $k ? 'on' : '' }}" href="{{ $q(['by'=>$k !== 'customers' ? $k : null]) }}">{{ $l }}</a>@endforeach</span><span class="ar-seg"><a href="/admin/analytics/area-orders/export{{ $days ? '?days='.$days : '' }}">⬇ CSV</a></span></div></div>
<div class="ar-t"><div><small>Customers</small><b>{{ number_format($tot['customers']) }}</b></div><div><small>Orders</small><b>{{ number_format($tot['orders']) }}</b></div><div><small>Sales</small><b>৳{{ number_format($tot['sales']) }}</b></div><div><small>Districts reached</small><b>{{ $reached }} / 64</b></div><div><small>Address not matched</small><b>{{ number_format($unknown['orders']) }}</b><small>orders · {{ number_format($unknown['customers']) }} customers</small></div></div>
<div class="ar-g">
<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h2 style="margin:0;font-size:15px">Top 10 districts for campaigns</h2><small style="color:#6B7280">by {{ strtolower($key[$by]) }}</small></div>
@foreach($top as $i=>$x)<div class="ar-b"><span class="ar-rk">{{ $i+1 }}</span><div style="flex:1;min-width:0"><div style="display:flex;gap:8px;align-items:baseline"><b style="font-size:14px">{{ $x['name'] }}</b><span style="color:#6B7280;font-size:12px">{{ $x['bn'] }}</span><span style="margin-left:auto;font-weight:800">{{ $by === 'sales' ? '৳'.number_format($x['sales']) : number_format($x[$by]) }}</span></div>
<div class="ar-bar"><i style="width:{{ round($x[$by]/$max*100) }}%"></i></div>
<div class="ar-m"><span><b>{{ number_format($x['customers']) }}</b> customers</span><span><b>{{ number_format($x['orders']) }}</b> orders</span><span>৳{{ number_format($x['sales']) }}</span>@if($x['customers'] > 0)<span>{{ round($x['repeat']/$x['customers']*100) }}% repeat</span>@endif @if($x['success'] !== null)<span>{{ $x['success'] }}% delivered</span>@endif</div></div><a class="ar-dl" href="/admin/analytics/area-orders/customers?district={{ urlencode($x['name']) }}{{ $days ? '&days='.$days : '' }}" title="Name, phone and address of every customer in {{ $x['name'] }}">⬇ Customers</a></div>@endforeach</div>
<div class="card"><h2 style="margin:0 0 6px;font-size:15px">Heat map · {{ strtolower($key[$by]) }} by district</h2><div id="arMap" style="min-height:260px"></div><div class="ar-lg"><span>Low</span><i></i><span>High</span></div></div>
</div>
<div class="card" style="margin-top:12px;overflow:auto"><h2 style="margin:0 0 8px;font-size:15px">All 64 districts</h2>
<table class="ar-tb"><thead><tr><th>#</th><th>District</th><th>Division</th><th class="n">Customers</th><th class="n">Repeat</th><th class="n">Orders</th><th class="n">Sales</th><th class="n">Delivered</th><th></th></tr></thead><tbody>
@foreach($rows as $i=>$x)<tr><td>{{ $i+1 }}</td><td><b>{{ $x['name'] }}</b> <small style="color:#6B7280">{{ $x['bn'] }}</small></td><td>{{ $x['division'] }}</td><td class="n">{{ number_format($x['customers']) }}</td><td class="n">{{ number_format($x['repeat']) }}</td><td class="n">{{ number_format($x['orders']) }}</td><td class="n">৳{{ number_format($x['sales']) }}</td><td class="n">{{ $x['success'] !== null ? $x['success'].'%' : '—' }}</td><td>@if($x['customers'] > 0)<a class="ar-dl" href="/admin/analytics/area-orders/customers?district={{ urlencode($x['name']) }}{{ $days ? '&days='.$days : '' }}">⬇ CSV</a>@endif</td></tr>@endforeach</tbody></table>
<p style="font-size:12px;color:#6B7280;margin:8px 0 0">Data is refreshed every 10 minutes. District comes from the order's city/district field; when it is blank it is read from the address text. Orders whose address names no district are counted under “Address not matched”.</p></div>
<script>
(function(){var H=@json($heat),mx={{ $max }};var box=document.getElementById('arMap');
function col(v){if(!v)return '#EEF1F5';var t=Math.sqrt(v/mx);var a=[255,233,217],b=[255,90,0];return 'rgb('+a.map(function(x,i){return Math.round(x+(b[i]-x)*t)}).join(',')+')'}
fetch('/bd64.json').then(function(r){return r.json()}).then(function(j){var s='<svg class="ar-map" viewBox="0 0 '+j.w+' '+j.h+'" role="img" aria-label="District heat map">';
j.d.forEach(function(d){var h=H[d.en]||{c:0,o:0,s:0,v:0};s+='<path d="'+d.d+'" fill="'+col(h.v)+'"><title>'+d.en+' · '+d.bn+'\n'+h.c+' customers · '+h.o+' orders · ৳'+Number(h.s).toLocaleString('en-US')+'</title></path>'});
box.innerHTML=s+'</svg>'}).catch(function(){box.textContent='Map could not be loaded.'})})();
</script>
@endsection
