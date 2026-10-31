@extends('layout')@section('title',"Today's Deal")
@section('body')
<div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap"><h1 style="margin:0;flex:1">Today's Deal</h1><a class="btn g" href="/admin/deals.csv">Download CSV</a><a class="btn g" href="/" target="_blank">View on site</a></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:12px">
<div class="card"><div style="font-size:12px;color:#6B7280">On the homepage now</div><div style="font-size:26px;font-weight:800">{{ $cur->count() }} <small style="font-size:12px;font-weight:500">of {{ $max }} slots</small></div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">All discounted products</div><div style="font-size:26px;font-weight:800">{{ $total }}</div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Pinned to top</div><div style="font-size:26px;font-weight:800">{{ $pinned->count() }}</div></div>
<div class="card"><div style="font-size:12px;color:#6B7280">Needs attention</div><div style="font-size:26px;font-weight:800;color:#B91C1C">{{ $cur->filter(fn($p)=>($p->stock ?? 1) <= 0 || $p->off >= 60)->count() }}</div></div></div>
<form class="card" method="post" action="/admin/deals/settings" style="max-width:640px;margin-bottom:12px">@csrf<h3 style="margin-top:0">Rules</h3>
<div style="font-size:12px;color:#6B7280;margin-bottom:8px">Rule: a published product with a real old price above its price. The biggest discount comes first. Pinned products always stay on top. Hidden products never show. Changes appear on the site within 5 minutes.</div>
<label>Minimum discount % (0 shows every discounted product)</label><input name="deal_min_off" type="number" step="1" min="0" max="90" value="{{ (int)$min }}">
<label>How many products to show (1 to 24)</label><input name="deal_max" type="number" min="1" max="24" value="{{ $max }}"><div style="margin-top:12px"><button>Save rules</button></div></form>
<h2>Showing now</h2>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Product</th><th>Price</th><th>Old price</th><th>Off</th><th>Stock</th><th>Sold 30d</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($cur as $p)<tr>
<td><a href="/admin/products/{{ $p->id }}/edit">{{ $p->name }}</a></td><td>৳{{ number_format($p->price) }}</td><td><s>৳{{ number_format($p->compare_price) }}</s></td><td><b>{{ $p->off }}%</b></td><td>{{ $p->stock ?? '-' }}</td><td>{{ $p->sold30 }}</td>
<td>@if($p->flag==='pin')<span style="color:#FF5A00;font-weight:700">Pinned</span>@endif @if(($p->stock ?? 1) <= 0)<span style="color:#B91C1C">Out of stock</span>@endif @if($p->off >= 60)<span style="color:#B45309">Check old price</span>@endif</td>
<td style="white-space:nowrap">@if($p->flag!=='pin')<form method="post" action="/admin/deals/{{ $p->id }}/pin" style="display:inline">@csrf<button class="g">Pin</button></form> @endif<form method="post" action="/admin/deals/{{ $p->id }}/exclude" style="display:inline">@csrf<button class="g">Hide</button></form>@if($p->flag) <form method="post" action="/admin/deals/{{ $p->id }}/clear" style="display:inline">@csrf<button class="g">Auto</button></form>@endif</td></tr>
@empty<tr><td colspan="8">No product qualifies. Add an old price to a product, or lower the minimum discount.</td></tr>@endforelse</tbody></table></div>
<h2>Discounted, but not showing</h2>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Product</th><th>Price</th><th>Old price</th><th>Off</th><th>Stock</th><th>Why not showing</th><th></th></tr></thead><tbody>
@forelse($others as $p)<tr><td><a href="/admin/products/{{ $p->id }}/edit">{{ $p->name }}</a></td><td>৳{{ number_format($p->price) }}</td><td><s>৳{{ number_format($p->compare_price) }}</s></td><td>{{ $p->off }}%</td><td>{{ $p->stock ?? '-' }}</td>
<td>@if($p->flag==='exclude')Hidden by you @elseif($p->off < $min)Below the {{ (int)$min }}% minimum @else Beyond the {{ $max }} slots @endif</td>
<td style="white-space:nowrap"><form method="post" action="/admin/deals/{{ $p->id }}/pin" style="display:inline">@csrf<button class="g">Pin</button></form>@if($p->flag) <form method="post" action="/admin/deals/{{ $p->id }}/clear" style="display:inline">@csrf<button class="g">Auto</button></form>@endif</td></tr>
@empty<tr><td colspan="7">Nothing else is discounted.</td></tr>@endforelse</tbody></table></div>
@endsection
