@extends('layout')
@section('title', 'Cylinder exchange')
@section('content')
@php($bd = fn ($n) => \App\Support\Ui::bd($n))
@php($defGiven = old('given_brand_id', $brands->firstWhere('for_sale', false)?->id))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Cylinder exchange</h1><p class="sub">Customer gives an empty of one brand and takes a full of another · price = refill price + exchange charge · every route request needs office approval</p></div>
  <a class="btn pri" href="#cx-form">＋ New exchange</a>
</header>
<section class="kpis">
  <div class="card kpi" style="background:#efe7fb"><span>Waiting for approval</span><b style="color:#5b2fa8">{{ $stats['pending'] }}</b></div>
  <div class="card kpi" style="background:#e3f6ec"><span>Exchanged today</span><b style="color:#0f6b3c">{{ number_format($stats['today']) }}</b></div>
  <div class="card kpi" style="background:#e6eeff"><span>Today's exchange bills</span><b style="color:#1d4ed8">৳{{ $bd($stats['todayAmt']) }}</b></div>
  <div class="card kpi"><span>This month</span><b>{{ number_format($stats['month']) }}</b></div>
</section>

<section class="card">
  <div class="pad" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
    <h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Exchanges</h2>
    <div class="tabs">
      <a class="{{ ! $status ? 'on' : '' }}" href="{{ route('cx.index') }}">All</a>
      @foreach(\App\Models\CylinderExchange::STATUS as $k => $v)<a class="{{ $status === $k ? 'on' : '' }}" href="{{ route('cx.index', ['s' => $k]) }}">{{ $v }}</a>@endforeach
    </div>
  </div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Ref</th><th>Date</th><th>Where</th><th>Customer</th><th>Empty received</th><th>Full given</th><th class="r">Qty</th><th class="r">Refill</th><th class="r">Charge</th><th class="r">Total</th><th class="r">Paid</th><th>Status</th><th>Invoice</th><th class="no-sort"></th></tr></thead>
    <tbody>
    @forelse($list as $x)
      <tr @if($x->status === 'pending') style="background:#faf7ff" @endif>
        <td class="nw"><b>{{ $x->ref_no }}</b></td>
        <td class="nw">{{ $x->created_at->timezone('Asia/Dhaka')->format('d-m-Y h:i A') }}</td>
        <td class="nw">@if($x->channel === 'route')Route · {{ $x->trip?->vehicle?->code ?? '—' }}@else Counter @endif<br><span class="sub">{{ $x->requester?->name }}</span></td>
        <td>{{ $x->buyer() }}@if($x->walkin_phone)<br><span class="sub">{{ $x->walkin_phone }}</span>@endif</td>
        <td class="nw">{{ $x->givenBrand?->name }} {{ $x->size_kg }} kg</td>
        <td class="nw"><b>{{ $x->takenBrand?->name }} {{ $x->size_kg }} kg</b></td>
        <td class="r">{{ $x->qty }}</td>
        <td class="r">৳{{ $bd($x->unit_price) }}</td>
        <td class="r">৳{{ $bd($x->charge_unit) }}</td>
        <td class="r"><b>৳{{ $bd($x->total) }}</b></td>
        <td class="r">৳{{ $bd($x->paid) }}<br><span class="sub">{{ \App\Models\Sale::METHODS[$x->method] ?? $x->method }}</span></td>
        <td><span class="pill {{ $x->status === 'approved' ? 'st-delivered' : '' }}" @if($x->status === 'pending') style="background:#efe7fb;color:#5b2fa8" @elseif($x->status === 'rejected') style="background:#fde8e6;color:#b3261e" @endif>{{ \App\Models\CylinderExchange::STATUS[$x->status] }}</span>
          @if($x->decision_note)<br><span class="sub">{{ $x->decision_note }}</span>@endif
          @if($x->decider)<br><span class="sub">{{ $x->decider->name }}</span>@endif</td>
        <td class="nw">@if($x->sale)<a href="{{ route('sales.show', $x->sale) }}">{{ $x->sale->invoice_no }}</a>@else — @endif</td>
        <td class="nw">
          @if($x->status === 'pending' && $canDecide)
            <form method="post" action="{{ route('cx.approve', $x) }}" style="display:inline" onsubmit="return confirm('Approve {{ $x->ref_no }}? Stock and the invoice are posted now.')">@csrf<button class="btn sm pri" type="submit">Approve</button></form>
            <form method="post" action="{{ route('cx.reject', $x) }}" style="display:inline" onsubmit="var r=prompt('Why reject {{ $x->ref_no }}?');if(!r)return false;this.reason.value=r;">@csrf<input type="hidden" name="reason"><button class="btn sm" type="submit" style="color:#b3261e;border-color:#e3b4ae">Reject</button></form>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="14" class="c" style="padding:28px;color:var(--muted)">No exchanges yet. Use “New exchange” below, or the salesman app's “এক্সচেঞ্জ” button on the route.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  @if($list->hasPages())<div class="pad">{{ $list->links() }}</div>@endif
