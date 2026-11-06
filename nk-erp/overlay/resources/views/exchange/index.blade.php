@extends('layout')
@section('title', 'Cylinder exchange · '.\App\Http\Controllers\CylinderExchangeController::VIEWS[$v])
@section('content')
@php($bd = fn ($n) => \App\Support\Ui::bd($n))
@php($X = \App\Models\CylinderExchange::class)
@php($stTone = ['pending' => 'background:#efe7fb;color:#5b2fa8', 'held' => 'background:#fff4e5;color:#8a4b00', 'approved' => 'background:#e3f6ec;color:#0f6b3c', 'rejected' => 'background:#fde8e6;color:#b3261e', 'reversed' => 'background:#eef2f9;color:#51607a'])
<style>
.cxnav{display:flex;gap:6px;overflow-x:auto;padding-bottom:2px;scrollbar-width:thin}
.cxnav a{flex-shrink:0;padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:600;font-size:var(--fs-12);text-decoration:none;white-space:nowrap}
.cxnav a.on{background:var(--navy);border-color:var(--navy);color:#fff}
.cxnav .b{display:inline-block;margin-left:6px;background:#b3261e;color:#fff;border-radius:999px;padding:0 6px;font-size:var(--fs-10)}
.cxl td{vertical-align:top}
.cxl .ln{display:block;white-space:nowrap}
.cxrow{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(130px,100%),1fr));gap:8px;align-items:end;border:1px solid var(--line);border-radius:12px;padding:10px;position:relative}
.cxrow .tg i{font-style:normal}
.cxrow .tg{position:absolute;top:-10px;left:10px;background:var(--navy);color:#fff;border-radius:999px;font-size:var(--fs-10);font-weight:700;padding:1px 8px}
.cxrow .sd{grid-column:1/-1;font-size:var(--fs-11);font-weight:700;color:#51607a;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
.warnbox{background:#fff4e5;color:#8a4b00;border-radius:12px;padding:10px 12px;font-size:var(--fs-13)}
</style>
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Cylinder exchange</h1><p class="sub">Inventory &amp; cylinder management · exchanges post through the normal stock ledger and sales; other-brand cylinders go to the brand settlement ledger; deposits are kept apart from sales</p></div>
  @if($v !== 'new')<a class="btn pri" href="{{ route('cx.index', ['v' => 'new']) }}">＋ New exchange</a>@endif
</header>
<nav class="cxnav" aria-label="Cylinder exchange sections">
  @foreach(\App\Http\Controllers\CylinderExchangeController::VIEWS as $nk => $label)
    <a class="{{ $v === $nk ? 'on' : '' }}" href="{{ route('cx.index', ['v' => $nk]) }}" @if($v === $nk) aria-current="page" @endif>{{ $loop->iteration }}. {{ $label }}@if($nk === 'approve' && $pendingCount)<span class="b">{{ $pendingCount }}</span>@endif</a>
  @endforeach
</nav>

@php($unauth = $active->where('for_sale', false)->reject(fn ($b) => $b->exchangeAuthorized())->count())
@if($unauth && in_array($v, ['dash', 'new', 'rates'], true))
  <div class="warnbox">{{ $unauth }} active brand(s) have no exchange agreement on record. Exchanges involving them are <b>held for review</b> and cannot be posted until an admin records the agreement in <a href="{{ route('cx.index', ['v' => 'rates']) }}#rules" style="color:inherit;font-weight:700">Rate settings → Brand exchange rules</a>.</div>
@endif

{{-- ===================== 1. DASHBOARD ===================== --}}
@if($v === 'dash')
<section class="kpis">
  <div class="card kpi" style="background:#e3f6ec"><span>Today's exchanges</span><b style="color:#0f6b3c">{{ $k['today'] }}</b></div>
  <div class="card kpi"><span>Own brand (A)</span><b>{{ $k['own'] }}</b></div>
  <div class="card kpi"><span>Cross-brand (B–D)</span><b>{{ $k['cross'] }}</b></div>
  <div class="card kpi" style="background:#def5f7"><span>Empties received today</span><b style="color:#0b6670">{{ number_format($k['in']) }}</b></div>
  <div class="card kpi" style="background:#e6eeff"><span>Fulls delivered today</span><b style="color:#1d4ed8">{{ number_format($k['out']) }}</b></div>
  <div class="card kpi"><span>Today's exchange bills</span><b>৳{{ $bd($k['todayAmt']) }}</b></div>
  <a class="card kpi" href="{{ route('cx.index', ['v' => 'approve']) }}" style="background:#efe7fb;text-decoration:none"><span>Pending approvals</span><b style="color:#5b2fa8">{{ $k['pending'] }}</b></a>
  <a class="card kpi" href="{{ route('cx.index', ['v' => 'approve', 's' => 'held']) }}" style="background:#fff4e5;text-decoration:none"><span>Held for review</span><b style="color:#8a4b00">{{ $k['held'] }}</b></a>
  <a class="card kpi" href="{{ route('cx.index', ['v' => 'settle']) }}" style="text-decoration:none"><span>Brands to settle (brand · size)</span><b>{{ $k['settle'] }}</b></a>
  <div class="card kpi" style="background:#fde8e6"><span>Damaged / quarantined from exchange</span><b style="color:#b3261e">{{ number_format($k['quarantine']) }}</b></div>
</section>
<div class="grid2" style="align-items:start">
  <section class="card">
    <div class="pad"><h2 style="margin:0;font-size:var(--fs-15)">Brand-wise balances</h2><span class="sub">Other brands' cylinders that passed through us. Net + = we hold theirs; net − = we handed out more of theirs than we took in.</span></div>
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Brand</th><th>Size</th><th class="r">Received</th><th class="r">Delivered</th><th class="r">Net</th></tr></thead>
      <tbody>
      @forelse($balances as $b)
        <tr><td><b>{{ $b['brand']?->name }}</b></td><td>{{ $b['size'] }} kg</td><td class="r">{{ $b['in'] }}</td><td class="r">{{ $b['out'] }}</td><td class="r"><b style="color:{{ $b['net'] > 0 ? '#0f6b3c' : ($b['net'] < 0 ? '#b3261e' : 'inherit') }}">{{ $b['net'] > 0 ? '+' : '' }}{{ $b['net'] }}</b></td></tr>
      @empty
        <tr><td colspan="5" class="c" style="padding:22px;color:var(--muted)">No cross-brand exchange posted yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
  <section class="card">
    <div class="pad" style="display:flex;align-items:center"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Latest exchanges</h2><a class="btn sm" href="{{ route('cx.index', ['v' => 'all']) }}">All transactions</a></div>
    <div class="dt-wrap"><table class="dt cxl">
      <thead><tr><th>Ref</th><th>Customer</th><th>Lines</th><th class="r">Total</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($recent as $x)
        <tr><td class="nw"><b>{{ $x->ref_no }}</b><br><span class="sub">{{ $x->type === 'MX' ? 'Mixed' : 'Type '.$x->type }}</span></td><td>{{ $x->buyer() }}</td>
          <td>@foreach($x->lines as $l)<span class="ln">{{ $l->inText() }} → {{ $l->outText() }}</span>@endforeach</td>
          <td class="r">৳{{ $bd($x->total) }}</td><td><span class="pill" style="{{ $stTone[$x->status] }}">{{ $X::STATUS[$x->status] }}</span></td></tr>
      @empty
        <tr><td colspan="5" class="c" style="padding:22px;color:var(--muted)">No exchanges yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div>
@endif

{{-- ===================== 2. NEW EXCHANGE ===================== --}}
@if($v === 'new')
<form id="cx-form" class="card pad" method="post" action="{{ route('cx.store') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px">
  @csrf
  <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">New exchange</h2><span class="sub">Exchange number is given automatically when you save.</span></div>
  <div class="grid2">
    <label class="f">Exchange date<input type="date" name="exchange_date" value="{{ old('exchange_date', now('Asia/Dhaka')->toDateString()) }}" max="{{ now('Asia/Dhaka')->toDateString() }}" @unless($isAdmin) readonly @endunless required></label>
    <label class="f">Warehouse / vehicle<select name="trip_id" id="cxTrip">
      <option value="">Godown (counter)</option>
      @foreach($trips as $t)<option value="{{ $t->id }}" @selected(old('trip_id') == $t->id)>{{ $t->vehicle?->code }} · {{ $t->trip_no }} · {{ $t->salesman?->name ?? 'no salesman' }}{{ $t->driver ? ' / '.$t->driver->name : '' }}</option>@endforeach
    </select></label>
    <label class="f">Customer / dealer<select name="customer_id" id="cxCust"><option value="">Walk-in customer</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}{{ $c->type === 'dealer' ? ' · dealer' : '' }}</option>@endforeach</select></label>
    <label class="f cx-walk">Walk-in name<input type="text" name="walkin_name" value="{{ old('walkin_name') }}" placeholder="Optional"></label>
    <label class="f cx-walk">Walk-in phone<input type="tel" name="walkin_phone" value="{{ old('walkin_phone') }}" placeholder="01XXXXXXXXX"></label>
  </div>

  <div style="display:flex;flex-direction:column;gap:14px" id="cxLines"></div>
  <div><button type="button" class="btn" id="cxAdd">＋ Add another line</button> <span class="sub">Several brands and sizes can go in one exchange. Type is set from the brands you pick.</span></div>

  <div class="grid2">
    <label class="f">Payment<select name="method" id="cxMethod">@foreach(\App\Models\Sale::METHODS as $mk => $mv)<option value="{{ $mk }}" @selected(old('method', 'cash') === $mk)>{{ $mv }}</option>@endforeach</select></label>
    <label class="f" id="cxPaidWrap" style="display:none">Paid now (৳)<input type="number" name="paid" min="0" step="0.01" value="{{ old('paid', 0) }}"></label>
    <label class="f">Deposit adjustment (৳)<input type="number" name="deposit_adj" step="1" value="{{ old('deposit_adj', 0) }}"><span class="sub" style="font-weight:400">+ deposit taken · − deposit returned. Kept as a liability, never as sales.</span></label>
    <label class="f">Attachment (photo or PDF)<input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></label>
  </div>
  <label class="f">Notes<input type="text" name="note" value="{{ old('note') }}" placeholder="Optional"></label>
  <div id="cxQuote" class="card" style="background:#f5f8ff;padding:12px;display:flex;flex-direction:column;gap:4px;font-size:var(--fs-13)" aria-live="polite"><span class="sub">Fill the lines to see the price.</span></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <button class="btn pri" type="submit" id="cxGo">{{ $canDecide ? 'Save & post' : 'Send for approval' }}</button>
    <span class="sub" id="cxGoNote">{{ $canDecide ? 'Posts now if every check passes; anything unsafe or without a brand agreement is held for an admin.' : 'A manager approves it; stock moves after approval.' }}</span>
  </div>
</form>
<template id="cxTpl">
  <div class="cxrow"><span class="tg">Line <i class="n"></i> · <i class="ty">—</i></span>
    <span class="sd">Received from customer</span>
    <label class="f">Empty brand<select data-k="in_brand_id">@foreach($active as $b)<option value="{{ $b->id }}" data-sizes="{{ implode(',', $b->sizes ?? []) }}">{{ $b->name }}{{ $b->for_sale ? ' (our brand)' : '' }}</option>@endforeach</select></label>
    <label class="f">Size<select data-k="in_size">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}">{{ $s }} kg</option>@endforeach</select></label>
    <label class="f">Quantity<input type="number" data-k="in_qty" min="1" max="500" value="1"></label>
    <label class="f">Condition<select data-k="in_condition">@foreach($X::CONDITIONS as $ck => $cv)<option value="{{ $ck }}">{{ $cv }}</option>@endforeach</select></label>
    <label class="f">Ownership<select data-k="in_ownership">@foreach($X::OWNERSHIP as $ok => $ov)<option value="{{ $ok }}">{{ $ov }}</option>@endforeach</select></label>
    <span class="sd">Delivered to customer</span>
    <label class="f">Full / empty<select data-k="out_kind"><option value="full">Full (with gas)</option><option value="empty">Empty only (swap)</option></select></label>
    <label class="f">Brand<select data-k="out_brand_id">@foreach($active as $b)<option value="{{ $b->id }}" data-sizes="{{ implode(',', $b->sizes ?? []) }}" @selected($b->for_sale)>{{ $b->name }}{{ $b->for_sale ? ' (our brand)' : '' }}</option>@endforeach</select></label>
    <label class="f">Size<select data-k="out_size">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}">{{ $s }} kg</option>@endforeach</select></label>
    <label class="f">Quantity<input type="number" data-k="out_qty" min="1" max="500" value="1"></label>
    <label class="f">Condition<select data-k="out_condition" disabled><option value="good">Good (checked)</option></select></label>
    <div style="display:flex;align-items:end;justify-content:flex-end"><button type="button" class="btn sm rm" style="color:#b3261e;border-color:#e3b4ae">Remove line</button></div>
  </div>
