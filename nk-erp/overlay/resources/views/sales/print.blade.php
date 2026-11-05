<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title>{{ $sale->invoice_no }}</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Plus Jakarta Sans','Hind Siliguri',Arial,sans-serif;color:#000;font-variant-numeric:tabular-nums}
.bar{padding:10px;background:#eef2f9;display:flex;gap:8px;justify-content:center}
.bar button{min-height:40px;padding:0 18px;border-radius:999px;border:0;background:#123a86;color:#fff;font-weight:700;cursor:pointer}
@media print{.bar{display:none}}
.paper{margin:0 auto;background:#fff}
table{width:100%;border-collapse:collapse}
td,th{padding:4px 0;text-align:left;vertical-align:top}
.r{text-align:right}.c{text-align:center}
@if($size === 'a4')
@page{size:A4;margin:14mm}
.paper{width:182mm;font-size:12px;padding:0}
.hd{display:flex;gap:14px;align-items:center;border-bottom:2px solid #123a86;padding-bottom:10px}
.hd img{width:64px;height:64px;border-radius:12px}
.items th{background:#eef2f9;border:1px solid #c9d2e3;padding:6px 8px;text-align:center}
.items td{border:1px solid #c9d2e3;padding:6px 8px}
@else
@page{size:{{ $size }}mm auto;margin:2mm}
.paper{width:{{ $size === '80' ? '72mm' : '50mm' }};font-size:{{ $size === '80' ? '12px' : '10.5px' }};padding:2mm 0}
.hd{text-align:center;border-bottom:1px dashed #000;padding-bottom:4px}
.hd img{width:40px;height:40px;filter:grayscale(1)}
.items th{border-bottom:1px dashed #000}
.sep{border-top:1px dashed #000;margin:4px 0}
@endif
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">Print</button></div>
<div class="paper">
  <div class="hd">
    <img src="{{ $s['logo'] ?? '/img/logo.webp' }}" alt="">
    <div>
      <div style="font-weight:800;font-size:{{ $size === 'a4' ? '20px' : '14px' }}">{{ $s['company_name'] ?? 'NK Corporation' }}</div>
      <div>{{ $s['brand'] ?? '' }} LPG · {{ $s['slogan'] ?? '' }}</div>
      <div>{{ $s['address_en'] ?? '' }}</div>
      <div>Phone {{ $s['phone'] ?? '' }}{{ ! empty($s['bin']) ? ' · BIN '.$s['bin'] : '' }}{{ ! empty($s['trade_license']) ? ' · Trade license '.$s['trade_license'] : '' }}</div>
    </div>
  </div>
  <table style="margin:8px 0">
    <tr><td><b>{{ $sale->type === 'wholesale' ? 'Challan / Invoice' : 'Cash memo' }} {{ $sale->invoice_no }}</b></td><td class="r">{{ $sale->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</td></tr>
    <tr><td colspan="2">Customer: {{ $sale->buyerName() }} @if($sale->buyerPhone())· {{ $sale->buyerPhone() }}@endif</td></tr>
    @if($sale->stage === 'voided')<tr><td colspan="2"><b>VOIDED — {{ $sale->void_reason }}</b></td></tr>@endif
  </table>
  <table class="items">
    <thead><tr><th>Item</th><th class="c">Qty</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead>
    <tbody>@foreach($sale->items as $i)<tr><td>{{ $i->description }}</td><td class="c">{{ $i->qty }}</td><td class="r">{{ number_format($i->unit_price, 2) }}</td><td class="r">{{ number_format($i->line_total, 2) }}</td></tr>@endforeach</tbody>
  </table>
  @if($size !== 'a4')<div class="sep"></div>@endif
  <table style="margin-top:6px">
    <tr><td>Subtotal</td><td class="r">{{ number_format($sale->subtotal, 2) }}</td></tr>
    @if((float) $sale->delivery_charge)<tr><td>Delivery</td><td class="r">{{ number_format($sale->delivery_charge, 2) }}</td></tr>@endif
    @if((float) $sale->discount)<tr><td>Discount</td><td class="r">-{{ number_format($sale->discount, 2) }}</td></tr>@endif
    <tr><td><b>Total (৳)</b></td><td class="r"><b>{{ number_format($sale->total, 2) }}</b></td></tr>
    <tr><td>Paid ({{ \App\Models\Sale::METHODS[$sale->payment_method] ?? '—' }})</td><td class="r">{{ number_format($sale->paid, 2) }}</td></tr>
    @if($sale->due() > 0)<tr><td><b>Due</b></td><td class="r"><b>{{ number_format($sale->due(), 2) }}</b></td></tr>@endif
    <tr><td>Empties received</td><td class="r">{{ $sale->empties_received }} / {{ $sale->empties_expected }}</td></tr>
  </table>
  <div style="margin-top:{{ $size === 'a4' ? '40px' : '8px' }};{{ $size === 'a4' ? 'display:flex;justify-content:space-between' : 'text-align:center' }}">
    @if($size === 'a4')<span>Customer signature</span><span style="text-align:center">@if(! empty($s['signature']))<img src="{{ $s['signature'] }}" alt="" style="height:40px;display:block;margin:0 auto 2px">@endif For {{ $s['company_name'] ?? 'NK Corporation' }}</span>@else<span>Thank you · {{ $s['slogan'] ?? '' }}</span>@endif
  </div>
  @if(! empty($s['invoice_footer']))<div style="margin-top:8px;font-size:{{ $size === 'a4' ? '11px' : '9px' }};text-align:center;border-top:1px solid #c9d2e3;padding-top:6px">{{ $s['invoice_footer'] }}</div>@endif
</div>
<script>if(location.search.indexOf('auto=1')>=0)window.print();</script>
</body>
</html>
