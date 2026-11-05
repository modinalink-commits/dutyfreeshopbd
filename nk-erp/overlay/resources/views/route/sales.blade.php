@extends('layout')
@section('title', 'Trip sales · '.$trip->trip_no)
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($open = $trip->status === 'dispatched')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Trip sales — {{ $trip->vehicle->code }}</h1>
    <span class="sub">{{ $trip->trip_no }} · {{ $trip->trip_date->format('d-m-Y') }} · {{ $trip->route?->name ?? 'No route' }} · Salesman {{ $trip->salesman?->name ?? '—' }} · entered at the office until the salesman app is live</span></div>
  <span class="pill" style="{{ $U::pill(\App\Models\Trip::STATUS_LABELS[$trip->status]) }}">{{ \App\Models\Trip::STATUS_LABELS[$trip->status] }}</span>
  <a class="btn" href="{{ route('trips.return', ['trip' => $trip->id]) }}">Vehicle return →</a>
</header>
<section class="kpis">
  <div class="card kpi"><span>On vehicle now (full)</span><b>{{ array_sum($onVehicle) }}</b><span>{{ collect($onVehicle)->filter()->map(fn ($q, $s) => $s.' kg '.$q)->implode(' · ') ?: '—' }}</span></div>
  <div class="card kpi"><span>Invoices</span><b>{{ $c['invoices'] }}</b><span>{{ $c['soldTotal'] }} cylinders sold</span></div>
  <div class="card kpi"><span>Total sales</span><b>{{ $U::tk($c['totalSales']) }}</b><span>credit {{ $U::tk($c['credit']) }}</span></div>
  <div class="card kpi"><span>Collection</span><b style="color:#0f6b3c">{{ $U::tk($c['collection']) }}</b><span>incl. {{ $U::tk($c['prevCash'] + $c['prevDigital']) }} old due</span></div>
</section>
@if($open)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
<form class="card pad" method="post" action="{{ route('trips.sales.store', $trip) }}" style="display:flex;flex-direction:column;gap:12px">@csrf
  <h2 class="h2">New sale from the vehicle</h2>
  <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
    <label class="fld" style="grid-column:1/-1">Shop / customer<select name="customer_id"><option value="">Walk-in (pays in full)</option>@foreach($customers as $cu)<option value="{{ $cu->id }}" @selected(old('customer_id') == $cu->id)>{{ $cu->name }} · {{ $cu->phone }}</option>@endforeach</select></label>
    <label class="fld">Walk-in name<input name="walkin_name" maxlength="120" value="{{ old('walkin_name') }}"></label>
    <label class="fld">Walk-in mobile<input name="walkin_phone" maxlength="20" value="{{ old('walkin_phone') }}"></label>
  </div>
  <div class="dt-wrap"><table class="dt"><thead><tr><th>Item</th><th class="r">Price</th><th class="r">Qty</th></tr></thead><tbody>
  @forelse($products as $p)
    @php($pr = $p->dealer_price ?? $p->retail_price)
    <tr><td>{{ $p->name }} <span class="sub" style="font-size:var(--fs-11)">{{ $p->category === 'refill' ? 'exchange' : 'new cylinder' }}</span></td><td class="r">{{ $pr !== null ? $U::tk($pr) : 'No price' }}</td>
      <td class="r"><input class="qin" type="number" min="0" name="qty[{{ $p->id }}]" value="{{ old('qty.'.$p->id, 0) }}" @disabled($pr === null)></td></tr>
  @empty
    <tr><td colspan="3" class="empty">Add cylinder products in Products &amp; prices.</td></tr>
  @endforelse
  </tbody></table></div>
  <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
    <label class="fld">Empties received<input type="number" min="0" name="empties" value="{{ old('empties') }}" placeholder="same as exchange qty"></label>
    <label class="fld">Empty brand<select name="empty_brand_id">@foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('empty_brand_id', $ownBrand) == $b->id)>{{ $b->code }} · {{ $b->name }}</option>@endforeach</select></label>
    <label class="fld">Payment<select name="method">@foreach(\App\Models\Sale::METHODS as $k => $l)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $l }}</option>@endforeach</select></label>
    <label class="fld">Paid now (for due sales)<input type="number" min="0" step="0.01" name="paid" value="{{ old('paid') }}"></label>
    <label class="fld" style="grid-column:1/-1">Note / empty condition<input name="note" maxlength="255" value="{{ old('note') }}"></label>
  </div>
  <button class="btn pri" type="submit" style="align-self:flex-start">Save sale</button>
</form>
<form class="card pad" method="post" action="{{ route('trips.collect', $trip) }}" style="display:flex;flex-direction:column;gap:12px">@csrf
  <h2 class="h2">Previous due collected on this trip</h2>
  <p class="sub" style="margin:0">Not counted as today's sale. It reduces the shop's due in its ledger.</p>
  <label class="fld">Shop / customer<select name="customer_id" required><option value="">Choose</option>@foreach($customers as $cu)<option value="{{ $cu->id }}">{{ $cu->name }} · {{ $cu->phone }}</option>@endforeach</select></label>
  <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
    <label class="fld">Amount<input type="number" min="1" step="0.01" name="amount" required></label>
    <label class="fld">Method<select name="method"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="bank">Bank</option></select></label>
  </div>
  <label class="fld">Note<input name="note" maxlength="255"></label>
  <button class="btn pri" type="submit" style="align-self:flex-start">Save collection</button>
</form>
</div>
@endif
<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px"><h2 class="h2">Invoices on this trip</h2></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Time</th><th>Invoice</th><th>Shop</th><th>Items</th><th class="r">Total</th><th>Payment</th><th class="r">Due</th></tr></thead>
    <tbody>
    @forelse($c['sales']->sortByDesc('id') as $s)
      <tr><td class="c">{{ $s->created_at->timezone('Asia/Dhaka')->format('H:i') }}</td><td class="nw"><a href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a></td><td>{{ $s->buyerName() }}</td>
        <td>{{ $s->items->map(fn ($i) => $i->size_kg.' kg × '.$i->qty.' '.($i->category === 'refill' ? 'exchange' : 'new'))->implode(' · ') }}</td><td class="r">{{ $U::tk($s->total) }}</td><td class="c">{{ \App\Models\Sale::METHODS[$s->payment_method] ?? $s->payment_method }}</td><td class="r">{{ $s->due() > 0 ? $U::tk($s->due()) : '—' }}</td></tr>
    @empty
      <tr><td colspan="7" class="empty">No sales on this trip yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
