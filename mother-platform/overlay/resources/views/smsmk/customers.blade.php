@extends('layout')@section('title','Customer Segments')
@section('body')
<h1 style="margin:0 0 12px">Customer Segments &amp; Selection</h1>@include('smsmk.nav')
<form class="card" method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
<div><label>Search name / phone</label><input name="q" value="{{ request('q') }}"></div>
<div><label>Segment</label><select name="seg"><option value="">All customers</option>@foreach($segments as $k=>$v)@if($k!=='all')<option value="{{ $k }}" {{ request('seg')===$k?'selected':'' }}>{{ $v }}</option>@endif @endforeach</select></div>
<div><label>Min orders</label><input type="number" name="min_orders" value="{{ request('min_orders') }}" style="width:90px"></div>
<div><label>Min spend ৳</label><input type="number" name="min_spend" value="{{ request('min_spend') }}" style="width:110px"></div>
<div><label>No order in (days)</label><input type="number" name="inactive_days" value="{{ request('inactive_days') }}" style="width:100px"></div>
<button>Filter</button></form>
<div class="card tw"><div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px"><b id="cnt">{{ number_format($total) }} customers</b><span style="font-size:12px;color:#6B7280">(showing first {{ count($rows) }})</span>
<span style="flex:1"></span><label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" id="all" style="width:auto"> Select shown</label>
<a class="btn g" id="allf" href="#">Select all {{ number_format($total) }} filtered</a><a class="btn" id="go" href="#">Send SMS to selected (<span id="n">0</span>)</a></div>
<table><thead><tr><th></th><th>Customer</th><th>Mobile</th><th>Category</th><th>Orders</th><th>Spend</th><th>Last order</th></tr></thead><tbody>
@foreach($rows as $c)<tr><td><input type="checkbox" class="pk" value="{{ $c->id }}" style="width:auto"></td><td>{{ $c->name }}</td><td>{{ $c->phone }} @if(isset($opt[$c->num]))<span class="bd failed">opted out</span>@endif</td>
<td style="font-size:12px">@foreach(array_slice(array_intersect($c->tags,['vip','returning','high_value','new','inactive','membership']),0,3) as $t)<span class="bd">{{ \App\Support\SmsMarketing::SEGMENTS[$t] ?? $t }}</span> @endforeach</td><td>{{ $c->orders }}</td><td>৳{{ number_format($c->spend) }}</td><td>{{ $c->last ? $c->last->timezone('Asia/Dhaka')->format('d M Y') : '—' }}</td></tr>@endforeach</tbody></table></div>
<script>(function(){var all=@json($ids),forceAll=false,pk=[].slice.call(document.querySelectorAll('.pk')),n=document.getElementById('n');
function sel(){return forceAll?all:pk.filter(function(x){return x.checked}).map(function(x){return x.value})}
function upd(){n.textContent=sel().length}
pk.forEach(function(x){x.onchange=function(){forceAll=false;upd()}});
document.getElementById('all').onchange=function(){forceAll=false;var v=this.checked;pk.forEach(function(x){x.checked=v});upd()};
document.getElementById('allf').onclick=function(e){e.preventDefault();forceAll=true;pk.forEach(function(x){x.checked=true});upd()};
document.getElementById('go').onclick=function(e){e.preventDefault();var s=sel();if(!s.length){alert('Select at least one customer');return}
if(s.length>800){alert('For more than 800 customers use Bulk SMS with a segment or filter.');return}location.href='/admin/sms-marketing/send?ids='+s.join(',')};})();</script>
@endsection
