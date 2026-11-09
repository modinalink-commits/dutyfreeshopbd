@extends('layout')@section('title','Coupons')
@section('body')
<style>
.cpx .chip{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700;color:#0B1220;border:1px solid transparent}
.cpx .live{background:#DCFCE7;border-color:#BBF7D0}.cpx .sch{background:#E6EFF9;border-color:#BFDBFE}.cpx .exp{background:#F3F4F6;border-color:#E5E7EB}.cpx .off{background:#FEE2E2;border-color:#FECACA}.cpx .used{background:#FEF3C7;border-color:#FDE68A}
.cpx .tab{display:inline-block;padding:6px 12px;border-radius:999px;border:1px solid #E5E7EB;color:#0B1220;font-weight:700;font-size:12.5px;text-decoration:none;background:#fff;margin:0 4px 6px 0}.cpx .tab.on{border:2px solid #000;background:#FFF1E8}
.cpx td small{color:#6B7280;display:block}.cpx .act{display:flex;gap:4px;flex-wrap:wrap}.cpx .act form{margin:0}.cpx .act a,.cpx .act button{padding:4px 9px;font-size:12px;color:#0B1220;background:#F3F4F6;border:1px solid #E5E7EB;border-radius:8px;font-weight:700;cursor:pointer;text-decoration:none}
.cpx .act .e{background:#EAF2FF}.cpx .act .u{background:#FFF5DB}.cpx .act .d{background:#F3ECFF}.cpx .act .t{background:#E7F8EC}.cpx .act .x{background:#FEE2E2}
</style>
<div class="cpx">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px"><h1 style="margin:0;flex:1">Coupons</h1><a class="btn g" href="/admin/coupons/export.csv">Download CSV</a><a class="btn" href="/admin/coupons/create">+ New coupon</a></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#0B1220">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#0B1220">{{ session('bad') }}</div>@endif
<div class="grid" style="margin-bottom:12px">
<div class="card k"><b>{{ $cnt['live'] }}</b><span>Live now</span></div><div class="card k"><b>{{ $cnt['sch'] }}</b><span>Scheduled (starts later)</span></div><div class="card k"><b>{{ $uses }}</b><span>Orders using coupons</span></div><div class="card k"><b>৳{{ number_format($given) }}</b><span>Total discount given</span></div></div>
<div class="card">
<div style="margin-bottom:8px">
@foreach([''=>'All '.$cnt['all'],'live'=>'Live '.$cnt['live'],'sch'=>'Scheduled '.$cnt['sch'],'exp'=>'Expired '.$cnt['exp'],'used'=>'Used up '.$cnt['used'],'off'=>'Off '.$cnt['off']] as $k=>$l)<a class="tab {{ $st===$k?'on':'' }}" href="/admin/coupons?status={{ $k }}&q={{ urlencode($q) }}&audience={{ $au }}&type={{ $ty }}">{{ $l }}</a>@endforeach</div>
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px"><input type="hidden" name="status" value="{{ $st }}"><input name="q" value="{{ $q }}" placeholder="Search code or name" style="max-width:240px">
<select name="audience" style="max-width:260px"><option value="">All customers types</option>@foreach(\App\Support\CouponRules::AUDIENCES as $k=>$l)<option value="{{ $k }}" {{ $au===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select>
<select name="type" style="max-width:200px"><option value="">Any discount type</option>@foreach(\App\Support\CouponRules::TYPES as $k=>$l)<option value="{{ $k }}" {{ $ty===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select><button>Filter</button></form>
<div style="overflow-x:auto"><table><thead><tr><th>Code</th><th>Discount</th><th>For customers</th><th>Valid (Dhaka time)</th><th>Used</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($rows as $c)@php($w = \App\Support\CouponRules::window($c))
<tr><td><b>{{ $c->code }}</b>@if($c->name)<small>{{ $c->name }}</small>@endif @if($c->is_public)<small>Shown in customer account</small>@endif</td>
<td>{{ \App\Support\CouponRules::summary($c) }}@if((float)$c->min_order>0)<small>Min order ৳{{ number_format($c->min_order) }}</small>@endif @if(\App\Support\CouponRules::scoped($c))<small>Selected products / categories only</small>@endif</td>
<td>{{ \App\Support\CouponRules::AUDIENCES[$c->audience ?: 'all'] ?? $c->audience }}@if(($c->channel ?? 'any')!=='any')<small>{{ \App\Support\CouponRules::CHANNELS[$c->channel] }}</small>@endif</td>
<td>@if($w[0]||$w[1])<small style="color:#0B1220">{{ $w[0] ? 'From '.\App\Support\CouponRules::local($w[0]) : 'No start' }}</small><small style="color:#0B1220">{{ $w[1] ? 'To '.\App\Support\CouponRules::local($w[1]) : 'No end' }}</small>@else No time limit @endif</td>
<td>{{ $c->_u->n ?? 0 }} / {{ $c->usage_limit ?: '∞' }}@if($c->_u)<small>Discount ৳{{ number_format($c->_u->d) }}</small>@endif<small>{{ $c->per_customer ? $c->per_customer.' per customer' : 'No per-customer limit' }}</small></td>
<td><span class="chip {{ $c->_st[1] }}">{{ $c->_st[0] }}</span></td>
<td><div class="act"><a class="e" href="/admin/coupons/{{ $c->id }}/edit">Edit</a><a class="u" href="/admin/coupons/{{ $c->id }}/usage">Usage</a>
<form method="post" action="/admin/coupons/{{ $c->id }}/toggle">@csrf<button class="t">{{ $c->active ? 'Turn off' : 'Turn on' }}</button></form>
<form method="post" action="/admin/coupons/{{ $c->id }}/duplicate">@csrf<button class="d">Copy</button></form>
<form method="post" action="/admin/coupons/{{ $c->id }}/delete" onsubmit="return confirm('Delete coupon {{ $c->code }}?')">@csrf<button class="x">Delete</button></form></div></td></tr>
@empty<tr><td colspan="7">No coupons match.</td></tr>@endforelse</tbody></table></div></div></div>
@endsection
