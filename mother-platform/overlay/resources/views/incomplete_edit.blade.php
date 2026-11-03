@extends('layout')@section('title','Edit incomplete checkout')
@section('body')
@php $ph = \App\Support\StoreScanner::ph(); $inv = $o->invoice ?: 'INC-'.str_pad($o->id, 5, '0', STR_PAD_LEFT); @endphp
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px"><h1 style="margin:0;flex:1">Edit {{ $inv }}</h1><a class="btn g" href="/admin/incomplete/{{ $o->id }}">Cancel</a></div>
@if(session('bad'))<div class="card err" style="margin-bottom:10px">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-bottom:10px">{{ $errors->first() }}</div>@endif
@if($o->status === 'converted')<div class="card err">This checkout is already converted to an order. Edit the order instead.</div>@else
<form method="post" action="/admin/incomplete/{{ $o->id }}/update" class="card" style="max-width:820px">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px">
<div><label>Customer name</label><input name="name" value="{{ old('name', $o->name) }}" maxlength="120"></div>
<div><label>Mobile</label><input name="phone" value="{{ old('phone', $o->phone) }}" required></div>
<div><label>Email</label><input name="email" value="{{ old('email', $o->email) }}" maxlength="120"></div>
<div><label>City</label><select name="district"><option value="">Select City</option>@foreach($cities as $c)<option value="{{ $c }}" @selected(old('district', $o->district) === $c)>{{ $c }}</option>@endforeach @if($o->district && !in_array($o->district, $cities))<option value="{{ $o->district }}" selected>{{ $o->district }}</option>@endif</select></div></div>
<label style="margin-top:10px">Address</label><textarea name="address" rows="2" maxlength="500">{{ old('address', $o->address) }}</textarea>
<label style="margin-top:10px">Note</label><input name="note" value="{{ old('note', $o->note) }}" maxlength="500">
<h3 style="margin:14px 0 6px">Products <small style="color:#6B7280;font-weight:400">(set quantity to 0 to remove)</small></h3>
<table><thead><tr><th></th><th>Product</th><th>Price</th><th>Qty</th></tr></thead><tbody>
@foreach($items as $k => $i)<tr><td><img src="{{ ($imgs[$i['id'] ?? 0] ?? null) ?: $ph }}" alt="" width="36" height="36" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $ph }}'" style="object-fit:cover;border-radius:6px;border:1px solid #E5E7EB"></td><td>{{ $i['name'] ?? '' }}</td><td>৳{{ number_format((float)($i['price'] ?? 0)) }}</td><td><input type="number" name="qty[{{ $k }}]" value="{{ old('qty.'.$k, (int)($i['qty'] ?? 0)) }}" min="0" max="999" style="width:80px"></td></tr>@endforeach</tbody></table>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px"><div><label>Delivery charge (৳)</label><input type="number" step="1" min="0" name="shipping" value="{{ old('shipping', (float)$o->shipping + 0) }}"></div>
<div><label>Discount (৳)</label><input type="number" step="1" min="0" name="discount" value="{{ old('discount', (float)$o->discount + 0) }}"></div>
<div><label>Payment method</label><select name="pay_method"><option value="">Not chosen</option>@foreach(['Cash on Delivery','ShurjoPay','NPSB Bank Transfer','bKash Personal','Nagad Personal'] as $m)<option @selected(old('pay_method', $o->pay_method) === $m)>{{ $m }}</option>@endforeach @if($o->pay_method && !in_array($o->pay_method, ['Cash on Delivery','ShurjoPay','NPSB Bank Transfer','bKash Personal','Nagad Personal']))<option selected>{{ $o->pay_method }}</option>@endif</select></div>
<div><label>Courier</label><select name="courier"><option value="">Not chosen</option>@foreach(\App\Http\Controllers\QuickEditController::COURIERS as $c)<option @selected(old('courier', $o->courier ?? '') === $c)>{{ $c }}</option>@endforeach</select></div>
<div><label>Tracking / consignment</label><input name="consignment" value="{{ old('consignment', $o->consignment ?? '') }}" maxlength="80"></div></div>
<div style="margin-top:14px"><button class="btn">Save</button> <a class="btn g" href="/admin/incomplete/{{ $o->id }}">Cancel</a></div>
<p style="color:#6B7280;font-size:12px;margin:10px 0 0">The total is recalculated when you save. If the customer is still on the checkout page, their own changes will overwrite these.</p></form>@endif
@endsection
