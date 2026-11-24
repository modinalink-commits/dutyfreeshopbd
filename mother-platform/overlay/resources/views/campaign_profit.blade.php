@extends('layout')@section('title','Campaign Profit')
@section('body')
<style>.cpf td,.cpf th{font-size:12.5px;white-space:nowrap}.cpf .n{text-align:right}.cpf .pos{color:#166534;font-weight:700}.cpf .neg{color:#B91C1C;font-weight:700}</style>
<div class="cpf">
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Campaign Profit</h1><span style="font-size:13px;color:#6B7280">Which automatic campaign really earns money. An order counts for a campaign when the customer got its message or call in the {{ $win }} hours before ordering (the last message wins). Profit counts delivered orders only (sales − discount − product cost − courier), then the message cost is taken off.</span></div><a class="btn g" href="/admin/winback/autopilot">Marketing Autopilot</a></div>
@foreach([7 => 'Last 7 days', 30 => 'Last 30 days'] as $d => $label)
<h3 style="margin:14px 0 6px">{{ $label }}</h3>
<div class="card" style="overflow-x:auto">
@if($rep[$d] === null)<div style="padding:10px;color:#B91C1C">Could not be calculated now. Try again in a minute.</div>
@elseif(!count($rep[$d]))<div style="padding:10px;color:#6B7280">No campaign messages in this period.</div>
@else<table><thead><tr><th>Campaign</th><th>Channel</th><th class="n">Customers</th><th class="n">Messages</th><th class="n">Orders</th><th class="n">Delivered</th><th class="n">Returned</th><th class="n">Cancelled</th><th class="n">Delivered sales</th><th class="n">Profit</th><th class="n">Message cost</th><th class="n">Net</th><th class="n">Net / 100 customers</th></tr></thead><tbody>
@foreach($rep[$d] as $r)<tr><td><b>{{ $r['name'] }}</b></td><td>{{ $r['ch'] }}</td><td class="n">{{ number_format($r['people']) }}</td><td class="n">{{ number_format($r['msgs']) }}</td><td class="n">{{ number_format($r['orders']) }}</td><td class="n">{{ number_format($r['delivered']) }}</td><td class="n">{{ number_format($r['returned']) }}</td><td class="n">{{ number_format($r['cancelled']) }}</td><td class="n">৳{{ number_format($r['sales']) }}</td><td class="n">৳{{ number_format($r['profit']) }}</td><td class="n">৳{{ number_format($r['cost']) }}</td><td class="n {{ $r['net'] >= 0 ? 'pos' : 'neg' }}">৳{{ number_format($r['net']) }}</td><td class="n {{ $r['per100'] >= 0 ? 'pos' : 'neg' }}">৳{{ number_format($r['per100']) }}</td></tr>@endforeach
</tbody></table>@endif</div>
@endforeach
<div style="font-size:12px;color:#6B7280;margin-top:8px">Orders still on the way are counted in Orders but not yet in Profit. Email is not included yet (its send log carries no per-person record). Updated every 10 minutes.</div>
</div>
@endsection