</template>
<script>
(function(){
  var box=document.getElementById('cxLines'),tpl=document.getElementById('cxTpl'),out=document.getElementById('cxQuote'),go=document.getElementById('cxGo'),
      c=document.getElementById('cxCust'),m=document.getElementById('cxMethod'),pw=document.getElementById('cxPaidWrap'),trip=document.getElementById('cxTrip'),
      enabled=@json($types),T=@json($X::TYPES),seq=0,timer=null,own={{ (int) $own?->id }};
  var old=@json(old('lines', []));
  function tk(n){return '৳'+Number(n).toLocaleString('en-IN',{maximumFractionDigits:2});}
  function esc(x){var d=document.createElement('div');d.textContent=x;return d.innerHTML;}
  function sizes(row,bk,sk){var b=row.querySelector('[data-k='+bk+']'),s=row.querySelector('[data-k='+sk+']'),ok=(b.selectedOptions[0]&&b.selectedOptions[0].dataset.sizes||'').split(',');
    [].forEach.call(s.options,function(o){o.disabled=ok.indexOf(o.value)<0;});if(s.selectedOptions[0]&&s.selectedOptions[0].disabled){var f=[].find.call(s.options,function(o){return!o.disabled;});if(f)s.value=f.value;}}
  function typeOf(row){var i=+row.querySelector('[data-k=in_brand_id]').value,o=+row.querySelector('[data-k=out_brand_id]').value;
    if(row.querySelector('[data-k=out_kind]').value==='empty')return 'E';return i===own?(o===own?'A':'C'):(o===own?'B':'D');}
  function renumber(){[].forEach.call(box.children,function(r,i){r.querySelector('.n').textContent=i+1;r.querySelectorAll('[data-k]').forEach(function(e){e.name='lines['+i+']['+e.dataset.k+']';});
    var t=typeOf(r);r.querySelector('.ty').textContent='Type '+t+(enabled.indexOf(t)<0?' (switched off)':'')+' · '+T[t];r.querySelector('.rm').style.visibility=box.children.length>1?'visible':'hidden';});}
  function add(v){var r=tpl.content.firstElementChild.cloneNode(true);box.appendChild(r);
    if(v)Object.keys(v).forEach(function(k){var e=r.querySelector('[data-k='+k+']');if(e)e.value=v[k];});
    else if(box.children.length===1){var ib=r.querySelector('[data-k=in_brand_id]'),f=[].find.call(ib.options,function(o){return +o.value!==own;});if(f)ib.value=f.value;}
    sizes(r,'in_brand_id','in_size');sizes(r,'out_brand_id','out_size');
    r.addEventListener('change',function(e){var k=e.target.dataset.k;if(k==='in_brand_id')sizes(r,'in_brand_id','in_size');if(k==='out_brand_id')sizes(r,'out_brand_id','out_size');
      if(k==='in_size'){var os=r.querySelector('[data-k=out_size]');if(![].find.call(os.options,function(o){return o.value===e.target.value&&o.disabled;}))os.value=e.target.value;}
      if(k==='in_qty')r.querySelector('[data-k=out_qty]').value=e.target.value;renumber();later();});
    r.querySelector('[data-k=in_qty]').addEventListener('input',function(e){r.querySelector('[data-k=out_qty]').value=e.target.value;later();});
    r.querySelector('[data-k=out_qty]').addEventListener('input',later);
    r.querySelector('.rm').onclick=function(){r.remove();renumber();later();};renumber();later();}
  function later(){clearTimeout(timer);timer=setTimeout(quote,250);}
  function quote(){
    var fd=new FormData(document.getElementById('cx-form'));fd.delete('attachment');if(trip.value)fd.append('channel','route');
    var my=++seq;out.innerHTML='<span class="sub">Checking price…</span>';
    fetch('{{ route('cx.quote') }}',{method:'POST',body:fd,headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){
      if(my!==seq)return;
      if(!d.ok){out.innerHTML='<span style="color:#b3261e">'+esc(d.msg)+'</span>';go.disabled=true;return;}
      go.disabled=false;var h='';
      d.lines.forEach(function(l,i){h+='<div style="display:flex;gap:10px"><span style="flex-grow:1">Line '+(i+1)+' · Type '+l.type+(l.product?' · '+esc(l.product)+' '+tk(l.refill):' · empty swap')+(l.fee?' + charge '+tk(l.fee):'')+' × '+l.qty+'</span><b>'+tk(l.total)+'</b></div>';});
      h+='<div style="display:flex;border-top:1px solid #d6dff0;padding-top:6px;margin-top:2px"><b style="flex-grow:1">Total</b><b style="font-size:var(--fs-16)">'+tk(d.total)+'</b></div>';
      if(d.hold.length)h+='<div class="warnbox" style="margin-top:6px"><b>Will be held for review:</b><br>'+d.hold.map(esc).join('<br>')+'</div>';
      out.innerHTML=h;
    }).catch(function(){if(my===seq)out.innerHTML='<span class="sub">Could not get the price. Check the connection.</span>';});
  }
  function walk(){var w=!c.value;document.querySelectorAll('.cx-walk').forEach(function(e){e.style.display=w?'':'none';});
    if(w&&m.value==='due')m.value='cash';[].forEach.call(m.options,function(o){if(o.value==='due')o.disabled=w;});pw.style.display=m.value==='due'?'':'none';}
  document.getElementById('cxAdd').onclick=function(){add();};
  c.addEventListener('change',walk);m.addEventListener('change',walk);trip.addEventListener('change',later);
  if(old.length)old.forEach(function(v){add(v);});else add();
  walk();
  document.getElementById('cx-form').addEventListener('submit',function(){go.disabled=true;go.textContent='Saving…';});
})();
</script>
@endif