</section>

<div class="grid2" style="align-items:start">
  <form id="cx-form" class="card pad" method="post" action="{{ route('cx.store') }}" style="display:flex;flex-direction:column;gap:12px">
    @csrf
    <h2 style="margin:0;font-size:var(--fs-15)">New counter exchange</h2>
    <div class="grid2">
      <label class="f">Empty the customer gives (brand)<select name="given_brand_id" id="cxGiven" required>
        @foreach($brands as $b)<option value="{{ $b->id }}" data-sizes="{{ implode(',', $b->sizes ?? []) }}" @selected($defGiven == $b->id)>{{ $b->name }}{{ $b->for_sale ? ' (our brand)' : '' }}</option>@endforeach
      </select></label>
      <label class="f">Full we give (brand)<select name="taken_brand_id" id="cxTaken" required>
        @foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('taken_brand_id', $own?->id) == $b->id)>{{ $b->name }}{{ $b->for_sale ? ' (our brand)' : '' }}</option>@endforeach
      </select></label>
      <label class="f">Size<select name="size_kg" id="cxSize">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}" @selected(old('size_kg', 12) == $s)>{{ $s }} kg</option>@endforeach</select></label>
      <label class="f">Quantity<input type="number" name="qty" id="cxQty" min="1" max="500" value="{{ old('qty', 1) }}" required></label>
      <label class="f" style="grid-column:1/-1">Saved customer<select name="customer_id" id="cxCust"><option value="">Walk-in customer</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>@endforeach</select></label>
      <label class="f cx-walk">Walk-in name<input type="text" name="walkin_name" value="{{ old('walkin_name') }}" placeholder="Optional"></label>
      <label class="f cx-walk">Walk-in phone<input type="tel" name="walkin_phone" value="{{ old('walkin_phone') }}" placeholder="01XXXXXXXXX"></label>
      <label class="f">Payment<select name="method" id="cxMethod">@foreach(\App\Models\Sale::METHODS as $k => $v)<option value="{{ $k }}" @selected(old('method', 'cash') === $k)>{{ $v }}</option>@endforeach</select></label>
      <label class="f" id="cxPaidWrap" style="display:none">Paid now (৳)<input type="number" name="paid" min="0" step="0.01" value="{{ old('paid', 0) }}"></label>
    </div>
    <label class="f">Note<input type="text" name="note" value="{{ old('note') }}" placeholder="Optional"></label>
    <div id="cxQuote" class="card" style="background:#f5f8ff;padding:12px;display:grid;grid-template-columns:1fr auto;gap:4px 12px;font-size:var(--fs-13)" aria-live="polite">
      <span class="sub">Pick both brands and the size to see the price.</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button class="btn pri" type="submit" id="cxGo">{{ $canDecide ? 'Save exchange & print invoice' : 'Send for approval' }}</button>
      <span class="sub">{{ $canDecide ? 'Stock moves now: the empty comes in, the full goes out.' : 'A manager approves it; stock moves after approval.' }}</span>
    </div>
  </form>

  <section class="card pad" style="display:flex;flex-direction:column;gap:10px">
    <h2 style="margin:0;font-size:var(--fs-15)">Exchange charge per cylinder</h2>
    <span class="sub">Set on the other brand, by size. The same charge applies both ways (their empty → our full, or our empty → their full). Empty box = no charge.</span>
    @if($canCharge)<form method="post" action="{{ route('cx.charges') }}">@csrf @endif
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Brand</th>@foreach(\App\Models\Brand::SIZES as $s)<th class="r">{{ $s }} kg</th>@endforeach</tr></thead>
      <tbody>
      @foreach($brands->where('for_sale', false) as $b)
        <tr><td class="nw"><b>{{ $b->name }}</b></td>
          @foreach(\App\Models\Brand::SIZES as $s)
            @php($v = $charges[$b->id][$s] ?? null)
            <td class="r">@if(! in_array($s, array_map('intval', $b->sizes ?? []), true))<span class="sub">—</span>
              @elseif($canCharge)<input type="number" name="c[{{ $b->id }}][{{ $s }}]" value="{{ $v !== null ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '' }}" min="0" step="1" placeholder="0" aria-label="{{ $b->name }} {{ $s }} kg charge" style="width:90px;text-align:right">
              @else ৳{{ $bd($v ?? 0) }}@endif</td>
          @endforeach
        </tr>
      @endforeach
      </tbody>
    </table></div>
    @if($canCharge)<div><button class="btn pri" type="submit">Save charges</button></div></form>@else<span class="sub">Only Super Admin or Admin can change the charges.</span>@endif
  </section>
