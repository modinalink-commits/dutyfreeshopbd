@extends('layout')@section('title','Customer Benefit Eligibility')
@section('body')
<h1 class="h">Customer Benefit Eligibility</h1>
@if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
@if(session('err'))<div style="background:#FEE2E2;color:#B91C1C;padding:10px;border-radius:8px;margin-bottom:10px">{{ session('err') }}</div>@endif
<p style="color:#6B7280;margin-top:0;max-width:860px">Policy: nobody earns a benefit just by placing orders. Membership, returning/VIP/special discounts, birthday and win-back offers depend only on <b>successfully received parcels</b>. Placed, approved, shipped, returned and cancelled orders never count.</p>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0">
@foreach(['Eligible'=>['#DCFCE7','#166534'],'Limited'=>['#FEF3C7','#B45309'],'Review Required'=>['#FFEDD5','#C2410C'],'Suspended'=>['#FEE2E2','#B91C1C']] as $k=>$c)<div style="background:{{ $c[0] }};color:{{ $c[1] }};padding:10px 16px;border-radius:10px;font-weight:700">{{ $k }}: {{ $sum[$k] }}</div>@endforeach
</div>
<form method="get" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
<input name="q" value="{{ $q }}" placeholder="Search mobile or name"><select name="days">@foreach([30,90,180,365] as $d)<option value="{{ $d }}" @selected($days==$d)>Orders in last {{ $d }} days</option>@endforeach</select>
<button class="btn">Filter</button> <a href="/admin/customer-benefit/export?q={{ urlencode($q) }}&days={{ $days }}">Export CSV</a> <button type="button" onclick="window.print()">Print</button></form>
<div class="card" style="overflow:auto"><table><thead><tr><th>Customer</th><th>Mobile</th><th>Total orders</th><th>Received</th><th>Received amount</th><th>Cancelled</th><th>Returned</th><th>Success</th><th>Benefit status</th><th>Reason</th>@if($admin)<th>Override</th>@endif</tr></thead><tbody>
@forelse($rows as $x)@php($col=['Eligible'=>'#166534','Limited'=>'#B45309','Review Required'=>'#C2410C','Suspended'=>'#B91C1C'][$x['status']])
<tr><td>{{ $x['name'] }}</td><td>{{ $x['phone'] }}</td><td>{{ $x['total'] }}</td><td>{{ $x['received'] }}</td><td>৳{{ number_format($x['amount']) }}</td><td>{{ $x['cancelled'] }}</td><td>{{ $x['returned'] }}</td><td>{{ $x['rate'] !== null ? $x['rate'].'%' : '—' }}</td><td style="color:{{ $col }};font-weight:700">{{ $x['status'] }}</td><td style="font-size:12px">{{ $x['reason'] }}@if($x['note'])<br><i>{{ $x['note'] }}</i>@endif</td>
@if($admin)<td><form method="post" action="/admin/customer-benefit/flag" style="display:flex;gap:4px">@csrf<input type="hidden" name="phone" value="{{ $x['phone'] }}"><select name="flag"><option value="suspended">Suspend</option><option value="review">Review Required</option><option value="clear">Clear</option></select><input name="reason" placeholder="Reason *" required minlength="5" maxlength="250" size="16"><button class="btn">Set</button></form></td>@endif</tr>
@empty<tr><td colspan="11">No customers found.</td></tr>@endforelse</tbody></table></div>
@if($admin)<form class="card" method="post" action="/admin/customer-benefit/settings" style="max-width:620px">@csrf<b>Benefit switches (default ON)</b>
@foreach($sw as $k=>$s)<label style="display:flex;gap:8px;align-items:center;margin-top:8px"><input type="checkbox" name="sw_{{ $k }}" value="1" @checked($s['on'])> {{ $s['label'] }}</label>@endforeach
<button class="btn" style="margin-top:10px">Save switches</button></form>@endif
@endsection
