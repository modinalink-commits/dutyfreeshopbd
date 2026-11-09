@php
  $__ln = \App\Support\OrderDiscount::lines($o); $__disc = round((float)($o->discount ?? 0), 2); $__ship = round((float)($o->shipping ?? 0), 2); $__tot = round((float)$o->total, 2);
  $__sub = round($__tot + $__disc - $__ship, 2); $__paid = min(round((float)($o->paid_total ?? 0), 2), $__tot); $__due = max(round($__tot - $__paid, 2), 0); $__cod = \App\Support\PaymentSettle::cod($o);
  $__f = fn($n) => '৳'.number_format($n, 2);
@endphp
<div class="card" style="font-size:13px;line-height:1.7"><b>Order Amount</b>
<table style="width:100%;max-width:560px;margin-top:6px"><tbody>
<tr><td>Subtotal (original)</td><td style="text-align:right">{{ $__f($__sub) }}</td></tr>
@if($__ln)
<tr><td colspan="2" style="padding-top:6px;color:#6B7280"><b>Discounts</b></td></tr>
@foreach($__ln as $__l)
@php $__c = \App\Support\OrderDiscount::colorOf((string)($__l['name'] ?? '').' '.(empty($__l['auto']) ? 'manual' : '')); @endphp
<tr><td style="padding-left:10px;vertical-align:top"><b style="display:inline-block;padding:1px 8px;border-radius:999px;color:{{ $__c[0] }};background:{{ $__c[1] }};border:1px solid {{ $__c[2] }}">{{ $__l['name'] }}</b>@if(!empty($__l['pct'])) {{ rtrim(rtrim(number_format($__l['pct'], 2), '0'), '.') }}%@elseif(($__l['kind'] ?? '') === 'Fixed') <span style="color:#6B7280">(Fixed)</span>@endif
<div style="font-size:12px;color:#6B7280">@if(!empty($__l['source']))Source: {{ $__l['source'] }}@endif @if(!empty($__l['rule'])) · Rule: {{ $__l['rule'] }}@endif @if(!empty($__l['code'])) · Code: <b>{{ $__l['code'] }}</b>@endif · Applied: {{ !empty($__l['auto']) ? 'Automatic' : 'Manual' }} by {{ $__l['by'] ?? 'System' }}@if(!empty($__l['reason'])) · Reason: {{ $__l['reason'] }}@endif @if(!empty($__l['at'])) · {{ \Carbon\Carbon::parse($__l['at'])->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}@endif @if(!empty($__l['legacy']) && ($__l['amount'] ?? null) === null) · <i>(amount per offer not saved on older orders)</i>@endif</div></td>
<td style="text-align:right;vertical-align:top;font-weight:700;color:{{ $__c[0] }}">@if(($__l['amount'] ?? null) !== null)-{{ $__f($__l['amount']) }}@else — @endif</td></tr>
@endforeach
@endif
<tr style="border-top:1px solid #E5E7EB"><td><b>Total Discount</b></td><td style="text-align:right"><b>{{ $__disc > 0 ? '-' : '' }}{{ $__f($__disc) }}</b></td></tr>
<tr><td>Delivery Charge</td><td style="text-align:right">{{ $__f($__ship) }}</td></tr>
<tr style="border-top:1px solid #E5E7EB"><td><b>Grand Total (final order value)</b></td><td style="text-align:right"><b>{{ $__f($__tot) }}</b></td></tr>
<tr><td>Paid</td><td style="text-align:right">{{ $__f($__paid) }}</td></tr>
<tr><td>Due / Collectable</td><td style="text-align:right">{{ $__f($__due) }}</td></tr>
<tr><td>Courier COD</td><td style="text-align:right">{{ $__f($__cod) }}</td></tr>
@if(!empty($o->consignment_id) && ($o->courier_cod ?? null) !== null && !in_array($o->status, ['Cancel','Returned','Delivered'], true) && abs((float)$o->courier_cod - $__cod) > 0.99)
<tr><td colspan="2" style="padding-top:6px"><div style="background:#FEF3C7;color:#B45309;border-radius:8px;padding:6px 10px;font-size:12px"><b>Check the courier parcel:</b> {{ $o->courier }} was booked to collect {{ $__f((float)$o->courier_cod) }}, but the amount to collect is now {{ $__f($__cod) }}. Change the COD amount on the courier parcel so the customer is not charged wrongly.</div></td></tr>
@endif
</tbody></table></div>
