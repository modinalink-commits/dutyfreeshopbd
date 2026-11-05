@extends('layout')
@section('title', $sale->invoice_no)
@section('content')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>{{ $sale->type === 'wholesale' ? 'Dealer order' : 'Memo' }} {{ $sale->invoice_no }}</h1>
    <p class="sub">{{ $sale->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }} · {{ $sale->user?->name }} · @if($sale->stage === 'voided')<b style="color:#b3261e">Voided: {{ $sale->void_reason }}</b>@else {{ $sale->type === 'wholesale' ? $sale->wholesaleStatus() : 'Posted' }}@endif</p></div>
  <a class="btn" href="{{ route('sales.print', [$sale, 'size' => '80']) }}" target="_blank">Print 80 mm</a>
  <a class="btn" href="{{ route('sales.print', [$sale, 'size' => '58']) }}" target="_blank">Print 58 mm</a>
  <a class="btn pri" href="{{ route('sales.print', [$sale, 'size' => 'a4']) }}" target="_blank">A4 invoice</a>
</header>
<div class="grid2" style="align-items:start">
  <section class="card pad" style="display:flex;flex-direction:column;gap:6px">
    <b>{{ $sale->buyerName() }}</b><span class="sub">{{ $sale->buyerPhone() }} @if($sale->customer)· <a href="{{ route('customers.index', ['c' => $sale->customer_id]) }}">customer account</a>@endif</span>
    <span class="sub">Empties: {{ $sale->empties_received }} received of {{ $sale->empties_expected }} expected @if($sale->emptyBrand)({{ $sale->emptyBrand->name }})@endif</span>
  </section>
  <section class="card pad" style="display:flex;flex-direction:column;gap:6px;font-variant-numeric:tabular-nums">
    <div style="display:flex"><span style="flex-grow:1" class="sub">Subtotal</span><span>৳{{ number_format($sale->subtotal, 2) }}</span></div>
    @if((float) $sale->delivery_charge)<div style="display:flex"><span style="flex-grow:1" class="sub">Delivery</span><span>৳{{ number_format($sale->delivery_charge, 2) }}</span></div>@endif
    @if((float) $sale->discount)<div style="display:flex"><span style="flex-grow:1" class="sub">Discount</span><span>−৳{{ number_format($sale->discount, 2) }}</span></div>@endif
    <div style="display:flex;font-weight:700;font-size:var(--fs-16)"><span style="flex-grow:1">Total</span><span>৳{{ number_format($sale->total, 2) }}</span></div>
    <div style="display:flex"><span style="flex-grow:1" class="sub">Paid ({{ \App\Models\Sale::METHODS[$sale->payment_method] ?? '—' }})</span><span>৳{{ number_format($sale->paid, 2) }}</span></div>
    @if($sale->due() > 0)<div style="display:flex;color:#b3261e;font-weight:700"><span style="flex-grow:1">Due</span><span>৳{{ number_format($sale->due(), 2) }}</span></div>@endif
  </section>
</div>
<div class="dt-wrap"><table class="dt">
  <thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
  <tbody>@foreach($sale->items as $i)<tr><td>{{ $i->description }}</td><td class="c">{{ $i->qty }}</td><td class="r">৳{{ number_format($i->unit_price, 2) }}</td><td class="r">৳{{ number_format($i->line_total, 2) }}</td></tr>@endforeach</tbody>
</table></div>
@if($sale->stage !== 'voided' && in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))
<form method="post" action="{{ route('sales.void', $sale) }}" class="card pad" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;max-width:640px" onsubmit="return confirm('Void {{ $sale->invoice_no }}? Stock, empties and the customer account will be reversed.')">
  @csrf
  <label class="f" style="flex-grow:1">Void this {{ $sale->type === 'wholesale' ? 'order' : 'memo' }} — reason<input type="text" name="reason" required></label>
  <button class="btn" type="submit" style="border-color:#b3261e;color:#b3261e">Void</button>
</form>
@endif
@endsection
