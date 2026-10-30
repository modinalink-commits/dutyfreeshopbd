@extends('layout')@section('title','Discount Center')
@section('body')<div style="display:flex;gap:10px;align-items:center;margin-bottom:12px"><h1 style="margin:0;flex:1">Discount Center</h1><a class="btn g" href="/admin/coupons-export.csv">Download coupons CSV</a></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
@foreach($rules as $r)@php $cfg = json_decode($r->config, true); @endphp
<form class="card" method="post" action="/admin/discounts/rules/{{ $r->id }}">@csrf<h3 style="margin:0">{{ $loop->iteration }}. {{ $r->title }}</h3><div style="font-size:12px;color:#6B7280">{{ $r->help }}</div>
@foreach($cfg as $k=>$v)<label>{{ $k }}</label><input name="c[{{ $k }}]" type="number" step="0.01" min="0" value="{{ $v }}">@endforeach
<label style="display:flex;gap:8px;align-items:center;margin-top:12px"><input type="checkbox" name="enabled" value="1" {{ $r->enabled?'checked':'' }} style="width:auto"> Enabled</label><div style="margin-top:10px"><button>Save</button></div></form>
@endforeach</div>
<h2>Coupon codes</h2>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min order</th><th>Used / limit</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($coupons as $c)<tr><td><b>{{ $c->code }}</b></td><td>{{ $c->type }}</td><td>{{ $c->type==='percent' ? (float)$c->value.'%' : '৳'.number_format($c->value) }}</td><td>৳{{ number_format($c->min_order) }}</td><td>{{ $c->used_count }} / {{ $c->usage_limit ?: '∞' }}</td><td>{{ $c->valid_from ?: '-' }} to {{ $c->valid_to ?: '-' }}</td><td><span class="pill {{ $c->active?'ok':'' }}">{{ $c->active?'Active':'Off' }}</span></td>
<td><form method="post" action="/admin/discounts/coupons/{{ $c->id }}/toggle">@csrf<button class="g">{{ $c->active?'Turn off':'Turn on' }}</button></form></td></tr>
@empty<tr><td colspan="8">No coupons yet.</td></tr>@endforelse</tbody></table></div>
<form class="card" method="post" action="/admin/discounts/coupons" style="max-width:640px">@csrf<h3 style="margin-top:0">New coupon</h3>
<label>Code</label><input name="code" required><label>Type</label><select name="type"><option value="percent">Percent off</option><option value="fixed">Fixed amount off</option><option value="free_delivery">Free delivery</option></select>
<label>Value</label><input name="value" type="number" step="0.01" min="0" value="0" required><label>Minimum order (TK)</label><input name="min_order" type="number" step="0.01" min="0">
<label>Max discount (TK)</label><input name="max_discount" type="number" step="0.01" min="0"><label>Total usage limit</label><input name="usage_limit" type="number" min="1"><label>Limit per customer</label><input name="per_customer" type="number" min="1" value="1">
<label>Valid from</label><input name="valid_from" type="date"><label>Valid to</label><input name="valid_to" type="date">@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif<div style="margin-top:14px"><button>Save coupon</button></div></form>@endsection
