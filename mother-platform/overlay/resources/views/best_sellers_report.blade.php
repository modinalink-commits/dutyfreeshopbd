@extends('layout')@section('title','Best Seller Orders')
@section('body')
@php $t = $d['total']; $nf = fn($v) => number_format((float)$v); $q = fn($a) => '/admin/best-sellers/report?'.http_build_query(array_merge(['from'=>$from,'to'=>$to,'product'=>$pid,'type'=>$type], $a));
$stc = ['Delivered'=>'g','Partial Delivered'=>'g','Returned'=>'r','Return Pending'=>'r','Return'=>'r','Cancel'=>'x','Cancelled'=>'x']; $tz='Asia/Dhaka';
$pre = ['Today'=>[$today,$today],'Yesterday'=>[now($tz)->subDay()->format('Y-m-d'),now($tz)->subDay()->format('Y-m-d')],'Last 7 days'=>[now($tz)->subDays(6)->format('Y-m-d'),$today],'Since offer start'=>[$start,$today]]; @endphp
<style>.bsr{color:#0B1B33}.bsr h1{font-size:18px;margin:0}.bsr h2{font-size:14px;margin:0 0 8px}.bsr .card{padding:12px 14px;margin-bottom:10px}
.bsr .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin:10px 0 6px}.bsr .bar label{font-size:11px;color:#6B7280;display:block}.bsr .bar input,.bsr .bar select{height:32px;border:1px solid #E5E7EB;border-radius:6px;padding:0 8px;font-size:12.5px;background:#fff;max-width:190px}
.bsr .btn{height:32px;border-radius:6px;border:1px solid #C2410C;background:#C2410C;color:#fff;padding:0 14px;font-size:12.5px;font-weight:600;cursor:pointer}
.bsr .pre{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}.bsr .pre a{border:1px solid #E5E7EB;background:#fff;color:#0B1B33;border-radius:999px;font-size:11.5px;padding:4px 11px;text-decoration:none}.bsr .pre a.on{border-color:#F95505;color:#C2410C;background:#FFF1E8;font-weight:600}
.bsr .tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(128px,1fr));gap:8px;margin-bottom:10px}.bsr .tile{border:1px solid #E5E7EB;border-radius:8px;background:#fff;padding:8px 10px}.bsr .tile span{font-size:11px;color:#6B7280;display:block}.bsr .tile b{font-size:19px;font-variant-numeric:tabular-nums}.bsr .tile.o{border-color:#F9B08A;background:#FFF7F2}
.bsr .tw{overflow-x:auto}.bsr table{width:100%;border-collapse:collapse;font-size:12.5px}.bsr th{font-size:11px;color:#6B7280;text-align:left;padding:7px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap;background:#F9FAFB}.bsr td{padding:7px 8px;border-bottom:1px solid #EEF1F4;white-space:nowrap}.bsr .n{text-align:right;font-variant-numeric:tabular-nums}.bsr tr.tt td{font-weight:700;background:#FFF1E8}
.bsr .pill{display:inline-block;font-size:11px;font-weight:600;border-radius:999px;padding:1px 8px;background:#EEF2F6;color:#334155}.bsr .pill.g{background:#DCFCE7;color:#166534}.bsr .pill.r{background:#FEE2E2;color:#B91C1C}.bsr .pill.x{background:#F3F4F6;color:#6B7280}.bsr .pill.of{background:#FFF1E8;color:#C2410C}
.bsr .empty{padding:16px;text-align:center;color:#6B7280}.bsr td a{color:#C2410C;font-weight:600;text-decoration:none}
@media(max-width:760px){.bsr .bar label{flex:1 1 44%}.bsr .bar input,.bsr .bar select{width:100%;max-width:none;height:40px}.bsr .btn{height:40px;flex:1 1 100%}}</style>
<div class="bsr">
<div><h1>Best Seller Orders</h1><small>How many orders the Best Seller products received: by day, by product, and every order. Offer order = 2 or more cartons of one product, ordered after the offer started{{ $since ? ' ('.$since.')' : '' }}. List value = cartons × single price, before the offer or any other discount; open the invoice for the amount charged. Dates are Dhaka time.</small></div>
<form method="get" action="/admin/best-sellers/report" class="bar noprint">
<label>From<br><input type="date" name="from" value="{{ $from }}"></label><label>To<br><input type="date" name="to" value="{{ $to }}"></label>
<label>Product<br><select name="product"><option value="0">All Best Sellers</option>@foreach($products as $id => $nm)<option value="{{ $id }}" @selected($pid === (int)$id)>{{ $nm }}</option>@endforeach</select></label>
<label>Order type<br><select name="type"><option value="">All</option><option value="pack" @selected($type==='pack')>Offer (2+ cartons)</option><option value="pre" @selected($type==='pre')>2+ cartons before the offer</option><option value="single" @selected($type==='single')>Single carton</option></select></label>
<button class="btn">Show</button></form>
<div class="pre noprint">@foreach($pre as $lb => $r)<a href="{{ $q(['from'=>$r[0],'to'=>$r[1]]) }}" class="{{ $from===$r[0] && $to===$r[1] ? 'on' : '' }}">{{ $lb }}</a>@endforeach</div>
<div class="tiles">
<div class="tile o"><span>Total orders</span><b>{{ $nf($t['orders']) }}</b></div><div class="tile"><span>Offer orders (2+)</span><b>{{ $nf($t['pack']) }}</b></div><div class="tile"><span>2+ before offer</span><b>{{ $nf($t['pre']) }}</b></div><div class="tile"><span>Single-carton orders</span><b>{{ $nf($t['single']) }}</b></div>
<div class="tile"><span>Cartons ordered</span><b>{{ $nf($t['cartons']) }}</b></div><div class="tile"><span>List value (TK)</span><b>{{ $nf($t['amount']) }}</b></div>
<div class="tile"><span>Delivered</span><b>{{ $nf($t['delivered']) }}</b></div><div class="tile"><span>In progress</span><b>{{ $nf($t['progress']) }}</b></div><div class="tile"><span>Returned</span><b>{{ $nf($t['returned']) }}</b></div><div class="tile"><span>Cancelled</span><b>{{ $nf($t['cancelled']) }}</b></div>
</div>
@php $cols = ['orders'=>'Orders','pack'=>'Offer (2+)','pre'=>'2+ before offer','single'=>'Single','cartons'=>'Cartons','amount'=>'List value (TK)','delivered'=>'Delivered','progress'=>'In progress','returned'=>'Returned','cancelled'=>'Cancelled']; @endphp
<div class="card"><h2>By product</h2>@if(!$d['products'])<div class="empty">No Best Seller orders in this period.</div>@else
<div class="tw"><table><tr><th>Product</th>@foreach($cols as $c)<th class="n">{{ $c }}</th>@endforeach<th class="n">Net at fixed profit (TK)</th></tr>
@foreach($d['products'] as $nm => $r)<tr><td>{{ $nm }}</td>@foreach($cols as $k => $c)<td class="n">{{ $nf($r[$k]) }}</td>@endforeach<td class="n">{{ $nf($r['delivered_pack_cartons'] * $profit) }}</td></tr>@endforeach
<tr class="tt"><td>Total</td>@foreach($cols as $k => $c)<td class="n">{{ $nf($t[$k]) }}</td>@endforeach<td class="n">{{ $nf($t['delivered_pack_cartons'] * $profit) }}</td></tr></table></div>
<small style="display:block;margin-top:6px">Net at fixed profit = delivered offer cartons × {{ $nf($profit) }} TK. The cost of returned parcels is not taken off.</small>@endif</div>
<div class="card"><h2>By day</h2>@if(!$d['days'])<div class="empty">No Best Seller orders in this period.</div>@else
<div class="tw"><table><tr><th>Date</th>@foreach($cols as $c)<th class="n">{{ $c }}</th>@endforeach</tr>
@foreach($d['days'] as $day => $r)<tr><td>{{ \Carbon\Carbon::parse($day)->format('d M Y, D') }}</td>@foreach($cols as $k => $c)<td class="n">{{ $nf($r[$k]) }}</td>@endforeach</tr>@endforeach
<tr class="tt"><td>Total</td>@foreach($cols as $k => $c)<td class="n">{{ $nf($t[$k]) }}</td>@endforeach</tr></table></div>@endif</div>
<div class="card"><h2>Orders ({{ $nf(count($d['list'])) }})</h2>@if(!$d['list'])<div class="empty">No Best Seller orders in this period.</div>@else
<div class="tw"><table><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Phone</th><th>Product</th><th class="n">Cartons</th><th>Type</th><th class="n">List value (TK)</th><th>Status</th></tr>
@foreach($d['list'] as $r)<tr><td>{{ $r['at'] }}</td><td><a href="/admin/orders/{{ $r['id'] }}">{{ $r['invoice'] ?: '#'.$r['id'] }}</a></td><td>{{ $r['customer'] }}</td><td>{{ $r['phone'] }}</td><td>{{ $r['product'] }}</td><td class="n">{{ $r['q'] }}</td><td><span class="pill {{ $r['type']==='pack' ? 'of' : '' }}">{{ ['pack'=>'Offer','pre'=>'2+ before offer','single'=>'Single'][$r['type']] }}</span></td><td class="n">{{ $nf($r['amt']) }}</td><td><span class="pill {{ $stc[$r['status']] ?? '' }}">{{ $r['status'] }}</span></td></tr>@endforeach
</table></div>@endif</div>
</div>
@endsection
