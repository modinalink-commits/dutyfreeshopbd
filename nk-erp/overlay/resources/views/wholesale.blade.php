@extends('layout')
@section('title', 'Wholesale orders')
@section('content')
@php($mgr = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Wholesale orders</h1><p class="sub">Dealers and sub-dealers · loading, delivery and collection</p></div>
  <a class="btn" href="{{ route('products.index') }}">Dealer price list</a>
</header>
<section class="kpis">
  @foreach(['waiting' => ['Waiting to load', '#e6eeff', '#1d4ed8'], 'on_road' => ['On the road', '#fff1d6', '#8a4b00'], 'unpaid' => ['Delivered, unpaid', '#fde9e7', '#b3261e'], 'part' => ['Part paid', '#efe7fb', '#5b2fa8'], 'closed' => ['Closed', '#e3f6ec', '#0f6b3c']] as $k => [$l, $bg, $fg])
  <div class="card kpi"><span class="pill" style="align-self:flex-start;background:{{ $bg }};color:{{ $fg }}">{{ $l }}</span><b>{{ $stats[$k][0] }}</b><span>{{ $stats[$k][1] }}</span></div>
  @endforeach
</section>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 560px;min-width:0">
    <form method="get" class="pad" style="display:flex;align-items:center;gap:8px"><h2 style="margin:0;font-size:15px;flex-grow:1">Orders — last 60 days</h2>
      <select name="stage" onchange="this.form.submit()" aria-label="Filter by stage" style="max-width:200px;border-radius:999px"><option value="">All stages</option>@foreach(['waiting' => 'Waiting to load', 'on_road' => 'On the road', 'delivered' => 'Delivered', 'voided' => 'Voided'] as $k => $l)<option value="{{ $k }}" @selected($stage === $k)>{{ $l }}</option>@endforeach</select></form>
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Order</th><th>Date</th><th>Dealer</th><th>12 kg</th><th>35 kg</th><th>45 kg</th><th>Empties back</th><th>Bill</th><th>Paid</th><th>Stage</th>@if($mgr)<th class="no-sort">Next step</th>@endif</tr></thead>
      <tbody>
      @forelse($orders as $o)
        @php($bySize = $o->items->whereNotNull('size_kg')->groupBy('size_kg')->map->sum('qty'))
        <tr>
          <td class="c"><a href="{{ route('sales.show', $o) }}">{{ $o->invoice_no }}</a></td><td class="c nw">{{ $o->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</td><td>{{ $o->customer?->name }}</td>
          <td class="c">{{ $bySize[12] ?? '—' }}</td><td class="c">{{ $bySize[35] ?? '—' }}</td><td class="c">{{ $bySize[45] ?? '—' }}</td>
          <td class="c">{{ $o->stage === 'delivered' ? $o->empties_received.' / '.$o->empties_expected : '— / '.$o->empties_expected }}</td>
          <td class="r"><b>৳{{ number_format($o->total) }}</b></td><td class="r">{{ (float) $o->paid ? '৳'.number_format($o->paid) : '—' }}</td>
          <td class="c"><span class="pill">{{ $o->wholesaleStatus() }}</span></td>
          @if($mgr)<td class="nw">
            @if($o->stage === 'waiting')
              <form method="post" action="{{ route('wholesale.load', $o) }}" style="display:flex;gap:4px">@csrf<input type="text" name="vehicle" value="{{ $o->vehicle }}" placeholder="Vehicle" style="width:110px;min-height:30px"><button class="btn sm pri" type="submit">Loaded</button></form>
            @elseif($o->stage === 'on_road')
              <details><summary style="cursor:pointer;font-weight:600">Mark delivered</summary>
                <form method="post" action="{{ route('wholesale.deliver', $o) }}" style="display:flex;flex-direction:column;gap:6px;margin-top:6px;min-width:220px">@csrf
                  <label class="f">Empties received<input type="number" name="empties" min="0" value="{{ $o->empties_expected }}" required></label>
                  <label class="f">Empty brand<select name="empty_brand_id">@foreach($brands as $b)<option value="{{ $b->id }}" @selected($b->id == $ownBrand)>{{ $b->name }}</option>@endforeach</select></label>
                  <label class="f">Paid now<input type="number" name="paid" min="0" value="0"></label>
                  <label class="f">Method<select name="method"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="bank">Bank</option><option value="due">All on due</option></select></label>
                  <button class="btn sm pri" type="submit">Save delivery</button></form></details>
            @elseif($o->stage === 'delivered' && $o->due() > 0)
              <a class="btn sm" href="{{ route('customers.index', ['c' => $o->customer_id]) }}">Receive payment</a>
            @endif
          </td>@endif
        </tr>
      @empty
        <tr><td colspan="{{ $mgr ? 11 : 10 }}" class="empty">No dealer orders yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
  @if($mgr)
  <form class="card pad" method="post" action="{{ route('wholesale.store') }}" style="flex:1 1 320px;min-width:290px;display:flex;flex-direction:column;gap:12px" id="wform">
    @csrf
    <h2 style="margin:0;font-size:15px">New dealer order</h2>
    <label class="f">Dealer<select name="customer_id" required><option value="">Choose dealer</option>@foreach($dealers as $d)<option value="{{ $d->id }}" @selected(old('customer_id') == $d->id)>{{ $d->name }}@if($d->area) — {{ $d->area }}@endif</option>@endforeach</select></label>
    @if($dealers->isEmpty())<span class="sub">Add dealers first in <a href="{{ route('customers.index', ['new' => 1]) }}">Customers</a> (type Dealer or Commercial).</span>@endif
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      @foreach($products as $p)
        @php($price = $p->dealer_price ?? $p->retail_price)
        <label class="f">{{ $p->name }} <span style="font-weight:500">{{ $price !== null ? '@ ৳'.number_format($price) : '(no price)' }}</span><input type="number" min="0" name="qty[{{ $p->id }}]" value="{{ old('qty.'.$p->id, 0) }}" data-price="{{ $price ?? 0 }}" @disabled($price === null)></label>
      @endforeach
    </div>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Dealer discount (৳)<input type="number" min="0" name="discount" value="{{ old('discount', 0) }}" id="wdisc"></label>
      <label class="f">Vehicle<input type="text" name="vehicle" value="{{ old('vehicle') }}"></label>
    </div>
    <div style="background:#eef2f9;border-radius:12px;padding:12px;display:flex;font-size:16px;font-weight:700"><span style="flex-grow:1">Bill</span><span id="wbill">৳0</span></div>
    <button class="btn pri" type="submit" style="min-height:46px">Save order</button>
    <span class="sub">Stock leaves when you press Loaded. The dealer is billed when the order is delivered.</span>
  </form>
  <script>
  (function(){var f=document.getElementById('wform');function calc(){var t=0;f.querySelectorAll('input[data-price]').forEach(function(i){t+=(parseFloat(i.value)||0)*parseFloat(i.dataset.price);});t-=parseFloat(document.getElementById('wdisc').value)||0;document.getElementById('wbill').textContent='৳'+Math.round(t).toLocaleString('en-IN');}f.addEventListener('input',calc);calc();})();
  </script>
  @endif
</div>
@endsection
