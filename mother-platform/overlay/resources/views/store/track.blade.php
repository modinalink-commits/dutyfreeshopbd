@extends('store.layout')
@section('main')
@php
  $steps = ['Placed','Confirmed','Packed','On the way','Delivered'];
  $map = ['Approved'=>1,'Scheduled'=>1,'WFP'=>1,'Packaging'=>2,'Shipment'=>3,'Late Delivery'=>3,'Partial Delivered'=>4,'Delivered'=>4];
  $at = $o ? ($map[$o->status] ?? 0) : -1; $bad = $o && in_array($o->status, ['Cancel','Return Pending','Returned']);
@endphp
<style>.tk2{padding:24px 12px 40px;display:flex;justify-content:center}.tkc{width:926px;max-width:100%;background:#fff;border:1px solid #FED7AA;border-radius:16px;box-shadow:0 2px 10px rgba(15,23,42,.05);padding:22px 24px}
.tkc h1{font-size:19px;font-weight:700;color:#0F172A;margin:0}.tkc .sub{font-size:12.5px;color:#64748B;margin:3px 0 16px}
.tkf{display:flex;gap:8px}.tkf input{flex:1;height:42px;border:1px solid #D1D5DB;border-radius:10px;padding:0 14px;font-size:13px;color:#111827;outline:0;min-width:0}.tkf input:focus{border-color:#FF5A00;box-shadow:0 0 0 3px rgba(255,90,0,.12)}
.tkf button{background:#FF5A00;color:#fff;border:0;border-radius:10px;padding:0 20px;height:42px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:7px;cursor:pointer;transition:.15s}.tkf button:hover{background:#E04F00}
.tkm{margin-top:18px;border:1px solid #FED7AA;background:#FFF7ED;border-radius:12px;padding:12px 14px;display:flex;flex-wrap:wrap;gap:6px 22px;font-size:12.5px;color:#111827}.tkm b{color:#0F172A}.tkm small{display:block;color:#64748B;font-size:11px}
.tke{margin-top:16px;background:#FEE2E2;border:1px solid #FCA5A5;color:#991B1B;border-radius:10px;padding:11px 14px;font-size:12.5px}.tkn{margin-top:16px;font-size:12.5px;color:#64748B;text-align:center}
.stp{display:flex;margin-top:22px;overflow-x:auto;padding-bottom:4px}.stp div{flex:1;min-width:84px;text-align:center;font-size:11.5px;font-weight:700;color:#111827;position:relative}.stp div small{display:block;font-size:10.5px;font-weight:500;color:#64748B;margin-top:1px}
.stp i{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;margin:0 auto 7px;background:#E5E7EB;color:#6B7280;position:relative;z-index:1}
.stp div:not(:last-child):after{content:"";position:absolute;top:16px;left:calc(50% + 17px);right:calc(-50% + 17px);height:2px;background:#E5E7EB}
.stp.tr{padding-top:44px}.trk{position:absolute;left:50%;top:-44px;width:58px;height:auto;margin-left:-29px;z-index:3;pointer-events:none;filter:drop-shadow(0 2px 2px rgba(0,0,0,.18));animation:trkGo 1.6s cubic-bezier(.22,.8,.3,1) both}.trk.mv{animation:trkGo 1.6s cubic-bezier(.22,.8,.3,1) both,trkBob .5s ease-in-out 1.6s infinite alternate}.stp div.c.tkdone .trk,.stp div.tkend .trk{animation:trkGo 1.6s cubic-bezier(.22,.8,.3,1) both}
@keyframes trkGo{from{transform:translateX(calc(var(--drv,0px) * -1))}to{transform:translateX(0)}}@keyframes trkBob{from{transform:translateY(0)}to{transform:translateY(-2px)}}@media (prefers-reduced-motion:reduce){.trk,.trk.mv{animation:none}}
.stp div.d i{background:#FF5A00;color:#fff}.stp div.d:not(:last-child):after{background:#FF5A00}.stp div.c i{background:#FF5A00;color:#fff;box-shadow:0 0 0 4px rgba(255,90,0,.2)}
.stp div:not(.d):not(.c){color:#64748B}
.ok{padding:40px 0 56px;display:flex;justify-content:center}.okc{width:560px;max-width:100%;background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:30px;text-align:center}.okc .ck{width:54px;height:54px;border-radius:50%;background:#16A34A;color:#fff;font-size:28px;line-height:54px;margin:0 auto 12px}.okc .bt{display:flex;gap:10px;margin-top:18px}.okc .bt a{flex:1;font-size:12px;font-weight:700;padding:10px 0;border-radius:4px}
@media (max-width:760px){.tk2,.ok{padding:14px 12px 28px}.tkc{padding:16px 14px}.tkf{flex-direction:column}.tkf button{justify-content:center}.okc{padding:22px 16px}}</style>
@if(request('new') && $o)
<div class="ok"><div class="okc"><div class="ck">✓</div><div style="font-size:18px;font-weight:700">Your order is placed</div><div style="font-size:12px;color:#555;margin:6px 0 16px">Invoice no. {{ $o->invoice }} · We will call you to confirm. Pay cash on delivery.</div>
<div style="border-top:1px solid #EEE;border-bottom:1px solid #EEE;padding:12px 0;font-size:12px;display:flex;justify-content:space-between"><span>Payable amount</span><b>{{ number_format($o->total) }} TK</b></div>
@if($o->pay_status==='Verification Pending')@php $wm = "Hello DutyFree Shop BD. Payment proof for order {$o->invoice}. Method: {$o->payment_method}. Amount: ".number_format($o->total)." TK. Transaction ID: {$o->pay_txn}"; @endphp<div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:10px;padding:10px;margin:0 0 12px;font-size:12px;color:#065F46"><b>Payment Verification Pending.</b> Your order will be processed only after we approve your payment on WhatsApp. Please send the payment screenshot now.<br><a href="https://wa.me/{{ \App\Http\Controllers\PaymentController::waNumber() }}?text={{ rawurlencode($wm) }}" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;background:#16A34A;color:#fff;padding:10px 16px;border-radius:10px;font-weight:800;text-decoration:none">Send proof on WhatsApp</a></div>@endif
<div class="bt"><a href="{{ \App\Support\Inv::trackUrl($o->invoice, false) }}" style="background:#0B1220;color:#fff">TRACK ORDER</a><a href="/" style="border:1.25px solid #FF5A00;color:#FF5A00">CONTINUE SHOPPING</a></div></div></div>
<script>try{localStorage.removeItem('dfc')}catch(e){}</script>
@include('store.pixel_purchase', ['withAm' => false])
@else
<div class="tk2"><div class="tkc">
@php
 $ic = ['<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>','<path d="m5 12 5 5 9-10"/>','<path d="m3 8 9-5 9 5v8l-9 5-9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>','<path d="M2 6h11v10H2zM13 9h4l3 3v4h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>','<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'];
 $bn = ['অর্ডার হয়েছে','নিশ্চিত হয়েছে','প্যাক করা হয়েছে','পথে রয়েছে','ডেলিভারি হয়েছে']; $en = ['Order Placed','Confirmed','Packed','On the Way','Delivered'];
@endphp
<h1>Track Your Order</h1><div class="sub">আপনার অর্ডারের বর্তমান অবস্থা সহজেই দেখুন</div>
<form class="tkf" action="/track"><input name="invoice" value="{{ request('invoice') }}" placeholder="অর্ডার / ইনভয়েস নম্বর লিখুন" required aria-label="Invoice number"><input name="phone" value="{{ request('phone') }}" inputmode="tel" maxlength="20" placeholder="অর্ডারে দেওয়া মোবাইল নম্বর" required aria-label="Phone number"><button><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>Track Order</button></form>
@if(!empty($askPhone))<div class="tkn">নিরাপত্তার জন্য অর্ডারে ব্যবহৃত মোবাইল নম্বরটিও দিন।</div>@endif
@if(!empty($badTrack))<div class="tke">অর্ডার নম্বর ও মোবাইল নম্বর মিলছে না। দুটোই আবার পরীক্ষা করুন।</div>@endif
@if(!request('invoice'))<div class="tkn">অর্ডার নম্বর ও মোবাইল নম্বর দিয়ে আপনার অর্ডারের বর্তমান অবস্থা দেখুন।</div>@endif
@if($o)
<div class="tkm"><div><small>Order</small><b>#{{ $o->invoice }}</b></div><div><small>Current Status</small><b style="color:{{ $bad ? '#B91C1C' : '#FF5A00' }}">{{ ['New'=>'Confirmation Pending','Approved'=>'Confirmed','Packaging'=>'Processing','Shipment'=>'On the Way'][$o->status] ?? $o->status }}</b></div><div><small>Placed</small><b>{{ \Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</b></div></div>
@if(!$bad)<div class="stp tr">@foreach($steps as $i => $st)@php $dn = $i < $at || ($i === $at && $at === 4); @endphp<div class="{{ $dn ? 'd' : ($i === $at ? 'c' : '') }}" @if($i === $at) data-i="{{ $i }}" @endif>@if($i === $at)<picture><source srcset="/track-truck.webp" type="image/webp"><img class="trk {{ $at < 4 && $at > 0 ? 'mv' : '' }}" src="/track-truck.png" alt="" width="58" height="38" loading="eager"></picture>@endif<i><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ic[$i] !!}</svg></i>{{ $en[$i] }}<small>{{ $bn[$i] }}</small></div>@endforeach</div>@endif
<script>(function(){var c=document.querySelector('.stp div[data-i]');if(!c)return;var i=parseInt(c.getAttribute('data-i'),10)||0,t=c.querySelector('.trk');if(!t)return;t.style.setProperty('--drv',(c.offsetWidth*i)+'px')})();</script>
<table style="width:100%;margin-top:18px;font-size:12.5px;border-collapse:collapse;color:#111827">@foreach($items as $it)<tr><td style="padding:6px 0;border-top:1px solid #F1F5F9">{{ $it->name }} × {{ $it->qty }}</td><td style="text-align:right;border-top:1px solid #F1F5F9">{{ number_format($it->price * $it->qty) }} TK</td></tr>@endforeach
<tr><td style="padding:6px 0;border-top:1px solid #F1F5F9">Shipping Charge</td><td style="text-align:right;border-top:1px solid #F1F5F9">{{ number_format($o->shipping) }} TK</td></tr>@if((float)$o->discount > 0)<tr><td style="padding:6px 0">Discount</td><td style="text-align:right">-{{ number_format($o->discount) }} TK</td></tr>@endif
<tr><td style="padding:6px 0;border-top:1px solid #E5E7EB"><b>Payable Amount</b></td><td style="text-align:right;border-top:1px solid #E5E7EB"><b>{{ number_format($o->total) }} TK</b></td></tr></table>
@else
<div class="stp" style="opacity:.8">@foreach($steps as $i => $st)<div><i><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ic[$i] !!}</svg></i>{{ $en[$i] }}<small>{{ $bn[$i] }}</small></div>@endforeach</div>
@endif
</div></div>
@endif
@endsection