{{-- ===================== 3/4/5/9. LISTS ===================== --}}
@if(in_array($v, ['all', 'own', 'cross', 'approve'], true))
<section class="card">
  <form method="get" class="pad" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
    <input type="hidden" name="v" value="{{ $v }}">
    <h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">{{ ['all' => 'All exchange transactions', 'own' => 'Own brand exchanges (type A)', 'cross' => 'Cross-brand exchanges (types B, C, D)', 'approve' => 'Waiting for approval or review'][$v] }}</h2>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Ref or customer" aria-label="Search exchanges" style="max-width:200px;border-radius:999px">
    <div class="tabs">
      <a class="{{ ! $s ? 'on' : '' }}" href="{{ route('cx.index', ['v' => $v]) }}">All</a>
      @foreach($X::STATUS as $sk => $sv)@continue($v === 'approve' && ! in_array($sk, ['pending', 'held'], true))<a class="{{ $s === $sk ? 'on' : '' }}" href="{{ route('cx.index', ['v' => $v, 's' => $sk]) }}">{{ $sv }}</a>@endforeach
    </div>
  </form>
  <div class="dt-wrap"><table class="dt cxl">
    <thead><tr><th>Ref</th><th>Date</th><th>Where</th><th>Customer</th><th>Type</th><th class="no-sort">Received → delivered</th><th class="r">Refill</th><th class="r">Fee</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Deposit</th><th>Status</th><th>Invoice</th><th class="no-sort"></th></tr></thead>
    <tbody>
    @forelse($list as $x)
      <tr @if(in_array($x->status, ['pending', 'held'], true)) style="background:#faf7ff" @endif>
        <td class="nw"><b>{{ $x->ref_no }}</b>@if($x->attachment)<br><a href="{{ route('cx.doc', ['p' => $x->attachment]) }}" target="_blank" rel="noopener">Attachment</a>@endif @if($x->photo)<br><a href="{{ route('cx.doc', ['p' => $x->photo]) }}" target="_blank" rel="noopener">Photo</a>@endif</td>
        <td class="nw">{{ ($x->exchange_date ?? $x->created_at)->format('d-m-Y') }}<br><span class="sub">{{ $x->created_at->timezone('Asia/Dhaka')->format('h:i A') }}</span></td>
        <td class="nw">@if($x->channel === 'route')Vehicle {{ $x->trip?->vehicle?->code ?? '—' }}@else Godown @endif<br><span class="sub">{{ $x->requester?->name }}</span></td>
        <td>{{ $x->buyer() }}@if($x->walkin_phone)<br><span class="sub">{{ $x->walkin_phone }}</span>@endif @if($x->ack_name)<br><span class="sub">Acknowledged: {{ $x->ack_name }}</span>@endif</td>
        <td class="nw">{{ $x->type === 'MX' ? 'Mixed' : $x->type }}</td>
        <td>@foreach($x->lines as $l)<span class="ln">{{ $l->inText() }}@if($l->in_condition !== 'good') <b style="color:#b3261e">({{ strtolower($X::CONDITIONS[$l->in_condition]) }})</b>@endif → <b>{{ $l->outText() }}</b></span>@endforeach</td>
        <td class="r">৳{{ $bd($x->lines->sum(fn ($l) => $l->out_qty * $l->refill_price)) }}</td>
        <td class="r">৳{{ $bd($x->lines->sum(fn ($l) => $l->out_qty * $l->fee)) }}</td>
        <td class="r"><b>৳{{ $bd($x->total) }}</b></td>
        <td class="r">৳{{ $bd($x->paid) }}<br><span class="sub">{{ \App\Models\Sale::METHODS[$x->method] ?? $x->method }}</span></td>
        <td class="r">@if($x->deposit_adj)৳{{ $bd($x->deposit_adj) }}@else — @endif</td>
        <td><span class="pill" style="{{ $stTone[$x->status] }}">{{ $X::STATUS[$x->status] }}</span>
          @if($x->hold_reason && in_array($x->status, ['held', 'pending'], true))<br><span class="sub" style="color:#8a4b00">{{ $x->hold_reason }}</span>@endif
          @if(! empty($blockers[$x->id] ?? null))<br><span class="sub" style="color:#b3261e">{{ implode(' ', $blockers[$x->id]) }}</span>@endif
          @if($x->decision_note)<br><span class="sub">{{ $x->decision_note }}</span>@endif
          @if($x->decider)<br><span class="sub">{{ $x->decider->name }}</span>@endif
          @if($x->reverse_reason)<br><span class="sub">Reversed: {{ $x->reverse_reason }}</span>@endif</td>
        <td class="nw">@if($x->sale)<a href="{{ route('sales.show', $x->sale) }}">{{ $x->sale->invoice_no }}</a>@else — @endif</td>
        <td class="nw">
          @if($x->status === 'pending' && $canDecide)
            <form method="post" action="{{ route('cx.approve', $x) }}" style="display:inline" onsubmit="return confirm('Approve {{ $x->ref_no }}? Stock and the invoice are posted now.')">@csrf<button class="btn sm pri" type="submit">Approve</button></form>
          @elseif($x->status === 'held' && $isAdmin)
            <form method="post" action="{{ route('cx.approve', $x) }}" style="display:inline" onsubmit="var r=prompt('Release {{ $x->ref_no }}: write what you checked (condition, ownership, agreement).');if(!r)return false;this.note.value=r;">@csrf<input type="hidden" name="note"><button class="btn sm pri" type="submit">Review &amp; post</button></form>
          @endif
          @if(in_array($x->status, ['pending', 'held'], true) && $canDecide)
            <form method="post" action="{{ route('cx.reject', $x) }}" style="display:inline" onsubmit="var r=prompt('Why reject {{ $x->ref_no }}?');if(!r)return false;this.reason.value=r;">@csrf<input type="hidden" name="reason"><button class="btn sm" type="submit" style="color:#b3261e;border-color:#e3b4ae">Reject</button></form>
          @endif
          @if($x->status === 'approved' && $isAdmin)
            <form method="post" action="{{ route('cx.reverse', $x) }}" style="display:inline" onsubmit="var r=prompt('Reverse {{ $x->ref_no }}? Stock, invoice, settlement and deposit get reversing entries. Reason:');if(!r)return false;this.reason.value=r;">@csrf<input type="hidden" name="reason"><button class="btn sm" type="submit">Reverse</button></form>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="14" class="c" style="padding:28px;color:var(--muted)">{{ $v === 'approve' ? 'Nothing waiting.' : 'No exchanges here yet.' }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
  @if($list->hasPages())<div class="pad">{{ $list->links() }}</div>@endif
</section>
@endif

{{-- ===================== 6. BRAND-WISE EMPTY STOCK ===================== --}}
@if($v === 'stock')
<section class="card">
  <div class="pad"><h2 style="margin:0;font-size:var(--fs-15)">Brand-wise cylinder stock</h2><span class="sub">From the one stock ledger · damaged includes cylinders quarantined from exchanges · “via exchange” = empties taken in through posted exchanges (all time)</span></div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Brand</th><th>Size</th><th class="r">Godown full</th><th class="r">Godown empty</th><th class="r">Damaged / quarantined</th><th class="r">Company-owned</th><th class="r">On vehicles full</th><th class="r">On vehicles empty</th><th class="r">Via exchange</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
      <tr @if($r['brand']->for_sale) style="background:#f5f8ff" @endif><td><b>{{ $r['brand']->name }}</b>{{ $r['brand']->for_sale ? ' (ours)' : '' }}</td><td>{{ $r['size'] }} kg</td>
        <td class="r">{{ number_format($r['g']['full']) }}</td><td class="r"><b>{{ number_format($r['g']['empty']) }}</b></td><td class="r" style="color:{{ $r['g']['damaged'] ? '#b3261e' : 'inherit' }}">{{ number_format($r['g']['damaged']) }}</td>
        <td class="r">{{ number_format($r['g']['company']) }}</td><td class="r">{{ number_format($r['vf']) }}</td><td class="r">{{ number_format($r['ve']) }}</td><td class="r">{{ number_format($r['xin']) }}</td></tr>
    @empty
      <tr><td colspan="9" class="c" style="padding:22px;color:var(--muted)">No stock recorded yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endif

{{-- ===================== 7. BRAND SETTLEMENT ===================== --}}
@if($v === 'settle')
<section class="card">
  <div class="pad"><h2 style="margin:0;font-size:var(--fs-15)">Outstanding by brand</h2><span class="sub">Net + = their cylinders we hold (to return or settle) · net − = we handed out more of theirs than we took in</span></div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Brand</th><th>Size</th><th>Agreement</th><th class="r">Received</th><th class="r">Delivered / returned</th><th class="r">Outstanding</th>@if($isSuper)<th class="r">Money (+ we owe)</th>@endif<th>Last entry</th></tr></thead>
    <tbody>
    @forelse($balances as $b)
      <tr><td><b>{{ $b['brand']?->name }}</b></td><td>{{ $b['size'] }} kg</td><td>{{ $b['brand']?->agreement_ref ?: '—' }}</td><td class="r">{{ $b['in'] }}</td><td class="r">{{ $b['out'] }}</td>
        <td class="r"><b style="color:{{ $b['net'] ? '#8a4b00' : '#0f6b3c' }}">{{ $b['net'] > 0 ? '+' : '' }}{{ $b['net'] }}</b></td>@if($isSuper)<td class="r">৳{{ $bd($b['amount']) }}</td>@endif
        <td class="nw">{{ \Carbon\Carbon::parse($b['last'])->timezone('Asia/Dhaka')->format('d-m-Y') }}</td></tr>
    @empty
      <tr><td colspan="8" class="c" style="padding:22px;color:var(--muted)">No other-brand cylinders have passed through an exchange yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<div class="grid2" style="align-items:start">
  @if($isAdmin)
  <form class="card pad" method="post" action="{{ route('cx.settle') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">Record a settlement with a brand</h2>
    <span class="sub">Only brands with an agreement reference can be settled. Cylinders returned or received here move godown empty stock.</span>
    <div class="grid2">
      <label class="f">Brand<select name="brand_id" required>@foreach($brands->where('for_sale', false) as $b)<option value="{{ $b->id }}" @disabled(! $b->agreement_ref) @selected(old('brand_id') == $b->id)>{{ $b->name }}{{ $b->agreement_ref ? ' · '.$b->agreement_ref : ' · no agreement' }}</option>@endforeach</select></label>
      <label class="f">Size<select name="size_kg">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}">{{ $s }} kg</option>@endforeach</select></label>
      <label class="f">Their empties returned to them<input type="number" name="qty_returned" min="0" value="{{ old('qty_returned', 0) }}"></label>
      <label class="f">Their empties received from them<input type="number" name="qty_taken" min="0" value="{{ old('qty_taken', 0) }}"></label>
      @if($isSuper)<label class="f">Money (৳, + we owe them, − they owe us)<input type="number" name="amount" step="0.01" value="{{ old('amount', 0) }}"></label>@endif
      <label class="f">Status<select name="status"><option value="open">Open</option><option value="settled">Settled (closes open entries for this brand and size)</option></select></label>
      <label class="f">Reference / challan<input type="text" name="reference" value="{{ old('reference') }}" placeholder="Optional"></label>
      <label class="f">Supporting document<input type="file" name="document" accept="image/png,image/jpeg,image/webp,application/pdf"></label>
    </div>
    <label class="f">Note<input type="text" name="note" value="{{ old('note') }}" required placeholder="What was settled and with whom"></label>
    <div><button class="btn pri" type="submit">Save settlement</button></div>
  </form>
  @endif
  <section class="card">
    <div class="pad"><h2 style="margin:0;font-size:var(--fs-15)">Brand settlement ledger</h2></div>
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Date</th><th>Brand</th><th>Size</th><th>Entry</th><th>Reference</th><th class="r">In</th><th class="r">Out</th>@if($isSuper)<th class="r">Money</th>@endif<th>Status</th><th>Agreement</th><th>Document</th><th>Note</th></tr></thead>
      <tbody>
      @forelse($ledger as $e)
        <tr><td class="nw">{{ $e->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</td><td>{{ $e->brand?->name }}</td><td>{{ $e->size_kg }} kg</td><td>{{ ucfirst($e->kind) }}</td><td class="nw">{{ $e->reference }}</td>
          <td class="r">{{ $e->qty_received ?: '' }}</td><td class="r">{{ $e->qty_delivered ?: '' }}</td>@if($isSuper)<td class="r">{{ $e->amount ? '৳'.$bd($e->amount) : '' }}</td>@endif
          <td><span class="pill {{ $e->status === 'settled' ? 'st-delivered' : '' }}">{{ ucfirst($e->status) }}</span></td><td>{{ $e->agreement_ref }}</td>
          <td>@if($e->document && ($isSuper || ! str_contains($e->document, '/settle-')))<a href="{{ route('cx.doc', ['p' => $e->document]) }}" target="_blank" rel="noopener">Open</a>@elseif($e->document)<span class="sub">Super Admin</span>@endif</td><td>{{ $e->note }}</td></tr>
      @empty
        <tr><td colspan="12" class="c" style="padding:22px;color:var(--muted)">No entries yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
    @if($ledger->hasPages())<div class="pad">{{ $ledger->links() }}</div>@endif
  </section>
</div>
@endif

{{-- ===================== 8. RATE SETTINGS ===================== --}}
@if($v === 'rates')
<div class="grid2" style="align-items:start">
  <section class="card pad" style="display:flex;flex-direction:column;gap:10px">
    <h2 style="margin:0;font-size:var(--fs-15)">Exchange types</h2>
    <span class="sub">Each type can be switched on or off. A type that is off cannot be entered at the counter or in the app.</span>
    @if($isAdmin)<form method="post" action="{{ route('cx.types') }}" style="display:flex;flex-direction:column;gap:8px">@csrf @endif
      @foreach($X::TYPES as $tk => $tv)
        <label style="display:flex;align-items:center;gap:10px;font-weight:600"><input type="checkbox" name="types[]" value="{{ $tk }}" style="width:auto;min-height:0" @checked(in_array($tk, $types, true)) @disabled(! $isAdmin)> <span class="pill" style="min-width:24px;text-align:center">{{ $tk }}</span> {{ $tv }}</label>
      @endforeach
    @if($isAdmin)<div><button class="btn pri" type="submit">Save types</button></div></form>@endif
  </section>
  <section class="card pad" style="display:flex;flex-direction:column;gap:10px">
    <h2 style="margin:0;font-size:var(--fs-15)">Exchange charge per cylinder</h2>
    <span class="sub">Set on each other brand, by size. Every other brand in a line adds its charge (B and C: one brand; D: both brands). Same brand on both sides = no charge. Empty box = no charge.</span>
    @if($isAdmin)<form method="post" action="{{ route('cx.charges') }}">@csrf @endif
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Brand</th>@foreach(\App\Models\Brand::SIZES as $s)<th class="r">{{ $s }} kg</th>@endforeach</tr></thead>
      <tbody>
      @foreach($active->where('for_sale', false) as $b)
        <tr><td class="nw"><b>{{ $b->name }}</b></td>
          @foreach(\App\Models\Brand::SIZES as $s)
            @php($cv = $charges[$b->id][$s] ?? null)
            <td class="r">@if(! in_array($s, array_map('intval', $b->sizes ?? []), true))<span class="sub">—</span>
              @elseif($isAdmin)<input type="number" name="c[{{ $b->id }}][{{ $s }}]" value="{{ $cv !== null ? rtrim(rtrim(number_format($cv, 2, '.', ''), '0'), '.') : '' }}" min="0" step="1" placeholder="0" aria-label="{{ $b->name }} {{ $s }} kg charge" style="width:90px;text-align:right">
              @else ৳{{ $bd($cv ?? 0) }}@endif</td>
          @endforeach
        </tr>
      @endforeach
      </tbody>
    </table></div>
    @if($isAdmin)<div style="margin-top:10px"><button class="btn pri" type="submit">Save charges</button></div></form>@endif
  </section>
</div>
<section class="card" id="rules">
  <div class="pad"><h2 style="margin:0;font-size:var(--fs-15)">Brand exchange rules</h2><span class="sub">Cross-brand exchange is allowed only for a brand that is marked “exchange allowed” and “approved partner” with a supplier agreement reference. Others are held for review.</span></div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Brand</th><th>Code</th><th>Sizes</th><th>Ownership policy</th><th>Exchange allowed</th><th>Approved partner</th><th>Agreement ref</th><th>Deposit rule</th><th>Settlement rule</th><th>Fee rule</th><th>Can exchange</th>@if($isAdmin)<th></th>@endif</tr></thead>
    <tbody>
    @foreach($brands as $b)
      <tr @if($b->for_sale) style="background:#f5f8ff" @endif><td><b>{{ $b->name }}</b>{{ $b->for_sale ? ' (ours)' : '' }}</td><td>{{ $b->code }}</td><td class="nw">{{ implode(' · ', $b->sizes ?? []) }} kg</td>
        <td>{{ \App\Models\Brand::OWNERSHIP[$b->ownership_policy] ?? $b->ownership_policy }}</td><td>{{ $b->exchange_allowed ? 'Yes' : 'No' }}</td><td>{{ $b->exchange_partner ? 'Yes' : 'No' }}</td>
        <td>{{ $b->agreement_ref ?: '—' }}</td><td>{{ $b->deposit_rule ?: '—' }}</td><td>{{ $b->settlement_rule ?: '—' }}</td><td>{{ $b->fee_rule ?: '—' }}</td>
        <td><span class="pill {{ $b->exchangeAuthorized() ? 'st-delivered' : '' }}">{{ $b->exchangeAuthorized() ? 'Yes' : ($b->is_active ? 'Held for review' : 'Inactive') }}</span></td>
        @if($isAdmin)<td><a class="btn sm" href="{{ route('cx.index', ['v' => 'rates', 'brand' => $b->id]) }}#rule-form">Edit</a></td>@endif</tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@if($isAdmin && $edit)
<form id="rule-form" class="card pad" method="post" action="{{ route('cx.rule', $edit) }}" style="display:flex;flex-direction:column;gap:12px">@csrf
  <h2 style="margin:0;font-size:var(--fs-15)">Exchange rules — {{ $edit->name }}</h2>
  <div class="grid2">
    <label class="f">Exchange allowed<select name="exchange_allowed"><option value="0" @selected(! old('exchange_allowed', $edit->exchange_allowed))>No</option><option value="1" @selected(old('exchange_allowed', $edit->exchange_allowed))>Yes</option></select></label>
    <label class="f">Approved exchange partner<select name="exchange_partner"><option value="0" @selected(! old('exchange_partner', $edit->exchange_partner))>No</option><option value="1" @selected(old('exchange_partner', $edit->exchange_partner))>Yes</option></select></label>
    <label class="f">Ownership policy<select name="ownership_policy">@foreach(\App\Models\Brand::OWNERSHIP as $ok => $ov)<option value="{{ $ok }}" @selected(old('ownership_policy', $edit->ownership_policy) === $ok)>{{ $ov }}</option>@endforeach</select></label>
    <label class="f">Supplier agreement reference<input type="text" name="agreement_ref" value="{{ old('agreement_ref', $edit->agreement_ref) }}" placeholder="e.g. agreement no. and date"></label>
    <label class="f">Deposit rule<input type="text" name="deposit_rule" value="{{ old('deposit_rule', $edit->deposit_rule) }}" placeholder="Optional"></label>
    <label class="f">Settlement rule<input type="text" name="settlement_rule" value="{{ old('settlement_rule', $edit->settlement_rule) }}" placeholder="Optional"></label>
    <label class="f">Exchange fee rule<input type="text" name="fee_rule" value="{{ old('fee_rule', $edit->fee_rule) }}" placeholder="Optional note; charges are set in the grid above"></label>
  </div>
  <span class="sub">Only allow exchange where the supplier agreement, cylinder ownership, safety requirements and regulations permit it.</span>
  <div style="display:flex;gap:8px"><button class="btn pri" type="submit">Save rules</button><a class="btn" href="{{ route('cx.index', ['v' => 'rates']) }}">Cancel</a></div>
</form>
@endif
@endif

{{-- ===================== 10. REPORTS ===================== --}}
@if($v === 'reports')
<section class="card">
  <form method="get" class="pad" style="display:flex;flex-wrap:wrap;align-items:end;gap:10px">
    <input type="hidden" name="v" value="reports">
    <label class="f" style="flex:1 1 220px">Report<select name="r" onchange="this.form.submit()">@foreach($reports as $rk => $rv)<option value="{{ $rk }}" @selected($rep === $rk)>{{ $rv[0] }}</option>@endforeach</select></label>
    <label class="f">From<input type="date" name="from" value="{{ $from }}"></label>
    <label class="f">To<input type="date" name="to" value="{{ $to }}"></label>
    <button class="btn" type="submit">Show</button>
    @if($rep)@include('partials.export-menu', ['r' => $rep, 'from' => $from, 'to' => $to])@endif
  </form>
  @if($rep)
  <div class="dt-wrap"><table class="dt">
    <thead><tr>@foreach($head as $h)<th @if(is_numeric($rows[0][$loop->index] ?? null)) class="r" @endif>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $r)
      <tr>@foreach($r as $cell)<td @if(is_numeric($cell)) class="r" @endif>{{ is_float($cell) ? $bd($cell) : $cell }}</td>@endforeach</tr>
    @empty
      <tr><td colspan="{{ count($head) }}" class="c" style="padding:22px;color:var(--muted)">Nothing in this period.</td></tr>
    @endforelse
    </tbody>
    @if($total && $rows)<tfoot><tr>@foreach($total as $cell)<th @if(is_numeric($cell)) class="r" @endif>{{ is_float($cell) ? $bd($cell) : $cell }}</th>@endforeach</tr></tfoot>@endif
  </table></div>
  @endif
</section>
@endif
@endsection
