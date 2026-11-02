<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $type === 'label' ? 'Label Print' : 'Invoice Print' }}</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,'Helvetica Neue','Noto Sans Bengali','Nirmala UI',sans-serif;color:#000;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.bar{position:sticky;top:0;background:#0B1F3A;color:#fff;padding:8px 14px;display:flex;gap:12px;align-items:center;font-size:14px}.bar button{background:#FF5A00;color:#fff;border:0;border-radius:6px;padding:7px 16px;font-weight:700;cursor:pointer}
.sheet{page-break-after:always;break-after:page}.sheet:last-child{page-break-after:auto;break-after:auto}
img{max-width:100%}.bc svg{display:block;width:100%;height:100%}
/* INVOICE: same 80 mm column as the old invoice, centred */
.inv{width:64mm;margin:0 auto;font-size:10.5px;line-height:1.4;border-top:3px solid #FF5A00;padding-top:6px}.inv .hd{text-align:center}.inv .hd img{height:36px}.inv .site{font-weight:700;color:#0B1F3A;font-size:12px}
.inv .id{margin:6px 0 2px;text-align:center;font-size:17px;font-weight:800;letter-spacing:.3px}.inv .meta{display:flex;justify-content:space-between;font-size:10.5px;color:#222;margin-bottom:5px}
.inv .chips{text-align:center;margin-bottom:5px}.inv .chips span{display:inline-block;border:1px solid #0B1F3A;border-radius:10px;padding:1px 7px;margin:1px;font-size:10px;font-weight:700;color:#0B1F3A}
.inv .cust{border:1px solid #0B1F3A;border-radius:5px;padding:5px 7px;margin:4px 0}.inv .cust b{color:#0B1F3A}
.inv table{width:100%;border-collapse:collapse;margin-top:4px}.inv th{font-size:9.5px;text-transform:uppercase;text-align:left;border-bottom:1.5px solid #0B1F3A;padding:2px 0;color:#0B1F3A}.inv td{padding:3px 0;border-bottom:.5px solid #bbb;vertical-align:top}.inv .r{text-align:right}.inv small{color:#333;font-size:9.5px}
.inv .tot{margin-top:5px}.inv .tot div{display:flex;justify-content:space-between;padding:1.5px 0}.inv .due{border-top:1.5px solid #0B1F3A;margin-top:3px;padding-top:4px!important;font-size:14.5px;font-weight:800;color:#0B1F3A}
.inv .save{border:1px dashed #FF5A00;border-radius:5px;padding:3px 7px;margin:5px 0;text-align:center;font-weight:700}.inv .words{font-weight:700;margin:6px 0 4px}.inv .ship{font-size:10.5px;margin:3px 0}
.inv .ft{text-align:center;font-size:10.5px;margin-top:6px}.inv .codes{display:flex;justify-content:space-between;align-items:center;margin-top:6px}.inv .codes .qr{width:18mm;height:18mm}.inv .codes .bc{width:42mm;height:10mm}.qr svg{width:100%;height:100%}
/* LABEL: full printable width, like the old label */
.lbl{width:59mm;margin:0 auto;border:1px dashed #000;font-size:11px;line-height:1.3;padding:4px 5px;box-sizing:border-box}.lbl .hd{text-align:center}.lbl .hd img{height:34px}.lbl .site{font-size:12px;color:#0B1F3A;font-weight:700}.lbl .id{text-align:center;font-size:14px;font-weight:800;margin:3px 0}
.lbl .bc{width:85%;height:11mm;margin:2px auto 8px}.lbl .row{margin:3px 0;font-size:11.5px}.lbl .row b{display:inline-block;min-width:48px;color:#0B1F3A}
.lbl .phone{font-size:19px;font-weight:900;letter-spacing:.3px;margin:5px 0;border-top:1px solid #000;padding-top:5px}.lbl .addr{font-size:13px;font-weight:800;border:2px solid #000;border-radius:6px;padding:7px 9px;margin:6px 0;line-height:1.35}
.lbl .cod{border:3px solid #000;border-radius:8px;text-align:center;font-size:23px;font-weight:900;padding:4px;margin:6px 0}.lbl .cod small{display:block;font-size:11px;font-weight:700;letter-spacing:1px}
.lbl .ex{display:flex;flex-wrap:wrap;gap:2px 16px;font-size:10.5px;border-top:1px solid #000;padding-top:5px}.lbl .ex b{color:#0B1F3A}
@media print{.bar{display:none}}
</style></head><body>
<script>(function(){var ids={!! json_encode($sheets->pluck('o.id')->values()) !!},type={!! json_encode($type) !!},last=0;function log(){if(Date.now()-last<4000)return;last=Date.now();var b=(self.crypto&&crypto.randomUUID?crypto.randomUUID():String(Date.now())+Math.random()).replace(/-/g,"").slice(0,32);var t=document.querySelector('meta[name=csrf-token]').content;try{fetch('/admin/orders/print-log',{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':t},body:JSON.stringify({type:type,ids:ids,batch:b,device:(navigator.platform||"").slice(0,60)})})}catch(e){}}window.addEventListener('beforeprint',log)})();</script>
<div class="bar"><span>{{ count($sheets) }} {{ $type === 'label' ? 'label' : 'invoice' }}{{ count($sheets) === 1 ? '' : 's' }} ready: each order prints on its own page.</span><button onclick="window.print()">Print</button></div>
@foreach($sheets as $s)@php $o = $s['o']; $disc = (float)($o->discount ?? 0); $dname = trim((string)($o->discount_type ?? '')) ?: 'Discount'; $dpct = $o->discount_pct ?? null; $addr = trim($o->address.($o->district ? ', '.$o->district : '')); @endphp
@if($type === 'invoice')
<div class="sheet"><div class="inv">
<div class="hd"><img src="/logo.webp" alt="DutyFree"><div class="site">www.dutyfreebd.com</div></div>
<div class="id">{{ $o->invoice }}</div>
<div class="meta"><span>Date: {{ \Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('Y-m-d') }}</span><span>Qty: {{ $s['qty'] }}</span></div>
<div class="chips"><span>Status: {{ $o->status }}</span><span>Payment: {{ $s['pay'] }}</span>@if($o->courier)<span>Courier: {{ $o->courier }}</span>@endif @if($s['track'])<span>Tracking: {{ $s['track'] }}</span>@endif</div>
<div class="cust"><b>Customer:</b> {{ $o->customer_name }}<br><b>Phone:</b> {{ $o->phone }}<br><b>Address:</b> {{ $addr }}</div>
<table><thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr></thead><tbody>@foreach($s['items'] as $it)<tr><td>{{ $it->name }}@if($it->sku)<br><small>Code: {{ $it->sku }}</small>@endif</td><td class="r">{{ $it->qty }}</td><td class="r">{{ number_format($it->price, 2) }}</td><td class="r">{{ number_format($it->price * $it->qty, 2) }}</td></tr>@endforeach</tbody></table>
<div class="tot"><div><span>Sub Total:</span><b>{{ number_format($s['sub'], 2) }}</b></div><div><span>Delivery Charge{{ ($o->delivery_zone ?? null) ? ' ('.$o->delivery_zone.')' : '' }}:</span><b>{{ ($o->shipping ?? 0) <= 0 && ($o->delivery_free_discount ?? 0) > 0 ? 'FREE' : number_format($o->shipping, 2) }}</b></div>@if(($o->delivery_manual_adj ?? null) !== null)<div><span>Manual delivery adjustment:</span><b>{{ number_format($o->delivery_manual_adj, 2) }}</b></div>@endif<div><span>Discount:</span><b>{{ number_format($disc, 2) }}</b></div><div><span>Paid:</span><b>{{ number_format($s['paid'], 2) }}</b></div><div class="due"><span>Amount Due:</span><span>{{ number_format($s['due'], 2) }}</span></div></div>
@if($disc > 0)<div class="save">{{ $dname }}{{ $dpct !== null && (float)$dpct > 0 ? ': '.rtrim(rtrim(number_format((float)$dpct, 2), '0'), '.').'%' : '' }} · You Saved: ৳{{ number_format($disc) }}</div>@endif
<div class="words">In Words: {{ $s['words'] }}</div>
<div class="ft">Thank You For Shopping With Us.<br>Please Come Again</div>
<div class="codes"><div class="qr" data-qr="{{ $s['url'] }}"></div><div class="bc">{!! $s['bc'] !!}</div></div>
</div></div>
@else
<div class="sheet"><div class="lbl">
<div class="hd"><img src="/logo.webp" alt="DutyFree"><div class="site">www.dutyfreebd.com</div></div>
<div class="id">Invoice #{{ $o->invoice }}</div><div class="bc">{!! $s['bc'] !!}</div>
<div class="row"><b>Name:</b> {{ $o->customer_name }}</div>
<div class="phone">{{ $o->phone }}</div>
<div class="addr">{{ $addr }}</div>
<div class="cod"><small>{{ $s['due'] > 0 ? 'CASH ON DELIVERY' : 'PAID: NO COLLECTION' }}</small>COD: ৳{{ number_format($s['due']) }}</div>
<div class="ex">@if($o->courier)<span><b>Courier:</b> {{ $o->courier }}</span>@endif @if($s['track'])<span><b>Tracking:</b> {{ $s['track'] }}</span>@endif<span><b>Qty:</b> {{ $s['qty'] }}</span><span><b>Payment:</b> {{ $s['pay'] }}</span></div>
</div></div>
@endif
@endforeach
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
(function(){
 function qr(){try{document.querySelectorAll('[data-qr]').forEach(function(e){var q=qrcode(0,'M');q.addData(e.getAttribute('data-qr'));q.make();e.innerHTML=q.createSvgTag({cellSize:2,margin:0,scalable:true})})}catch(x){}}
 qr();var done=false;function go(){if(done)return;done=true;setTimeout(function(){window.print()},400)}
 var imgs=[].slice.call(document.images),n=imgs.length;if(!n)return go();imgs.forEach(function(i){if(i.complete)--n;else{i.onload=i.onerror=function(){if(--n<=0)go()}}});if(n<=0)go();setTimeout(go,3000);
})();
</script></body></html>
