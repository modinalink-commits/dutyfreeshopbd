@extends('layout')@section('title','Marine Catalog')
@section('body')
<style>
.mct label{display:inline-flex!important;align-items:center;gap:6px;margin:0;white-space:nowrap;font-size:13px;width:auto}
.mct input[type=checkbox]{width:auto!important;min-width:0;height:auto;margin:0;flex:none}
.mct select,.mct input[type=text],.mct input:not([type]){width:auto!important;min-width:0;height:32px}
.mct .mcf{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.mct .mcf input[name=q]{width:240px!important}.mct .mcf select{max-width:240px}
.mct table.mcat td{vertical-align:middle;padding:7px 6px}
.mct table.mcat tr:has(input[type=checkbox]:checked)>td{background:transparent!important}
.mct .mports{display:flex;gap:12px;flex-wrap:nowrap}
</style>
<div class="mct">
<div style="margin-bottom:10px"><h1 style="margin:0">Marine Catalog</h1><div style="font-size:12px;color:#6B7280">Choose which products go on the marine channel, where they show, at which ports and the USD price. Name, image and code come from the main catalog. Tobacco and alcohol are always shown after login only.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if($adm)<form method="post" action="/admin/marine/catalog-bulk" class="card mcf">@csrf<b style="font-size:13px">Publish all store products to marine</b><label>BDT per 1 USD <input name="rate" value="{{ $rate ?: '' }}" inputmode="decimal" style="width:80px!important;text-align:right" placeholder="128"></label><label><input type="checkbox" name="sure" value="1"> Set USD price = store price / rate for every published product</label><button class="btn">Run</button><span style="font-size:12px;color:#6B7280">Bonded categories stay after-login. Store prices are not changed. Run again after a rate change.</span></form>@endif
<form method="get" class="card mcf"><input name="q" value="{{ $q }}" placeholder="Name or code" style="width:220px"><select name="category"><option value="0">All categories</option>@foreach($cats as $c)<option value="{{ $c->id }}" @selected($cat==$c->id)>{{ $c->name }}</option>@endforeach</select><select name="show"><option value="all" @selected($show==='all')>All products</option><option value="on" @selected($show==='on')>On marine only</option><option value="featured" @selected($show==='featured')>Home page picks</option></select><button class="btn">Filter</button><span style="font-size:12px;color:#6B7280;margin-left:auto">Home page picks: <b style="color:#0C161F">{{ $featN }} of {{ $featMax }}</b> · <a href="/admin/marine/catalog?show=featured">View picks</a></span></form>
<div class="card" style="overflow:auto"><table class="mcat" style="width:100%;min-width:1080px;font-size:13px;border-collapse:collapse"><thead><tr style="text-align:left;color:#6B7280"><th style="padding:6px 4px">Product</th><th>Store price</th><th>Stock</th><th>Marine</th><th>Shown</th><th>Ports</th><th>USD price</th><th title="Position on the marine home page, 1 to 8. Leave empty for not shown on the home page.">Home page</th><th></th></tr></thead><tbody>
@forelse($rows as $x)@php $b = !empty($bond[$x->cid ?? 0]); $pp = array_filter(explode(',', (string)$x->ports)); $fid = 'mp'.$x->id; @endphp
<tr style="border-top:1px solid #EEE;vertical-align:middle"><td style="padding:8px 4px"><b>{{ $x->name }}</b><div style="font-size:11.5px;color:#6B7280">{{ $x->sku }} · {{ $x->cat ?: '-' }}@if($b) · <span style="color:#B45309;font-weight:600">Bonded</span>@endif</div></td>
<td style="white-space:nowrap">BDT {{ number_format((float)$x->price) }}</td><td>{{ (int)$x->stock }}</td>
<td><form id="{{ $fid }}" method="post" action="/admin/marine/catalog/{{ $x->id }}">@csrf</form><label><input form="{{ $fid }}" type="checkbox" name="enabled" value="1" @checked($x->enabled)> On</label></td>
<td>@if($b)<input form="{{ $fid }}" type="hidden" name="visibility" value="login"><span style="font-size:12px;color:#6B7280">After login only</span>@else<select form="{{ $fid }}" name="visibility"><option value="login" @selected(($x->visibility ?? 'login')==='login')>After login</option><option value="public" @selected($x->visibility==='public')>Public</option></select>@endif</td>
<td><div class="mports">@foreach($ports as $pk=>$pl)<label><input form="{{ $fid }}" type="checkbox" name="ports[]" value="{{ $pk }}" @checked(in_array($pk, $pp, true))> {{ $pl }}</label>@endforeach</div></td>
<td><input form="{{ $fid }}" name="usd_price" value="{{ $x->usd_price !== null ? number_format((float)$x->usd_price, 2, '.', '') : '' }}" placeholder="0.00" inputmode="decimal" style="width:90px!important;text-align:right"></td>
<td>@if($b)<span style="font-size:12px;color:#6B7280">-</span>@else<select form="{{ $fid }}" name="featured" title="Position on the marine home page"><option value="0">No</option>@for($i=1;$i<=$featMax;$i++)<option value="{{ $i }}" @selected((int)($x->featured ?? 0)===$i)>#{{ $i }}</option>@endfor</select>@endif</td>
<td><button form="{{ $fid }}" class="btn">Save</button></td></tr>
@empty<tr><td colspan="9" style="padding:14px;color:#6B7280">No products found.</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>
<div style="font-size:12px;color:#6B7280;margin-top:8px"><b>On</b> + <b>Public</b> publishes a product to the marine site: it appears in View All Products and in search. <b>Home page</b> picks up to {{ $featMax }} of those products for the front page, in the order #1 to #{{ $featMax }}; empty places are filled with in-stock products. Nothing here changes the main store's price, stock or visibility.</div>
</div>
@endsection
