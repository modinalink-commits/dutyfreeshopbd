@extends('store.layout')
@section('main')
@php
  $steps = ['Placed','Confirmed','Packed','On the way','Delivered'];
  $map = ['Approved'=>1,'Scheduled'=>1,'WFP'=>1,'Packaging'=>2,'Shipment'=>3,'Late Delivery'=>3,'Partial Delivered'=>4,'Delivered'=>4];
  $at = $o ? ($map[$o->status] ?? 0) : -1; $bad = $o && in_array($o->status, ['Cancel','Return Pending','Returned','Exchange Received']);
@endphp
<style>.tk2{padding:36px 0 56px;display:flex;justify-content:center}.tkc{width:926px;max-width:100%;background:#fff;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:34px 32px 26px}.tkf{display:flex;gap:6px}.tkf input{flex-grow:1;height:32px;border:1px solid #D1D5DB;padding:0 12px;font-size:11px;outline:0;min-width:0}.tkf button{width:210px;background:#0B1220;color:#fff;border:0;display:flex;align-items:center;justify-content:center}
.stp{display:flex;margin-top:6px}.stp div{flex:1;text-align:center;font-size:10px;font-weight:600}.stp i{display:block;width:22px;height:22px;border-radius:50%;margin:0 auto 6px;color:#fff;line-height:22px;font-style:normal}
.ok{padding:40px 0 56px;display:flex;justify-content:center}.okc{width:560px;max-width:100%;background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:30px;text-align:center}.okc .ck{width:54px;height:54px;border-radius:50%;background:#16A34A;color:#fff;font-size:28px;line-height:54px;margin:0 auto 12px}.okc .bt{display:flex;gap:10px;margin-top:18px}.okc .bt a{flex:1;font-size:12px;font-weight:700;padding:10px 0;border-radius:4px}
@media (max-width:760px){.tk2,.ok{padding:16px 10px 30px}.tkc{padding:18px 14px}.tkf button{width:70px}.okc{padding:22px 16px}}</style>
@if(request('new') && $o)
<div class="ok"><div class="okc"><div class="ck">✓</div><div style="font-size:18px;font-weight:700">Your order is placed</div><div style="font-size:12px;color:#555;margin:6px 0 16px">Invoice no. {{ $o->invoice }} · We will call you to confirm. Pay cash on delivery.</div>
<div style="border-top:1px solid #EEE;border-bottom:1px solid #EEE;padding:12px 0;font-size:12px;display:flex;justify-content:space-between"><span>Payable amount</span><b>{{ number_format($o->total) }} TK</b></div>
@if($o->pay_status==='Verification Pending')@php $wm = "Hello DutyFree Shop BD. Payment proof for order {$o->invoice}. Method: {$o->payment_method}. Amount: ".number_format($o->total)." TK. Transaction ID: {$o->pay_txn}"; @endphp<div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:10px;padding:10px;margin:0 0 12px;font-size:12px;color:#065F46"><b>Payment Verification Pending.</b> Your order will be processed only after we approve your payment on WhatsApp. Please send the payment screenshot now.<br><a href="https://wa.me/{{ \App\Http\Controllers\PaymentController::waNumber() }}?text={{ rawurlencode($wm) }}" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;background:#16A34A;color:#fff;padding:10px 16px;border-radius:10px;font-weight:800;text-decoration:none">Send proof on WhatsApp</a></div>@endif
<div class="bt"><a href="/track?invoice={{ $o->invoice }}" style="background:#0B1220;color:#fff">TRACK ORDER</a><a href="/" style="border:1.25px solid #FF5A00;color:#FF5A00">CONTINUE SHOPPING</a></div></div></div>
<script>try{localStorage.removeItem('dfc')}catch(e){}</script>
<script>(function(){var k='dfp_'+@json($o->invoice);try{if(localStorage.getItem(k))return;localStorage.setItem(k,'1')}catch(e){}window.dataLayer.push({ecommerce:null});window.dataLayer.push({event:'purchase',ecommerce:{transaction_id:@json($o->invoice),currency:'BDT',value:{{ (float)$o->total }}}});if(window.gtag&&!{{ !empty($s['gtm_id']) ? 'true':'false' }})gtag('event','purchase',{transaction_id:@json($o->invoice),currency:'BDT',value:{{ (float)$o->total }}});if(window.fbq)fbq('track','Purchase',{currency:'BDT',value:{{ (float)$o->total }}},{eventID:@json($o->invoice)})})();</script>
@else
<div class="tk2"><div class="tkc">
<h1 style="font-size:12px;margin-bottom:10px;font-weight:400"><b>TRACK YOUR CONSIGNMENT</b> &nbsp; Now you can easily track your consignment</h1>
<form class="tkf" action="/track"><input name="invoice" value="{{ request('invoice') }}" placeholder="Enter Order Invoice No" required aria-label="Invoice number"><button aria-label="Track"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button></form>
@if(request('invoice') && !$o)<div style="border-top:1px solid #FDC2A5;margin-top:18px;padding-top:18px;font-size:12px;color:#B91C1C">No order found with this invoice number. Please check and try again, or call {{ $s['call_number'] }}.</div>@endif
@if($o)
<div style="border-top:1px solid #FDC2A5;margin-top:18px;padding-top:18px;font-size:12px"><b>{{ $o->invoice }}</b> · {{ \Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }} · Status: <b style="color:{{ $bad ? '#B91C1C' : '#FF5A00' }}">{{ ['New'=>'Confirmation Pending','Approved'=>'Confirmed','Packaging'=>'Processing'][$o->status] ?? $o->status }}</b></div>
@if(!$bad)<div class="stp" style="margin-top:16px">@foreach($steps as $i => $st)<div><i style="background:{{ $i < $at || ($i === $at && $at === 4) ? '#16A34A' : ($i === $at ? '#FF5A00' : '#D1D5DB') }}">{{ $i < $at || ($i === $at && $at === 4) ? '✓' : $i + 1 }}</i>{{ $st }}</div>@endforeach</div>@endif
<table style="width:100%;margin-top:16px;font-size:12px;border-collapse:collapse">@foreach($items as $it)<tr><td style="padding:5px 0;border-top:1px solid #EEE">{{ $it->name }} × {{ $it->qty }}</td><td style="text-align:right;border-top:1px solid #EEE">{{ number_format($it->price * $it->qty) }} TK</td></tr>@endforeach
<tr><td style="padding:5px 0;border-top:1px solid #EEE">Shipping Charge</td><td style="text-align:right;border-top:1px solid #EEE">{{ number_format($o->shipping) }} TK</td></tr>@if((float)$o->discount > 0)<tr><td style="padding:5px 0">Discount</td><td style="text-align:right">-{{ number_format($o->discount) }} TK</td></tr>@endif
<tr><td style="padding:5px 0;border-top:1px solid #EEE"><b>Payable Amount</b></td><td style="text-align:right;border-top:1px solid #EEE"><b>{{ number_format($o->total) }} TK</b></td></tr></table>
@else
<div class="stp" style="margin-top:18px;opacity:.55">@foreach($steps as $i => $st)<div><i style="background:#D1D5DB">{{ $i + 1 }}</i>{{ $st }}</div>@endforeach</div>
@endif
</div></div>
@endif
@endsection
