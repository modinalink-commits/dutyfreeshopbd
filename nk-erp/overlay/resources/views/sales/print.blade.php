@if($size === 'a4')
@php
  $sale->loadMissing(['trip.vehicle', 'trip.route', 'trip.salesman', 'user', 'customer']);
  $t = $sale->trip;
  $cats = ['refill' => 'Refill / exchange', 'new_package' => 'New package', 'accessory' => 'Accessory', 'service' => 'Service'];
  $sizes = $sale->items->whereIn('category', ['refill', 'new_package'])->pluck('size_kg')->filter()->unique();
  $sz = $sizes->count() === 1 ? rtrim(rtrim(number_format((float) $sizes->first(), 1), '0'), '.').' kg ' : '';
  $pend = max(0, (int) $sale->empties_expected - (int) $sale->empties_received);
  $custDue = $sale->customer?->due();
  $tk = fn ($n) => '৳'.number_format((float) $n, 2);
  $copy = (int) $sale->a4_prints <= 0 ? 'Original' : 'Copy '.((int) $sale->a4_prints);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title>{{ $sale->invoice_no }}</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;600&display=swap">
<style>
*{box-sizing:border-box}
body{margin:0;background:#eef2f9;font-family:'Plus Jakarta Sans','Hind Siliguri',Arial,sans-serif;color:#14213d}
.bar{padding:10px;display:flex;gap:8px;justify-content:center}
.bar button{min-height:40px;padding:0 18px;border-radius:999px;border:0;background:#123a86;color:#fff;font-weight:700;cursor:pointer;font-family:inherit}
@page{size:A4;margin:0}
@media print{body{background:#fff}.bar{display:none}.pg{margin:0!important;box-shadow:none!important}}
.pg{width:210mm;min-height:297mm;margin:0 auto 24px;box-shadow:0 4px 18px rgba(20,33,61,.12);padding:56px 56px 48px;background:#fff;display:flex;flex-direction:column;gap:24px}
.tbl{width:100%;border-collapse:collapse;font-size:12px;font-variant-numeric:tabular-nums}
.tbl th{background:#eef2f9;color:#51607a;font-size:11px;font-weight:600;text-align:left;padding:9px 10px;border-bottom:1px solid #dbe3f0}
.tbl td{padding:10px;border-bottom:1px solid #dbe3f0}
.tbl .r{text-align:right}
.kv{display:flex;font-size:12px;padding:4px 0}
.kv span:first-child{width:110px;flex-shrink:0;color:#51607a}
.sm{display:flex;font-size:12px;padding:6px 0;font-variant-numeric:tabular-nums}
.sm span:first-child{flex-grow:1;color:#51607a}
.lb{font-size:10px;font-weight:700;letter-spacing:.08em;color:#51607a}
.void{border:2px solid #b42318;color:#b42318;border-radius:10px;padding:8px 12px;font-weight:800;font-size:13px}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">Print</button></div>
<div class="pg">
  <header style="display:flex;align-items:flex-start;gap:16px;padding-bottom:18px;border-bottom:2px solid #123a86">
    <img src="{{ $s['logo'] ?? '/img/logo.webp' }}" alt="{{ $s['company_name'] ?? 'NK Corporation' }} logo" style="width:72px;height:72px;border-radius:12px;object-fit:cover;flex-shrink:0">
    <div style="flex-grow:1;display:flex;flex-direction:column;gap:3px">
      <span style="font-size:24px;font-weight:800;color:#123a86">{{ $s['company_name'] ?? 'NK Corporation' }}</span>
      <span style="font-size:12px;color:#51607a">{{ trim(($s['brand'] ?? '').' LPG') }}{{ ! empty($s['slogan']) ? ' · '.$s['slogan'] : '' }}</span>
      @if(! empty($s['address_en']))<span style="font-size:11px;color:#51607a;line-height:16px">{{ $s['address_en'] }}</span>@endif
      <span style="font-size:11px;color:#51607a">Phone: {{ $s['phone'] ?? '' }}{{ ! empty($s['bin']) ? ' · BIN '.$s['bin'] : '' }}{{ ! empty($s['trade_license']) ? ' · Trade license '.$s['trade_license'] : '' }}</span>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
      <span style="font-size:20px;font-weight:800;letter-spacing:.04em;white-space:nowrap">{{ $sale->type === 'wholesale' ? 'SALES INVOICE' : 'CASH MEMO' }}</span>
      <span style="font-size:13px;font-weight:700">{{ $sale->invoice_no }}</span>
      <span style="font-size:12px;color:#51607a">{{ $sale->created_at->timezone('Asia/Dhaka')->format('d-m-Y · H:i') }}</span>
    </div>
  </header>

  @if($sale->stage === 'voided')<div class="void">VOIDED — {{ $sale->void_reason }}</div>@endif

  <section style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px">
    <div style="display:flex;flex-direction:column">
      <span class="lb" style="margin-bottom:6px">BILL TO</span>
      <span style="font-size:15px;font-weight:700">{{ $sale->buyerName() }}</span>
      <div class="kv"><span>Mobile</span><span>{{ $sale->buyerPhone() ?: '—' }}</span></div>
      <div class="kv"><span>Address</span><span>{{ $sale->customer?->address ?: ($sale->customer?->area ?: '—') }}</span></div>
      <div class="kv"><span>Customer ID</span><span>{{ $sale->customer?->code ?: 'Walk-in' }}</span></div>
    </div>
    <div style="display:flex;flex-direction:column">
      <span class="lb" style="margin-bottom:6px">{{ $t ? 'DELIVERED BY' : 'SOLD BY' }}</span>
      @if($t)
      <div class="kv"><span>Salesman</span><span>{{ $t->salesman?->name ?? '—' }}</span></div>
      <div class="kv"><span>Vehicle</span><span>{{ $t->vehicle ? $t->vehicle->code.' · '.$t->vehicle->reg_no : '—' }}</span></div>
      <div class="kv"><span>Trip</span><span>{{ $t->trip_no }}</span></div>
      <div class="kv"><span>Route</span><span>{{ $t->route?->name ?? '—' }}</span></div>
      @else
      <div class="kv"><span>Staff</span><span>{{ $sale->user?->name ?? '—' }}</span></div>
      <div class="kv"><span>Sale</span><span>{{ $sale->type === 'wholesale' ? 'Wholesale' : 'Counter (POS)' }}</span></div>
      @if($sale->vehicle)<div class="kv"><span>Vehicle</span><span>{{ $sale->vehicle }}</span></div>@endif
      @endif
    </div>
  </section>

  <table class="tbl">
    <thead><tr><th>#</th><th>Product</th><th>Size</th><th>Sale type</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
    <tbody>
      @foreach($sale->items as $n => $i)
      <tr><td>{{ $n + 1 }}</td><td>{{ $i->description }}</td><td>{{ $i->size_kg ? rtrim(rtrim(number_format((float) $i->size_kg, 1), '0'), '.').' kg' : '—' }}</td><td>{{ $cats[$i->category] ?? ucfirst((string) $i->category) }}</td><td class="r">{{ $i->qty }}</td><td class="r">{{ $tk($i->unit_price) }}</td><td class="r" style="font-weight:700">{{ $tk($i->line_total) }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <section style="display:flex;gap:24px;align-items:flex-start">
    <div style="flex:1;display:flex;flex-direction:column;gap:6px">
      <span class="lb">EMPTY CYLINDERS</span>
      <div style="border:1px solid #dbe3f0;border-radius:10px;padding:10px 12px;display:flex;flex-direction:column">
        <div class="sm"><span>{{ $sz }}expected</span><span>{{ (int) $sale->empties_expected }}</span></div>
        <div class="sm"><span>{{ $sz }}received</span><span>{{ (int) $sale->empties_received }}</span></div>
        <div class="sm" style="font-weight:700"><span>Pending with customer</span><span>{{ $pend }}</span></div>
      </div>
    </div>
    <div style="width:300px;display:flex;flex-direction:column">
      <span class="lb" style="margin-bottom:6px">PAYMENT</span>
      <div class="sm"><span>Sub total</span><span>{{ $tk($sale->subtotal) }}</span></div>
      @if((float) $sale->delivery_charge)<div class="sm"><span>Delivery</span><span>{{ $tk($sale->delivery_charge) }}</span></div>@endif
      @if((float) $sale->discount)<div class="sm"><span>Discount</span><span>−{{ $tk($sale->discount) }}</span></div>@endif
      <div class="sm" style="font-size:15px;font-weight:800;border-top:1px solid #dbe3f0;padding-top:8px"><span style="color:#14213d">Total</span><span>{{ $tk($sale->total) }}</span></div>
      <div class="sm"><span>Paid — {{ strtolower(\App\Models\Sale::METHODS[$sale->payment_method] ?? '—') }}</span><span>{{ $tk($sale->paid) }}</span></div>
      <div class="sm"><span>Due on this invoice</span><span>{{ $tk($sale->due()) }}</span></div>
      @if($custDue !== null)<div class="sm" style="background:#fff1d6;border-radius:8px;padding:8px 10px;margin-top:4px"><span style="color:#8a4b00;font-weight:600">Customer total due</span><span style="color:#8a4b00;font-weight:700">{{ $tk($custDue) }}</span></div>@endif
    </div>
  </section>

  <span style="font-size:12px;color:#51607a">Amount in words: {{ \App\Support\Ui::words($sale->total) }}</span>

  <footer style="margin-top:auto;display:flex;flex-direction:column;gap:28px">
    <div style="display:flex;justify-content:space-between;align-items:flex-end">
      <div style="display:flex;flex-direction:column;gap:6px;width:200px">
        <div style="border-top:1px solid #14213d"></div>
        <span style="font-size:11px;color:#51607a">Customer signature</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;width:200px;align-items:flex-end">
        @if(! empty($s['signature']))
        <img src="{{ $s['signature'] }}" alt="Authorized signature" style="height:36px;max-width:100%;object-fit:contain;align-self:center">
        @else
        <div style="width:100%;height:36px;border:1px dashed #7b8aa8;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#51607a">Signature not uploaded</div>
        @endif
        <div style="border-top:1px solid #14213d;width:100%"></div>
        <span style="font-size:11px;color:#51607a">Authorized signature · {{ $s['company_name'] ?? 'NK Corporation' }}</span>
      </div>
    </div>
    <div style="border-top:1px solid #dbe3f0;padding-top:10px;display:flex;gap:12px;font-size:10px;color:#51607a">
      <span style="flex-grow:1">Printed {{ now('Asia/Dhaka')->format('d-m-Y H:i') }} · {{ $copy }} · Page 1 of 1</span>
      <span>{{ ! empty($s['invoice_footer']) ? $s['invoice_footer'] : 'Invoice footer: not set in Company settings' }}</span>
    </div>
  </footer>
</div>
<script>if(location.search.indexOf('auto=1')>=0)window.print();</script>
</body>
</html>
@else
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

@endif
