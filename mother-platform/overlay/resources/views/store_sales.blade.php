@extends('layout')@section('title','Store Sales')
@section('body')
@php
  $f = fn($n) => '৳' . number_format((float)$n);
  $pct = fn($a, $b) => $b > 0 ? number_format($a / $b * 100, 1) . '%' : '—';
  $maxRev = max(1, collect($list)->map(fn($r) => (float)($r['x']->revenue ?? 0))->max());
  $tmax = max(1, (float)collect($trend)->max());
@endphp
<style>
.ss .kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px}.ss .k{background:#fff;border:1px solid #E3E6EC;border-radius:10px;padding:10px 12px}.ss .k small{color:#6B7280;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em}.ss .k b{display:block;font-size:20px;margin-top:2px}.ss .k span{font-size:11.5px;color:#6B7280}
.ss .bar{height:6px;background:#F1F3F7;border-radius:4px;overflow:hidden;min-width:70px}.ss .bar i{display:block;height:100%;background:#FF5A00}
.ss .rng a{margin-right:4px}.ss .neg{color:#B91C1C;font-weight:700}.ss .pos{color:#0E7A3F;font-weight:700}
.ss .tr{display:flex;align-items:flex-end;gap:3px;height:54px}.ss .tr div{flex:1;background:#FFD2B8;border-radius:3px 3px 0 0;min-height:2px}.ss .tr div:last-child{background:#FF5A00}
</style>
<div class="ss">
<h1>Store Sales</h1>
<p style="color:#6B7280;font-size:13px;margin:0 0 10px">How many orders and how much sale each source store brings. Cancelled and returned orders are not counted in sales. Staff only.</p>
<div class="rng" style="margin-bottom:12px"><a class="btn {{ $range==='today'?'':'g' }}" href="?range=today">Today</a><a class="btn {{ $range==='7'?'':'g' }}" href="?range=7">7 days</a><a class="btn {{ $range==='30'?'':'g' }}" href="?range=30">30 days</a><a class="btn {{ $range==='90'?'':'g' }}" href="?range=90">90 days</a><a class="btn {{ $range==='all'?'':'g' }}" href="?range=all">All time</a> <a class="btn g" href="/admin/stores">← Stores</a> <a class="btn g" href="/admin/vendor-orders">Vendor Orders</a></div>
<div class="kp">
 <div class="k"><small>Sourcing orders</small><b>{{ number_format($src['orders']) }}</b><span>of {{ number_format($tot['orders']) }} total ({{ $pct($src['orders'], $tot['orders']) }})</span></div>
 <div class="k"><small>Sourcing sales</small><b>{{ $f($src['revenue']) }}</b><span>{{ $pct($src['revenue'], $tot['revenue']) }} of all sales</span></div>
 <div class="k"><small>Vendor cost</small><b>{{ $f($src['cost']) }}</b><span>what we pay vendors</span></div>
 <div class="k"><small>Gross profit</small><b class="{{ $src['revenue'] - $src['cost'] >= 0 ? 'pos' : 'neg' }}">{{ $f($src['revenue'] - $src['cost']) }}</b><span>margin {{ $pct($src['revenue'] - $src['cost'], $src['revenue']) }}</span></div>
 <div class="k"><small>Sales last 14 days</small><div class="tr" style="margin-top:6px">@foreach($trend as $d => $v)<div title="{{ $d }} · {{ $f($v) }}" style="height:{{ max(4, round($v / $tmax * 100)) }}%"></div>@endforeach</div><span>sourcing, per day</span></div>
</div>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Store</th><th>Source category</th><th>Orders</th><th>Units</th><th>Sales</th><th></th><th>Vendor cost</th><th>Gross profit</th><th>Margin</th><th>Avg order</th><th>Delivered</th><th>Cancelled</th><th>Returned</th><th>To order</th></tr></thead><tbody>
@foreach($list as $r)@php $x = $r['x']; $rev = (float)($x->revenue ?? 0); $cost = (float)($x->cost ?? 0); $o = (int)($x->orders ?? 0); $gp = $rev - $cost; @endphp
<tr><td><b>{{ $r['name'] }}</b>@if($r['paused']) <span class="pill" style="background:#FEE2E2;color:#B91C1C">paused</span>@endif<br><small style="color:#6B7280">{{ $r['domain'] }}</small></td>
<td>@foreach(array_filter(array_map('trim', explode(',', (string)$r['cat']))) as $c)<span class="pill" style="margin:1px">{{ $c }}</span> @endforeach</td>
<td><b>{{ number_format($o) }}</b></td><td>{{ number_format((int)($x->units ?? 0)) }}</td><td><b>{{ $f($rev) }}</b></td><td><div class="bar"><i style="width:{{ round($rev / $maxRev * 100) }}%"></i></div></td>
<td>{{ $f($cost) }}</td><td class="{{ $gp >= 0 ? 'pos' : 'neg' }}">{{ $f($gp) }}</td><td>{{ $pct($gp, $rev) }}</td><td>{{ $o ? $f($rev / $o) : '—' }}</td>
<td>{{ (int)($x->delivered ?? 0) }} <small style="color:#6B7280">({{ $pct((int)($x->delivered ?? 0), $o) }})</small></td><td>{{ (int)($x->cancelled ?? 0) }}</td><td>{{ (int)($x->returned ?? 0) }}</td>
<td>@if((int)($x->to_order ?? 0) > 0)<a href="/admin/vendor-orders?status=todo"><b>{{ (int)$x->to_order }}</b> lines</a>@else—@endif</td></tr>@endforeach
@if($own)<tr style="background:#F8F9FB"><td><b>DutyFreeBD (own stock)</b><br><small style="color:#6B7280">not sourced from a vendor</small></td><td>—</td><td><b>{{ number_format((int)$own->orders) }}</b></td><td>{{ number_format((int)$own->units) }}</td><td><b>{{ $f($own->revenue) }}</b></td><td></td><td colspan="2" style="color:#6B7280">cost in Profit &amp; Loss</td><td>—</td><td>{{ (int)$own->orders ? $f($own->revenue / $own->orders) : '—' }}</td><td>{{ (int)$own->delivered }}</td><td>{{ (int)$own->cancelled }}</td><td>{{ (int)$own->returned }}</td><td>—</td></tr>@endif
</tbody></table></div>
<p style="color:#6B7280;font-size:12px">Order lines older than the vendor tracking start are matched from the product's current source store. A line shows under the vendor it was sold from at order time.</p>
</div>
@endsection
