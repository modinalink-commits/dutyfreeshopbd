@extends('layout')@section('title','Marine Catalog')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Marine Catalog</h1><div style="font-size:12px;color:#6B7280">কোন পণ্য মেরিন চ্যানেলে যাবে, কোথায় দেখাবে, কোন বন্দরে এবং ডলারে দাম কত। পণ্যের নাম, ছবি ও কোড মূল ক্যাটালগ থেকেই আসে। তামাক ও অ্যালকোহল সবসময় শুধু লগইনের পরে।</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
<form method="get" class="card" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input name="q" value="{{ $q }}" placeholder="নাম বা কোড" style="width:220px"><select name="category"><option value="0">সব ক্যাটাগরি</option>@foreach($cats as $c)<option value="{{ $c->id }}" @selected($cat==$c->id)>{{ $c->name }}</option>@endforeach</select><select name="show"><option value="all" @selected($show==='all')>সব পণ্য</option><option value="on" @selected($show==='on')>শুধু মেরিনে চালু</option></select><button class="btn">Filter</button></form>
<div class="card" style="overflow:auto"><table style="width:100%;min-width:980px;font-size:13px;border-collapse:collapse"><thead><tr style="text-align:left;color:#6B7280"><th style="padding:6px 4px">Product</th><th>Store price</th><th>Stock</th><th>Marine</th><th>Shown</th><th>Ports</th><th>USD price</th><th></th></tr></thead><tbody>
@forelse($rows as $x)@php $b = !empty($bond[$x->cid ?? 0]); $pp = array_filter(explode(',', (string)$x->ports)); $fid = 'mp'.$x->id; @endphp
<tr style="border-top:1px solid #EEE;vertical-align:middle"><td style="padding:8px 4px"><b>{{ $x->name }}</b><div style="font-size:11.5px;color:#6B7280">{{ $x->sku }} · {{ $x->cat ?: '-' }}@if($b) · <span style="color:#B45309;font-weight:600">Bonded</span>@endif</div></td>
<td style="white-space:nowrap">৳{{ number_format((float)$x->price) }}</td><td>{{ (int)$x->stock }}</td>
<td><form id="{{ $fid }}" method="post" action="/admin/marine/catalog/{{ $x->id }}">@csrf</form><label style="display:flex;gap:6px;align-items:center"><input form="{{ $fid }}" type="checkbox" name="enabled" value="1" @checked($x->enabled)> On</label></td>
<td>@if($b)<input form="{{ $fid }}" type="hidden" name="visibility" value="login"><span style="font-size:12px;color:#6B7280">After login only</span>@else<select form="{{ $fid }}" name="visibility"><option value="login" @selected(($x->visibility ?? 'login')==='login')>After login</option><option value="public" @selected($x->visibility==='public')>Public</option></select>@endif</td>
<td style="white-space:nowrap">@foreach($ports as $pk=>$pl)<label style="margin-right:8px"><input form="{{ $fid }}" type="checkbox" name="ports[]" value="{{ $pk }}" @checked(in_array($pk, $pp, true))> {{ $pl }}</label>@endforeach</td>
<td><input form="{{ $fid }}" name="usd_price" value="{{ $x->usd_price !== null ? number_format((float)$x->usd_price, 2, '.', '') : '' }}" placeholder="0.00" inputmode="decimal" style="width:90px;text-align:right"></td>
<td><button form="{{ $fid }}" class="btn">Save</button></td></tr>
@empty<tr><td colspan="8" style="padding:14px;color:#6B7280">কোনো পণ্য পাওয়া যায়নি।</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>
<div style="font-size:12px;color:#6B7280;margin-top:8px">এখানে চালু করলে পণ্য মেরিন চ্যানেলের তালিকায় ঢোকে। মেরিন সাইটের পণ্য পাতা এই তালিকা থেকে দেখানো পরের ধাপে যুক্ত হবে। মূল দোকানের দাম, স্টক বা দৃশ্যমানতা এখান থেকে বদলায় না।</div>
@endsection
