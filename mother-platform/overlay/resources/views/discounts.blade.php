@extends('layout')@section('title','Discount Center')
@section('body')<div style="display:flex;gap:10px;align-items:center;margin-bottom:12px"><h1 style="margin:0;flex:1">Discount Center</h1><a class="btn g" href="/admin/coupons-export.csv">Download coupons CSV</a></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
@foreach($rules as $r)@php $cfg = json_decode($r->config, true); @endphp
<form class="card" method="post" action="/admin/discounts/rules/{{ $r->id }}">@csrf<h3 style="margin:0">{{ $loop->iteration }}. {{ $r->title }}</h3><div style="font-size:12px;color:#6B7280">{{ $r->help }}</div>
@foreach($cfg as $k=>$v)<label>{{ $k }}</label><input name="c[{{ $k }}]" type="number" step="0.01" min="0" value="{{ $v }}">@endforeach
<label style="display:flex;gap:8px;align-items:center;margin-top:12px"><input type="checkbox" name="enabled" value="1" {{ $r->enabled?'checked':'' }} style="width:auto"> Enabled</label><div style="margin-top:10px"><button>Save</button></div></form>
@endforeach</div>
<h2>Coupon codes</h2>
<div class="card" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><div style="flex:1;min-width:240px"><b>{{ $coupons->count() }} coupons</b> · {{ $coupons->where('active', true)->count() }} active<div style="font-size:12px;color:#6B7280">Create, edit, schedule and target coupons (customers, time, products, limits) in the Coupon portal.</div></div><a class="btn" href="/admin/coupons">Open Coupon portal</a></div>
<form class="card" method="post" action="/admin/discounts/policy" style="max-width:640px">@csrf<h3 style="margin-top:0">Order discount policy (Create Order)</h3><p style="font-size:12px;color:#6B7280;margin:0 0 8px">When a customer qualifies for several discounts. Priority order: Special customer, Membership, Birthday, First order, Coupon. A discount is never applied twice.</p>
<select name="mode" style="max-width:360px">@foreach(\App\Support\OrderDiscount::MODES as $mk=>$ml)<option value="{{ $mk }}" {{ \App\Support\OrderDiscount::mode()===$mk ? 'selected' : '' }}>{{ $ml }}</option>@endforeach</select> <button>Save policy</button></form>
@endsection
