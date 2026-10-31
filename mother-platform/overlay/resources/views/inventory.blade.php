@extends('layout')@section('title','Central Inventory')
@section('body')
@php $PH = \App\Support\StoreScanner::ph(); $qs = http_build_query(request()->except('page','view')); @endphp
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Central Inventory</h1><span style="font-size:13px;color:#6B7280">One Mother Dashboard → all stores → every product with image, SKU and store-wise stock.</span></div>
<span style="display:inline-flex;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download: <a target="_blank" href="/admin/inventory/export/pdf?{{ $qs }}">PDF</a> · <a href="/admin/inventory/export/xls?{{ $qs }}">Excel</a> · <a href="/admin/inventory/export/doc?{{ $qs }}">Word</a> · <a href="/admin/inventory/export/csv?{{ $qs }}">CSV</a></span>
<a class="btn g" href="/admin/m/stock-log">Stock log</a><a class="btn" href="/admin/stores">Stores &amp; Scan</a></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">@foreach($stats as [$l,$v,$n])<div class="card k" style="margin:0"><span style="font-size:13px">{{ $l }}</span><b style="font-size:26px">{{ $v }}</b><span style="color:#FF5A00;font-weight:600">{{ $n }}</span></div>@endforeach</div>
<form class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
<select name="store" style="max-width:220px"><option value="">All Stores</option><option value="0" {{ request('store')==='0'?'selected':'' }}>DutyFreeShopBD (this site) · {{ number_format($ownN) }}</option>@foreach($stores as $s)<option value="{{ $s->id }}" {{ request('store')==$s->id && request('store')!=='0' ?'selected':'' }}>{{ $s->name }} ({{ $s->domain }}) · {{ number_format($perStore[$s->id] ?? 0) }}</option>@endforeach</select>
<input name="q" value="{{ request('q') }}" placeholder="Product name, SKU or category" style="flex:1;min-width:200px">
<input name="sku" value="{{ request('sku') }}" placeholder="SKU" style="max-width:130px">
<select name="stock" style="max-width:150px"><option value="">Stock: all</option><option value="in" {{ request('stock')==='in'?'selected':'' }}>In stock</option><option value="low" {{ request('stock')==='low'?'selected':'' }}>Low (1-5)</option><option value="out" {{ request('stock')==='out'?'selected':'' }}>Out of stock</option></select>
<select name="published" style="max-width:150px"><option value="">Status: all</option><option value="1" {{ request('published')==='1'?'selected':'' }}>Published</option><option value="0" {{ request('published')==='0'?'selected':'' }}>Unpublished</option></select>
<select name="view" style="max-width:190px"><option value="">One row per store</option><option value="grouped" {{ $grouped?'selected':'' }}>Same product, all stores</option></select>
<button>Search</button><a class="btn g" href="/admin/inventory">Reset</a></form>
<div class="card"><div style="display:flex;gap:10px;align-items:center;margin-bottom:10px"><b>{{ number_format($rows->total()) }} {{ $grouped ? 'products' : 'rows' }}</b><span style="font-size:12px;color:#6B7280">Where a shop does not publish exact quantities, the stock shows "In stock" / "Out of stock".</span></div>
<div style="overflow-x:auto"><table><thead><tr><th style="width:64px">Image</th><th>Product Name</th><th>SKU</th><th>{{ $grouped ? 'Stock by store' : 'Store / Domain' }}</th><th>{{ $grouped ? 'Total qty' : 'Stock Qty' }}</th><th>Price</th><th>Status</th></tr></thead><tbody>
@forelse($rows as $x)
@if($grouped)
<tr><td><img src="{{ $x->image ?: $PH }}" alt="" width="52" height="52" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $PH }}'" style="object-fit:cover;border-radius:8px;border:1px solid #FDC2A5;background:#fff"></td><td><b>{{ $x->name }}</b><br><small style="color:#6B7280">{{ $x->stores }} store{{ $x->stores>1?'s':'' }}</small></td><td>{{ $x->sku ?: '—' }}</td><td style="font-size:12px">{{ $x->per_store }}</td><td><b>{{ number_format($x->total) }}</b></td><td>{{ $x->price !== null ? '৳'.number_format($x->price) : '—' }}</td><td></td></tr>
@else
@php $st = \App\Http\Controllers\InventoryController::statusText($x); $cls = $st==='Active' ? 'ok' : ($st==='Unpublished'||$st==='Low stock' ? '' : 'red'); $link = $x->src==='own' ? '/admin/products/'.$x->id.'/edit' : $x->url; @endphp
<tr><td><img src="{{ $x->image ?: $PH }}" alt="" width="52" height="52" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $PH }}'" style="object-fit:cover;border-radius:8px;border:1px solid #FDC2A5;background:#fff"></td>
<td><b>@if($link)<a href="{{ $link }}" @if($x->src!=='own') target="_blank" rel="noopener nofollow" @endif style="color:inherit">{{ $x->name }}</a>@else{{ $x->name }}@endif</b>@if($x->category)<br><small style="color:#6B7280">{{ $x->category }}</small>@endif</td>
<td>{{ $x->sku ?: '—' }}</td><td>{{ $x->store_name }}</td><td><b>{{ \App\Http\Controllers\InventoryController::stockText($x) }}</b></td><td>{{ $x->price !== null ? '৳'.number_format($x->price) : '—' }}</td><td><span class="pill {{ $cls }}">{{ $st }}</span></td></tr>
@endif
@empty<tr><td colspan="7">No products match. Add a store and press Scan Products on the Stores page.</td></tr>@endforelse</tbody></table></div>{{ $rows->links('pagination.admin') }}</div>
@endsection