</div>
<script>
(function(){
  var g=document.getElementById('cxGiven'),t=document.getElementById('cxTaken'),s=document.getElementById('cxSize'),q=document.getElementById('cxQty'),out=document.getElementById('cxQuote'),
      c=document.getElementById('cxCust'),m=document.getElementById('cxMethod'),pw=document.getElementById('cxPaidWrap'),go=document.getElementById('cxGo'),seq=0;
  function tk(n){return '৳'+Number(n).toLocaleString('en-IN',{maximumFractionDigits:2});}
  function esc(x){var d=document.createElement('div');d.textContent=x;return d.innerHTML;}
  function walk(){var w=!c.value;document.querySelectorAll('.cx-walk').forEach(function(e){e.style.display=w?'':'none';});
    if(w&&m.value==='due')m.value='cash';[].forEach.call(m.options,function(o){if(o.value==='due')o.disabled=w;});pw.style.display=m.value==='due'?'':'none';}
  function sizes(){var o=g.options[g.selectedIndex],ok=(o&&o.dataset.sizes||'').split(',');[].forEach.call(s.options,function(x){x.disabled=ok.indexOf(x.value)<0;});
    if(s.selectedOptions[0]&&s.selectedOptions[0].disabled){var f=[].find.call(s.options,function(x){return !x.disabled;});if(f)s.value=f.value;}}
  function quote(){
    sizes();
    if(g.value===t.value){out.innerHTML='<span style="grid-column:1/-1;color:#b3261e">Same brand on both sides — that is a normal refill. Use Retail sale (POS).</span>';go.disabled=true;return;}
    var my=++seq;out.innerHTML='<span class="sub">Checking price…</span>';
    fetch('{{ route('cx.quote') }}?given='+g.value+'&taken='+t.value+'&size='+s.value,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){
      if(my!==seq)return;
      if(!d.ok){out.innerHTML='<span style="grid-column:1/-1;color:#b3261e">'+esc(d.msg)+'</span>';go.disabled=true;return;}
      var n=Math.max(1,parseInt(q.value||'1',10)),tot=n*(d.unit+d.charge);go.disabled=false;
      out.innerHTML='<span>'+esc(d.product)+' (refill) × '+n+'</span><b style="text-align:right">'+tk(n*d.unit)+'</b>'+
        '<span>Exchange charge '+tk(d.charge)+' × '+n+'</span><b style="text-align:right">'+tk(n*d.charge)+'</b>'+
        '<span style="font-weight:700;border-top:1px solid #d6dff0;padding-top:4px">Total</span><b style="text-align:right;border-top:1px solid #d6dff0;padding-top:4px;font-size:var(--fs-16)">'+tk(tot)+'</b>';
    }).catch(function(){if(my===seq)out.innerHTML='<span class="sub">Could not get the price. Check the connection.</span>';});
  }
  [g,t,s].forEach(function(e){e.addEventListener('change',quote);});q.addEventListener('input',quote);
  c.addEventListener('change',walk);m.addEventListener('change',walk);walk();quote();
})();
</script>
@endsection
